<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryTrack;
use App\Models\Driver;
use App\Models\PackingList;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    public const STATUSES = [
        'pending_scan' => 'Belum Scan Out',
        'scanned_out' => 'Sudah Scan Out',
        'in_delivery' => 'Dalam Pengiriman',
        'arrived' => 'Tiba',
        'received' => 'Selesai / Diterima',
        'problem' => 'Bermasalah',
    ];

    public function index(Request $request): View
    {
        $query = Delivery::with(['salesOrder', 'packingList', 'packingLists', 'branch', 'vehicle'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('delivery_no', 'like', '%'.$request->string('q').'%')
                ->orWhereHas('vehicle', fn ($v) => $v->where('plate_number', 'like', '%'.$request->string('q').'%'))));

        $deliveries = $this->scopeBranch($query)->latest('id')->paginate(12)->withQueryString();

        $stats = [];
        foreach (self::STATUSES as $key => $label) {
            $stats[$key] = ['label' => $label, 'count' => $this->scopeBranch(Delivery::query())->where('status', $key)->count()];
        }

        return view('delivery.index', compact('deliveries', 'stats'));
    }

    public function create(Request $request): View
    {
        $selectedIds = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('packing_list_ids', [])))));

        if ($request->filled('packing_list_id')) {
            $selectedIds[] = $request->integer('packing_list_id');
            $selectedIds = array_values(array_unique($selectedIds));
        }

        $packingLists = PackingList::with(['salesOrder', 'customer', 'boxes.items.item'])
            ->whereIn('status', ['packed', 'loaded'])
            ->when(! $this->canSeeAllBranches(), fn ($q) => $q->where('branch_id', $this->activeBranchId()))
            ->latest('id')
            ->limit(50)
            ->get();

        $selected = $packingLists->whereIn('id', $selectedIds)->values();
        $primary = $selected->first();
        $branchId = (int) ($primary?->branch_id ?: $this->activeBranchId());

        return view('delivery.form', [
            'packingLists' => $packingLists,
            'selectedIds' => $selected->pluck('id')->all(),
            'boxes' => $selected->flatMap(fn (PackingList $pl) => $pl->boxes)->values(),
            'vehicles' => Vehicle::orderBy('plate_number')->get(),
            'drivers' => Driver::where('role', 'driver')->where('is_active', true)->orderBy('name')->get(),
            'helpers' => Driver::where('role', 'helper')->where('is_active', true)->orderBy('name')->get(),
            'defaultAddress' => $primary?->customer?->address ?? $primary?->salesOrder?->customer?->address ?? '',
            'branchId' => $branchId,
        ]);
    }

    /**
     * Buat surat jalan (bisa gabung beberapa packing list / SO) + daftar muatan.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'packing_list_ids' => ['required', 'array', 'min:1'],
            'packing_list_ids.*' => ['integer', 'exists:packing_lists,id'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'exists:drivers,id'],
            'helper_id' => ['nullable', 'exists:drivers,id'],
            'ekspedisi' => ['nullable', 'string', 'max:100'],
            'method' => ['required', 'in:armada,pickup_sendiri'],
            'destination_address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'boxes' => ['nullable', 'array'],
            'boxes.*' => ['exists:packing_list_boxes,id'],
        ]);

        $packings = PackingList::with(['salesOrder', 'customer', 'boxes.items'])
            ->whereIn('id', $data['packing_list_ids'])
            ->orderBy('id')
            ->get();

        if ($packings->isEmpty()) {
            return back()->with('toast', 'Packing list tidak ditemukan.')->withErrors(['packing_list_ids' => 'Packing list tidak ditemukan.']);
        }

        foreach ($packings as $packing) {
            $this->guardBranch((int) $packing->branch_id);
        }

        $primary = $packings->first();
        $vehicle = ! empty($data['vehicle_id']) ? Vehicle::find($data['vehicle_id']) : null;
        $driver = ! empty($data['driver_id']) ? Driver::find($data['driver_id']) : null;
        $helper = ! empty($data['helper_id']) ? Driver::find($data['helper_id']) : null;

        $delivery = DB::transaction(function () use ($data, $packings, $primary, $vehicle, $driver, $helper) {
            $delivery = Delivery::create([
                'delivery_no' => $this->nextDocNumber('SJ-'.now()->format('Ym').'-', 'deliveries', 'delivery_no'),
                'sales_order_id' => $primary->sales_order_id,
                'packing_list_id' => $primary->id,
                'branch_id' => $primary->branch_id,
                'vehicle_id' => $vehicle?->id,
                'driver_id' => $driver?->id,
                'helper_id' => $helper?->id,
                'driver_name' => $driver?->name,
                'helper_name' => $helper?->name,
                'destination_address' => $data['destination_address']
                    ?? $primary->customer?->address
                    ?? $primary->salesOrder?->customer?->address,
                'method' => $data['method'],
                'ekspedisi' => $data['ekspedisi'] ?? null,
                'status' => 'pending_scan',
                'user_id' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);

            $delivery->packingLists()->attach($packings->pluck('id'));

            $selected = $data['boxes'] ?? [];

            foreach ($packings as $packing) {
                foreach ($packing->boxes as $box) {
                    if ($selected && ! in_array($box->id, $selected, true) && ! in_array((string) $box->id, $selected, true)) {
                        continue;
                    }

                    foreach ($box->items as $boxItem) {
                        DeliveryItem::create([
                            'delivery_id' => $delivery->id,
                            'packing_list_box_id' => $box->id,
                            'item_id' => $boxItem->item_id,
                            'batch_id' => $boxItem->batch_id,
                            'qty' => $boxItem->qty,
                            'uom_id' => $boxItem->uom_id,
                            'barcode' => $box->box_barcode,
                            'status' => 'pending',
                        ]);
                    }

                    $box->update(['status' => 'loaded']);
                }

                $packing->update(['status' => 'loaded']);
            }

            DeliveryTrack::create([
                'delivery_id' => $delivery->id,
                'status' => 'pending_scan',
                'description' => 'Surat jalan gabungan '.count($packings).' packing list dibuat, menunggu scan out ke armada '.($vehicle?->plate_number ?? '-'),
                'user_id' => auth()->id(),
            ]);

            return $delivery;
        });

        return redirect()->route('delivery.show', $delivery)->with('toast', "Surat jalan {$delivery->delivery_no} dibuat dari {$packings->count()} packing list.");
    }

    public function show(Delivery $delivery): View
    {
        $delivery->load([
            'salesOrder.customer', 'packingList', 'packingLists.salesOrder', 'branch', 'vehicle',
            'driver', 'helper', 'items.item', 'items.uom', 'tracks.user',
        ]);

        $scanned = $delivery->items->where('status', 'scanned_out')->count();

        return view('delivery.show', [
            'delivery' => $delivery,
            'statuses' => self::STATUSES,
            'scanned' => $scanned,
            'total' => $delivery->items->count(),
            'manifest' => $delivery->items->groupBy('barcode')->map(fn ($group) => [
                'barcode' => $group->first()->barcode,
                'status' => $group->every(fn ($i) => $i->status === 'scanned_out') ? 'scanned_out' : 'pending',
                'scanned_out_at' => $group->max('scanned_out_at'),
                'master_box_no' => $group->first()->master_box_no,
                'item_ids' => $group->pluck('id')->all(),
                'items' => $group,
                'qty' => $group->sum('qty'),
            ])->values(),
        ]);
    }

    /**
     * Konsolidasi koli/kardus: gabungkan beberapa unit/dus ke 1 master box sebelum naik truk.
     */
    public function consolidate(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->guardBranch((int) $delivery->branch_id);

        $data = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['nullable', 'string', 'max:60'],
        ]);

        $ids = collect($data['item_ids'])
            ->flatMap(fn (string $value) => explode(',', $value))
            ->map(fn (string $value) => (int) trim($value))
            ->filter()
            ->values();

        if (! in_array($delivery->status, ['pending_scan', 'scanned_out'], true)) {
            return back()->with('toast', 'Konsolidasi koli hanya bisa dilakukan sebelum pengiriman berangkat.');
        }

        $items = $delivery->items()
            ->whereIn('id', $ids->all())
            ->where('status', 'pending')
            ->get();

        if ($items->isEmpty()) {
            return back()->with('toast', 'Tidak ada muatan pending pada pilihan tersebut.');
        }

        $masterBoxNo = $this->nextDocNumber('HF-MB-'.now()->format('Ym').'-', 'delivery_items', 'master_box_no', 3);

        DB::transaction(function () use ($delivery, $items, $masterBoxNo) {
            foreach ($items as $item) {
                $item->update(['master_box_no' => $masterBoxNo]);
            }

            DeliveryTrack::create([
                'delivery_id' => $delivery->id,
                'status' => $delivery->status,
                'description' => $items->count().' baris muatan digabung menjadi koli/kardus master '.$masterBoxNo,
                'user_id' => auth()->id(),
            ]);
        });

        return back()->with('toast', $items->count().' baris muatan digabung jadi 1 master box '.$masterBoxNo.'.');
    }

    /**
     * Scan Out: barcode dus/barang sebelum masuk mobil.
     */
    public function scanOut(Request $request, Delivery $delivery): RedirectResponse
    {
        $data = $request->validate(['barcode' => ['required', 'string', 'max:80']]);

        [$ok, $message] = $this->doScanOut($delivery, $data['barcode']);

        return back()->with('toast', $message)->withErrors($ok ? [] : ['barcode' => $message]);
    }

    public function scanOutApi(Request $request, Delivery $delivery)
    {
        $data = $request->validate(['barcode' => ['required', 'string', 'max:80']]);
        [$ok, $message] = $this->doScanOut($delivery, $data['barcode']);

        $delivery->refresh();

        return response()->json([
            'ok' => $ok,
            'message' => $message,
            'scanned' => $delivery->items()->where('status', 'scanned_out')->count(),
            'total' => $delivery->items()->count(),
        ], $ok ? 200 : 422);
    }

    private function doScanOut(Delivery $delivery, string $barcode): array
    {
        $items = $delivery->items()->where('barcode', $barcode)->get();

        if ($items->isEmpty()) {
            // Scan master box (hasil konsolidasi koli/kardus) → seluruh muatan di dalamnya.
            $items = $delivery->items()->where('master_box_no', $barcode)->get();
        }

        if ($items->isEmpty()) {
            return [false, "Barcode {$barcode} tidak ada dalam daftar muatan surat jalan ini."];
        }

        $fresh = $items->where('status', '!=', 'scanned_out');

        if ($fresh->isEmpty()) {
            return [false, "Barcode {$barcode} sudah pernah di-scan out."];
        }

        $fresh->each(function (DeliveryItem $item) {
            $item->update(['status' => 'scanned_out', 'scanned_out_at' => now()]);
        });

        $delivery->items()->where('status', 'scanned_out')->count();
        $allScanned = $delivery->items()->where('status', 'pending')->count() === 0;

        if ($delivery->status === 'pending_scan' && $allScanned) {
            $delivery->update(['status' => 'scanned_out']);
            DeliveryTrack::create([
                'delivery_id' => $delivery->id,
                'status' => 'scanned_out',
                'description' => 'Seluruh muatan selesai discan out ke '.($delivery->vehicle?->plate_number ?? 'armada'),
                'user_id' => auth()->id(),
            ]);
        }

        return [true, "Barcode {$barcode} tercatat masuk ke ".($delivery->vehicle?->plate_number ?? 'armada').'.'];
    }

    /**
     * Update timeline pengiriman + upload bukti.
     */
    public function updateStatus(Request $request, Delivery $delivery): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:in_delivery,arrived,received,problem'],
            'description' => ['nullable', 'string', 'max:500'],
            'received_by' => ['nullable', 'string', 'max:120'],
            'proof' => ['nullable', 'image', 'max:4096'],
        ]);

        if ($delivery->status === 'pending_scan' && $data['status'] !== 'problem') {
            return back()->with('toast', 'Lakukan scan out seluruh muatan terlebih dahulu.');
        }

        $photo = null;

        if ($request->hasFile('proof')) {
            $photo = $request->file('proof')->store('deliveries/'.$delivery->id, 'public');
        }

        DB::transaction(function () use ($delivery, $data, $photo) {
            $delivery->update([
                'status' => $data['status'],
                'received_by' => $data['received_by'] ?? $delivery->received_by,
                'received_notes' => $data['description'] ?? $delivery->received_notes,
                'received_photo' => $photo ?? $delivery->received_photo,
                'departure_time' => $data['status'] === 'in_delivery' ? ($delivery->departure_time ?? now()) : $delivery->departure_time,
                'arrival_time' => $data['status'] === 'arrived' ? ($delivery->arrival_time ?? now()) : $delivery->arrival_time,
            ]);

            DeliveryTrack::create([
                'delivery_id' => $delivery->id,
                'status' => $data['status'],
                'description' => $data['description'] ?? self::STATUSES[$data['status']],
                'photo' => $photo,
                'user_id' => auth()->id(),
            ]);
        });

        return back()->with('toast', 'Status pengiriman: '.self::STATUSES[$data['status']].'.');
    }

    public function manifest(Delivery $delivery): View
    {
        $delivery->load(['salesOrder.customer', 'packingList', 'packingLists.salesOrder', 'branch', 'vehicle', 'driver', 'helper', 'items.item', 'items.uom']);

        return view('delivery.manifest', ['delivery' => $delivery, 'statuses' => self::STATUSES]);
    }

    public function openVehicles()
    {
        return response()->json(
            Vehicle::withCount(['deliveries as open_deliveries' => fn ($q) => $q->whereIn('status', ['pending_scan', 'scanned_out', 'in_delivery'])])
                ->get(['id', 'plate_number', 'vehicle_name', 'type', 'status'])
        );
    }
}
