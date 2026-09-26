<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Item;
use App\Models\Location;
use App\Models\Stock;
use App\Models\StockRepack;
use App\Models\StockRepackItem;
use App\Models\Uom;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RepackController extends Controller
{
    public function index(Request $request): View
    {
        $query = StockRepack::with(['branch', 'warehouse', 'items.item', 'user'])
            ->when($request->filled('q'), fn ($q) => $q->where('repack_no', 'like', '%'.$request->string('q').'%'));

        $repacks = $this->scopeBranch($query)->latest('id')->paginate(12)->withQueryString();

        return view('repack.index', compact('repacks'));
    }

    public function create(Request $request): View
    {
        $branchId = $this->activeBranchId();

        return view('repack.form', [
            'branchId' => $branchId,
            'warehouses' => Warehouse::when(! $this->canSeeAllBranches(), fn ($q) => $q->where('branch_id', $branchId))->orderBy('code')->get(),
            'items' => Item::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'base_uom_id']),
            'uoms' => Uom::orderBy('code')->get(),
            'bins' => $this->locationsOf($this->defaultWarehouse((int) $branchId)?->id),
            'stocks' => $this->availableStock($branchId),
        ]);
    }

    public function bins(Request $request)
    {
        return response()->json(
            $this->locationsOf($request->integer('warehouse_id'))
                ->map(fn (Location $l) => ['id' => $l->id, 'code' => $l->code])
        );
    }

    public function stocks(Request $request)
    {
        return response()->json($this->availableStock(
            $request->integer('branch_id') ?: (int) $this->activeBranchId(),
            $request->integer('item_id') ?: null
        ));
    }

    /**
     * Pecah satuan: potong stok asal -> terbitkan stok + barcode baru
     * dengan tautan riwayat ke batch asal.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'source_item_id' => ['required', 'exists:items,id'],
            'source_uom_id' => ['required', 'exists:uoms,id'],
            'source_qty' => ['required', 'numeric', 'min:0.001'],
            'source_location_id' => ['required', 'exists:locations,id'],
            'target_item_id' => ['nullable', 'exists:items,id'],
            'target_uom_id' => ['required', 'exists:uoms,id'],
            'target_qty' => ['required', 'numeric', 'min:0.001'],
            'target_location_id' => ['nullable', 'exists:locations,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->guardBranch((int) $data['branch_id']);

        $sourcePcs = $this->toPcs((int) $data['source_item_id'], (float) $data['source_qty'], (int) $data['source_uom_id']);
        $targetItemId = (int) (($data['target_item_id'] ?? null) ?: $data['source_item_id']);
        $targetQty = (float) $data['target_qty'];
        $targetUomId = (int) $data['target_uom_id'];
        $targetPcs = $this->toPcs($targetItemId, $targetQty, $targetUomId);

        $available = (float) Stock::where('branch_id', $data['branch_id'])
            ->where('item_id', $data['source_item_id'])
            ->where('location_id', $data['source_location_id'])
            ->where('quantity_pcs', '>', 0)
            ->sum('quantity_pcs');

        if ($available + 0.0001 < $sourcePcs) {
            return back()->withInput()->withErrors([
                'source_qty' => 'Stok asal tidak mencukupi (tersedia '.number_format($available, 0).' PCS).',
            ]);
        }

        $repack = DB::transaction(function () use ($data, $sourcePcs, $targetItemId, $targetQty, $targetUomId, $targetPcs) {
            $sourceStock = Stock::where('branch_id', $data['branch_id'])
                ->where('item_id', $data['source_item_id'])
                ->where('location_id', $data['source_location_id'])
                ->where('quantity_pcs', '>', 0)
                ->orderByRaw('CASE WHEN received_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('received_at')
                ->orderBy('id')
                ->first();

            $repack = StockRepack::create([
                'repack_no' => $this->nextDocNumber('RPK-'.now()->format('Ym').'-', 'stock_repacks', 'repack_no'),
                'date' => now()->toDateString(),
                'branch_id' => $data['branch_id'],
                'warehouse_id' => $data['warehouse_id'],
                'user_id' => auth()->id(),
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
            ]);

            $newBarcode = sprintf('HF-RPK-%s-%04d', now()->format('Ymd'), $repack->id);

            StockRepackItem::create([
                'stock_repack_id' => $repack->id,
                'item_id' => $data['source_item_id'],
                'source_barcode' => $sourceStock?->original_barcode,
                'batch_no' => $sourceStock?->batch?->batch_number,
                'batch_id' => $sourceStock?->batch_id,
                'source_uom_id' => $data['source_uom_id'],
                'source_qty' => (float) $data['source_qty'],
                'source_qty_pcs' => $sourcePcs,
                'target_item_id' => $targetItemId,
                'target_barcode_new' => $newBarcode,
                'target_uom_id' => $targetUomId,
                'target_qty' => $targetQty,
                'target_location_id' => ($data['target_location_id'] ?? null) ?: $data['source_location_id'],
            ]);

            // Potong stok asal
            StockService::remove((int) $data['branch_id'], (int) $data['source_item_id'], $sourcePcs, [
                'location_id' => (int) $data['source_location_id'],
                'type' => 'REPACK_OUT',
                'reference_no' => $repack->repack_no,
                'user_id' => auth()->id(),
                'notes' => 'Pecah satuan dari '.$repack->repack_no,
            ]);

            // Terbitkan stok hasil repack (barcode baru, riwayat batch asal)
            $targetBatchId = $targetItemId === (int) $data['source_item_id']
                ? ($sourceStock?->batch_id ?: Batch::where('item_id', $targetItemId)->latest('id')->value('id'))
                : Batch::firstOrCreate(
                    ['item_id' => $targetItemId, 'batch_number' => $newBarcode],
                    ['received_at' => now(), 'cost_price' => Item::whereKey($targetItemId)->value('cost_price') ?? 0]
                )->id;

            StockService::add(
                (int) $data['branch_id'],
                (int) $data['warehouse_id'],
                (int) (($data['target_location_id'] ?? null) ?: $data['source_location_id']),
                $targetItemId,
                $targetBatchId,
                $targetUomId,
                $targetPcs,
                [
                    'original_barcode' => $newBarcode,
                    'type' => 'REPACK',
                    'reference_no' => $repack->repack_no,
                    'user_id' => auth()->id(),
                    'notes' => 'Hasil repack '.$repack->repack_no.' (batch asal: '.($sourceStock?->batch?->batch_number ?? '-').')',
                ]
            );

            return $repack;
        });

        return redirect()->route('repack.show', $repack)->with('toast', "Repack {$repack->repack_no} selesai, barcode baru dibuat.");
    }

    public function show(StockRepack $repack): View
    {
        $repack->load(['branch', 'warehouse', 'user', 'items.item', 'items.batch', 'items.sourceUom', 'items.targetUom', 'items.targetLocation']);

        return view('repack.show', compact('repack'));
    }

    private function availableStock(int $branchId, ?int $itemId = null)
    {
        return Stock::with(['item', 'batch', 'location', 'warehouse'])
            ->where('branch_id', $branchId)
            ->where('quantity_pcs', '>', 0)
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId))
            ->orderBy('received_at')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'item_id' => $s->item_id,
                'sku' => $s->item?->sku,
                'item' => $s->item?->name,
                'batch' => $s->batch?->batch_number,
                'location_id' => (int) $s->location_id,
                'location' => $s->location?->code,
                'warehouse_id' => $s->warehouse_id,
                'received_at' => optional($s->received_at)->format('Y-m-d'),
                'pcs' => (float) $s->quantity_pcs,
                'barcode' => $s->original_barcode,
            ]);
    }
}
