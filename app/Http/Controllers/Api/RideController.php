<?php

namespace App\Http\Controllers\Api;

use App\Enums\RideStatus;
use App\Events\RideRequested;
use App\Events\RideStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ride\CreateRideRequest;
use App\Http\Requests\Ride\EstimateRequest;
use App\Http\Requests\Ride\RateRideRequest;
use App\Models\PricingFormula;
use App\Models\Ride;
use App\Models\RideStatusLog;
use App\Services\GeoService;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RideController extends Controller
{
    public function __construct(
        private readonly GeoService $geo,
        private readonly PricingService $pricing,
    ) {
    }

    /**
     * POST /api/rides/estimate — calcul d'estimation avant création.
     */
    public function estimate(EstimateRequest $request): JsonResponse
    {
        $data = $request->validated();

        $distanceKm = $this->geo->haversine(
            (float) $data['pickup_lat'],
            (float) $data['pickup_lng'],
            (float) $data['dropoff_lat'],
            (float) $data['dropoff_lng'],
        );
        $durationMin = $this->geo->estimateDurationMinutes($distanceKm);
        $price       = $this->pricing->estimate($distanceKm, $durationMin);
        $formulas    = $this->pricing->estimateAll($distanceKm, $durationMin);

        return response()->json([
            'distance_km'     => round($distanceKm, 2),
            'duration_min'    => $durationMin,
            'price_estimated' => $price,
            'currency'        => $this->pricing->currency(),
            'formulas'        => $formulas,
        ]);
    }

    /**
     * POST /api/rides — le passager crée une course.
     */
    public function store(CreateRideRequest $request): JsonResponse
    {
        $data = $request->validated();

        $distanceKm  = $this->geo->haversine(
            (float) $data['pickup_lat'],
            (float) $data['pickup_lng'],
            (float) $data['dropoff_lat'],
            (float) $data['dropoff_lng'],
        );
        $durationMin = $this->geo->estimateDurationMinutes($distanceKm);

        $formula = isset($data['pricing_formula_id'])
            ? PricingFormula::find($data['pricing_formula_id'])
            : null;

        $price = $this->pricing->estimate($distanceKm, $durationMin, $formula);

        $ride = DB::transaction(function () use ($data, $request, $distanceKm, $durationMin, $price, $formula) {
            $ride = Ride::create([
                'passenger_id'       => $request->user()->id,
                'pricing_formula_id' => $formula?->id,
                'formula_name'       => $formula?->name,
                'status'             => RideStatus::REQUESTED,
                'pickup_lat'         => $data['pickup_lat'],
                'pickup_lng'         => $data['pickup_lng'],
                'pickup_address'     => $data['pickup_address'] ?? null,
                'dropoff_lat'        => $data['dropoff_lat'],
                'dropoff_lng'        => $data['dropoff_lng'],
                'dropoff_address'    => $data['dropoff_address'] ?? null,
                'distance_km'        => round($distanceKm, 2),
                'duration_minutes'   => $durationMin,
                'price_estimated'    => $price,
                'currency'           => $this->pricing->currency(),
                'payment_method'     => $data['payment_method'] ?? 'cash',
                'payment_status'     => 'pending',
                'requested_at'       => now(),
            ]);

            RideStatusLog::create([
                'ride_id'    => $ride->id,
                'status'     => RideStatus::REQUESTED,
                'changed_by' => $request->user()->id,
                'lat'        => $data['pickup_lat'],
                'lng'        => $data['pickup_lng'],
                'created_at' => now(),
            ]);

            return $ride;
        });

        broadcast(new RideRequested($ride))->toOthers();

        return response()->json([
            'ride' => $this->transformRide($ride->fresh(['driver.user', 'driver.activeVehicle'])),
        ], 201);
    }

    /**
     * GET /api/rides/{id}
     */
    public function show(Request $request, Ride $ride): JsonResponse
    {
        $this->authorizeAccess($request, $ride);

        return response()->json([
            'ride' => $this->transformRide($ride->load(['driver.user', 'driver.activeVehicle'])),
        ]);
    }

    /**
     * GET /api/rides/history — historique des courses du passager connecté.
     */
    public function history(Request $request): JsonResponse
    {
        $rides = Ride::where('passenger_id', $request->user()->id)
            ->with(['driver.user', 'driver.activeVehicle'])
            ->orderByDesc('requested_at')
            ->limit(50)
            ->get();

        return response()->json([
            'rides' => $rides->map(fn (Ride $r) => $this->transformRide($r))->all(),
        ]);
    }

    /**
     * POST /api/rides/{id}/cancel — annulation par le passager.
     */
    public function cancel(Request $request, Ride $ride): JsonResponse
    {
        $this->authorizeAccess($request, $ride);

        if ($ride->status === RideStatus::COMPLETED || $ride->status === RideStatus::CANCELLED) {
            return response()->json(['message' => 'Course déjà finalisée.'], 422);
        }

        $previous = $ride->status;
        $reason   = (string) $request->input('reason', 'Annulée par le passager');

        DB::transaction(function () use ($ride, $reason, $request) {
            $ride->update([
                'status'              => RideStatus::CANCELLED,
                'cancelled_at'        => now(),
                'cancellation_reason' => $reason,
            ]);

            RideStatusLog::create([
                'ride_id'    => $ride->id,
                'status'     => RideStatus::CANCELLED,
                'changed_by' => $request->user()->id,
                'created_at' => now(),
            ]);
        });

        broadcast(new RideStatusChanged($ride->fresh(), $previous))->toOthers();

        return response()->json(['ride' => $this->transformRide($ride->fresh())]);
    }

    /**
     * POST /api/rides/{id}/rate — notation par le passager.
     */
    public function rate(RateRideRequest $request, Ride $ride): JsonResponse
    {
        $this->authorizeAccess($request, $ride);

        if ($ride->status !== RideStatus::COMPLETED) {
            return response()->json(['message' => 'Course non terminée.'], 422);
        }

        $data = $request->validated();

        DB::transaction(function () use ($ride, $data) {
            $ride->update([
                'rating' => $data['rating'],
                'review' => $data['review'] ?? null,
            ]);

            // Recalculer la moyenne du chauffeur
            if ($ride->driver_id) {
                $avg = Ride::where('driver_id', $ride->driver_id)
                           ->whereNotNull('rating')
                           ->avg('rating');
                $ride->driver?->update(['rating_avg' => round((float) $avg, 2)]);
            }
        });

        return response()->json(['ride' => $this->transformRide($ride->fresh('driver.user'))]);
    }

    // ---------------------------------------------------------------- Helpers

    private function authorizeAccess(Request $request, Ride $ride): void
    {
        $user = $request->user();

        $isPassenger = $user->id === (int) $ride->passenger_id;
        $isAssignedDriver = $user->isDriver()
            && $ride->driver_id
            && $user->driver?->id === (int) $ride->driver_id;
        $isAdmin = $user->isAdmin();

        abort_unless($isPassenger || $isAssignedDriver || $isAdmin, 403);
    }

    public function transformRide(Ride $ride): array
    {
        return [
            'id'              => $ride->id,
            'status'          => $ride->status->value,
            'pickup'          => [
                'lat'     => (float) $ride->pickup_lat,
                'lng'     => (float) $ride->pickup_lng,
                'address' => $ride->pickup_address,
            ],
            'dropoff'         => [
                'lat'     => (float) $ride->dropoff_lat,
                'lng'     => (float) $ride->dropoff_lng,
                'address' => $ride->dropoff_address,
            ],
            'formula_name'    => $ride->formula_name,
            'distance_km'     => (float) $ride->distance_km,
            'duration_min'    => (int) $ride->duration_minutes,
            'price_estimated' => (int) $ride->price_estimated,
            'price_final'     => $ride->price_final ? (int) $ride->price_final : null,
            'currency'        => $ride->currency,
            'payment_method'  => $ride->payment_method,
            'payment_status'  => $ride->payment_status,
            'rating'          => $ride->rating,
            'review'          => $ride->review,
            'passenger_id'    => (int) $ride->passenger_id,
            'driver'          => $ride->driver ? [
                'id'         => $ride->driver->id,
                'name'       => $ride->driver->user?->name,
                'phone'      => $ride->driver->user?->phone,
                'avatar_url' => $ride->driver->user?->avatar_url,
                'rating_avg' => (float) $ride->driver->rating_avg,
                'lat'        => $ride->driver->current_lat ? (float) $ride->driver->current_lat : null,
                'lng'        => $ride->driver->current_lng ? (float) $ride->driver->current_lng : null,
                'vehicle'    => $ride->driver->activeVehicle ? [
                    'brand'        => $ride->driver->activeVehicle->brand,
                    'model'        => $ride->driver->activeVehicle->model,
                    'color'        => $ride->driver->activeVehicle->color,
                    'plate_number' => $ride->driver->activeVehicle->plate_number,
                ] : null,
            ] : null,
            'requested_at'    => $ride->requested_at?->toIso8601String(),
            'accepted_at'     => $ride->accepted_at?->toIso8601String(),
            'started_at'      => $ride->started_at?->toIso8601String(),
            'completed_at'    => $ride->completed_at?->toIso8601String(),
            'cancelled_at'    => $ride->cancelled_at?->toIso8601String(),
        ];
    }
}
