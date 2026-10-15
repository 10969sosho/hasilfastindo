<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Staging Purchase Order dari NAV/BC — mandiri, tidak menyentuh data operasional gudang.
 */
class NavPurchaseOrder extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RELEASED = 'released';

    public const STATUS_PARTIAL = 'partially_received';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'po_no',
        'order_date',
        'supplier_name',
        'status',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(NavPurchaseOrderItem::class, 'po_id');
    }

    public function isReceivable(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_RELEASED, self::STATUS_PARTIAL], true);
    }

    public function totalNavQtyPcs(): float
    {
        return (float) $this->items->sum('nav_qty_pcs');
    }

    public function totalReceivedQtyPcs(): float
    {
        return (float) $this->items->sum('received_qty_pcs');
    }
}
