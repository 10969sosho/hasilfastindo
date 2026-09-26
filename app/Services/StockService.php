<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satu-satunya tempat penambahan / pengurangan baris stok + pencatatan karteks.
 */
class StockService
{
    /**
     * Tambah stok (INSERT atau ACCUMULATE pada baris yang sama), lalu catat karteks.
     *
     * @param  array{received_at?: Carbon, original_barcode?: string, type?: string, reference_no?: ?string, user_id?: ?int, notes?: ?string, date?: Carbon, uom_id?: int}  $opts
     */
    public static function add(int $branchId, int $warehouseId, int $locationId, int $itemId, ?int $batchId, int $uomId, float $qtyPcs, array $opts = []): Stock
    {
        $stock = Stock::firstOrNew([
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'item_id' => $itemId,
            'batch_id' => $batchId,
            'uom_id' => $uomId,
        ]);

        if (! $stock->exists) {
            $stock->original_barcode = $opts['original_barcode'] ?? null;
            $stock->received_at = $opts['received_at'] ?? now();
            $stock->quantity_pcs = 0;
        }

        $stock->quantity_pcs = (float) $stock->quantity_pcs + $qtyPcs;
        $stock->save();

        static::log($opts, [
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'item_id' => $itemId,
            'batch_id' => $batchId,
            'qty_in' => $qtyPcs,
            'type' => $opts['type'] ?? 'IN',
        ], $uomId);

        return $stock;
    }

    /**
     * Kurangi stok (FIFO, atau dibatasi lokasi/batch/warehouse tertentu).
     * Mengembalikan jumlah PCS yang berhasil dikurangi.
     *
     * @param  array{location_id?: int, batch_id?: int, warehouse_id?: int, type?: string, reference_no?: ?string, user_id?: ?int, notes?: ?string, date?: Carbon}  $opts
     */
    public static function remove(int $branchId, int $itemId, float $qtyPcs, array $opts = []): float
    {
        if ($qtyPcs <= 0) {
            return 0;
        }

        $rows = static::candidates($branchId, $itemId, $opts)->get();

        $remaining = $qtyPcs;
        $consumed = 0;

        foreach ($rows as $stock) {
            if ($remaining <= 0) {
                break;
            }

            $take = min((float) $stock->quantity_pcs, $remaining);

            $stock->quantity_pcs = (float) $stock->quantity_pcs - $take;
            $stock->save();

            static::log($opts, [
                'branch_id' => $branchId,
                'warehouse_id' => $stock->warehouse_id,
                'location_id' => $stock->location_id,
                'item_id' => $itemId,
                'batch_id' => $stock->batch_id,
                'qty_out' => $take,
                'type' => $opts['type'] ?? 'OUT',
            ], (int) $stock->uom_id);

            $remaining -= $take;
            $consumed += $take;
        }

        return $consumed;
    }

    /**
     * Pindahkan sebagian stok antar BIN (kuantitas tidak berubah, hanya lokasi).
     */
    public static function moveLocation(Stock $stock, int $targetLocationId, float $qtyPcs): Stock
    {
        $qtyPcs = min($qtyPcs, (float) $stock->quantity_pcs);

        if ($qtyPcs <= 0) {
            return $stock;
        }

        if ((int) $stock->location_id === $targetLocationId) {
            return $stock;
        }

        $stock->quantity_pcs = (float) $stock->quantity_pcs - $qtyPcs;
        $stock->save();

        return static::add(
            (int) $stock->branch_id,
            (int) $stock->warehouse_id,
            $targetLocationId,
            (int) $stock->item_id,
            $stock->batch_id ? (int) $stock->batch_id : null,
            (int) $stock->uom_id,
            $qtyPcs,
            [
                'received_at' => $stock->received_at ?? now(),
                'original_barcode' => $stock->original_barcode,
                'type' => null,
            ]
        );
    }

    /**
     * @param  array{location_id?: int, batch_id?: int, warehouse_id?: int}  $opts
     * @return Builder<Stock>
     */
    protected static function candidates(int $branchId, int $itemId, array $opts)
    {
        return Stock::query()
            ->where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->where('quantity_pcs', '>', 0)
            ->when($opts['location_id'] ?? null, fn ($q) => $q->where('location_id', $opts['location_id']))
            ->when($opts['batch_id'] ?? null, fn ($q) => $q->where('batch_id', $opts['batch_id']))
            ->when($opts['warehouse_id'] ?? null, fn ($q) => $q->where('warehouse_id', $opts['warehouse_id']))
            ->orderByRaw('CASE WHEN received_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('received_at')
            ->orderBy('id');
    }

    /**
     * @param  array{type?: ?string, reference_no?: ?string, user_id?: ?int, notes?: ?string, date?: Carbon|null}  $opts
     * @param  array{branch_id: int, warehouse_id?: int, location_id?: int, item_id: int, batch_id?: int, qty_in?: float, qty_out?: float, type?: ?string}  $payload
     */
    protected static function log(array $opts, array $payload, int $uomId): void
    {
        $type = $payload['type'] ?? null;

        if ($type === null) {
            return;
        }

        StockMovement::create([
            'date' => $opts['date'] ?? Carbon::today(),
            'branch_id' => $payload['branch_id'],
            'warehouse_id' => $payload['warehouse_id'] ?? null,
            'item_id' => $payload['item_id'],
            'batch_id' => $payload['batch_id'] ?? null,
            'location_id' => $payload['location_id'] ?? null,
            'uom_id' => $uomId,
            'type' => $type,
            'reference_no' => $opts['reference_no'] ?? null,
            'qty_in' => $payload['qty_in'] ?? 0,
            'qty_out' => $payload['qty_out'] ?? 0,
            'user_id' => $opts['user_id'] ?? auth()->id(),
            'notes' => $opts['notes'] ?? null,
        ]);
    }
}
