<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'location_id',
        'item_id',
        'batch_id',
        'uom_id',
        'quantity_pcs',
        'original_barcode',
        'received_at',
    ];

    protected $casts = [
        'quantity_pcs' => 'float',
        'received_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    /* ------------------------------------------------------------------
     |  Helpers: kalkulasi stok & FIFO picking
     ------------------------------------------------------------------ */

    public function scopeAvailable($query)
    {
        return $query->where('quantity_pcs', '>', 0);
    }

    public function scopeOfBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeOfItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    /**
     * Total stok (dalam PCS) sebuah item pada satu cabang.
     */
    public static function totalQtyPcs(int $branchId, int $itemId): float
    {
        return (float) static::query()
            ->where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->sum('quantity_pcs');
    }

    /**
     * Total stok (PCS) sebuah item di seluruh cabang.
     */
    public static function totalQtyPcsAllBranches(int $itemId): float
    {
        return (float) static::query()->where('item_id', $itemId)->sum('quantity_pcs');
    }

    /**
     * Total stok (PCS) seluruh item dalam satu cabang.
     */
    public static function totalByBranch(int $branchId): float
    {
        return (float) static::query()->where('branch_id', $branchId)->sum('quantity_pcs');
    }

    /**
     * FIFO picking: ambil baris stok paling awal masuk (received_at ASC)
     * sampai kebutuhan qtyPcs terpenuhi.
     *
     * @return array<int, array{stock: Stock, qty: float}> alokasi per baris stok
     */
    public static function fifoPick(int $branchId, int $itemId, float $qtyPcs, ?int $locationId = null): array
    {
        if ($qtyPcs <= 0) {
            return [];
        }

        $query = static::query()
            ->where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->where('quantity_pcs', '>', 0)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->orderByRaw('CASE WHEN received_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('received_at')
            ->orderBy('id');

        $allocations = [];
        $remaining = $qtyPcs;

        foreach ($query->get() as $stock) {
            if ($remaining <= 0) {
                break;
            }

            $take = min((float) $stock->quantity_pcs, $remaining);
            $allocations[] = ['stock' => $stock, 'qty' => $take];
            $remaining -= $take;
        }

        return $allocations;
    }

    /**
     * Ketersediaan stok FIFO: true bila seluruh qtyPcs bisa dipenuhi.
     */
    public static function canFifoPick(int $branchId, int $itemId, float $qtyPcs): bool
    {
        $available = (float) static::query()
            ->where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->where('quantity_pcs', '>', 0)
            ->sum('quantity_pcs');

        return $available >= $qtyPcs;
    }
}
