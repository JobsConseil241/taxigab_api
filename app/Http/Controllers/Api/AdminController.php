<?php

namespace App\Http\Controllers\Api;

use App\Enums\RideStatus;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\PricingFormula;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $today      = now()->startOfDay();
        $yesterday  = now()->subDay()->startOfDay();
        $weekStart  = now()->startOfWeek();

        // Today vs yesterday
        $ridesToday      = (int) Ride::where('requested_at', '>=', $today)->count();
        $ridesYesterday  = (int) Ride::whereBetween('requested_at', [$yesterday, $today])->count();
        $revenueToday    = (int) Ride::where('status', RideStatus::COMPLETED)
            ->where('completed_at', '>=', $today)->sum('price_final');
        $revenueYesterday = (int) Ride::where('status', RideStatus::COMPLETED)
            ->whereBetween('completed_at', [$yesterday, $today])->sum('price_final');

        // Live counts
        $activeRides  = (int) Ride::whereIn('status', [
            RideStatus::ACCEPTED, RideStatus::ARRIVING, RideStatus::IN_PROGRESS,
        ])->count();
        $waitingRides = (int) Ride::where('status', RideStatus::REQUESTED)->count();
        $driversOnline = (int) Driver::where('is_online', true)->count();
        $driversFree   = (int) Driver::where('is_online', true)
            ->where('status', 'approved')
            ->whereDoesntHave('rides', function ($q) {
                $q->whereIn('status', [
                    RideStatus::ACCEPTED, RideStatus::ARRIVING, RideStatus::IN_PROGRESS,
                ]);
            })->count();

        // Hourly breakdown for today (24 buckets)
        $hourly = array_fill(0, 24, 0);
        $rows = Ride::where('requested_at', '>=', $today)
            ->selectRaw('HOUR(requested_at) AS h, COUNT(*) AS c')
            ->groupBy('h')->get();
        foreach ($rows as $r) {
            $hourly[(int) $r->h] = (int) $r->c;
        }

        // 7-day rides series
        $weekly = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = now()->subDays($i)->startOfDay();
            $weekly[] = (int) Ride::whereBetween('requested_at', [$d, $d->copy()->endOfDay()])->count();
        }

        // Avg wait minutes today (accepted - requested)
        $avgWait = (float) Ride::whereNotNull('accepted_at')
            ->where('requested_at', '>=', $today)
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, requested_at, accepted_at)) AS s')
            ->value('s');
        $avgWaitMinutes = $avgWait ? round($avgWait / 60, 1) : 0;

        // Cancellation rate today
        $cancelToday = (int) Ride::where('status', RideStatus::CANCELLED)
            ->where('requested_at', '>=', $today)->count();
        $cancelRate = $ridesToday > 0 ? round($cancelToday * 100 / $ridesToday, 1) : 0;

        // Avg fare (completed today)
        $avgFare = (int) Ride::where('status', RideStatus::COMPLETED)
            ->where('completed_at', '>=', $today)->avg('price_final') ?? 0;

        // Avg rating last 7 days
        $avgRating = (float) Ride::whereNotNull('rating')
            ->where('completed_at', '>=', now()->subDays(7))
            ->avg('rating') ?? 0;

        // Alerts: drivers idle while online, KYC pending
        $idleDrivers = (int) Driver::where('is_online', true)
            ->where('updated_at', '<', now()->subMinutes(8))->count();
        $kycPending = (int) Driver::where('status', 'pending')->count();

        return response()->json([
            'total_users'      => (int) User::where('role', 'passenger')->count(),
            'total_drivers'    => (int) Driver::count(),
            'drivers_online'   => $driversOnline,
            'drivers_free'     => $driversFree,
            'drivers_pending'  => $kycPending,
            'total_rides'      => (int) Ride::count(),
            'rides_today'      => $ridesToday,
            'rides_yesterday'  => $ridesYesterday,
            'rides_active'     => $activeRides,
            'rides_waiting'    => $waitingRides,
            'revenue_today'    => $revenueToday,
            'revenue_yesterday'=> $revenueYesterday,
            'revenue_week'     => (int) Ride::where('status', RideStatus::COMPLETED)
                ->where('completed_at', '>=', $weekStart)->sum('price_final'),
            'total_formulas'   => (int) PricingFormula::active()->count(),
            'avg_wait_minutes' => $avgWaitMinutes,
            'avg_fare'         => $avgFare,
            'avg_rating'       => round($avgRating, 2),
            'cancellation_rate'=> $cancelRate,
            'hourly_today'     => array_values($hourly),
            'weekly_rides'     => $weekly,
            'alerts' => [
                ['type'=>'idle_drivers', 'count'=>$idleDrivers, 'severity'=>'urgent',
                 'title'=>'Chauffeurs immobilisés', 'sub'=>'Aucun mouvement > 8 min'],
                ['type'=>'waiting_rides', 'count'=>$waitingRides, 'severity'=>'warn',
                 'title'=>'Courses en attente', 'sub'=>'Pas encore acceptées'],
                ['type'=>'kyc_pending', 'count'=>$kycPending, 'severity'=>'info',
                 'title'=>'KYC à valider', 'sub'=>'Nouveaux chauffeurs en attente'],
            ],
            'currency' => 'XAF',
        ]);
    }

    /**
     * GET /api/admin/revenue?period=12w
     */
    public function revenue(Request $request): JsonResponse
    {
        $period = $request->query('period', '12w');
        [$buckets, $labels] = match ($period) {
            '7d'  => [$this->bucketsByDay(7),   'jours'],
            '4w'  => [$this->bucketsByWeek(4),  'semaines'],
            '1y'  => [$this->bucketsByMonth(12),'mois'],
            default => [$this->bucketsByWeek(12), 'semaines'],
        };

        // Payment method donut (period)
        $start = collect($buckets)->first()['start'] ?? now()->subDays(84);
        $pay = Ride::where('status', RideStatus::COMPLETED)
            ->where('completed_at', '>=', $start)
            ->selectRaw('payment_method, SUM(price_final) AS v')
            ->groupBy('payment_method')->pluck('v', 'payment_method')->all();

        $total = array_sum($pay) ?: 1;
        $methods = [
            ['key'=>'mobile_money','label'=>'Mobile Money','color'=>'#E40012','value'=>(int)($pay['mobile_money']??0)],
            ['key'=>'cash','label'=>'Espèces','color'=>'#0F9B6D','value'=>(int)($pay['cash']??0)],
        ];
        foreach ($methods as &$m) {
            $m['pct'] = round($m['value']*100/$total);
        }
        unset($m);

        // Top pickup zones (by address text)
        $zones = Ride::where('status', RideStatus::COMPLETED)
            ->where('completed_at', '>=', $start)
            ->whereNotNull('pickup_address')
            ->selectRaw('pickup_address AS name, SUM(price_final) AS v, COUNT(*) AS rides')
            ->groupBy('pickup_address')
            ->orderByDesc('v')->limit(5)->get();
        $zoneTotal = (int) $zones->sum('v') ?: 1;

        $totalRevenue = (int) collect($buckets)->sum('value');
        $totalRides   = (int) collect($buckets)->sum('count');

        return response()->json([
            'period'        => $period,
            'unit'          => $labels,
            'buckets'       => $buckets,
            'total_revenue' => $totalRevenue,
            'total_rides'   => $totalRides,
            'avg_ticket'    => $totalRides > 0 ? (int) round($totalRevenue/$totalRides) : 0,
            'commission'    => (int) round($totalRevenue * 0.15),
            'payment_methods' => $methods,
            'top_zones'     => $zones->map(fn($z) => [
                'name'  => $z->name,
                'value' => (int) $z->v,
                'rides' => (int) $z->rides,
                'share' => (int) round($z->v * 100 / $zoneTotal),
            ]),
            'currency' => 'XAF',
        ]);
    }

    private function bucketsByWeek(int $weeks): array
    {
        $out = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $start = now()->subWeeks($i)->startOfWeek();
            $end   = $start->copy()->endOfWeek();
            $sum = (int) Ride::where('status', RideStatus::COMPLETED)
                ->whereBetween('completed_at', [$start, $end])->sum('price_final');
            $cnt = (int) Ride::whereBetween('requested_at', [$start, $end])->count();
            $out[] = [
                'label' => 'S'.$start->isoWeek,
                'start' => $start->toIso8601String(),
                'value' => $sum,
                'count' => $cnt,
            ];
        }
        return $out;
    }

    private function bucketsByDay(int $days): array
    {
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $start = now()->subDays($i)->startOfDay();
            $end   = $start->copy()->endOfDay();
            $sum = (int) Ride::where('status', RideStatus::COMPLETED)
                ->whereBetween('completed_at', [$start, $end])->sum('price_final');
            $cnt = (int) Ride::whereBetween('requested_at', [$start, $end])->count();
            $out[] = [
                'label' => $start->isoFormat('ddd D'),
                'start' => $start->toIso8601String(),
                'value' => $sum,
                'count' => $cnt,
            ];
        }
        return $out;
    }

    private function bucketsByMonth(int $months): array
    {
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end   = $start->copy()->endOfMonth();
            $sum = (int) Ride::where('status', RideStatus::COMPLETED)
                ->whereBetween('completed_at', [$start, $end])->sum('price_final');
            $cnt = (int) Ride::whereBetween('requested_at', [$start, $end])->count();
            $out[] = [
                'label' => $start->isoFormat('MMM'),
                'start' => $start->toIso8601String(),
                'value' => $sum,
                'count' => $cnt,
            ];
        }
        return $out;
    }

    public function rides(Request $request): JsonResponse
    {
        $query = Ride::with(['passenger:id,name,phone', 'driver.user:id,name,phone'])
            ->orderByDesc('requested_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($date = $request->query('date')) {
            $query->whereDate('requested_at', $date);
        }

        $rides = $query->limit(100)->get();

        return response()->json([
            'rides' => $rides->map(function (Ride $ride) {
                return [
                    'id'              => $ride->id,
                    'status'          => $ride->status->value,
                    'passenger'       => $ride->passenger?->only(['id', 'name', 'phone']),
                    'driver'          => $ride->driver?->user?->only(['id', 'name', 'phone']),
                    'pickup_address'  => $ride->pickup_address,
                    'dropoff_address' => $ride->dropoff_address,
                    'distance_km'     => (float) $ride->distance_km,
                    'price_estimated' => (int) $ride->price_estimated,
                    'price_final'     => $ride->price_final,
                    'formula_name'    => $ride->formula_name,
                    'payment_method'  => $ride->payment_method,
                    'rating'          => $ride->rating,
                    'requested_at'    => $ride->requested_at?->toIso8601String(),
                    'completed_at'    => $ride->completed_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    public function drivers(Request $request): JsonResponse
    {
        $query = Driver::with(['user:id,name,email,phone,avatar_url', 'activeVehicle'])
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $drivers = $query->limit(100)->get();

        return response()->json([
            'drivers' => $drivers->map(fn (Driver $d) => [
                'id'             => $d->id,
                'user'           => $d->user?->only(['id', 'name', 'email', 'phone', 'avatar_url']),
                'license_number' => $d->license_number,
                'status'         => $d->status,
                'is_online'      => $d->is_online,
                'rating_avg'     => (float) $d->rating_avg,
                'total_rides'    => (int) $d->total_rides,
                'vehicle'        => $d->activeVehicle?->only(['brand', 'model', 'color', 'plate_number']),
                'created_at'     => $d->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function driverLocations(): JsonResponse
    {
        $drivers = Driver::with('user:id,name')
            ->where('is_online', true)
            ->where('status', 'approved')
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->get();

        return response()->json([
            'drivers' => $drivers->map(fn (Driver $d) => [
                'id'          => $d->id,
                'name'        => $d->user?->name,
                'current_lat' => (float) $d->current_lat,
                'current_lng' => (float) $d->current_lng,
            ]),
        ]);
    }

    public function approveDriver(Driver $driver): JsonResponse
    {
        $driver->update(['status' => 'approved']);
        return response()->json(['driver_id' => $driver->id, 'status' => 'approved']);
    }

    // ---------------------------------------------------------------- Formulas CRUD

    public function formulas(): JsonResponse
    {
        $formulas = PricingFormula::ordered()->get();

        return response()->json([
            'formulas' => $formulas->map(fn (PricingFormula $f) => [
                'id'              => $f->id,
                'slug'            => $f->slug,
                'name'            => $f->name,
                'description'     => $f->description,
                'icon'            => $f->icon,
                'base_fare'       => $f->base_fare,
                'per_km'          => $f->per_km,
                'per_minute_wait' => $f->per_minute_wait,
                'min_fare'        => $f->min_fare,
                'rounding_step'   => $f->rounding_step,
                'sort_order'      => $f->sort_order,
                'is_active'       => $f->is_active,
                'created_at'      => $f->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function storeFormula(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug'            => ['required', 'string', 'max:50', 'unique:pricing_formulas,slug'],
            'name'            => ['required', 'string', 'max:100'],
            'description'     => ['nullable', 'string'],
            'icon'            => ['nullable', 'string', 'max:50'],
            'base_fare'       => ['required', 'integer', 'min:0'],
            'per_km'          => ['required', 'integer', 'min:0'],
            'per_minute_wait' => ['nullable', 'integer', 'min:0'],
            'min_fare'        => ['required', 'integer', 'min:0'],
            'rounding_step'   => ['nullable', 'integer', 'min:0'],
            'sort_order'      => ['nullable', 'integer'],
            'is_active'       => ['nullable', 'boolean'],
        ]);

        $formula = PricingFormula::create($data);

        return response()->json(['formula' => $formula], 201);
    }

    public function updateFormula(Request $request, PricingFormula $formula): JsonResponse
    {
        $data = $request->validate([
            'slug'            => ['sometimes', 'string', 'max:50', 'unique:pricing_formulas,slug,' . $formula->id],
            'name'            => ['sometimes', 'string', 'max:100'],
            'description'     => ['nullable', 'string'],
            'icon'            => ['nullable', 'string', 'max:50'],
            'base_fare'       => ['sometimes', 'integer', 'min:0'],
            'per_km'          => ['sometimes', 'integer', 'min:0'],
            'per_minute_wait' => ['nullable', 'integer', 'min:0'],
            'min_fare'        => ['sometimes', 'integer', 'min:0'],
            'rounding_step'   => ['nullable', 'integer', 'min:0'],
            'sort_order'      => ['nullable', 'integer'],
            'is_active'       => ['nullable', 'boolean'],
        ]);

        $formula->update($data);

        return response()->json(['formula' => $formula->fresh()]);
    }

    public function destroyFormula(PricingFormula $formula): JsonResponse
    {
        if ($formula->rides()->exists()) {
            $formula->update(['is_active' => false]);
            return response()->json(['message' => 'Formule désactivée (courses liées).', 'deactivated' => true]);
        }

        $formula->delete();
        return response()->json(['message' => 'Formule supprimée.']);
    }

    // ---------------------------------------------------------------- Users

    public function users(Request $request): JsonResponse
    {
        $query = User::orderByDesc('created_at');

        if ($role = $request->query('role')) {
            $roles = is_array($role) ? $role : array_filter(explode(',', (string) $role));
            if (!empty($roles)) {
                $query->whereIn('role', $roles);
            }
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->withCount(['rides as rides_count' => function ($q) {
                $q->where('status', RideStatus::COMPLETED);
            }])
            ->limit(200)->get();

        return response()->json([
            'users' => $users->map(fn (User $u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'email'        => $u->email,
                'phone'        => $u->phone,
                'role'         => $u->role,
                'is_active'    => $u->is_active,
                'language'     => $u->language,
                'rides_count'  => (int) ($u->rides_count ?? 0),
                'created_at'   => $u->created_at?->toIso8601String(),
                'last_seen_at' => $u->updated_at?->toIso8601String(),
            ]),
        ]);
    }
}
