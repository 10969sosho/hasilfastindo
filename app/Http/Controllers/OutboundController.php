<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Stock;
use App\Models\Uom;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OutboundController extends Controller
{
    public function index(Request $request): View
    {
        $query = SalesOrder::with(['customer', 'branch', 'items.item'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('so_number', 'like', '%'.$request->string('q').'%')
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$request->string('q').'%'))));

        $orders = $this->scopeBranch($query)->latest('order_date')->paginate(12)->withQueryString();

        return view('outbound.index', compact('orders'));
    }

    public function create(): View
    {
        return view('outbound.form', [
            'customers' => Customer::orderBy('name')->get(),
            'branches' => $this->branchesForUser(),
            'items' => Item::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'sell_price', 'base_uom_id']),
            'uoms' => Uom::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'fulfillment_branch_id' => ['nullable', 'exists:branches,id'],
            'order_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.uom_id' => ['required', 'exists:uoms,id'],
            'items.*.requested_qty' => ['required', 'numeric', 'min:0.001'],
        ]);

        $this->guardBranch((int) $data['branch_id']);

        $so = DB::transaction(function () use ($data) {
            $so = SalesOrder::create([
                'so_number' => $this->nextDocNumber('SO-'.now()->format('Ym').'-', 'sales_orders', 'so_number'),
                'customer_id' => $data['customer_id'],
                'branch_id' => $data['branch_id'],
                'fulfillment_branch_id' => $data['fulfillment_branch_id'] ?? $data['branch_id'],
                'order_date' => $data['order_date'],
                'status' => 'pending',
                'total_amount' => 0,
                'notes' => $data['notes'] ?? null,
                'user_id' => auth()->id(),
            ]);

            $total = 0;

            foreach ($data['items'] as $row) {
                $price = (float) Item::whereKey($row['item_id'])->value('sell_price');
                $qty = (float) $row['requested_qty'];

                SalesOrderItem::create([
                    'sales_order_id' => $so->id,
                    'item_id' => $row['item_id'],
                    'uom_id' => $row['uom_id'],
                    'requested_qty' => $qty,
                    'fulfilled_qty' => 0,
                    'unit_price' => $price,
                ]);

                $total += $qty * $price;
            }

            $so->update(['total_amount' => $total]);

            return $so;
        });

        return redirect()->route('outbound.show', $so)->with('toast', "SO {$so->so_number} dibuat.");
    }

    public function show(Request $request, SalesOrder $outbound): View
    {
        $outbound->load(['customer', 'branch', 'fulfillmentBranch', 'items.item', 'items.uom', 'packingLists', 'deliveries']);

        $sourceBranchId = $request->integer('source_branch_id')
            ?: ($outbound->fulfillment_branch_id ?: $this->activeBranchId());

        $lines = $outbound->items->map(function (SalesOrderItem $line) use ($sourceBranchId) {
            $remaining = max(0, (float) $line->requested_qty - (float) $line->fulfilled_qty);
            $pcsRemaining = $this->toPcs((int) $line->item_id, $remaining, (int) $line->uom_id);

            $fifo = $remaining > 0
                ? Stock::fifoPick((int) $sourceBranchId, (int) $line->item_id, $pcsRemaining)
                : [];

            $availableByBranch = Branch::query()
                ->withSum('stocks as pcs', 'quantity_pcs')
                ->whereHas('stocks', fn ($q) => $q->where('item_id', $line->item_id)->where('quantity_pcs', '>', 0))
                ->get()
                ->map(fn ($b) => ['branch' => $b, 'pcs' => (float) $b->pcs]);

            return [
                'line' => $line,
                'remaining' => $remaining,
                'canFifo' => count($fifo) > 0 && $remaining > 0,
                'fifo' => collect($fifo)->map(fn ($a) => [
                    'stock' => $a['stock'],
                    'batch' => Batch::find($a['stock']->batch_id),
                    'qty' => $a['qty'],
                ]),
                'availableByBranch' => $availableByBranch,
            ];
        })->values();

        $branches = $this->branchesForUser();

        return view('outbound.show', compact('outbound', 'lines', 'branches', 'sourceBranchId'));
    }

    /**
     * Pemenuhan FIFO (bisa cross-branch: SO milik cabang A, barang keluar dari cabang B).
     */
    public function fulfill(Request $request, SalesOrder $outbound): RedirectResponse
    {
        $data = $request->validate([
            'source_branch_id' => ['required', 'exists:branches,id'],
            'lines' => ['required', 'array'],
            'lines.*.sales_order_item_id' => ['required', 'exists:sales_order_items,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0'],
        ]);

        $sourceBranchId = (int) $data['source_branch_id'];

        if (! $this->canSeeAllBranches() && ! in_array($sourceBranchId, [(int) $this->activeBranchId(), (int) $outbound->branch_id], true)) {
            abort(403, 'Anda tidak berwenang mengeluarkan stok dari cabang tersebut.');
        }

        $processed = 0;
        $messages = [];

        DB::transaction(function () use ($outbound, $data, $sourceBranchId, &$processed, &$messages) {
            foreach ($data['lines'] as $row) {
                $qty = (float) $row['qty'];

                if ($qty <= 0) {
                    continue;
                }

                $line = SalesOrderItem::with('item')->find($row['sales_order_item_id']);

                if (! $line || $line->sales_order_id !== $outbound->id) {
                    continue;
                }

                $remaining = max(0, (float) $line->requested_qty - (float) $line->fulfilled_qty);

                if ($qty > $remaining + 0.0001) {
                    $messages[] = "{$line->item->sku}: melebihi sisa pesanan";

                    continue;
                }

                $pcs = $this->toPcs((int) $line->item_id, $qty, (int) $line->uom_id);
                $taken = StockService::remove($sourceBranchId, (int) $line->item_id, $pcs, [
                    'type' => 'OUT',
                    'reference_no' => $outbound->so_number,
                    'user_id' => auth()->id(),
                    'notes' => 'Pemenuhan SO '.$outbound->so_number,
                ]);

                if ($taken + 0.0001 < $pcs) {
                    $messages[] = $line->item->sku.': stok tidak cukup ('.number_format($taken, 0).' dari '.number_format($pcs, 0).' PCS)';

                    continue;
                }

                $line->fulfilled_qty = (float) $line->fulfilled_qty + $qty;
                $line->picked_qty = (float) $line->picked_qty + $qty;
                $line->save();

                $processed++;
            }

            $outbound->update([
                'fulfillment_branch_id' => $sourceBranchId,
                'status' => $this->resolveSoStatus($outbound),
            ]);
        });

        $toast = $processed > 0 ? "{$processed} baris berhasil dikeluarkan (FIFO)." : 'Tidak ada baris yang diproses.';
        if ($messages) {
            $toast .= ' '.implode('; ', $messages);
        }

        return redirect()->route('outbound.show', $outbound)->with('toast', $toast);
    }

    /**
     * Pemenuhan otomatis seluruh sisa SO memakai rekomendasi FIFO.
     */
    public function autoFulfill(Request $request, SalesOrder $outbound): RedirectResponse
    {
        $sourceBranchId = $request->integer('source_branch_id') ?: ($outbound->fulfillment_branch_id ?: $this->activeBranchId());

        $lines = $outbound->items->map(fn (SalesOrderItem $line) => [
            'sales_order_item_id' => $line->id,
            'qty' => max(0, (float) $line->requested_qty - (float) $line->fulfilled_qty),
        ])->filter(fn ($l) => $l['qty'] > 0)->values();

        if ($lines->isEmpty()) {
            return redirect()->route('outbound.show', $outbound)->with('toast', 'Seluruh item SO sudah terpenuhi.');
        }

        return $this->fulfill($request->merge([
            'source_branch_id' => $sourceBranchId,
            'lines' => $lines->all(),
        ]), $outbound);
    }

    private function resolveSoStatus(SalesOrder $so): string
    {
        $items = $so->items()->get();

        $allDone = $items->every(fn ($i) => (float) $i->fulfilled_qty + 0.0001 >= (float) $i->requested_qty);
        $anyDone = $items->contains(fn ($i) => (float) $i->fulfilled_qty > 0);

        return match (true) {
            $allDone => 'completed',
            $anyDone => 'partial',
            default => 'processing',
        };
    }
}
