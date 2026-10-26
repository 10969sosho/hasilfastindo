<?php

namespace App\Http\Controllers;

use App\Models\NavWarehousePick;
use App\Services\BusinessCentralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NavWarehousePickController extends Controller
{
    public function sync(Request $request, BusinessCentralService $bcService): RedirectResponse
    {
        try {
            $pickNo = $request->filled('pick_no') ? trim($request->string('pick_no')) : null;
            $count = $bcService->syncWarehousePicks((int) $request->input('limit', 50), $pickNo);
        } catch (\Throwable $e) {
            return back()->with('toast', 'Sinkronisasi Pick NAV gagal: '.substr($e->getMessage(), 0, 160));
        }

        return back()->with('toast', "{$count} Dokumen Warehouse Pick berhasil disinkronkan dari Microsoft Dynamics NAV");
    }

    public function index(Request $request): View
    {
        $picks = NavWarehousePick::with(['items', 'takeItems'])
            ->withCount('items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('pick_no', 'like', '%'.$request->string('q').'%')
                ->orWhere('source_so_no', 'like', '%'.$request->string('q').'%')
                ->orWhere('whse_shipment_no', 'like', '%'.$request->string('q').'%')
                ->orWhere('destination_no', 'like', '%'.$request->string('q').'%')
                ->orWhere('assigned_user_id', 'like', '%'.$request->string('q').'%')))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('nav-picks.index', ['picks' => $picks]);
    }

    public function show(NavWarehousePick $pick): View
    {
        $pick->load('items');

        return view('nav-picks.show', ['pick' => $pick]);
    }
}
