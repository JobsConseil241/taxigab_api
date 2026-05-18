<?php

namespace App\Http\Controllers\Api;

use App\Enums\RideStatus;
use App\Events\RideAccepted;
use App\Events\RideStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\RideStatusLog;
use App\Services\GeoService;
use App\Services\MatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverController extends Controller
{
    public function __construct(
        private readonly GeoService $geo,
        private readonly MatchingService $matching,
    ) {
    }

    /**
     * GET /api/drivers/nearby — chauffeurs en ligne visibles par les passagers.
     */
    public function nearby(Request $request): JsonResponse
    {
        $lat = (float) $request->query('lat', -1.5660);
        $lng = (float) $request->query('lng', 13.2581);
        $radiusKm = (float) $request->query('radius', 10);

        [$minLat, $maxLat, $minLng, $maxLng] = $this->geo->boundingBox($lat, $lng, $radiusKm);

        $drivers = \App\Models\Driver::with('user:id,name', 'activeVehicle')
            ->where('is_online', true)
            ->where('status', 'approved')
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->whereBetween('current_lat', [$minLat, $maxLat])
            ->whereBetween('current_lng', [$minLng, $maxLng])
            ->get();

        return response()->json([
            'drivers' => $drivers->map(fn (\App\Models\Driver $d) => [
                'id'          => $d->id,
                'name'        => $d->user?->name,
                'lat'         => (float) $d->current_lat,
                'lng'         => (float) $d->current_lng,
                'heading'     => $d->current_heading,
                'vehicle'     => $d->activeVehicle ? [
                    'brand' => $d->activeVehicle->brand,
                    'model' => $d->activeVehicle->model,
                    'color' => $d->activeVehicle->color,
                    'plate' => $d->activeVehicle->plate_number,
                ] : null,
            ])->values(),
        ]);
    }

    /**
     * POST /api/driver/toggle-online
     */
    public function toggleOnline(Request $request): JsonResponse
    {
        $driver = $this->driverOrFail($request);

        $request->validate([
            'is_online' => ['required', 'boolean'],
        ]);

        $driver->update([
            'is_online'        => (bool) $request->boolean('is_online'),
            'last_location_at' => now(),
        ]);

        return response()->json([
            'is_online' => $driver->is_online,
        ]);
    }

    /**
     * GET /api/driver/available-rides — courses à proximité en statut requested.
     */
    public function availableRides(Request $request): JsonResponse
    {
        $driver = $this->driverOrFail($request);

        if (! $driver->is_online || ! $driver->current_lat || ! $driver->current_lng) {
            return response()->json(['rides' => []]);
        }

        $radiusKm = (float) config('taxigab.matching.radius_km', 10);
        [$minLat, $maxLat, $minLng, $maxLng] = $this->geo->boundingBox(
            (float) $driver->current_lat,
            (float) $driver->current_lng,
            $radiusKm,
        );

        $rides = Ride::where('status', RideStatus::REQUESTED)
            ->whereBetween('pickup_lat', [$minLat, $maxLat])
            ->whereBetween('pickup_lng', [$minLng, $maxLng])
            ->orderByDesc('requested_at')
            ->limit(20)
            ->get();

        return response()->json([
            'rides' => $rides->map(function (Ride $ride) use ($driver) {
                $distance = $this->geo->haversine(
                    (float) $driver->current_lat,
                    (float) $driver->current_lng,
                    (float) $ride->pickup_lat,
                    (float) $ride->pickup_lng,
                );
                return [
                    'id'              => $ride->id,
                    'pickup_lat'      => (float) $ride->pickup_lat,
                    'pickup_lng'      => (float) $ride->pickup_lng,
                    'pickup_address'  => $ride->pickup_address,
                    'dropoff_lat'     => (float) $ride->dropoff_lat,
                    'dropoff_lng'     => (float) $ride->dropoff_lng,
                    'dropoff_address' => $ride->dropoff_address,
                    'distance_km'     => round((float) $ride->distance_km, 2),
                    'duration_minutes' => (int) $ride->duration_minutes,
                    'price_estimated' => (int) $ride->price_estimated,
                    'currency'        => $ride->currency,
                    'formula_name'    => $ride->formula_name,
                    'payment_method'  => $ride->payment_method,
                    'distance_to_pickup_km' => round($distance, 2),
                    'requested_at'    => $ride->requested_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * POST /api/driver/rides/{ride}/accept
     */
    public function accept(Request $request, Ride $ride): JsonResponse
    {
        $driver = $this->driverOrFail($request);

        if ($ride->status !== RideStatus::REQUESTED) {
            return response()->json(['message' => 'Course déjà attribuée.'], 409);
        }

        if ($driver->status !== 'approved' || ! $driver->is_online) {
            return response()->json(['message' => 'Chauffeur non disponible.'], 403);
        }

        $previous = $ride->status;

        DB::transaction(function () use ($ride, $driver, $request) {
            $ride->update([
                'driver_id'   => $driver->id,
                'vehicle_id'  => $driver->activeVehicle?->id,
                'status'      => RideStatus::ACCEPTED,
                'accepted_at' => now(),
            ]);

            RideStatusLog::create([
                'ride_id'    => $ride->id,
                'status'     => RideStatus::ACCEPTED,
                'changed_by' => $request->user()->id,
                'lat'        => $driver->current_lat,
                'lng'        => $driver->current_lng,
                'created_at' => now(),
            ]);
        });

        $fresh = $ride->fresh(['driver.user', 'driver.activeVehicle']);
        broadcast(new RideAccepted($fresh))->toOthers();
        broadcast(new RideStatusChanged($fresh, $previous))->toOthers();

        return response()->json(['ride' => app(RideController::class)->transformRide($fresh)]);
    }

    /**
     * POST /api/driver/rides/{ride}/arrive
     */
    public function arrive(Request $request, Ride $ride): JsonResponse
    {
        return $this->transition($request, $ride, RideStatus::ARRIVING, ['arriving_at' => now()]);
    }

    /**
     * POST /api/driver/rides/{ride}/start
     */
    public function start(Request $request, Ride $ride): JsonResponse
    {
        return $this->transition($request, $ride, RideStatus::IN_PROGRESS, ['started_at' => now()]);
    }

    /**
     * POST /api/driver/rides/{ride}/complete
     */
    public function complete(Request $request, Ride $ride): JsonResponse
    {
        $request->validate([
            'price_final' => ['nullable', 'integer', 'min:0'],
        ]);

        return $this->transition($request, $ride, RideStatus::COMPLETED, [
            'completed_at'   => now(),
            'price_final'    => $request->integer('price_final') ?: $ride->price_estimated,
            'payment_status' => 'paid', // proto : on considère le cash encaissé
        ], function (Ride $ride) {
            // Incrémente le compteur du chauffeur
            $ride->driver?->increment('total_rides');
        });
    }

    /**
     * GET /api/driver/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $driver = $this->driverOrFail($request);
        $today = now()->startOfDay();

        $todayRides = Ride::where('driver_id', $driver->id)
            ->where('status', RideStatus::COMPLETED)
            ->where('completed_at', '>=', $today)
            ->get();

        return response()->json([
            'today_rides'   => $todayRides->count(),
            'today_revenue' => (int) $todayRides->sum('price_final'),
            'total_rides'   => (int) $driver->total_rides,
            'rating'        => (float) $driver->rating_avg,
            'currency'      => 'XAF',
        ]);
    }

    /**
     * GET /api/driver/history — courses du chauffeur (50 dernières).
     */
    public function history(Request $request): JsonResponse
    {
        $driver = $this->driverOrFail($request);

        $rides = Ride::where('driver_id', $driver->id)
            ->with(['passenger:id,name,phone'])
            ->orderByDesc('requested_at')
            ->limit(50)
            ->get();

        $controller = app(RideController::class);

        return response()->json([
            'rides' => $rides->map(fn ($r) => $controller->transformRide($r)),
        ]);
    }

    /**
     * GET /api/driver/ratings — notes et avis reçus.
     */
    public function ratings(Request $request): JsonResponse
    {
        $driver = $this->driverOrFail($request);

        $rated = Ride::where('driver_id', $driver->id)
            ->whereNotNull('rating')
            ->with(['passenger:id,name'])
            ->orderByDesc('completed_at')
            ->limit(100)
            ->get();

        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($rated as $r) {
            $distribution[$r->rating] = ($distribution[$r->rating] ?? 0) + 1;
        }

        return response()->json([
            'average' => round((float) $driver->rating_avg, 2),
            'total'   => $rated->count(),
            'distribution' => $distribution,
            'reviews' => $rated->map(fn ($r) => [
                'ride_id'        => (int) $r->id,
                'rating'         => (int) $r->rating,
                'review'         => $r->review,
                'passenger_name' => $r->passenger?->name,
                'completed_at'   => optional($r->completed_at)->toIso8601String(),
            ]),
        ]);
    }

    // ---------------------------------------------------------------- Helpers

    private function driverOrFail(Request $request)
    {
        $driver = $request->user()?->driver;
        abort_unless($driver, 403, 'Not a driver account.');
        return $driver;
    }

    private function transition(
        Request $request,
        Ride $ride,
        RideStatus $next,
        array $updates = [],
        ?\Closure $after = null,
    ): JsonResponse {
        $driver = $this->driverOrFail($request);

        abort_unless(
            $ride->driver_id === $driver->id,
            403,
            'Course non assignée à ce chauffeur.',
        );

        if (! $ride->status->canTransitionTo($next)) {
            return response()->json([
                'message' => "Transition interdite: {$ride->status->value} -> {$next->value}",
            ], 422);
        }

        $previous = $ride->status;

        DB::transaction(function () use ($ride, $next, $updates, $request, $driver, $after) {
            $ride->update(array_merge(['status' => $next], $updates));

            RideStatusLog::create([
                'ride_id'    => $ride->id,
                'status'     => $next,
                'changed_by' => $request->user()->id,
                'lat'        => $driver->current_lat,
                'lng'        => $driver->current_lng,
                'created_at' => now(),
            ]);

            if ($after) {
                $after($ride);
            }
        });

        $fresh = $ride->fresh(['driver.user', 'driver.activeVehicle']);
        broadcast(new RideStatusChanged($fresh, $previous))->toOthers();

        return response()->json(['ride' => app(RideController::class)->transformRide($fresh)]);
    }
}
