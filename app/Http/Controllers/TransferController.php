<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Item;
use App\Models\Location;
use App\Models\Stock;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Uom;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransferController extends Controller
{
    public function index(Request $request): View
    {
        $query = StockTransfer::with(['fromBranch', 'toBranch', 'items.item'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($q) => $q->where('transfer_no', 'like', '%'.$request->string('q').'%'));

        if (! $this->canSeeAllBranches()) {
            $branchId = $this->activeBranchId();
            $query->where(fn ($q) => $q->where('from_branch_id', $branchId)->orWhere('to_branch_id', $branchId));
        }

        $transfers = $query->latest('id')->paginate(12)->withQueryString();

        $incoming = (clone $query)->where('status', 'in_transit')->get();

        return view('transfer.index', compact('transfers', 'incoming'));
    }

    public function create(Request $request): View
    {
        $fromBranchId = $request->integer('from_branch_id') ?: $this->activeBranchId();

        return view('transfer.form', [
            'branches' => $this->branchesForUser(),
            'toBranches' => $this->allBranches(),
            'fromBranchId' => $fromBranchId,
            'fromWarehouses' => Warehouse::where('branch_id', $fromBranchId)->orderBy('code')->get(),
            'items' => Item::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'base_uom_id']),
            'uoms' => Uom::orderBy('code')->get(),
            'bins' => $this->locationsOf($this->defaultWarehouse((int) $fromBranchId)?->id),
        ]);
    }

    public function warehouses(Request $request)
    {
        return response()->json(
            Warehouse::where('branch_id', $request->integer('branch_id'))
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'is_default'])
        );
    }

    public function bins(Request $request)
    {
        return response()->json(
            $this->locationsOf($request->integer('warehouse_id'))
                ->map(fn (Location $l) => ['id' => $l->id, 'code' => $l->code])
        );
    }

    /**
     * Stok per item per BIN di sebuah gudang, dipakai form untuk hanya
     * menawarkan BIN yang benar-benar berisi stok item terkait.
     */
    public function stockBins(Request $request)
    {
        return response()->json(
            Stock::query()
                ->join('locations', 'locations.id', '=', 'stocks.location_id')
                ->where('stocks.branch_id', $request->integer('branch_id'))
                ->where('locations.warehouse_id', $request->integer('warehouse_id'))
                ->where('stocks.quantity_pcs', '>', 0)
                ->groupBy('stocks.item_id', 'stocks.location_id', 'locations.code')
                ->orderBy('locations.code')
                ->select(
                    'stocks.item_id',
                    'stocks.location_id',
                    'locations.code as code',
                    DB::raw('SUM(stocks.quantity_pcs) as qty')
                )
                ->get()
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_branch_id' => ['required', 'exists:branches,id'],
            'to_branch_id' => ['required', 'exists:branches,id', 'different:from_branch_id'],
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.from_location_id' => ['required', 'exists:locations,id'],
            'items.*.to_location_id' => ['nullable', 'exists:locations,id'],
            'items.*.uom_id' => ['required', 'exists:uoms,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
        ]);

        $this->guardBranch((int) $data['from_branch_id']);

        if ($shortages = $this->arrayShortages((int) $data['from_branch_id'], $data['items'])) {
            return back()->withInput()->withErrors($shortages);
        }

        $transfer = DB::transaction(function () use ($data) {
            $transfer = StockTransfer::create([
                'transfer_no' => $this->nextDocNumber('TRF-'.now()->format('Ym').'-', 'stock_transfers', 'transfer_no'),
                'from_branch_id' => $data['from_branch_id'],
                'to_branch_id' => $data['to_branch_id'],
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'status' => 'draft',
                'user_id' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $row) {
                $pcs = $this->toPcs((int) $row['item_id'], (float) $row['qty'], (int) $row['uom_id']);
                $preview = Stock::fifoPick((int) $data['from_branch_id'], (int) $row['item_id'], $pcs, (int) $row['from_location_id']);

                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'item_id' => $row['item_id'],
                    'batch_id' => $preview[0]['stock']->batch_id ?? null,
                    'from_location_id' => $row['from_location_id'],
                    'to_location_id' => $row['to_location_id'] ?? null,
                    'qty' => (float) $row['qty'],
                    'received_qty' => 0,
                    'uom_id' => $row['uom_id'],
                    'notes' => null,
                ]);
            }

            return $transfer;
        });

        return redirect()->route('transfer.show', $transfer)->with('toast', "Transfer {$transfer->transfer_no} dibuat (draft).");
    }

    public function show(StockTransfer $transfer): View
    {
        $transfer->load([
            'fromBranch', 'toBranch', 'fromWarehouse', 'toWarehouse',
            'items.item', 'items.uom', 'items.fromLocation', 'items.toLocation', 'items.batch',
        ]);

        $toBins = $this->locationsOf($transfer->to_warehouse_id);

        return view('transfer.show', [
            'transfer' => $transfer,
            'toBins' => $toBins,
            'canShip' => $transfer->status === 'draft' && $this->allowedFor((int) $transfer->from_branch_id),
            'canReceive' => $transfer->status === 'in_transit' && $this->allowedFor((int) $transfer->to_branch_id),
        ]);
    }

    /**
     * Kirim: potong stok cabang asal, status jadi in_transit.
     */
    public function ship(StockTransfer $transfer): RedirectResponse
    {
        $this->guardBranch((int) $transfer->from_branch_id);

        if ($transfer->status !== 'draft') {
            return back()->with('toast', 'Transfer tidak dalam status draft.');
        }

        $transfer->loadMissing('items');

        if ($shortages = $this->arrayShortages((int) $transfer->from_branch_id, $transfer->items->map(fn (StockTransferItem $line) => [
            'item_id' => $line->item_id,
            'from_location_id' => $line->from_location_id,
            'qty' => $line->qty,
            'uom_id' => $line->uom_id,
        ])->all())) {
            return back()->withErrors($shortages);
        }

        DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $line) {
                $pcs = $this->toPcs((int) $line->item_id, (float) $line->qty, (int) $line->uom_id);

                $taken = StockService::remove((int) $transfer->from_branch_id, (int) $line->item_id, $pcs, [
                    'location_id' => (int) $line->from_location_id,
                    'type' => 'TRANSFER_OUT',
                    'reference_no' => $transfer->transfer_no,
                    'user_id' => auth()->id(),
                    'notes' => 'Transfer keluar '.$transfer->transfer_no,
                ]);

                if ($taken + 0.0001 < $pcs) {
                    // Stok berubah di tengah proses: lempar agar transaksi dibatalkan
                    // (stok tidak boleh terpotong sebagian) lalu kembali dengan pesan.
                    throw ValidationException::withMessages([
                        'ship' => 'Stok berubah saat proses kirim, mohon cek ulang stok '
                            .($line->item?->sku ?? 'item transfer').'.',
                    ]);
                }
            }

            $transfer->update(['status' => 'in_transit', 'shipped_at' => now()]);
        });

        return back()->with('toast', "Transfer {$transfer->transfer_no} dikirim (in transit).");
    }

    /**
     * Penerimaan di cabang tujuan + pencatatan selisih/kerusakan.
     */
    public function receive(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $this->guardBranch((int) $transfer->to_branch_id);

        if ($transfer->status !== 'in_transit') {
            return back()->with('toast', 'Transfer belum dikirim.');
        }

        $data = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.stock_transfer_item_id' => ['required', 'exists:stock_transfer_items,id'],
            'lines.*.received_qty' => ['required', 'numeric', 'min:0'],
            'lines.*.to_location_id' => ['required', 'exists:locations,id'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $hasDiscrepancy = false;

        DB::transaction(function () use ($transfer, $data, &$hasDiscrepancy) {
            foreach ($data['lines'] as $row) {
                $line = StockTransferItem::find($row['stock_transfer_item_id']);

                if (! $line || $line->stock_transfer_id !== $transfer->id) {
                    continue;
                }

                $received = max(0, (float) $row['received_qty']);
                $line->received_qty = $received;
                $line->to_location_id = (int) $row['to_location_id'];
                $line->notes = $row['notes'] ?? $line->notes;
                $line->save();

                if (abs($received - (float) $line->qty) > 0.0001) {
                    $hasDiscrepancy = true;
                }

                if ($received > 0) {
                    $pcs = $this->toPcs((int) $line->item_id, $received, (int) $line->uom_id);

                    StockService::add(
                        (int) $transfer->to_branch_id,
                        (int) ($transfer->to_warehouse_id ?: $this->defaultWarehouse((int) $transfer->to_branch_id)?->id),
                        (int) $row['to_location_id'],
                        (int) $line->item_id,
                        $line->batch_id ?: Batch::where('item_id', $line->item_id)->latest('id')->value('id'),
                        (int) $line->uom_id,
                        $pcs,
                        [
                            'type' => 'TRANSFER_IN',
                            'reference_no' => $transfer->transfer_no,
                            'user_id' => auth()->id(),
                            'notes' => 'Transfer masuk '.$transfer->transfer_no,
                        ]
                    );
                }
            }

            $transfer->update([
                'status' => $hasDiscrepancy ? 'discrepancy' : 'received',
                'received_at' => now(),
            ]);
        });

        return back()->with('toast', $hasDiscrepancy
            ? 'Transfer diterima dengan SELISIH, menunggu review pusat.'
            : 'Transfer diterima, stok masuk sesuai.');
    }

    public function destroy(StockTransfer $transfer): RedirectResponse
    {
        $this->guardBranch((int) $transfer->from_branch_id);

        if ($transfer->status !== 'draft') {
            return back()->with('toast', 'Hanya transfer draft yang bisa dibatalkan.');
        }

        $transfer->update(['status' => 'cancelled']);

        return redirect()->route('transfer.index')->with('toast', 'Transfer dibatalkan.');
    }

    private function allowedFor(int $branchId): bool
    {
        return $this->canSeeAllBranches() || $branchId === (int) $this->activeBranchId();
    }

    /**
     * Baris yang stoknya kurang di BIN asal, dipetakan sebagai error validasi
     * sehingga pesannya tampil di kotak merah layout, bukan halaman error 422.
     *
     * @param  array<int, array<string, mixed>>  $lines  butuh item_id, from_location_id, qty, uom_id
     * @return array<string, string>
     */
    private function arrayShortages(int $fromBranchId, array $lines): array
    {
        $skus = Item::whereIn('id', array_column($lines, 'item_id'))->pluck('sku', 'id');
        $codes = Location::whereIn('id', array_column($lines, 'from_location_id'))->pluck('code', 'id');

        $shortages = [];

        foreach ($lines as $index => $row) {
            $pcs = $this->toPcs((int) $row['item_id'], (float) $row['qty'], (int) $row['uom_id']);
            $available = $this->availablePcs($fromBranchId, (int) $row['item_id'], (int) $row['from_location_id']);

            if ($available + 0.0001 < $pcs) {
                $shortages["items.$index.from_location_id"] = sprintf(
                    '%s di %s butuh %s PCS, stok hanya %s PCS.',
                    $skus[$row['item_id']] ?? 'Item',
                    $codes[$row['from_location_id']] ?? 'BIN',
                    $pcs,
                    $available
                );
            }
        }

        return $shortages;
    }

    /**
     * Ketersediaan stok satu item di satu BIN (PCS).
     *
     * Kondisinya sengaja disamakan dengan kandidat yang dipilih
     * StockService::remove(), supaya validasi di form tidak pernah
     * lolos padahal proses kirim pasti gagal.
     */
    private function availablePcs(int $branchId, int $itemId, int $locationId): float
    {
        return (float) Stock::query()
            ->where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->where('location_id', $locationId)
            ->where('quantity_pcs', '>', 0)
            ->sum('quantity_pcs');
    }
}
