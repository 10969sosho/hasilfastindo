<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NavPurchaseOrderItem extends Model
{
    protected $fillable = [
        'po_id',
        'sku',
        'item_name',
        'uom_nav',
        'nav_qty_pcs',
        'received_qty_pcs',
        'status',
    ];

    protected $casts = [
        'nav_qty_pcs' => 'float',
        'received_qty_pcs' => 'float',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(NavPurchaseOrder::class, 'po_id');
    }

    public function remainingQtyPcs(): float
    {
        return max(0, $this->nav_qty_pcs - $this->received_qty_pcs);
    }
}
