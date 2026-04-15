<?php

namespace App\Events;

use App\Models\Driver;
use App\Models\Ride;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Driver $driver,
        public ?Ride $activeRide = null,
    ) {
    }

    public function broadcastOn(): array
    {
        // Broadcast sur le canal de la course active (si présente)
        // ET sur le canal privé du chauffeur (historique perso).
        $channels = [new PrivateChannel('driver.' . $this->driver->id)];

        if ($this->activeRide) {
            $channels[] = new PrivateChannel('ride.' . $this->activeRide->id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'driver.location_updated';
    }

    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->driver->id,
            'ride_id'   => $this->activeRide?->id,
            'lat'       => (float) $this->driver->current_lat,
            'lng'       => (float) $this->driver->current_lng,
            'heading'   => (int) ($this->driver->current_heading ?? 0),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
