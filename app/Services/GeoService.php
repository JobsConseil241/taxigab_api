<?php

namespace App\Services;

/**
 * Services géographiques : distances, bounding boxes, etc.
 * Pour le prototype on reste sur du pur Haversine — pas de dépendance externe.
 */
class GeoService
{
    public const EARTH_RADIUS_KM = 6371.0;

    /**
     * Distance orthodromique entre deux points (kilomètres).
     */
    public function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
           + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }

    /**
     * Estimation naïve de la durée en voiture : 30 km/h de moyenne urbaine.
     * Le vrai calcul utilisera Google Directions au MVP.
     */
    public function estimateDurationMinutes(float $distanceKm, float $avgSpeedKmh = 30.0): int
    {
        if ($distanceKm <= 0) {
            return 0;
        }
        return (int) max(1, ceil(($distanceKm / $avgSpeedKmh) * 60));
    }

    /**
     * Bounding box approximative autour d'un point — utile pour un pré-filtre SQL.
     * Retourne [minLat, maxLat, minLng, maxLng].
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public function boundingBox(float $lat, float $lng, float $radiusKm): array
    {
        $latDelta = $radiusKm / 111.0; // ~111 km par degré de latitude
        $lngDelta = $radiusKm / (111.0 * max(cos(deg2rad($lat)), 0.01));

        return [
            $lat - $latDelta,
            $lat + $latDelta,
            $lng - $lngDelta,
            $lng + $lngDelta,
        ];
    }
}
