<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Item;
use App\Models\ItemSerial;
use App\Models\ItemUomConversion;
use App\Models\Location;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InboundController extends Controller
{
    public function index(Request $request): View
    {
        $query = GoodsReceipt::with(['supplier', 'branch', 'warehouse', 'items'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('doc_number', 'like', '%'.$request->string('q').'%')
                ->orWhere('source_so', 'like', '%'.$request->string('q').'%')));

        $receipts = $this->scopeBranch($query)->latest('id')->paginate(12)->withQueryString();

        $summary = [
            'draft' => $this->scopeBranch(GoodsReceipt::query())->where('status', 'draft')->count(),
            'received' => $this->scopeBranch(GoodsReceipt::query())->where('status', 'received')->count(),
            'putaway' => $this->scopeBranch(GoodsReceipt::query())->where('status', 'putaway')->count(),
            'completed' => $this->scopeBranch(GoodsReceipt::query())->where('status', 'completed')->count(),
        ];

        return view('inbound.index', compact('receipts', 'summary'));
    }

    public function create(Request $request): View
    {
        return view('inbound.form', $this->formData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'destination_location_id' => ['nullable', 'exists:locations,id'],
            'source_so' => ['nullable', 'string', 'max:60'],
            'po_no' => ['nullable', 'string', 'max:60'],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.batch_no' => ['nullable', 'string', 'max:60'],
            'items.*.received_uom_id' => ['required', 'exists:uoms,id'],
            'items.*.received_qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.nav_qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.target_bin_id' => ['nullable', 'exists:locations,id'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $this->guardBranch((int) $warehouse->branch_id);

        $branchId = (int) $warehouse->branch_id;

        $docNumber = $this->nextDocNumber('GRN-'.now()->format('Ym').'-', 'goods_receipts', 'doc_number');
        $sequence = (int) preg_replace('/\D+/', '', substr($docNumber, strrpos($docNumber, '-') + 1));
        $stamp = now()->format('Ymd');

        // Acuan Qty NAV (unit terkecil) per SKU: diisi pada baris pertama tiap SKU.
        $navByItem = [];
        $lineNav = [];

        foreach ($data['items'] as $i => $row) {
            $lineNav[$i] = null;
            $itemId = (int) $row['item_id'];

            if (($row['nav_qty'] ?? '') !== '' && ! array_key_exists($itemId, $navByItem)) {
                $navByItem[$itemId] = (float) $row['nav_qty'];
                $lineNav[$i] = $navByItem[$itemId];
            }
        }

        $physicalByItem = [];

        $gr = null;

        DB::transaction(function () use (&$gr, $data, $branchId, $docNumber, $sequence, $stamp, $lineNav, $navByItem, $physicalByItem) {
            $gr = GoodsReceipt::create([
                'doc_number' => $docNumber,
                'supplier_id' => $data['supplier_id'],
                'branch_id' => $branchId,
                'warehouse_id' => $data['warehouse_id'],
                'destination_location_id' => $data['destination_location_id'] ?? $this->defaultWarehouse($branchId)?->locations()->first()?->id,
                'source_so' => $data['source_so'] ?? null,
                'po_no' => $data['po_no'] ?? null,
                'received_at' => ($data['received_at'] ?? null) ? now()->startOfDay() : null,
                'status' => 'draft',
                'user_id' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $i => $row) {
                $qty = (float) $row['received_qty'];
                $itemId = (int) $row['item_id'];
                $multiplier = $this->conversionMultiplier($itemId, (int) $row['received_uom_id'], (int) Item::whereKey($row['item_id'])->value('base_uom_id')) ?? 1;

                $physicalByItem[$itemId] = ($physicalByItem[$itemId] ?? 0) + $qty * $multiplier;

                GoodsReceiptItem::create([
                    'goods_receipt_id' => $gr->id,
                    'item_id' => $row['item_id'],
                    'batch_no' => $row['batch_no'] ?? null,
                    'received_uom_id' => $row['received_uom_id'],
                    'received_qty' => $qty,
                    'conversion_multiplier' => $multiplier,
                    'total_pcs' => $qty * $multiplier,
                    'nav_qty' => $lineNav[$i],
                    'qty_placed' => 0,
                    'target_bin_id' => $row['target_bin_id'] ?? null,
                    'original_barcode' => sprintf('HF-RCV-%s-%04d-%02d', $stamp, $sequence, $i + 1),
                    'notes' => $row['notes'] ?? null,
                ]);
            }

            $diffNote = $this->navDiffNotes($navByItem, $physicalByItem);

            if ($diffNote !== '') {
                $gr->update(['notes' => trim(($gr->notes ?? '')."\n".$diffNote)]);
            }
        });

        return redirect()->route('inbound.show', $gr)->with('toast', "Penerimaan {$docNumber} dibuat.");
    }

    public function show(Request $request, GoodsReceipt $inbound): View
    {
        $inbound->load(['supplier', 'branch', 'warehouse', 'location', 'items.item', 'items.receivedUom', 'items.targetBin']);

        $serials = $inbound->serials()->with('location')->get();

        // Perbandingan Qty NAV (unit terkecil) vs qty fisik input, dikelompokkan per SKU.
        $navSummary = $inbound->items->groupBy('item_id')->map(function ($lines) {
            $nav = $lines->pluck('nav_qty')->filter()->max();
            $physical = (float) $lines->sum('total_pcs');

            return [
                'sku' => $lines->first()?->item?->sku,
                'name' => $lines->first()?->item?->name,
                'nav' => $nav === null ? null : (float) $nav,
                'physical' => $physical,
                'diff' => $nav === null ? null : $physical - (float) $nav,
            ];
        })->values()->filter(fn (array $row) => $row['nav'] !== null)->values();

        return view('inbound.show', [
            'gr' => $inbound,
            'serials' => $serials,
            'navSummary' => $navSummary,
            'bins' => $this->locationsOf($inbound->warehouse_id),
            'canReceive' => $inbound->status === 'draft',
            'canPutaway' => in_array($inbound->status, ['received', 'putaway'], true),
        ]);
    }

    public function receive(Request $request, GoodsReceipt $inbound): RedirectResponse
    {
        $this->guardBranch((int) $inbound->branch_id);

        if ($inbound->status !== 'draft') {
            return back()->with('toast', 'Penerimaan sudah diproses sebelumnya.');
        }

        $stagingBin = (int) ($inbound->destination_location_id
            ?: $this->locationsOf($inbound->warehouse_id)->first()?->id);

        abort_if($stagingBin === 0, 422, 'Gudang belum memiliki BIN CODE.');

        DB::transaction(function () use ($inbound, $stagingBin, $request) {
            foreach ($inbound->items as $line) {
                $batch = $this->resolveBatch($line);

                StockService::add(
                    (int) $inbound->branch_id,
                    (int) $inbound->warehouse_id,
                    $stagingBin,
                    (int) $line->item_id,
                    $batch?->id,
                    (int) $line->received_uom_id,
                    (float) $line->total_pcs,
                    [
                        'original_barcode' => $line->original_barcode,
                        'received_at' => $inbound->received_at ?? now(),
                        'type' => 'IN',
                        'reference_no' => $inbound->doc_number,
                        'user_id' => auth()->id(),
                        'notes' => 'Penerimaan '.$inbound->doc_number,
                    ]
                );
            }

            $inbound->update(['status' => 'received', 'received_at' => $inbound->received_at ?? now()]);

            // 1 record serial = 1 unit UOM fisik, sequence per penerimaan + SKU + UOM.
            ItemSerial::generateForReceipt($inbound);

            $request->session()->flash('toast', 'Barang diterima, siap dilakukan putaway ke BIN.');
        });

        return back();
    }

    /**
     * Putaway: pindahkan stok dari BIN staging ke BIN tujuan (boleh sebagian).
     */
    public function putaway(Request $request, GoodsReceipt $inbound): RedirectResponse
    {
        $this->guardBranch((int) $inbound->branch_id);

        $data = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.goods_receipt_item_id' => ['required', 'exists:goods_receipt_items,id'],
            'lines.*.location_id' => ['required', 'exists:locations,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.001'],
        ]);

        [$updated, $message] = static::applyPutaway($inbound, collect($data['lines']), auth()->id());

        return back()->with('toast', $updated ? "Putaway selesai: {$message}" : $message);
    }

    public function printLabels(GoodsReceipt $inbound): View
    {
        $inbound->load(['items.item', 'items.receivedUom', 'supplier', 'branch']);

        $serials = $inbound->serials()->get();
        $itemNames = Item::whereIn('sku', $serials->pluck('sku')->unique())->pluck('name', 'sku');

        return view('inbound.print-labels', ['gr' => $inbound, 'serials' => $serials, 'itemNames' => $itemNames]);
    }

    public function barcodeLookup(Request $request)
    {
        $code = trim($request->input('barcode', ''));

        $serial = ItemSerial::find($code);

        if ($serial) {
            return response()->json([
                'ok' => true,
                'message' => 'Unit ditemukan: '.$serial->sku,
                'payload' => $serial->toLookupPayload(),
            ]);
        }

        $line = GoodsReceiptItem::where('original_barcode', $code)
            ->with(['item', 'goodsReceipt'])
            ->first();

        if (! $line) {
            return response()->json(['ok' => false, 'message' => 'Barcode penerimaan tidak ditemukan.'], 404);
        }

        $remaining = max(0, (float) $line->total_pcs - (float) $line->qty_placed);

        return response()->json([
            'ok' => true,
            'goods_receipt_item_id' => $line->id,
            'doc_number' => $line->goodsReceipt->doc_number,
            'status' => $line->goodsReceipt->status,
            'item' => $line->item->name,
            'sku' => $line->item->sku,
            'barcode' => $line->original_barcode,
            'total_pcs' => (float) $line->total_pcs,
            'qty_placed' => (float) $line->qty_placed,
            'remaining' => $remaining,
            'bins' => $this->locationsOf($line->goodsReceipt->warehouse_id)
                ->map(fn (Location $l) => ['id' => $l->id, 'code' => $l->code]),
        ]);
    }

    /* ------------------------------------------------------------------
     |  Logika putaway bersama (dipakai juga oleh MobileWarehouseController)
     |  @return array{0: int, 1: string} [jumlah baris diproses, pesan]
     ------------------------------------------------------------------ */

    public static function applyPutaway(GoodsReceipt $gr, $lines, ?int $userId): array
    {
        if (! in_array($gr->status, ['received', 'putaway'], true)) {
            return [0, 'Penerimaan belum berstatus diterima.'];
        }

        $stagingBin = (int) $gr->destination_location_id;
        $processed = 0;
        $failed = [];

        foreach ($lines as $line) {
            $grItem = GoodsReceiptItem::with('item')->find($line['goods_receipt_item_id'] ?? null);

            if (! $grItem || $grItem->goods_receipt_id !== $gr->id) {
                continue;
            }

            $qty = min((float) ($line['qty'] ?? 0), max(0, (float) $grItem->total_pcs - (float) $grItem->qty_placed));

            if ($qty <= 0) {
                $failed[] = $grItem->item?->sku.' sudah lengkap';

                continue;
            }

            $stock = Stock::where('location_id', $stagingBin)
                ->where('item_id', $grItem->item_id)
                ->where('quantity_pcs', '>', 0)
                ->when($grItem->batch_id, fn ($q) => $q->where('batch_id', $grItem->batch_id))
                ->when($grItem->original_barcode, fn ($q) => $q->where('original_barcode', $grItem->original_barcode))
                ->orderBy('id')
                ->first();

            if (! $stock) {
                $stock = Stock::where('location_id', $stagingBin)
                    ->where('item_id', $grItem->item_id)
                    ->where('quantity_pcs', '>', 0)
                    ->orderBy('id')
                    ->first();
            }

            if (! $stock) {
                $failed[] = $grItem->item?->sku.' tidak ada di BIN staging';

                continue;
            }

            $qty = min($qty, (float) $stock->quantity_pcs);

            StockService::moveLocation($stock, (int) $line['location_id'], $qty);

            $grItem->qty_placed = (float) $grItem->qty_placed + $qty;
            $grItem->target_bin_id = (int) $line['location_id'];
            $grItem->save();

            $processed++;
        }

        $pending = $gr->items()->get()->filter(
            fn ($i) => (float) $i->qty_placed + 0.001 < (float) $i->total_pcs
        )->count();

        if ($processed > 0) {
            $gr->update(['status' => $pending === 0 ? 'completed' : 'putaway']);
        }

        $message = $processed > 0
            ? ($pending === 0 ? 'Seluruh barang selesai ditempatkan.' : "{$processed} baris dipindahkan, {$pending} baris belum selesai.")
            : ($failed ? implode('; ', $failed) : 'Tidak ada baris yang diproses.');

        return [$processed, $message];
    }

    /* ------------------------------------------------------------------ */

    /**
     * Catatan selisih Qty NAV (unit terkecil) vs qty fisik yang diinput, per SKU.
     *
     * @param  array<int, float>  $navByItem
     * @param  array<int, float>  $physicalByItem
     */
    private function navDiffNotes(array $navByItem, array $physicalByItem): string
    {
        if (! $navByItem) {
            return '';
        }

        $skus = Item::whereIn('id', array_keys($navByItem))->pluck('sku', 'id');
        $fmt = fn (float $value) => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        $diffs = [];

        foreach ($navByItem as $itemId => $nav) {
            $physical = $physicalByItem[$itemId] ?? 0;

            if (abs($physical - $nav) <= 0.001) {
                continue;
            }

            $diffs[] = sprintf(
                '%s NAV %s PCS vs fisik %s PCS (%s)',
                $skus[$itemId] ?? $itemId,
                $fmt($nav),
                $fmt((float) $physical),
                ($physical > $nav ? '+' : '-').$fmt(abs($physical - $nav))
            );
        }

        return $diffs ? 'Selisih NAV: '.implode('; ', $diffs) : '';
    }

    private function formData(Request $request): array
    {
        $branchId = $this->activeBranchId();
        $warehouses = Warehouse::with('branch')->when(! $this->canSeeAllBranches(), fn ($q) => $q->where('branch_id', $branchId))->orderBy('code')->get();
        $warehouseId = $request->integer('warehouse_id') ?: $warehouses->first()?->id;
        $items = Item::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'base_uom_id']);

        $conversions = ItemUomConversion::whereIn('item_id', $items->pluck('id'))->get()
            ->groupBy('item_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($c) => [(int) $c->from_uom_id => (float) $c->multiplier]));

        $itemMeta = $items->mapWithKeys(fn ($i) => [$i->id => [
            'base_uom_id' => (int) $i->base_uom_id,
            'conversions' => $conversions[$i->id] ?? collect(),
        ]]);

        return [
            'warehouses' => $warehouses,
            'bins' => $this->locationsOf($warehouseId),
            'suppliers' => Supplier::orderBy('name')->get(),
            'items' => $items,
            'itemMeta' => $itemMeta,
            'uoms' => Uom::orderBy('code')->get(),
            'defaultWarehouseId' => $warehouseId,
            'defaultBinId' => $request->integer('location_id'),
        ];
    }

    private function resolveBatch(GoodsReceiptItem $line): ?Batch
    {
        $number = $line->batch_no ?: 'B-'.now()->format('Ymd');

        return Batch::firstOrCreate(
            ['item_id' => $line->item_id, 'batch_number' => $number],
            ['received_at' => now(), 'cost_price' => Item::whereKey($line->item_id)->value('cost_price') ?? 0]
        );
    }
}
