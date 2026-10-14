<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id',
        'item_id',
        'batch_id',
        'batch_no',
        'received_uom_id',
        'received_qty',
        'conversion_multiplier',
        'total_pcs',
        'nav_qty',
        'qty_placed',
        'target_bin_id',
        'bin_no',
        'weight',
        'unit_overrides',
        'original_barcode',
        'notes',
    ];

    protected $casts = [
        'received_qty' => 'float',
        'conversion_multiplier' => 'float',
        'total_pcs' => 'float',
        'nav_qty' => 'float',
        'qty_placed' => 'float',
        'weight' => 'float',
        'unit_overrides' => 'array',
    ];

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function receivedUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'received_uom_id');
    }

    public function targetBin(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'target_bin_id');
    }
}
