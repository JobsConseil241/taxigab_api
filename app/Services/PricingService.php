<?php

namespace App\Services;

use App\Models\PricingFormula;

/**
 * Calcul de prix d'une course Taxi Gab.
 *
 * Formule :
 *   prix = base_fare + (distance_km * per_km)
 *   puis arrondi à la borne supérieure par tranche (ex: 100 FCFA)
 *   puis borné au minimum par min_fare.
 *
 * Accepte une PricingFormula optionnelle ; sinon fallback sur config/taxigab.php.
 */
class PricingService
{
    /**
     * @return int Prix final en FCFA (entier, pas de centimes).
     */
    public function estimate(float $distanceKm, float $durationMinutes = 0, ?PricingFormula $formula = null): int
    {
        $baseFare = $formula ? $formula->base_fare : (int) config('taxigab.pricing.base_fare', 500);
        $perKm    = $formula ? $formula->per_km    : (int) config('taxigab.pricing.per_km', 350);
        $minFare  = $formula ? $formula->min_fare  : (int) config('taxigab.pricing.min_fare', 1000);
        $step     = $formula ? $formula->rounding_step : (int) config('taxigab.pricing.rounding_step', 100);

        $fare = $baseFare + ($distanceKm * $perKm);

        // Arrondi à la centaine supérieure.
        $rounded = $step > 0
            ? ((int) ceil($fare / $step)) * $step
            : (int) ceil($fare);

        return max($minFare, $rounded);
    }

    /**
     * Calcule le prix pour chaque formule active.
     *
     * @return array<int, array{id: int, slug: string, name: string, description: ?string, icon: string, price: int}>
     */
    public function estimateAll(float $distanceKm, float $durationMinutes = 0): array
    {
        $formulas = PricingFormula::active()->ordered()->get();

        return $formulas->map(fn (PricingFormula $f) => [
            'id'          => $f->id,
            'slug'        => $f->slug,
            'name'        => $f->name,
            'description' => $f->description,
            'icon'        => $f->icon,
            'price'       => $this->estimate($distanceKm, $durationMinutes, $f),
        ])->all();
    }

    public function currency(): string
    {
        return (string) config('taxigab.pricing.currency', 'XAF');
    }
}
