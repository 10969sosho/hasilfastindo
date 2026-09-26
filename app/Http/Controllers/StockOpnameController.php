<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Location;
use App\Models\Stock;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockOpnameController extends Controller
{
    public function index(Request $request): View
    {
        $query = StockOpname::with(['branch', 'warehouse', 'location', 'creator', 'approver'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));

        $sessions = $this->scopeBranch($query)->latest('id')->paginate(12)->withQueryString();

        $summary = [
            'open' => $this->scopeBranch(StockOpname::query())->whereIn('status', ['open', 'in_progress'])->count(),
            'completed' => $this->scopeBranch(StockOpname::query())->where('status', 'completed')->count(),
        ];

        return view('opname.index', compact('sessions', 'summary'));
    }

    public function create(Request $request): View
    {
        $branchId = $request->integer('branch_id') ?: $this->activeBranchId();

        return view('opname.form', [
            'branchId' => $branchId,
            'branches' => $this->branchesForUser(),
            'warehouses' => Warehouse::when(! $this->canSeeAllBranches(), fn ($q) => $q->where('branch_id', $branchId))->orderBy('code')->get(),
            'bins' => $this->locationsOf($this->defaultWarehouse((int) $branchId)?->id),
        ]);
    }

    public function bins(Request $request)
    {
        return response()->json(
            $this->locationsOf($request->integer('warehouse_id'))
                ->map(fn (Location $l) => ['id' => $l->id, 'code' => $l->code])
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'opname_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->guardBranch((int) $data['branch_id']);

        $session = StockOpname::create([
            'opname_no' => $this->nextDocNumber('SOP-'.now()->format('Ym').'-', 'stock_opnames', 'opname_no'),
            'branch_id' => $data['branch_id'],
            'warehouse_id' => $data['warehouse_id'],
            'location_id' => $data['location_id'],
            'title' => $data['title'] ?? null,
            'opname_date' => $data['opname_date'],
            'status' => 'open',
            'created_by' => auth()->id(),
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('opname.show', $session)->with('toast', "Sesi opname {$session->opname_no} dibuat.");
    }

    public function show(Request $request, StockOpname $opname): View
    {
        $this->guardBranch((int) $opname->branch_id);

        $opname->load(['branch', 'warehouse', 'location', 'creator', 'approver', 'items.item', 'items.batch', 'items.uom']);

        $systemRows = Stock::with(['item', 'batch', 'uom'])
            ->where('location_id', $opname->location_id)
            ->where('quantity_pcs', '>', 0)
            ->get()
            ->keyBy(fn ($s) => $s->item_id.'|'.($s->batch_id ?? 0));

        return view('opname.show', [
            'opname' => $opname,
            'systemRows' => $systemRows,
            'canEdit' => in_array($opname->status, ['open', 'in_progress'], true),
            'isEditable' => $opname->status === 'open',
        ]);
    }

    /**
     * Lookup barcode saat opname: cocokkan stok sistem di BIN sesi ini.
     */
    public function lookup(Request $request)
    {
        $data = $request->validate([
            'stock_opname_id' => ['required', 'exists:stock_opnames,id'],
            'barcode' => ['required', 'string', 'max:80'],
        ]);

        $session = StockOpname::findOrFail($data['stock_opname_id']);
        $code = $data['barcode'];

        $stock = Stock::with(['item', 'batch', 'uom'])
            ->where('location_id', $session->location_id)
            ->where('quantity_pcs', '>', 0)
            ->where(function ($q) use ($code) {
                $q->where('original_barcode', $code)
                    ->orWhereHas('item', fn ($i) => $i->where('sku', $code)->orWhere('barcode', $code))
                    ->orWhereHas('batch', fn ($b) => $b->where('batch_number', $code));
            })
            ->first();

        if (! $stock) {
            return response()->json(['ok' => false, 'message' => 'Barcode tidak ditemukan di BIN ini.'], 404);
        }

        $existing = StockOpnameItem::where('stock_opname_id', $session->id)
            ->where('item_id', $stock->item_id)
            ->where(fn ($q) => $q->where('batch_id', $stock->batch_id)->orWhereNull('batch_id'))
            ->first();

        return response()->json([
            'ok' => true,
            'item_id' => $stock->item_id,
            'batch_id' => $stock->batch_id,
            'uom_id' => $stock->uom_id,
            'sku' => $stock->item?->sku,
            'item' => $stock->item?->name,
            'batch' => $stock->batch?->batch_number,
            'barcode' => $stock->original_barcode,
            'system_qty' => (float) $stock->quantity_pcs,
            'physical_qty' => $existing ? (float) $existing->physical_qty : null,
        ]);
    }

    /**
     * Simpan hasil scan/input fisik (satu baris per item+batch).
     */
    public function scan(Request $request, StockOpname $opname): RedirectResponse
    {
        $this->guardBranch((int) $opname->branch_id);

        if (! in_array($opname->status, ['open', 'in_progress'], true)) {
            return back()->with('toast', 'Sesi opname sudah ditutup.');
        }

        $data = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'barcode' => ['nullable', 'string', 'max:80'],
            'physical_qty' => ['required', 'numeric', 'min:0'],
        ]);

        $systemQty = (float) Stock::where('location_id', $opname->location_id)
            ->where('item_id', $data['item_id'])
            ->when($data['batch_id'] ?? null, fn ($q) => $q->where('batch_id', $data['batch_id']))
            ->sum('quantity_pcs');

        $diff = (float) $data['physical_qty'] - $systemQty;

        DB::transaction(function () use ($opname, $data, $systemQty, $diff) {
            StockOpnameItem::updateOrCreate(
                [
                    'stock_opname_id' => $opname->id,
                    'item_id' => $data['item_id'],
                    'batch_id' => $data['batch_id'] ?? null,
                ],
                [
                    'uom_id' => Item::whereKey($data['item_id'])->value('base_uom_id'),
                    'scanned_barcode' => $data['barcode'] ?? null,
                    'system_qty' => $systemQty,
                    'physical_qty' => (float) $data['physical_qty'],
                    'difference_qty' => $diff,
                    'status' => abs($diff) < 0.0001 ? 'matched' : 'discrepancy',
                ]
            );

            $opname->update(['status' => 'in_progress']);
        });

        return back()->with('toast', abs($diff) < 0.0001
            ? 'Cocok dengan sistem.'
            : 'Selisih tercatat: '.number_format($diff, 0).' PCS.');
    }

    /**
     * Approval rekonsiliasi: sesuaikan stok dengan hitungan fisik.
     */
    public function approve(Request $request, StockOpname $opname): RedirectResponse
    {
        $this->guardBranch((int) $opname->branch_id);

        if (in_array($opname->status, ['completed', 'cancelled'], true)) {
            return back()->with('toast', 'Sesi opname sudah selesai.');
        }

        DB::transaction(function () use ($opname) {
            foreach ($opname->items as $row) {
                $diff = (float) $row->difference_qty;

                if (abs($diff) < 0.0001) {
                    $row->update(['status' => 'approved']);

                    continue;
                }

                $opts = [
                    'type' => 'ADJUSTMENT',
                    'reference_no' => $opname->opname_no,
                    'user_id' => auth()->id(),
                    'notes' => 'Rekonsiliasi opname '.$opname->opname_no,
                ];

                if ($diff > 0) {
                    StockService::add(
                        (int) $opname->branch_id,
                        (int) $opname->warehouse_id,
                        (int) $opname->location_id,
                        (int) $row->item_id,
                        $row->batch_id,
                        (int) ($row->uom_id ?: Item::whereKey($row->item_id)->value('base_uom_id')),
                        $diff,
                        $opts
                    );
                } else {
                    StockService::remove(
                        (int) $opname->branch_id,
                        (int) $row->item_id,
                        abs($diff),
                        $opts + ['location_id' => (int) $opname->location_id]
                    );
                }

                $row->update(['status' => 'approved']);
            }

            $opname->update([
                'status' => 'completed',
                'approved_by' => auth()->id(),
            ]);
        });

        return redirect()->route('opname.show', $opname)->with('toast', 'Opname disetujui, stok disesuaikan.');
    }

    public function destroy(StockOpname $opname): RedirectResponse
    {
        $this->guardBranch((int) $opname->branch_id);

        if ($opname->status === 'completed') {
            return back()->with('toast', 'Sesi opname yang sudah selesai tidak bisa dihapus.');
        }

        $opname->update(['status' => 'cancelled']);

        return redirect()->route('opname.index')->with('toast', 'Sesi opname dibatalkan.');
    }
}
