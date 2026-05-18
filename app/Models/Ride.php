<?php

namespace App\Models;

use App\Enums\RideStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ride extends Model
{
    use HasFactory;

    protected $fillable = [
        'passenger_id',
        'driver_id',
        'vehicle_id',
        'pricing_formula_id',
        'formula_name',
        'status',
        'pickup_lat',
        'pickup_lng',
        'pickup_address',
        'dropoff_lat',
        'dropoff_lng',
        'dropoff_address',
        'distance_km',
        'duration_minutes',
        'price_estimated',
        'price_final',
        'currency',
        'payment_method',
        'payment_status',
        'rating',
        'review',
        'requested_at',
        'accepted_at',
        'arriving_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status'           => RideStatus::class,
            'pickup_lat'       => 'float',
            'pickup_lng'       => 'float',
            'dropoff_lat'      => 'float',
            'dropoff_lng'      => 'float',
            'distance_km'      => 'float',
            'duration_minutes' => 'integer',
            'price_estimated'  => 'integer',
            'price_final'      => 'integer',
            'rating'           => 'integer',
            'requested_at'     => 'datetime',
            'accepted_at'      => 'datetime',
            'arriving_at'      => 'datetime',
            'started_at'       => 'datetime',
            'completed_at'     => 'datetime',
            'cancelled_at'     => 'datetime',
        ];
    }

    // ---------------------------------------------------------------- Relations

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function pricingFormula(): BelongsTo
    {
        return $this->belongsTo(PricingFormula::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(RideStatusLog::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(RideMessage::class);
    }

    // ---------------------------------------------------------------- Scopes

    public function scopeRequested($query)
    {
        return $query->where('status', RideStatus::REQUESTED);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            RideStatus::REQUESTED,
            RideStatus::ACCEPTED,
            RideStatus::ARRIVING,
            RideStatus::IN_PROGRESS,
        ]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', RideStatus::COMPLETED);
    }
}
