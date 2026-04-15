<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricingFormula extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'icon',
        'base_fare',
        'per_km',
        'per_minute_wait',
        'min_fare',
        'rounding_step',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_fare'       => 'integer',
            'per_km'          => 'integer',
            'per_minute_wait' => 'integer',
            'min_fare'        => 'integer',
            'rounding_step'   => 'integer',
            'sort_order'      => 'integer',
            'is_active'       => 'boolean',
        ];
    }

    // ---------------------------------------------------------------- Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    // ---------------------------------------------------------------- Relations

    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class, 'pricing_formula_id');
    }
}
