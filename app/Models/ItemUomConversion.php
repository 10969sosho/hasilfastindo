<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemUomConversion extends Model
{
    protected $fillable = [
        'item_id',
        'from_uom_id',
        'to_uom_id',
        'multiplier',
    ];

    protected $casts = [
        'multiplier' => 'float',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function fromUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'from_uom_id');
    }

    public function toUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'to_uom_id');
    }
}
