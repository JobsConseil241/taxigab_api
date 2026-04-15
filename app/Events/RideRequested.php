<?php

namespace App\Events;

use App\Models\Ride;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideRequested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Ride $ride)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('rides.available')];
    }

    public function broadcastAs(): string
    {
        return 'ride.requested';
    }

    public function broadcastWith(): array
    {
        return [
            'ride' => [
                'id'              => $this->ride->id,
                'pickup_lat'      => (float) $this->ride->pickup_lat,
                'pickup_lng'      => (float) $this->ride->pickup_lng,
                'pickup_address'  => $this->ride->pickup_address,
                'dropoff_lat'     => (float) $this->ride->dropoff_lat,
                'dropoff_lng'     => (float) $this->ride->dropoff_lng,
                'dropoff_address' => $this->ride->dropoff_address,
                'distance_km'     => (float) $this->ride->distance_km,
                'price_estimated' => (int) $this->ride->price_estimated,
                'currency'        => $this->ride->currency,
                'requested_at'    => $this->ride->requested_at?->toIso8601String(),
            ],
        ];
    }
}
