<?php

namespace App\Http\Controllers\Api;

use App\Enums\RideStatus;
use App\Events\DriverLocationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\UpdateLocationRequest;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    /**
     * POST /api/driver/location — le chauffeur envoie sa position (toutes les 3s).
     */
    public function update(UpdateLocationRequest $request): JsonResponse
    {
        $driver = $request->user()->driver;
        abort_unless($driver, 403);

        $driver->update([
            'current_lat'      => $request->input('lat'),
            'current_lng'      => $request->input('lng'),
            'current_heading'  => $request->integer('heading'),
            'last_location_at' => now(),
        ]);

        // S'il a une course active, on broadcast aussi sur ride.{id}
        $activeRide = Ride::where('driver_id', $driver->id)
            ->whereIn('status', [
                RideStatus::ACCEPTED,
                RideStatus::ARRIVING,
                RideStatus::IN_PROGRESS,
            ])
            ->first();

        broadcast(new DriverLocationUpdated($driver->fresh(), $activeRide))->toOthers();

        return response()->json(['ok' => true]);
    }
}
