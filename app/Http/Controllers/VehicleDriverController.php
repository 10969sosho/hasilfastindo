<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VehicleDriverController extends Controller
{
    /* ---------------- Kendaraan ---------------- */

    public function index(Request $request): View
    {
        $vehicles = Vehicle::with(['driver', 'helper'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('plate_number', 'like', '%'.$request->string('q').'%')
                ->orWhere('vehicle_name', 'like', '%'.$request->string('q').'%')))
            ->orderBy('plate_number')
            ->get();

        $drivers = Driver::orderBy('role')->orderBy('name')->get();

        $activeLoads = Delivery::whereIn('vehicle_id', $vehicles->pluck('id'))
            ->whereIn('status', ['pending_scan', 'scanned_out', 'in_delivery'])
            ->withCount('items')
            ->get()
            ->keyBy('vehicle_id');

        return view('fleet.index', compact('vehicles', 'drivers', 'activeLoads'));
    }

    public function vehicleCreate(): View
    {
        return view('fleet.vehicle-form', ['vehicle' => null, 'drivers' => Driver::orderBy('role')->orderBy('name')->get()]);
    }

    public function vehicleStore(Request $request): RedirectResponse
    {
        $data = $this->validateVehicle($request);
        $this->fillNames($data);
        Vehicle::create($data);

        return redirect()->route('fleet.index')->with('toast', 'Kendaraan berhasil ditambahkan.');
    }

    public function vehicleEdit(Vehicle $vehicle): View
    {
        return view('fleet.vehicle-form', ['vehicle' => $vehicle, 'drivers' => Driver::orderBy('role')->orderBy('name')->get()]);
    }

    public function vehicleUpdate(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $this->validateVehicle($request, $vehicle);
        $this->fillNames($data);
        $vehicle->update($data);

        return redirect()->route('fleet.index')->with('toast', 'Kendaraan diperbarui.');
    }

    public function vehicleDestroy(Vehicle $vehicle): RedirectResponse
    {
        if ($vehicle->deliveries()->exists()) {
            return back()->with('toast', 'Kendaraan punya riwayat pengiriman, tidak bisa dihapus.');
        }

        $vehicle->delete();

        return redirect()->route('fleet.index')->with('toast', 'Kendaraan dihapus.');
    }

    /* ---------------- Sopir & Helper ---------------- */

    public function driverCreate(): View
    {
        return view('fleet.driver-form', ['driver' => null]);
    }

    public function driverStore(Request $request): RedirectResponse
    {
        Driver::create($this->validateDriver($request));

        return redirect()->route('fleet.index')->with('toast', 'Sopir/helper berhasil ditambahkan.');
    }

    public function driverEdit(Driver $driver): View
    {
        return view('fleet.driver-form', compact('driver'));
    }

    public function driverUpdate(Request $request, Driver $driver): RedirectResponse
    {
        $driver->update($this->validateDriver($request, $driver));

        return redirect()->route('fleet.index')->with('toast', 'Data sopir/helper diperbarui.');
    }

    public function driverDestroy(Driver $driver): RedirectResponse
    {
        if ($driver->deliveriesAsDriver()->exists() || $driver->vehiclesAsDriver()->exists()) {
            return back()->with('toast', 'Masih terkait kendaraan/pengiriman, tidak bisa dihapus.');
        }

        $driver->delete();

        return redirect()->route('fleet.index')->with('toast', 'Data dihapus.');
    }

    /* ------------------------------------------------------------------ */

    private function validateVehicle(Request $request, ?Vehicle $vehicle = null): array
    {
        return $request->validate([
            'plate_number' => ['required', 'string', 'max:20', 'unique:vehicles,plate_number'.($vehicle ? ','.$vehicle->id : '')],
            'vehicle_name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:40'],
            'capacity_kg' => ['nullable', 'numeric', 'min:0'],
            'driver_id' => ['nullable', 'exists:drivers,id'],
            'helper_id' => ['nullable', 'exists:drivers,id'],
            'status' => ['required', 'in:available,in_use,maintenance'],
        ]);
    }

    private function fillNames(array &$data): void
    {
        $data['driver_name'] = isset($data['driver_id']) && $data['driver_id'] ? Driver::find($data['driver_id'])?->name : null;
        $data['helper_name'] = isset($data['helper_id']) && $data['helper_id'] ? Driver::find($data['helper_id'])?->name : null;
    }

    private function validateDriver(Request $request, ?Driver $driver = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'license_number' => ['nullable', 'string', 'max:40'],
            'role' => ['required', 'in:driver,helper'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }
}
