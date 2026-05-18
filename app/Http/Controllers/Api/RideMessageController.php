<?php

namespace App\Http\Controllers\Api;

use App\Enums\RideStatus;
use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\RideMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideMessageController extends Controller
{
    /**
     * GET /api/rides/{ride}/messages
     */
    public function index(Request $request, Ride $ride): JsonResponse
    {
        $this->authorizeAccess($request, $ride);

        // Mark counterpart messages as read first so the response reflects it.
        $userId = $request->user()->id;
        RideMessage::query()
            ->where('ride_id', $ride->id)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = $ride->messages()
            ->with('sender:id,name,role')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'messages' => $messages->map(fn (RideMessage $m) => $this->transform($m))->all(),
        ]);
    }

    /**
     * POST /api/rides/{ride}/messages  { body }
     */
    public function store(Request $request, Ride $ride): JsonResponse
    {
        $this->authorizeAccess($request, $ride);

        if (! in_array($ride->status, [
            RideStatus::ACCEPTED,
            RideStatus::ARRIVING,
            RideStatus::IN_PROGRESS,
        ], true)) {
            return response()->json([
                'message' => 'Messagerie indisponible pour cette course.',
            ], 422);
        }

        $data = $request->validate([
            'body' => 'required|string|min:1|max:1000',
        ]);

        $message = RideMessage::create([
            'ride_id'   => $ride->id,
            'sender_id' => $request->user()->id,
            'body'      => trim($data['body']),
        ]);

        $message->load('sender:id,name,role');

        broadcast(new MessageSent($message))->toOthers();

        return response()->json([
            'message' => $this->transform($message),
        ], 201);
    }

    // ---------------------------------------------------------------- Helpers

    private function authorizeAccess(Request $request, Ride $ride): void
    {
        $user = $request->user();

        $isPassenger = $user->id === (int) $ride->passenger_id;
        $isAssignedDriver = $user->isDriver()
            && $ride->driver_id
            && $user->driver?->id === (int) $ride->driver_id;

        abort_unless($isPassenger || $isAssignedDriver, 403);
    }

    private function transform(RideMessage $m): array
    {
        return [
            'id'          => (int) $m->id,
            'ride_id'     => (int) $m->ride_id,
            'sender_id'   => (int) $m->sender_id,
            'sender_name' => $m->sender?->name,
            'sender_role' => $m->sender?->role,
            'body'        => $m->body,
            'read_at'     => $m->read_at?->toIso8601String(),
            'created_at'  => $m->created_at?->toIso8601String(),
        ];
    }
}
