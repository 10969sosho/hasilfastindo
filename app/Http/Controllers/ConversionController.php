<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemSerial;
use App\Models\Location;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\UnitConversion;
use App\Models\Uom;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Konversi Satuan (breakdown/repack) berbasis perintah + scan barcode.
 * Barcode asal dinonaktifkan, barcode baru diterbitkan. Tanpa posting jurnal ke NAV.
 */
class ConversionController extends Controller
{
    public function index(Request $request): View
    {
        $query = UnitConversion::with(['item', 'fromUom', 'toUom', 'branch', 'warehouse', 'user'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($q) => $q->where('conversion_no', 'like', '%'.$request->string('q').'%'));

        $conversions = $this->scopeBranch($query)->latest('id')->paginate(12)->withQueryString();

        return view('conversion.index', [
            'conversions' => $conversions,
            'canCreate' => $this->canCreateCommand(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->canCreateCommand()) {
            return redirect()->route('conversion.index')
                ->with('toast', 'Perintah konversi hanya dibuat oleh Superadmin / Pusat.');
        }

        $branchId = $this->activeBranchId();

        return view('conversion.form', [
            'items' => Item::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'base_uom_id']),
            'uoms' => Uom::orderBy('code')->get(),
            'branches' => $this->branchesForUser(),
            'warehouses' => Warehouse::when(! $this->canSeeAllBranches(), fn ($q) => $q->where('branch_id', $branchId))->orderBy('code')->get(),
            'bins' => $this->locationsOf($request->integer('warehouse_id') ?: $this->defaultWarehouse((int) $branchId)?->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->canCreateCommand()) {
            return redirect()->route('conversion.index')
                ->with('toast', 'Perintah konversi hanya dibuat oleh Superadmin / Pusat.');
        }

        $data = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'from_uom_id' => ['required', 'exists:uoms,id'],
            'to_uom_id' => ['required', 'exists:uoms,id', 'different:from_uom_id'],
            'planned_qty' => ['required', 'numeric', 'min:0.001'],
            'branch_id' => ['required', 'exists:branches,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->guardBranch((int) $data['branch_id']);

        $conversion = UnitConversion::create([
            'conversion_no' => $this->nextDocNumber('CVN-'.now()->format('Ym').'-', 'unit_conversions', 'conversion_no'),
            'item_id' => $data['item_id'],
            'from_uom_id' => $data['from_uom_id'],
            'to_uom_id' => $data['to_uom_id'],
            'planned_qty' => (float) $data['planned_qty'],
            'executed_qty' => 0,
            'status' => UnitConversion::STATUS_OPEN,
            'branch_id' => $data['branch_id'],
            'warehouse_id' => $data['warehouse_id'],
            'user_id' => auth()->id(),
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('conversion.show', $conversion)
            ->with('toast', "Perintah konversi {$conversion->conversion_no} dibuat.");
    }

    public function show(UnitConversion $conversion): View
    {
        $conversion->load(['item', 'fromUom', 'toUom', 'branch', 'warehouse', 'user']);

        return view('conversion.show', [
            'conversion' => $conversion,
            'bins' => $this->locationsOf($conversion->warehouse_id),
        ]);
    }

    /**
     * Validasi barcode kemasan asal terhadap perintah konversi.
     */
    public function lookup(Request $request)
    {
        $code = trim($request->input('barcode', ''));

        if ($code === '') {
            return response()->json(['ok' => false, 'message' => 'Barcode kosong.'], 422);
        }

        $conversion = UnitConversion::with(['item', 'fromUom', 'toUom'])->findOrFail($request->integer('conversion_id'));
        $serial = ItemSerial::with('location')->find($code);

        if (! $serial) {
            return response()->json(['ok' => false, 'message' => "Barcode {$code} tidak ditemukan."], 404);
        }

        $payload = $serial->toLookupPayload();

        if (! $conversion->isOpen()) {
            return response()->json(['ok' => false, 'message' => 'Perintah konversi sudah ditutup.', 'payload' => $payload], 422);
        }

        if (! $serial->is_active) {
            return response()->json(['ok' => false, 'message' => "Barcode {$code} sudah invalid (terpakai/dikonversi).", 'payload' => $payload], 422);
        }

        if ($serial->sku !== $conversion->item?->sku) {
            return response()->json([
                'ok' => false,
                'message' => "Barcode berisi SKU {$serial->sku}, sedangkan perintah meminta {$conversion->item?->sku}.",
                'payload' => $payload,
            ], 422);
        }

        if (strtoupper((string) $serial->uom) !== strtoupper((string) $conversion->fromUom?->code)) {
            return response()->json([
                'ok' => false,
                'message' => "Barcode ber satuan {$serial->uom}, perintah meminta {$conversion->fromUom?->code}.",
                'payload' => $payload,
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Barcode valid untuk konversi.',
            'payload' => $payload,
            'source_pcs' => (float) $serial->qty_smallest_unit_in,
            'remaining' => $conversion->remainingQty(),
        ]);
    }

    /**
     * Eksekusi konversi: invalidasi barcode asal + terbitkan barcode baru.
     */
    public function execute(Request $request, UnitConversion $conversion): RedirectResponse
    {
        $this->guardBranch((int) $conversion->branch_id);

        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:160'],
            'produced_qty' => ['required', 'numeric', 'min:0.001'],
            'location_id' => ['required', 'exists:locations,id'],
        ]);

        if (! $conversion->isOpen()) {
            return back()->with('toast', 'Perintah konversi sudah ditutup.');
        }

        $target = Location::with('warehouse')->findOrFail($data['location_id']);
        $produced = (float) $data['produced_qty'];
        $remaining = $conversion->remainingQty();

        if ($produced > $remaining + 0.001) {
            return back()->withErrors(['produced_qty' => 'Hasil konversi melebihi sisa rencana ('.rtrim(rtrim(number_format($remaining, 2, '.', ''), '0'), '.').' '.$conversion->toUom?->code.').']);
        }

        $serial = ItemSerial::find($data['barcode']);

        if (! $serial) {
            return back()->withErrors(['barcode' => 'Barcode barang tidak ditemukan.']);
        }

        if (! $serial->is_active) {
            return back()->withErrors(['barcode' => 'Barcode sudah invalid (sudah pernah dikonversi / dipakai).']);
        }

        if ($serial->sku !== $conversion->item?->sku) {
            return back()->withErrors(['barcode' => "SKU barcode {$serial->sku} tidak sesuai perintah {$conversion->item?->sku}."]);
        }

        if (strtoupper((string) $serial->uom) !== strtoupper((string) $conversion->fromUom?->code)) {
            return back()->withErrors(['barcode' => "Satuan barcode {$serial->uom} tidak sesuai perintah {$conversion->fromUom?->code}."]);
        }

        $itemId = (int) $conversion->item_id;
        $sourcePcs = max(0, (float) $serial->qty_smallest_unit_in);
        $multiplier = $this->conversionMultiplier($itemId, (int) $conversion->to_uom_id, (int) $conversion->item->base_uom_id) ?? 1;
        $newSerialCount = max(1, (int) round($produced));

        $created = [];

        DB::transaction(function () use ($conversion, $serial, $target, $produced, $itemId, $sourcePcs, $multiplier, $newSerialCount, &$created) {
            $branchId = (int) $conversion->branch_id;

            // 1. Pindahkan stok fisik ke BIN hasil konversi (total PCS tidak berubah).
            if ($sourcePcs > 0) {
                $stock = Stock::query()
                    ->where('branch_id', $branchId)
                    ->where('item_id', $itemId)
                    ->where('quantity_pcs', '>', 0)
                    ->when($serial->location_id, fn ($q) => $q->where('location_id', $serial->location_id))
                    ->orderByRaw('CASE WHEN received_at IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('received_at')
                    ->orderBy('id')
                    ->first();

                if ($stock) {
                    $movedQty = min($sourcePcs, (float) $stock->quantity_pcs);
                    $moved = StockService::moveLocation($stock, (int) $target->id, $movedQty);

                    StockMovement::create([
                        'date' => now()->toDateString(),
                        'branch_id' => $branchId,
                        'warehouse_id' => (int) $target->warehouse_id,
                        'item_id' => $itemId,
                        'batch_id' => $stock->batch_id,
                        'location_id' => (int) $target->id,
                        'uom_id' => (int) $moved->uom_id,
                        'type' => 'CONVERSION',
                        'reference_no' => $conversion->conversion_no,
                        'qty_in' => $movedQty,
                        'user_id' => auth()->id(),
                        'notes' => 'Konversi '.$serial->kode_uom_serialno.' ke '.$conversion->toUom?->code,
                    ]);
                }
            }

            // 2. Invalidasi barcode asal.
            $serial->update([
                'is_active' => false,
                'notes' => 'Dikonversi ke '.$conversion->toUom?->code.' via Perintah #'.$conversion->id,
            ]);

            // 3. Terbitkan barcode baru (1 record = 1 unit UOM hasil konversi).
            $sku = $conversion->item->sku;
            $targetUom = strtoupper((string) $conversion->toUom?->code);

            for ($i = 1; $i <= $newSerialCount; $i++) {
                $code = sprintf('%s-%s-%s-%03d', $sku, $targetUom, $conversion->conversion_no, $i);

                if (ItemSerial::whereKey($code)->exists()) {
                    continue;
                }

                ItemSerial::create([
                    'kode_uom_serialno' => $code,
                    'sku' => $sku,
                    'uom' => $conversion->toUom?->code,
                    'location_id' => (int) $target->id,
                    'receive_no' => $conversion->conversion_no,
                    'po_no' => $serial->po_no,
                    'supplier' => $serial->supplier,
                    'qty_uom_in' => 1,
                    'qty_smallest_unit_in' => (float) $multiplier,
                    'bin_no' => $target->code,
                    'weight' => $sourcePcs > 0 && $newSerialCount > 0
                        ? round($serial->weight !== null ? $serial->weight / $newSerialCount : 0, 3)
                        : null,
                    'input_receive_by' => auth()->id(),
                    'input_receive_date_time' => now(),
                    'is_active' => true,
                    'notes' => 'Hasil konversi '.$conversion->conversion_no,
                ]);

                $created[] = $code;
            }

            // 4. Perbarui progres perintah.
            $results = $conversion->results ?? [];
            $results[] = [
                'source_serial' => $serial->kode_uom_serialno,
                'produced_qty' => $produced,
                'new_serials' => $created,
                'location' => $target->code,
                'executed_at' => now()->toIso8601String(),
                'user_id' => auth()->id(),
            ];

            $newExecuted = (float) $conversion->executed_qty + $produced;

            $conversion->update([
                'executed_qty' => $newExecuted,
                'results' => $results,
                'status' => $newExecuted + 0.001 >= (float) $conversion->planned_qty
                    ? UnitConversion::STATUS_COMPLETED
                    : UnitConversion::STATUS_OPEN,
            ]);
        });

        return redirect()->route('conversion.show', $conversion)->with('toast',
            'Konversi selesai: barcode asal di-invalid, '.count($created).' barcode baru diterbitkan. Cetak label barcode baru.');
    }

    public function print(UnitConversion $conversion): View
    {
        $conversion->load(['item', 'fromUom', 'toUom', 'branch']);

        $serials = ItemSerial::where('receive_no', $conversion->conversion_no)
            ->orderBy('kode_uom_serialno')
            ->get();

        return view('conversion.print', [
            'conversion' => $conversion,
            'serials' => $serials,
        ]);
    }

    private function canCreateCommand(): bool
    {
        $user = auth()->user();

        return $user && in_array($user->role, ['super_admin', 'central'], true);
    }
}
