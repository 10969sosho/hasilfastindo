<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kelola Supplier & Customer dalam satu modul Partner.
 */
class PartnerController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->get('tab', 'suppliers') === 'customers' ? 'customers' : 'suppliers';
        $q = $request->string('q')->toString();

        $query = $tab === 'suppliers' ? Supplier::query() : Customer::query();

        $partners = $query
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")))
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return view('partners.index', compact('partners', 'tab', 'q'));
    }

    public function create(Request $request): View
    {
        $tab = $request->get('type', 'suppliers') === 'customers' ? 'customers' : 'suppliers';

        return view('partners.form', ['tab' => $tab, 'partner' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tab = $request->get('type', 'suppliers') === 'customers' ? 'customers' : 'suppliers';
        $data = $this->validated($request, $tab);

        $model = $tab === 'suppliers' ? Supplier::create($data) : Customer::create($data);

        return redirect()->route('partners.index', ['tab' => $tab])->with('toast', "{$model->name} berhasil disimpan.");
    }

    public function edit(string $type, int $id): View
    {
        $tab = $type === 'customers' ? 'customers' : 'suppliers';
        $partner = $tab === 'customers' ? Customer::findOrFail($id) : Supplier::findOrFail($id);

        return view('partners.form', compact('tab', 'partner'));
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $tab = $type === 'customers' ? 'customers' : 'suppliers';
        $partner = $tab === 'customers' ? Customer::findOrFail($id) : Supplier::findOrFail($id);

        $partner->update($this->validated($request, $tab, $partner));

        return redirect()->route('partners.index', ['tab' => $tab])->with('toast', 'Data partner diperbarui.');
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        $tab = $type === 'customers' ? 'customers' : 'suppliers';
        $partner = $tab === 'customers' ? Customer::findOrFail($id) : Supplier::findOrFail($id);

        $protected = $tab === 'customers'
            ? $partner->salesOrders()->exists()
            : $partner->goodsReceipts()->exists();

        if ($protected) {
            return back()->with('toast', 'Partner masih punya transaksi, tidak bisa dihapus.');
        }

        $partner->delete();

        return redirect()->route('partners.index', ['tab' => $tab])->with('toast', 'Partner dihapus.');
    }

    private function validated(Request $request, string $tab, $partner = null): array
    {
        $unique = $partner ? ','.$partner->id : '';

        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', "unique:{$tab},code{$unique}"],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);

        if ($tab !== 'customers') {
            unset($data['city']);
        }

        return $data;
    }
}
