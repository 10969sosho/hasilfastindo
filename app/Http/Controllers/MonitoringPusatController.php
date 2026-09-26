<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\Location;
use App\Models\SalesOrder;
use App\Models\Stock;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Matriks stok nasional untuk pengawasan pusat.
 */
class MonitoringPusatController extends Controller
{
    public const STATUSES = [
        'ready' => 'Ready',
        'reserved' => 'Reserved SO',
        'in_transit' => 'In-Transit',
        'on_delivery' => 'On Delivery',
    ];

    public function index(Request $request): View
    {
        $query = Stock::with(['branch', 'warehouse', 'location', 'item.category', 'batch', 'uom'])
            ->where('quantity_pcs', '>', 0)
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('location_id'), fn ($q) => $q->where('location_id', $request->integer('location_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->whereHas('item', fn ($i) => $i->where('category_id', $request->integer('category_id'))))
            ->when($request->filled('batch_id'), fn ($q) => $q->where('batch_id', $request->integer('batch_id')))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request) {
                $search = $request->string('q')->toString();
                $w->where('original_barcode', 'like', "%{$search}%")
                    ->orWhereHas('item', fn ($i) => $i->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                    ->orWhereHas('batch', fn ($b) => $b->where('batch_number', 'like', "%{$search}%"));
            }));

        if (! $this->canSeeAllBranches()) {
            $query->where('branch_id', $this->activeBranchId());
        }

        $rows = $query->orderBy('branch_id')->orderBy('location_id')->get();

        // Angka agregat status per item+cabang
        $reserved = $this->aggregate(SalesOrder::query()
            ->when(! $this->canSeeAllBranches(), fn ($q) => $q->where('branch_id', $this->activeBranchId()))
            ->whereIn('status', ['pending', 'processing', 'partial'])
            ->join('sales_order_items', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
            ->select('sales_orders.branch_id', 'sales_order_items.item_id', DB::raw('SUM(requested_qty - fulfilled_qty) AS qty'))
            ->whereRaw('requested_qty > fulfilled_qty')
            ->groupBy('sales_orders.branch_id', 'sales_order_items.item_id'));

        $inTransit = $this->aggregate(StockTransfer::query()
            ->when(! $this->canSeeAllBranches(), fn ($q) => $q->where('to_branch_id', $this->activeBranchId()))
            ->where('status', 'in_transit')
            ->join('stock_transfer_items', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
            ->select('stock_transfers.to_branch_id AS branch_id', 'stock_transfer_items.item_id', DB::raw('SUM(qty) AS qty'))
            ->groupBy('stock_transfers.to_branch_id', 'stock_transfer_items.item_id'));

        $onDelivery = $this->aggregate(Delivery::query()
            ->when(! $this->canSeeAllBranches(), fn ($q) => $q->where('branch_id', $this->activeBranchId()))
            ->whereIn('deliveries.status', ['pending_scan', 'scanned_out', 'in_delivery'])
            ->join('delivery_items', 'deliveries.id', '=', 'delivery_items.delivery_id')
            ->select('deliveries.branch_id', 'delivery_items.item_id', DB::raw('SUM(qty) AS qty'))
            ->groupBy('deliveries.branch_id', 'delivery_items.item_id'));

        $matrix = $rows->map(function (Stock $stock) use ($reserved, $inTransit, $onDelivery) {
            $key = $stock->branch_id.'|'.$stock->item_id;

            return [
                'stock' => $stock,
                'reserved' => $reserved[$key] ?? 0,
                'in_transit' => $inTransit[$key] ?? 0,
                'on_delivery' => $onDelivery[$key] ?? 0,
            ];
        });

        $statusFilter = $request->get('status');

        if ($statusFilter && $statusFilter !== 'ready') {
            $matrix = $matrix->filter(fn ($row) => $row[$statusFilter] > 0);
        } elseif ($statusFilter === 'ready') {
            $matrix = $matrix->filter(fn ($row) => $row['stock']->quantity_pcs > 0);
        }

        // Filter status dilakukan setelah agregat, jadi pagination manual di atas koleksi
        $page = Paginator::resolveCurrentPage();
        $matrix = $matrix->values();
        $matrix = new LengthAwarePaginator(
            $matrix->forPage($page, 25),
            $matrix->count(),
            25,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => request()->query()]
        );

        $totals = [
            'ready' => (float) Stock::when(! $this->canSeeAllBranches(), fn ($q) => $q->where('branch_id', $this->activeBranchId()))->sum('quantity_pcs'),
            'reserved' => array_sum($reserved),
            'in_transit' => array_sum($inTransit),
            'on_delivery' => array_sum($onDelivery),
        ];

        return view('monitoring.index', [
            'matrix' => $matrix,
            'rows' => $rows,
            'totals' => $totals,
            'branches' => Branch::orderBy('name')->get(),
            'warehouses' => Warehouse::with('branch')->orderBy('code')->get(),
            'bins' => Location::orderBy('code')->get(),
            'categories' => Category::orderBy('name')->get(),
            'batches' => Batch::with('item')->orderByDesc('id')->limit(200)->get(),
            'statuses' => self::STATUSES,
        ]);
    }

    /**
     * @return array<string, float>
     */
    private function aggregate($query): array
    {
        return $query->get()
            ->mapWithKeys(fn ($row) => [$row->branch_id.'|'.$row->item_id => (float) $row->qty])
            ->all();
    }
}
