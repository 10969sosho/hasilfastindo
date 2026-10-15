<?php

namespace App\Http\Controllers;

use App\Models\NavPurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NavPurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $pos = NavPurchaseOrder::with('items')
            ->withCount('items')
            ->withSum('items', 'nav_qty_pcs')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('po_no', 'like', '%'.$request->string('q').'%')
                ->orWhere('supplier_name', 'like', '%'.$request->string('q').'%')))
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('nav-purchase-orders.index', ['pos' => $pos]);
    }

    public function show(NavPurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load('items');

        return view('nav-purchase-orders.show', ['po' => $purchaseOrder]);
    }
}
