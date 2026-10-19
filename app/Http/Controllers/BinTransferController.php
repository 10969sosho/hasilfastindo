<?php

namespace App\Http\Controllers;

use App\Models\ItemSerial;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Transfer Antar Bin: pindahkan satu barang (berdasarkan barcode) antar rak.
 */
class BinTransferController extends Controller
{
    public function index(): View
    {
        return view('bin-transfers.index');
    }

    /**
     * Detail barang + BIN asal saat ini hasil scan barcode.
     */
    public function lookup(Request $request)
    {
        $code = trim($request->input('barcode', ''));

        if ($code === '') {
            return response()->json(['ok' => false, 'message' => 'Barcode kosong.'], 422);
        }

        $serial = ItemSerial::with('location')->find($code);

        if (! $serial) {
            return response()->json(['ok' => false, 'message' => "Barcode {$code} tidak ditemukan."], 404);
        }

        $warehouseId = (int) ($serial->location?->warehouse_id
            ?: Location::where('code', $serial->bin_no)->value('warehouse_id'));

        return response()->json([
            'ok' => true,
            'message' => 'Barang ditemukan: '.$serial->sku,
            'payload' => $serial->toLookupPayload(),
            'bins' => PutawayController::binsOf($warehouseId ?: null),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode_uom_serialno' => ['required', 'string', 'max:160'],
            'location_id' => ['required', 'exists:locations,id'],
        ]);

        $serial = ItemSerial::find($data['kode_uom_serialno']);

        if (! $serial) {
            return back()->withErrors(['barcode' => 'Barcode barang tidak ditemukan.']);
        }

        $target = Location::with('warehouse')->findOrFail($data['location_id']);
        $this->guardBranch((int) $target->warehouse?->branch_id);

        [$ok, $message] = PutawayController::applySerialMove($serial, $target, null, 'TRANSFER_BIN');

        return $ok
            ? back()->with('toast', $message)
            : back()->withErrors(['barcode' => $message]);
    }
}
