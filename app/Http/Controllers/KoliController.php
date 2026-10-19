<?php

namespace App\Http\Controllers;

use App\Models\PackingList;
use App\Models\PackingListBox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Generate Barcode Koli: identitas resmi kemasan pengiriman (KOLI-YYYYMMDD-XXXX).
 */
class KoliController extends Controller
{
    public function index(Request $request): View
    {
        $query = PackingList::with(['salesOrder', 'customer', 'branch', 'boxes'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('no_packing', 'like', '%'.$request->string('q').'%')
                ->orWhereHas('salesOrder', fn ($o) => $o->where('so_number', 'like', '%'.$request->string('q').'%'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));

        $packings = $this->scopeBranch($query)->latest('id')->paginate(12)->withQueryString();

        return view('koli.index', compact('packings'));
    }

    public function create(PackingList $packing): View|RedirectResponse
    {
        $this->guardBranch((int) $packing->branch_id);

        if ($packing->deliveries()->exists()) {
            return redirect()->route('koli.index')
                ->with('toast', 'Packing list sudah masuk surat jalan — barcode koli tidak bisa dibuat ulang.');
        }

        $packing->load(['salesOrder', 'customer', 'branch', 'boxes.items.item', 'boxes.items.uom']);

        return view('koli.create', ['packing' => $packing]);
    }

    /**
     * Terbitkan barcode koli unik + berat per koli untuk seluruh koli pada packing list.
     */
    public function store(Request $request, PackingList $packing): RedirectResponse
    {
        $this->guardBranch((int) $packing->branch_id);

        if ($packing->deliveries()->exists()) {
            return back()->with('toast', 'Packing list sudah masuk surat jalan — barcode koli tidak bisa dibuat ulang.');
        }

        $data = $request->validate([
            'weights' => ['nullable', 'array'],
            'weights.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $boxes = $packing->boxes()->orderBy('box_number')->get();

        if ($boxes->isEmpty()) {
            return back()->with('toast', 'Packing list belum memiliki koli. Selesaikan proses picking & packing terlebih dahulu.');
        }

        $prefix = 'KOLI-'.now()->format('Ymd').'-';
        $seq = PackingListBox::where('box_barcode', 'like', $prefix.'%')->count() + 1;

        DB::transaction(function () use ($boxes, $data, $prefix, &$seq) {
            foreach ($boxes as $i => $box) {
                if (! str_starts_with((string) $box->box_barcode, 'KOLI-')) {
                    do {
                        $barcode = $prefix.sprintf('%04d', $seq++);
                    } while (PackingListBox::where('box_barcode', $barcode)->where('id', '!=', $box->id)->exists());

                    $box->box_barcode = $barcode;
                }

                if (array_key_exists($i, $data['weights'] ?? []) && $data['weights'][$i] !== null && $data['weights'][$i] !== '') {
                    $box->weight_kg = (float) $data['weights'][$i];
                }

                $box->save();
            }
        });

        return redirect()->route('koli.print', $packing)
            ->with('toast', 'Barcode koli diterbitkan untuk '.count($boxes).' koli — label siap dicetak.');
    }

    public function print(PackingList $packing): View
    {
        $packing->load(['salesOrder.customer', 'customer', 'branch', 'boxes.items.item', 'boxes.items.uom']);

        return view('koli.print', ['packing' => $packing]);
    }
}
