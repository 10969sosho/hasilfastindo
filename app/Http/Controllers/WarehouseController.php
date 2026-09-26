<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        $query = Warehouse::with(['branch', 'locations'])
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('code', 'like', '%'.$request->string('q').'%')));

        if (! $this->canSeeAllBranches()) {
            $query->where('branch_id', $this->activeBranchId());
        }

        $warehouses = $query->orderBy('code')->get();
        $branches = $this->branchesForUser();

        return view('warehouses.index', compact('warehouses', 'branches'));
    }

    public function create(): View
    {
        return view('warehouses.form', ['warehouse' => null, 'branches' => $this->branchesForUser()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'code' => ['required', 'string', 'max:30', 'unique:warehouses,code'],
            'name' => ['required', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $data['is_default'] = $request->boolean('is_default');
        $this->guardBranch((int) $data['branch_id']);
        Warehouse::create($data);

        return redirect()->route('warehouses.index')->with('toast', 'Gudang berhasil dibuat.');
    }

    public function edit(Warehouse $warehouse): View
    {
        $this->guardBranch((int) $warehouse->branch_id);

        return view('warehouses.form', ['warehouse' => $warehouse, 'branches' => $this->branchesForUser()]);
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'code' => ['required', 'string', 'max:30', 'unique:warehouses,code,'.$warehouse->id],
            'name' => ['required', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $this->guardBranch((int) $data['branch_id']);
        $data['is_default'] = $request->boolean('is_default');
        $warehouse->update($data);

        return redirect()->route('warehouses.index')->with('toast', 'Gudang diperbarui.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $this->guardBranch((int) $warehouse->branch_id);

        if ($warehouse->stocks()->where('quantity_pcs', '>', 0)->exists()) {
            return back()->with('toast', 'Gudang masih memiliki stok, tidak bisa dihapus.');
        }

        $warehouse->delete();

        return redirect()->route('warehouses.index')->with('toast', 'Gudang dihapus.');
    }

    /* ------------------------------------------------------------------
     |  BIN CODE (locations)
     ------------------------------------------------------------------ */

    public function locations(Request $request): View
    {
        $query = Location::with(['warehouse.branch'])
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', '%'.$request->string('q').'%')
                ->orWhere('area', 'like', '%'.$request->string('q').'%')));

        if (! $this->canSeeAllBranches()) {
            $query->whereIn('warehouse_id', Warehouse::where('branch_id', $this->activeBranchId())->pluck('id'));
        }

        $locations = $query->orderBy('code')->paginate(20)->withQueryString();

        $binStocks = Stock::whereIn('location_id', $locations->pluck('id'))
            ->where('quantity_pcs', '>', 0)
            ->groupBy('location_id')
            ->get(['location_id', DB::raw('SUM(quantity_pcs) AS pcs')])
            ->keyBy('location_id');

        $warehouses = Warehouse::with('branch')->orderBy('code')->get();

        return view('warehouses.locations', compact('locations', 'warehouses', 'binStocks'));
    }

    public function locationCreate(Request $request): View
    {
        return view('warehouses.location-form', [
            'location' => null,
            'warehouses' => Warehouse::with('branch')->orderBy('code')->get(),
            'defaultWarehouse' => $request->integer('warehouse_id') ?: $this->defaultWarehouse((int) $this->activeBranchId())?->id,
        ]);
    }

    public function locationStore(Request $request): RedirectResponse
    {
        $data = $this->validateLocation($request);

        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $this->guardBranch((int) $warehouse->branch_id);

        $data['code'] = $this->uniqueBinCode(($data['code'] ?? null) ?: $this->buildBinCode($data, $warehouse));
        Location::create($data);

        return redirect()->route('bins.index')->with('toast', "BIN {$data['code']} berhasil dibuat.");
    }

    public function locationEdit(Location $location): View
    {
        return view('warehouses.location-form', [
            'location' => $location,
            'warehouses' => Warehouse::with('branch')->orderBy('code')->get(),
            'defaultWarehouse' => $location->warehouse_id,
        ]);
    }

    public function locationUpdate(Request $request, Location $location): RedirectResponse
    {
        $data = $this->validateLocation($request, $location);

        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $this->guardBranch((int) $warehouse->branch_id);

        $data['code'] = $this->uniqueBinCode(($data['code'] ?? null) ?: $this->buildBinCode($data, $warehouse), $location->id);
        $location->update($data);

        return redirect()->route('bins.index')->with('toast', 'BIN diperbarui.');
    }

    public function locationDestroy(Location $location): RedirectResponse
    {
        if (Stock::where('location_id', $location->id)->where('quantity_pcs', '>', 0)->exists()) {
            return back()->with('toast', 'BIN masih berisi stok, tidak bisa dihapus.');
        }

        $location->delete();

        return redirect()->route('bins.index')->with('toast', 'BIN dihapus.');
    }

    private function validateLocation(Request $request, ?Location $location = null): array
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'code' => ['nullable', 'string', 'max:40', 'unique:locations,code'.($location ? ','.$location->id : '')],
            'area' => ['nullable', 'string', 'max:20'],
            'rack' => ['nullable', 'string', 'max:20'],
            'shelf' => ['nullable', 'string', 'max:20'],
            'bin' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function buildBinCode(array $data, ?Warehouse $warehouse = null): string
    {
        $parts = explode('-', (string) $warehouse?->code);
        $token = count($parts) > 1 ? strtoupper($parts[1]) : 'GEN';

        return sprintf(
            'BIN-%s-%s-%s-%s',
            $token,
            strtoupper(substr(($data['area'] ?? null) ?: 'A', 0, 6)),
            strtoupper(substr(($data['rack'] ?? null) ?: 'R1', 0, 6)),
            strtoupper(substr(($data['bin'] ?? null) ?: '01', 0, 6))
        );
    }

    /** locations.code unique global: auto-code diberi suffix bila sudah terpakai. */
    private function uniqueBinCode(string $code, ?int $ignoreId = null): string
    {
        $base = $code;
        $attempt = 2;

        while (Location::where('code', $code)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $code = $base.'-'.$attempt++;
        }

        return $code;
    }
}
