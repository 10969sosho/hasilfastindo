<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'license_number',
        'role',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function deliveriesAsDriver(): HasMany
    {
        return $this->hasMany(Delivery::class, 'driver_id');
    }

    public function deliveriesAsHelper(): HasMany
    {
        return $this->hasMany(Delivery::class, 'helper_id');
    }

    public function vehiclesAsDriver(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'driver_id');
    }

    public function vehiclesAsHelper(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'helper_id');
    }
}
