<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'license_number',
        'status',
        'is_online',
        'current_lat',
        'current_lng',
        'current_heading',
        'rating_avg',
        'total_rides',
        'last_location_at',
    ];

    protected function casts(): array
    {
        return [
            'is_online'        => 'boolean',
            'current_lat'      => 'float',
            'current_lng'      => 'float',
            'current_heading'  => 'integer',
            'rating_avg'       => 'float',
            'total_rides'      => 'integer',
            'last_location_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------- Relations

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function activeVehicle(): HasOne
    {
        return $this->hasOne(Vehicle::class)->where('is_active', true);
    }

    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class);
    }

    // ---------------------------------------------------------------- Scopes

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }

    public function scopeAvailable($query)
    {
        return $query->approved()
                     ->online()
                     ->whereNotNull('current_lat')
                     ->whereNotNull('current_lng');
    }
}
