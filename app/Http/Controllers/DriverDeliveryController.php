<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryTrack;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Portal Sopir (mobile): scan muat koli ke armada & scan tiba di tujuan.
 * Alur status: pending_scan/scanned_out -> in_delivery (Dalam Pengiriman) -> arrived (Tiba).
 */
class DriverDeliveryController extends Controller
{
    /**
     * 3 langkah status live tracking (dipakai juga halaman admin /delivery).
     *
     * @var array<int, array{key: string, label: string, statuses: array<int, string>}>
     */
    public const STEPS = [
        ['key' => 'ready', 'label' => 'Siap Kirim', 'statuses' => ['pending_scan', 'scanned_out']],
        ['key' => 'transit', 'label' => 'Sedang Diangkut', 'statuses' => ['in_delivery']],
        ['key' => 'arrived', 'label' => 'Sampai di Tujuan', 'statuses' => ['arrived', 'received', 'problem']],
    ];

    public function index(Request $request): View
    {
        $deliveries = $this->scopeBranch(Delivery::query()
            ->with(['vehicle', 'driver', 'branch', 'packingList.salesOrder'])
            ->whereIn('status', ['pending_scan', 'scanned_out', 'in_delivery', 'arrived'])
            ->when($request->filled('q'), fn ($q) => $q
                ->where(fn ($w) => $w
                    ->where('delivery_no', 'like', '%'.$request->string('q').'%')
                    ->orWhere('driver_name', 'like', '%'.$request->string('q').'%')
                    ->orWhereHas('vehicle', fn ($v) => $v->where('plate_number', 'like', '%'.$request->string('q').'%'))))
            ->latest('id'))->get();

        return view('driver.index', [
            'deliveries' => $deliveries,
            'steps' => self::STEPS,
        ]);
    }

    public function show(Delivery $delivery): View
    {
        $this->guardBranch((int) $delivery->branch_id);

        $delivery->load(['vehicle', 'driver', 'branch', 'packingList.salesOrder', 'items.item', 'items.uom']);

        return view('driver.show', [
            'delivery' => $delivery,
            'scanned' => $delivery->items()->where('status', 'scanned_out')->count(),
            'total' => $delivery->items()->count(),
            'steps' => self::STEPS,
        ]);
    }

    /**
     * Aksi 1 — Scan Muat: barcode koli/dus yang dinaikkan ke armada.
     */
    public function loadApi(Request $request, Delivery $delivery)
    {
        $this->guardBranch((int) $delivery->branch_id);

        $data = $request->validate(['barcode' => ['required', 'string', 'max:80']]);

        $items = $delivery->items()
            ->where(fn ($q) => $q->where('barcode', $data['barcode'])->orWhere('master_box_no', $data['barcode']))
            ->get();

        if ($items->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'Barcode '.$data['barcode'].' tidak ada dalam muatan surat jalan ini.',
            ], 422);
        }

        $fresh = $items->where('status', '!=', 'scanned_out');

        if ($fresh->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'Barcode '.$data['barcode'].' sudah termuat ke armada.',
            ], 422);
        }

        $fresh->each(fn (DeliveryItem $item) => $item->update([
            'status' => 'scanned_out',
            'scanned_out_at' => now(),
        ]));

        $delivery->refresh();
        $scanned = $delivery->items()->where('status', 'scanned_out')->count();
        $total = $delivery->items()->count();
        $allLoaded = $delivery->items()->where('status', 'pending')->count() === 0;

        if ($allLoaded && in_array($delivery->status, ['pending_scan', 'scanned_out'], true)) {
            $delivery->update([
                'status' => 'in_delivery',
                'departure_time' => $delivery->departure_time ?? now(),
            ]);

            DeliveryTrack::create([
                'delivery_id' => $delivery->id,
                'status' => 'in_delivery',
                'description' => 'Seluruh koli ('.$total.') termuat di '.($delivery->vehicle?->plate_number ?? 'armada').' — berangkat.',
                'user_id' => auth()->id(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Koli '.$data['barcode'].' termuat.',
            'scanned' => $scanned,
            'total' => $total,
            'status' => $delivery->fresh()->status,
            'done' => $allLoaded,
        ]);
    }

    /**
     * Aksi 2 — Scan Tiba: barcode koli saat sampai di tujuan.
     */
    public function arriveApi(Request $request, Delivery $delivery)
    {
        $this->guardBranch((int) $delivery->branch_id);

        $data = $request->validate(['barcode' => ['required', 'string', 'max:80']]);

        $items = $delivery->items()
            ->where(fn ($q) => $q->where('barcode', $data['barcode'])->orWhere('master_box_no', $data['barcode']))
            ->get();

        if ($items->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'Barcode '.$data['barcode'].' tidak ada dalam muatan surat jalan ini.',
            ], 422);
        }

        if (in_array($delivery->status, ['pending_scan', 'scanned_out'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Muatan belum lengkap discan muat — selesaikan scan muat terlebih dahulu.',
            ], 422);
        }

        if ($delivery->status === 'received') {
            return response()->json([
                'ok' => false,
                'message' => 'Pengiriman sudah selesai diterima.',
            ], 422);
        }

        if ($delivery->status !== 'arrived') {
            $delivery->update([
                'status' => 'arrived',
                'arrival_time' => now(),
            ]);

            DeliveryTrack::create([
                'delivery_id' => $delivery->id,
                'status' => 'arrived',
                'description' => 'Tiba di tujuan — scan koli '.$data['barcode'].' oleh sopir.',
                'user_id' => auth()->id(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Koli '.$data['barcode'].' tercatat tiba di tujuan.',
            'scanned' => $delivery->items()->where('status', 'scanned_out')->count(),
            'total' => $delivery->items()->count(),
            'status' => $delivery->fresh()->status,
        ]);
    }
}
