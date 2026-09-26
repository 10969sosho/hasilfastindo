<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderItem extends Model
{
    protected $fillable = [
        'sales_order_id',
        'item_id',
        'uom_id',
        'requested_qty',
        'fulfilled_qty',
        'picked_qty',
        'packed_qty',
        'unit_price',
        'notes',
    ];

    protected $casts = [
        'requested_qty' => 'float',
        'fulfilled_qty' => 'float',
        'picked_qty' => 'float',
        'packed_qty' => 'float',
        'unit_price' => 'float',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    public function packingItems(): HasMany
    {
        return $this->hasMany(PackingListItem::class);
    }

    public function remainingQty(): float
    {
        return max(0, (float) $this->requested_qty - (float) $this->fulfilled_qty);
    }
}
