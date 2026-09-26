<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Delivery;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = $this->activeBranchId();
        $allBranch = $this->canSeeAllBranches();

        $stockQuery = $this->scopeBranch(Stock::query());
        $totalPcs = (clone $stockQuery)->sum('quantity_pcs');
        $stockValue = (clone $stockQuery)
            ->join('items', 'items.id', '=', 'stocks.item_id')
            ->sum(DB::raw('stocks.quantity_pcs * items.cost_price'));

        $soQuery = $this->scopeBranch(SalesOrder::query());
        $openSo = (clone $soQuery)->whereIn('status', ['draft', 'pending', 'processing', 'partial'])->count();
        $monthSo = (clone $soQuery)
            ->whereBetween('order_date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->count();

        $inTransit = StockTransfer::query()
            ->where('status', 'in_transit')
            ->when(! $allBranch && $branchId, fn ($q) => $q->where(fn ($s) => $s->where('from_branch_id', $branchId)->orWhere('to_branch_id', $branchId)))
            ->count();

        $kpi = [
            'total_pcs' => (int) $totalPcs,
            'stock_value' => (int) $stockValue,
            'open_so' => $openSo,
            'month_so' => $monthSo,
            'pending_receipts' => $this->scopeBranch(GoodsReceipt::query())->whereIn('status', ['draft', 'received'])->count(),
            'in_transit' => $inTransit,
            'deliveries' => $this->scopeBranch(Delivery::query())->whereIn('status', ['pending_scan', 'scanned_out', 'in_delivery'])->count(),
            'items' => Item::where('is_active', true)->count(),
        ];

        $branchStock = Branch::orderByDesc('is_central')->get()->map(fn (Branch $b) => [
            'branch' => $b,
            'pcs' => (float) Stock::where('branch_id', $b->id)->sum('quantity_pcs'),
            'value' => (float) Stock::where('branch_id', $b->id)
                ->join('items', 'items.id', '=', 'stocks.item_id')
                ->sum(DB::raw('stocks.quantity_pcs * items.cost_price')),
        ]);

        // Grafik mutasi 30 hari terakhir
        $since = Carbon::today()->subDays(29);
        $movements = StockMovement::query()
            ->when(! $allBranch && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('date', '>=', $since)
            ->get(['date', 'type', 'qty_in', 'qty_out'])
            ->groupBy(fn (StockMovement $m) => $m->date->format('Y-m-d'));

        $chart = [];
        foreach ($since->daysUntil(Carbon::today()->addDay()) as $day) {
            $rows = $movements->get($day->format('Y-m-d'), collect());
            $chart[] = [
                'label' => $day->format('d/m'),
                'in' => (float) $rows->sum('qty_in'),
                'out' => (float) $rows->sum('qty_out'),
            ];
        }
        $chartMax = max(1, (int) collect($chart)->max(fn ($c) => max($c['in'], $c['out'])));

        // SO: barang belum diambil
        $notTaken = SalesOrder::query()
            ->with(['customer', 'branch', 'items.item', 'items.uom'])
            ->when(! $allBranch && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereIn('status', ['pending', 'processing', 'partial'])
            ->orderBy('order_date')
            ->get()
            ->flatMap(fn (SalesOrder $so) => $so->items->map(fn ($i) => [
                'so' => $so,
                'item' => $i,
                'remaining' => max(0, (float) $i->requested_qty - (float) $i->fulfilled_qty),
            ]))
            ->filter(fn ($row) => $row['remaining'] > 0)
            ->take(10)
            ->values();

        // Alert stok minimum
        $totalsByItem = Stock::query()
            ->when(! $allBranch && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy('item_id')
            ->get(['item_id', DB::raw('SUM(quantity_pcs) AS pcs')])
            ->keyBy('item_id');

        $lowStock = Item::where('is_active', true)
            ->with('category')
            ->get()
            ->map(fn (Item $item) => [
                'item' => $item,
                'pcs' => (float) ($totalsByItem[$item->id]->pcs ?? 0),
            ])
            ->filter(fn ($row) => (float) $row['pcs'] <= (float) $row['item']->min_stock)
            ->sortBy(fn ($row) => (float) $row['pcs'] / max(1, (float) $row['item']->min_stock))
            ->take(8)
            ->values();

        $recentMovements = StockMovement::with(['item', 'branch', 'user'])
            ->when(! $allBranch && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest('id')
            ->take(8)
            ->get();

        return view('dashboard', compact('kpi', 'branchStock', 'chart', 'chartMax', 'notTaken', 'lowStock', 'recentMovements'));
    }
}
