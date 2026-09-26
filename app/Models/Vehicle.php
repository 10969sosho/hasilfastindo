<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'plate_number',
        'vehicle_name',
        'type',
        'capacity_kg',
        'driver_id',
        'helper_id',
        'driver_name',
        'helper_name',
        'status',
    ];

    protected $casts = [
        'capacity_kg' => 'float',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function helper(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'helper_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
