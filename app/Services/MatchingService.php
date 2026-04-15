<?php

namespace App\Services;

use App\Models\Driver;
use Illuminate\Support\Collection;

/**
 * Matching "plus proche chauffeur disponible" pour le prototype.
 *
 * Algorithme proto :
 *  1. Bounding box SQL autour du pickup (rayon configurable)
 *  2. Post-filtre Haversine PHP
 *  3. Tri par distance croissante
 *  4. Retourne la collection ordonnée.
 *
 * Au MVP : remplacer par une requête SQL avec ST_Distance_Sphere (MySQL 8).
 */
class MatchingService
{
    public function __construct(private readonly GeoService $geo)
    {
    }

    /**
     * @return Collection<int, Driver> Chauffeurs disponibles, triés par distance.
     */
    public function findAvailableDrivers(float $pickupLat, float $pickupLng, ?float $radiusKm = null): Collection
    {
        $radius = $radiusKm ?? (float) config('taxigab.matching.radius_km', 10);

        [$minLat, $maxLat, $minLng, $maxLng] = $this->geo->boundingBox($pickupLat, $pickupLng, $radius);

        $candidates = Driver::available()
            ->whereBetween('current_lat', [$minLat, $maxLat])
            ->whereBetween('current_lng', [$minLng, $maxLng])
            ->with(['user:id,name,phone,avatar_url', 'activeVehicle'])
            ->get();

        return $candidates
            ->map(function (Driver $driver) use ($pickupLat, $pickupLng) {
                $driver->distance_km = $this->geo->haversine(
                    $pickupLat,
                    $pickupLng,
                    (float) $driver->current_lat,
                    (float) $driver->current_lng,
                );
                return $driver;
            })
            ->filter(fn (Driver $d) => $d->distance_km <= (float) config('taxigab.matching.radius_km', 10))
            ->sortBy('distance_km')
            ->values();
    }

    /**
     * Retourne le chauffeur le plus proche ou null.
     */
    public function findClosest(float $pickupLat, float $pickupLng, ?float $radiusKm = null): ?Driver
    {
        return $this->findAvailableDrivers($pickupLat, $pickupLng, $radiusKm)->first();
    }
}
