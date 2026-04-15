<?php

namespace App\Models;

use App\Enums\RideStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideStatusLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ride_id',
        'status',
        'changed_by',
        'lat',
        'lng',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'status'     => RideStatus::class,
            'lat'        => 'float',
            'lng'        => 'float',
            'created_at' => 'datetime',
        ];
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
