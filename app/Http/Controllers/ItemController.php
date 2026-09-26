<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemUomConversion;
use App\Models\Stock;
use App\Models\Uom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $items = Item::with(['category', 'baseUom'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $search = $request->string('q')->toString();
                $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%"));
            })
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->boolean('inactive') === false, fn ($q) => $q->where('is_active', true))
            ->withCount(['stocks as total_pcs' => fn ($q) => $q->select(DB::raw('COALESCE(SUM(quantity_pcs),0)'))])
            ->orderBy('sku')
            ->paginate(15)
            ->withQueryString();

        $categories = Category::orderBy('name')->get();

        return view('items.index', compact('items', 'categories'));
    }

    public function create(): View
    {
        return view('items.form', array_merge($this->formData(), ['item' => null]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            $item = Item::create($data);
            $this->syncConversions($item, $request->input('conversions', []));
        });

        return redirect()->route('items.index')->with('toast', 'Item berhasil disimpan.');
    }

    public function edit(Item $item): View
    {
        return view('items.form', array_merge($this->formData(), [
            'item' => $item,
            'conversions' => $item->uomConversions()->with(['fromUom', 'toUom'])->get(),
        ]));
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $data = $this->validated($request, $item);

        DB::transaction(function () use ($item, $data, $request) {
            $item->update($data);
            $this->syncConversions($item, $request->input('conversions', []));
        });

        return redirect()->route('items.index')->with('toast', 'Item berhasil diperbarui.');
    }

    public function destroy(Item $item): RedirectResponse
    {
        $hasStock = Stock::where('item_id', $item->id)->where('quantity_pcs', '>', 0)->exists();

        if ($hasStock) {
            return back()->with('toast', 'Item tidak bisa dihapus karena masih memiliki stok.');
        }

        $item->delete();

        return redirect()->route('items.index')->with('toast', 'Item dihapus.');
    }

    /**
     * Halaman cetak barcode barang (JsBarcode).
     */
    public function barcode(Request $request): View
    {
        $items = Item::with(['category', 'baseUom'])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->orderBy('sku')
            ->limit(60)
            ->get();

        return view('items.barcode', ['items' => $items, 'q' => $request->string('q')->toString()]);
    }

    public function printBarcode(Item $item): View
    {
        return view('items.barcode', ['items' => collect([$item]), 'q' => '']);
    }

    /* ------------------------------------------------------------------ */

    private function formData(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(),
            'uoms' => Uom::orderBy('code')->get(),
            'conversions' => collect(),
        ];
    }

    private function validated(Request $request, ?Item $item = null): array
    {
        return $request->validate([
            'sku' => ['required', 'string', 'max:60', 'unique:items,sku'.($item ? ','.$item->id : '')],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'base_uom_id' => ['required', 'exists:uoms,id'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sell_price' => ['nullable', 'numeric', 'min:0'],
            'barcode' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }

    private function syncConversions(Item $item, array $rows): void
    {
        $keep = [];

        foreach ($rows as $row) {
            if (empty($row['from_uom_id']) || empty($row['to_uom_id']) || ! isset($row['multiplier'])) {
                continue;
            }

            $from = (int) $row['from_uom_id'];
            $to = (int) $row['to_uom_id'];
            $multiplier = (float) $row['multiplier'];

            if ($from === $to || $multiplier <= 0) {
                continue;
            }

            ItemUomConversion::updateOrCreate(
                ['item_id' => $item->id, 'from_uom_id' => $from, 'to_uom_id' => $to],
                ['multiplier' => $multiplier]
            );

            $keep[] = [$from, $to];
        }

        ItemUomConversion::where('item_id', $item->id)->get()->each(function (ItemUomConversion $conv) use ($keep) {
            $pair = [(int) $conv->from_uom_id, (int) $conv->to_uom_id];

            foreach ($keep as $kept) {
                if ($kept === $pair) {
                    return;
                }
            }

            $conv->delete();
        });
    }
}
