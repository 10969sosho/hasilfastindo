<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockRepackItem extends Model
{
    protected $fillable = [
        'stock_repack_id',
        'item_id',
        'source_barcode',
        'batch_no',
        'batch_id',
        'source_uom_id',
        'source_qty',
        'source_qty_pcs',
        'target_item_id',
        'target_barcode_new',
        'target_uom_id',
        'target_qty',
        'target_location_id',
    ];

    protected $casts = [
        'source_qty' => 'float',
        'source_qty_pcs' => 'float',
        'target_qty' => 'float',
    ];

    public function stockRepack(): BelongsTo
    {
        return $this->belongsTo(StockRepack::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function targetItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'target_item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function sourceUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'source_uom_id');
    }

    public function targetUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'target_uom_id');
    }

    public function targetLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'target_location_id');
    }
}
