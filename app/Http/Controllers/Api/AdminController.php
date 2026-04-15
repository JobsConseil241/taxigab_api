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
        $today = now()->startOfDay();
        $revenueToday = Ride::where('status', RideStatus::COMPLETED)
            ->where('completed_at', '>=', $today)
            ->sum('price_final');

        return response()->json([
            'total_users'     => (int) User::where('role', 'passenger')->count(),
            'total_drivers'   => (int) Driver::count(),
            'drivers_online'  => (int) Driver::where('is_online', true)->count(),
            'total_rides'     => (int) Ride::count(),
            'rides_today'     => (int) Ride::where('requested_at', '>=', $today)->count(),
            'revenue_today'   => (int) $revenueToday,
            'total_formulas'  => (int) PricingFormula::active()->count(),
            'currency'        => 'XAF',
        ]);
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

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->limit(100)->get();

        return response()->json([
            'users' => $users->map(fn (User $u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'phone'      => $u->phone,
                'role'       => $u->role,
                'is_active'  => $u->is_active,
                'created_at' => $u->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
