<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Staging Warehouse Pick dari NAV/BC — acuan perintah pengambilan barang fisik.
 */
class NavWarehousePick extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'pick_no',
        'source_so_no',
        'whse_shipment_no',
        'location_code',
        'destination_no',
        'assigned_user_id',
        'assignment_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'assignment_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(NavWarehousePickItem::class, 'nav_pick_id');
    }

    public function takeItems(): HasMany
    {
        return $this->hasMany(NavWarehousePickItem::class, 'nav_pick_id')->where('action_type', 'Take');
    }

    public function totalQty(): float
    {
        return (float) $this->takeItems()->sum('quantity');
    }

    public function totalTakeQty(): float
    {
        if ($this->relationLoaded('takeItems')) {
            return (float) $this->takeItems->sum('quantity');
        }

        return (float) $this->takeItems()->sum('quantity');
    }

    public function totalQtyHandled(): float
    {
        return (float) $this->takeItems()->sum('qty_handled');
    }
}
