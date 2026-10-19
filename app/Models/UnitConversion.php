<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Perintah konversi satuan (breakdown/repack) berbasis barcode ItemSerial.
 * Eksekusi menonaktifkan barcode asal dan menerbitkan barcode baru.
 */
class UnitConversion extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'conversion_no',
        'item_id',
        'from_uom_id',
        'to_uom_id',
        'planned_qty',
        'executed_qty',
        'status',
        'branch_id',
        'warehouse_id',
        'user_id',
        'notes',
        'results',
    ];

    protected $casts = [
        'planned_qty' => 'float',
        'executed_qty' => 'float',
        'results' => 'array',
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function remainingQty(): float
    {
        return max(0, (float) $this->planned_qty - (float) $this->executed_qty);
    }
}
