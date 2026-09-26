<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'category_id',
        'base_uom_id',
        'min_stock',
        'cost_price',
        'sell_price',
        'barcode',
        'description',
        'is_active',
    ];

    protected $casts = [
        'min_stock' => 'float',
        'cost_price' => 'float',
        'sell_price' => 'float',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'base_uom_id');
    }

    public function uomConversions(): HasMany
    {
        return $this->hasMany(ItemUomConversion::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }
}
