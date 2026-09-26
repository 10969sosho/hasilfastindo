<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Uom extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'code',
        'name',
    ];

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_uom_conversions', 'from_uom_id', 'to_uom_id')
            ->withPivot('multiplier');
    }

    public function itemsAsBase(): HasMany
    {
        return $this->hasMany(Item::class, 'base_uom_id');
    }

    public function conversionsFrom(): HasMany
    {
        return $this->hasMany(ItemUomConversion::class, 'from_uom_id');
    }

    public function conversionsTo(): HasMany
    {
        return $this->hasMany(ItemUomConversion::class, 'to_uom_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'uom_id');
    }
}
