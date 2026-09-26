<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const TYPES = [
        'IN', 'OUT', 'TRANSFER_IN', 'TRANSFER_OUT', 'ADJUSTMENT', 'REPACK', 'REPACK_OUT',
    ];

    protected $fillable = [
        'date',
        'branch_id',
        'warehouse_id',
        'item_id',
        'batch_id',
        'location_id',
        'uom_id',
        'type',
        'reference_no',
        'qty_in',
        'qty_out',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'qty_in' => 'float',
        'qty_out' => 'float',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
