<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NavWarehousePickItem extends Model
{
    protected $fillable = [
        'nav_pick_id',
        'line_no',
        'action_type',
        'sku',
        'variant_code',
        'item_name',
        'uom_code',
        'bin_code',
        'zone_code',
        'quantity',
        'qty_base',
        'qty_to_handle',
        'qty_handled',
        'qty_outstanding',
        'due_date',
    ];

    protected $casts = [
        'quantity' => 'float',
        'qty_base' => 'float',
        'qty_to_handle' => 'float',
        'qty_handled' => 'float',
        'qty_outstanding' => 'float',
        'due_date' => 'date',
    ];

    public function pick(): BelongsTo
    {
        return $this->belongsTo(NavWarehousePick::class, 'nav_pick_id');
    }

    public function masterItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'sku', 'sku');
    }
}
