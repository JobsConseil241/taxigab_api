<?php

namespace App\Events;

use App\Enums\RideStatus;
use App\Models\Ride;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Ride $ride,
        public RideStatus $previousStatus,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('ride.' . $this->ride->id)];
    }

    public function broadcastAs(): string
    {
        return 'ride.status_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'ride_id'         => $this->ride->id,
            'previous_status' => $this->previousStatus->value,
            'new_status'      => $this->ride->status->value,
            'price_final'     => $this->ride->price_final,
            'timestamp'       => now()->toIso8601String(),
        ];
    }
}
