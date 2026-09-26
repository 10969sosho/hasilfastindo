<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\PackingList;
use App\Models\PackingListBox;
use App\Models\PackingListItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PackingController extends Controller
{
    public function index(Request $request): View
    {
        $query = PackingList::with(['salesOrder', 'customer', 'branch', 'sourceBranch'])
            ->when($request->filled('q'), fn ($q) => $q->where('no_packing', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));

        $packingLists = $this->scopeBranch($query)->latest('id')->paginate(12)->withQueryString();

        return view('packing.index', compact('packingLists'));
    }

    public function create(Request $request): View
    {
        $soId = $request->integer('so_id');
        $order = $soId ? SalesOrder::with(['customer', 'branch', 'items.item', 'items.uom'])->findOrFail($soId) : null;

        $candidates = SalesOrder::with('customer')
            ->whereIn('status', ['pending', 'processing', 'partial'])
            ->when(! $this->canSeeAllBranches(), fn ($q) => $q->where('branch_id', $this->activeBranchId()))
            ->latest('order_date')
            ->limit(50)
            ->get();

        return view('packing.form', [
            'order' => $order,
            'candidates' => $candidates,
            'items' => $order ? $order->items->map(fn ($line) => [
                'line' => $line,
                'remaining' => max(0, (float) $line->picked_qty - (float) $line->packed_qty),
            ]) : collect(),
        ]);
    }

    /**
     * Buat packing list: qty per item dipecah rata menjadi N dus
     * dengan barcode unik per dus.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sales_order_id' => ['required', 'exists:sales_orders,id'],
            'total_box' => ['required', 'integer', 'min:1', 'max:99'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sales_order_item_id' => ['required', 'exists:sales_order_items,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.001'],
        ]);

        $order = SalesOrder::findOrFail($data['sales_order_id']);
        $this->guardBranch((int) $order->branch_id);

        $packing = DB::transaction(function () use ($data, $order) {
            $packing = PackingList::create([
                'no_packing' => $this->nextDocNumber('PKL-'.now()->format('Ym').'-', 'packing_lists', 'no_packing'),
                'sales_order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'branch_id' => $order->fulfillment_branch_id ?: $order->branch_id,
                'source_branch_id' => $order->fulfillment_branch_id ?: $order->branch_id,
                'total_box' => (int) $data['total_box'],
                'status' => 'draft',
                'user_id' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Susun qty per item ke dalam tiap dus (dibagi rata, sisa ke box awal)
            $perBox = [];

            foreach ($data['lines'] as $i => $row) {
                $qty = (float) $row['qty'];
                $boxes = (int) $data['total_box'];
                $base = floor(($qty / $boxes) * 1000) / 1000;
                $remainder = $qty - $base * $boxes;

                for ($b = 0; $b < $boxes; $b++) {
                    $perBox[$b][] = [
                        'sales_order_item_id' => (int) $row['sales_order_item_id'],
                        'qty' => round($base + ($b === 0 ? $remainder : 0), 3),
                    ];
                }
            }

            foreach ($perBox as $index => $contents) {
                $box = PackingListBox::create([
                    'packing_list_id' => $packing->id,
                    'box_number' => $index + 1,
                    'box_barcode' => $this->nextDocNumber('HF-BOX-'.now()->format('Ym').'-', 'packing_list_boxes', 'box_barcode', 3),
                    'weight_kg' => $data['weight_kg'] ?? null,
                    'status' => 'packed',
                ]);

                foreach ($contents as $content) {
                    if ($content['qty'] <= 0) {
                        continue;
                    }

                    $line = SalesOrderItem::find($content['sales_order_item_id']);

                    PackingListItem::create([
                        'packing_list_id' => $packing->id,
                        'packing_list_box_id' => $box->id,
                        'item_id' => $line->item_id,
                        'batch_id' => null,
                        'sales_order_item_id' => $line->id,
                        'qty' => $content['qty'],
                        'uom_id' => $line->uom_id,
                    ]);

                    $line->packed_qty = (float) $line->packed_qty + $content['qty'];
                    $line->save();
                }
            }

            $packing->update(['status' => 'packed']);

            return $packing;
        });

        return redirect()->route('packing.show', $packing)->with('toast', "Packing list {$packing->no_packing} dibuat dengan {$packing->total_box} dus.");
    }

    public function show(PackingList $packing): View
    {
        $packing->load(['salesOrder', 'customer', 'branch', 'sourceBranch', 'items.item', 'items.uom', 'boxes.items.item']);

        return view('packing.show', compact('packing'));
    }

    public function print(PackingList $packing): View
    {
        $packing->load(['salesOrder.customer', 'customer', 'branch', 'sourceBranch', 'items.item', 'items.uom', 'boxes.items.item', 'boxes.items.uom']);

        return view('packing.print', compact('packing'));
    }

    public function printBoxes(PackingList $packing): View
    {
        $packing->load('salesOrder.customer', 'customer', 'boxes');

        return view('packing.print-boxes', compact('packing'));
    }

    public function destroy(PackingList $packing): RedirectResponse
    {
        $this->guardBranch((int) $packing->branch_id);

        if ($packing->deliveries()->exists()) {
            return back()->with('toast', 'Packing list sudah terkait pengiriman.');
        }

        DB::transaction(function () use ($packing) {
            foreach ($packing->items as $item) {
                if ($item->salesOrderItem) {
                    $item->salesOrderItem->packed_qty = max(0, (float) $item->salesOrderItem->packed_qty - (float) $item->qty);
                    $item->salesOrderItem->save();
                }
            }

            $packing->delete();
        });

        return redirect()->route('packing.index')->with('toast', 'Packing list dihapus.');
    }
}
