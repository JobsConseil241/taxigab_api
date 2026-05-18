<?php

namespace App\Events;

use App\Models\RideMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public RideMessage $message)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('ride.' . $this->message->ride_id)];
    }

    public function broadcastAs(): string
    {
        return 'ride.message';
    }

    public function broadcastWith(): array
    {
        $this->message->loadMissing('sender');

        return [
            'id'         => (int) $this->message->id,
            'ride_id'    => (int) $this->message->ride_id,
            'sender_id'  => (int) $this->message->sender_id,
            'sender_name'=> $this->message->sender?->name,
            'sender_role'=> $this->message->sender?->role,
            'body'       => $this->message->body,
            'read_at'    => $this->message->read_at?->toIso8601String(),
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
