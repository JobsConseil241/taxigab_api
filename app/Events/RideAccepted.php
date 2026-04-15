<?php

namespace App\Events;

use App\Models\Ride;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideAccepted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Ride $ride)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('ride.' . $this->ride->id)];
    }

    public function broadcastAs(): string
    {
        return 'ride.accepted';
    }

    public function broadcastWith(): array
    {
        $this->ride->loadMissing(['driver.user', 'driver.activeVehicle']);
        $driver  = $this->ride->driver;
        $vehicle = $driver?->activeVehicle;

        return [
            'ride_id'   => $this->ride->id,
            'status'    => $this->ride->status->value,
            'driver'    => $driver ? [
                'id'         => $driver->id,
                'name'       => $driver->user?->name,
                'phone'      => $driver->user?->phone,
                'avatar_url' => $driver->user?->avatar_url,
                'rating_avg' => (float) $driver->rating_avg,
                'lat'        => (float) $driver->current_lat,
                'lng'        => (float) $driver->current_lng,
            ] : null,
            'vehicle'   => $vehicle ? [
                'brand'        => $vehicle->brand,
                'model'        => $vehicle->model,
                'color'        => $vehicle->color,
                'plate_number' => $vehicle->plate_number,
                'photo_url'    => $vehicle->photo_url,
            ] : null,
            'accepted_at' => $this->ride->accepted_at?->toIso8601String(),
        ];
    }
}
