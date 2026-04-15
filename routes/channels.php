<?php

use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels (Taxi Gab)
|--------------------------------------------------------------------------
*/

// Canal par défaut généré par Laravel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal d'une course : accessible au passager et au chauffeur assigné
Broadcast::channel('ride.{rideId}', function (User $user, int $rideId) {
    $ride = Ride::find($rideId);
    if (! $ride) {
        return false;
    }

    // Passager de la course
    if ($user->id === (int) $ride->passenger_id) {
        return true;
    }

    // Chauffeur assigné
    if ($user->isDriver() && $ride->driver_id && $user->driver?->id === (int) $ride->driver_id) {
        return true;
    }

    return false;
});

// Canal privé d'un chauffeur : uniquement ce chauffeur
Broadcast::channel('driver.{driverId}', function (User $user, int $driverId) {
    return $user->isDriver() && $user->driver?->id === (int) $driverId;
});

// Canal des courses disponibles : tout chauffeur approuvé peut s'y abonner
Broadcast::channel('rides.available', function (User $user) {
    return $user->isDriver() && $user->driver?->status === 'approved';
});
