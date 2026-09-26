<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Location;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Request $request): View
    {
        $branches = Branch::withCount('warehouses')
            ->orderByDesc('is_central')
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $b) => $b->setRelation('stats', (object) [
                'pcs' => (float) Stock::where('branch_id', $b->id)->sum('quantity_pcs'),
                'bins' => Location::whereIn(
                    'warehouse_id',
                    Warehouse::where('branch_id', $b->id)->pluck('id')
                )->count(),
                'users' => $b->users()->count(),
            ]));

        return view('branches.index', compact('branches'));
    }

    public function create(): View
    {
        return view('branches.form', ['branch' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:branches,code'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_central' => ['nullable', 'boolean'],
        ]);

        $data['is_central'] = $request->boolean('is_central');
        $branch = Branch::create($data);

        return redirect()->route('branches.index')->with('toast', "Cabang {$branch->name} berhasil dibuat.");
    }

    public function edit(Branch $branch): View
    {
        return view('branches.form', compact('branch'));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:branches,code,'.$branch->id],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_central' => ['nullable', 'boolean'],
        ]);

        $data['is_central'] = $request->boolean('is_central');
        $branch->update($data);

        return redirect()->route('branches.index')->with('toast', 'Cabang diperbarui.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        if ($branch->users()->exists() || $branch->warehouses()->exists()) {
            return back()->with('toast', 'Cabang masih memiliki user/gudang, tidak bisa dihapus.');
        }

        $branch->delete();

        return redirect()->route('branches.index')->with('toast', 'Cabang dihapus.');
    }
}
