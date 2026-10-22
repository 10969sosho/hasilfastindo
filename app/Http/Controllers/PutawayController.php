<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemSerial;
use App\Models\Location;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Penempatan barang ke rak (Tahap 2): scan barcode ItemSerial -> berat aktual -> BIN tujuan.
 * Metode statis applySerialMove() juga dipakai modul Transfer Antar Bin.
 */
class PutawayController extends Controller
{
    public function index(): View
    {
        return view('putaway.index');
    }

    /**
     * Detail barang hasil scan barcode + daftar BIN dalam gudang yang sama.
     */
    public function lookup(Request $request)
    {
        $code = trim($request->input('barcode', ''));

        if ($code === '') {
            return response()->json(['ok' => false, 'message' => 'Barcode kosong.'], 422);
        }

        $serial = ItemSerial::with('location')->find($code);

        if (! $serial) {
            return response()->json(['ok' => false, 'message' => "Barcode {$code} tidak ditemukan."], 404);
        }

        $warehouseId = (int) ($serial->location?->warehouse_id
            ?: Location::where('code', $serial->bin_no)->value('warehouse_id'));

        return response()->json([
            'ok' => true,
            'message' => 'Barang ditemukan: '.$serial->sku,
            'payload' => $serial->toLookupPayload(),
            'bins' => static::binsOf($warehouseId ?: null, (int) $serial->location_id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode_uom_serialno' => ['required', 'string', 'max:160'],
            'location_id' => ['required', 'exists:locations,id'],
            'weight' => ['nullable', 'numeric', 'min:0'],
        ]);

        $serial = ItemSerial::find($data['kode_uom_serialno']);

        if (! $serial) {
            return back()->withErrors(['barcode' => 'Barcode barang tidak ditemukan.']);
        }

        $target = Location::with('warehouse')->findOrFail($data['location_id']);
        $this->guardBranch((int) $target->warehouse?->branch_id);

        [$ok, $message] = static::applySerialMove(
            $serial,
            $target,
            ($data['weight'] ?? '') !== '' ? (float) $data['weight'] : null,
            'PUTAWAY',
        );

        return $ok
            ? back()->with('toast', $message)
            : back()->withErrors(['barcode' => $message]);
    }

    /**
     * Pindahkan satu serial ke BIN tujuan: update stok, catat StockMovement, perbarui ItemSerial.
     * Otorisasi cabang wajib dipanggil penerima lewat guardBranch() sebelum method ini.
     *
     * @return array{0: bool, 1: string} [sukses, pesan]
     */
    public static function applySerialMove(ItemSerial $serial, Location $target, ?float $weight, string $movementType, ?string $reference = null): array
    {
        $branchId = (int) $target->warehouse?->branch_id;

        if ($branchId === 0) {
            return [false, 'BIN tujuan tidak tergabung pada gudang manapun.'];
        }

        if ((int) $serial->location_id === (int) $target->id) {
            return [false, "Barang sudah berada di BIN {$target->code}."];
        }

        $item = Item::where('sku', $serial->sku)->first();

        if (! $item) {
            return [false, "SKU {$serial->sku} tidak ada di Master Item."];
        }

        $qtyPcs = max(0, (float) $serial->qty_smallest_unit_in);

        DB::transaction(function () use ($serial, $target, $item, $qtyPcs, $branchId, $weight, $movementType, $reference) {
            $stock = Stock::query()
                ->where('branch_id', $branchId)
                ->where('item_id', $item->id)
                ->where('quantity_pcs', '>', 0)
                ->when($serial->location_id, fn ($q) => $q->where('location_id', $serial->location_id))
                ->orderByRaw('CASE WHEN received_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('received_at')
                ->orderBy('id')
                ->first();

            if ($stock && $qtyPcs > 0) {
                $movedQty = min($qtyPcs, (float) $stock->quantity_pcs);
                $moved = StockService::moveLocation($stock, (int) $target->id, $movedQty);

                StockMovement::create([
                    'date' => now()->toDateString(),
                    'branch_id' => $branchId,
                    'warehouse_id' => (int) $target->warehouse_id,
                    'item_id' => (int) $item->id,
                    'batch_id' => $stock->batch_id,
                    'location_id' => (int) $target->id,
                    'uom_id' => (int) $moved->uom_id,
                    'type' => $movementType,
                    'reference_no' => $reference ?: $serial->receive_no,
                    'qty_in' => $movedQty,
                    'user_id' => auth()->id(),
                    'notes' => $movementType.' '.$serial->kode_uom_serialno.' ke '.$target->code,
                ]);
            }

            $serial->update([
                'location_id' => (int) $target->id,
                'bin_no' => $target->code,
                'weight' => $weight ?? $serial->weight,
            ]);
        });

        return [true, "{$serial->kode_uom_serialno} dipindahkan ke BIN {$target->code}."];
    }

    /**
     * @return Collection<int, array{id: int, code: string, rack: ?string}>
     */
    public static function binsOf(?int $warehouseId, ?int $excludeLocationId = null)
    {
        if (! $warehouseId) {
            return collect();
        }

        return Location::where('warehouse_id', $warehouseId)
            ->when($excludeLocationId, fn ($q) => $q->where('id', '!=', $excludeLocationId))
            ->orderBy('code')
            ->get()
            ->map(fn (Location $l) => ['id' => $l->id, 'code' => $l->code, 'rack' => $l->rack]);
    }
}
