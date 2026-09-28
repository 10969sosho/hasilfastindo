<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\InboundController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MobileWarehouseController;
use App\Http\Controllers\MonitoringPusatController;
use App\Http\Controllers\OutboundController;
use App\Http\Controllers\PackingController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\RepackController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\SummarySoController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\VehicleDriverController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

/* ---------------------------------------------------------------- Auth */

Route::get('/', fn () => redirect()->route('dashboard'));
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest')->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'branch'])->group(function () {

    Route::post('/switch-branch', [AuthController::class, 'switchBranch'])->name('branch.switch');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /* ---------------------------------------------------------- Master: Item */
    Route::get('/items', [ItemController::class, 'index'])->name('items.index');
    Route::get('/items/create', [ItemController::class, 'create'])->name('items.create');
    Route::post('/items', [ItemController::class, 'store'])->name('items.store');
    Route::get('/items/barcode', [ItemController::class, 'barcode'])->name('items.barcode');
    Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
    Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update');
    Route::delete('/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
    Route::get('/items/{item}/print', [ItemController::class, 'printBarcode'])->name('items.print');

    /* ------------------------------------------------------- Master: Cabang */
    Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
    Route::get('/branches/create', [BranchController::class, 'create'])->name('branches.create');
    Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
    Route::get('/branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
    Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');

    /* ----------------------------------------------- Master: Gudang & BIN */
    Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
    Route::get('/warehouses/create', [WarehouseController::class, 'create'])->name('warehouses.create');
    Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
    Route::get('/warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->name('warehouses.edit');
    Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
    Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');

    Route::get('/bins', [WarehouseController::class, 'locations'])->name('bins.index');
    Route::get('/bins/create', [WarehouseController::class, 'locationCreate'])->name('bins.create');
    Route::post('/bins', [WarehouseController::class, 'locationStore'])->name('bins.store');
    Route::get('/bins/{location}/edit', [WarehouseController::class, 'locationEdit'])->name('bins.edit');
    Route::put('/bins/{location}', [WarehouseController::class, 'locationUpdate'])->name('bins.update');
    Route::delete('/bins/{location}', [WarehouseController::class, 'locationDestroy'])->name('bins.destroy');

    /* ------------------------------------------ Master: Armada & Sopir */
    Route::get('/fleet', [VehicleDriverController::class, 'index'])->name('fleet.index');
    Route::get('/fleet/vehicles/create', [VehicleDriverController::class, 'vehicleCreate'])->name('fleet.vehicles.create');
    Route::post('/fleet/vehicles', [VehicleDriverController::class, 'vehicleStore'])->name('fleet.vehicles.store');
    Route::get('/fleet/vehicles/{vehicle}/edit', [VehicleDriverController::class, 'vehicleEdit'])->name('fleet.vehicles.edit');
    Route::put('/fleet/vehicles/{vehicle}', [VehicleDriverController::class, 'vehicleUpdate'])->name('fleet.vehicles.update');
    Route::delete('/fleet/vehicles/{vehicle}', [VehicleDriverController::class, 'vehicleDestroy'])->name('fleet.vehicles.destroy');
    Route::get('/fleet/drivers/create', [VehicleDriverController::class, 'driverCreate'])->name('fleet.drivers.create');
    Route::post('/fleet/drivers', [VehicleDriverController::class, 'driverStore'])->name('fleet.drivers.store');
    Route::get('/fleet/drivers/{driver}/edit', [VehicleDriverController::class, 'driverEdit'])->name('fleet.drivers.edit');
    Route::put('/fleet/drivers/{driver}', [VehicleDriverController::class, 'driverUpdate'])->name('fleet.drivers.update');
    Route::delete('/fleet/drivers/{driver}', [VehicleDriverController::class, 'driverDestroy'])->name('fleet.drivers.destroy');

    /* ------------------------------------------- Master: Supplier/Customer */
    Route::get('/partners', [PartnerController::class, 'index'])->name('partners.index');
    Route::get('/partners/create', [PartnerController::class, 'create'])->name('partners.create');
    Route::post('/partners', [PartnerController::class, 'store'])->name('partners.store');
    Route::get('/partners/{type}/{id}/edit', [PartnerController::class, 'edit'])->name('partners.edit');
    Route::put('/partners/{type}/{id}', [PartnerController::class, 'update'])->name('partners.update');
    Route::delete('/partners/{type}/{id}', [PartnerController::class, 'destroy'])->name('partners.destroy');

    /* ------------------------------------------------------- Inbound (GR) */
    Route::get('/inbound', [InboundController::class, 'index'])->name('inbound.index');
    Route::get('/inbound/create', [InboundController::class, 'create'])->name('inbound.create');
    Route::post('/inbound', [InboundController::class, 'store'])->name('inbound.store');
    Route::get('/inbound/lookup', [InboundController::class, 'barcodeLookup'])->name('inbound.lookup');
    Route::get('/inbound/{inbound}', [InboundController::class, 'show'])->name('inbound.show');
    Route::post('/inbound/{inbound}/receive', [InboundController::class, 'receive'])->name('inbound.receive');
    Route::post('/inbound/{inbound}/putaway', [InboundController::class, 'putaway'])->name('inbound.putaway');
    Route::get('/inbound/{inbound}/print-labels', [InboundController::class, 'printLabels'])->name('inbound.print');

    /* -------------------------------------------------- Outbound (SO/GI) */
    Route::get('/outbound', [OutboundController::class, 'index'])->name('outbound.index');
    Route::get('/outbound/create', [OutboundController::class, 'create'])->name('outbound.create');
    Route::post('/outbound', [OutboundController::class, 'store'])->name('outbound.store');
    Route::get('/outbound/{outbound}', [OutboundController::class, 'show'])->name('outbound.show');
    Route::post('/outbound/{outbound}/fulfill', [OutboundController::class, 'fulfill'])->name('outbound.fulfill');
    Route::post('/outbound/{outbound}/auto-fulfill', [OutboundController::class, 'autoFulfill'])->name('outbound.auto');

    /* --------------------------------------------- Transfer Antar Cabang */
    Route::get('/transfers', [TransferController::class, 'index'])->name('transfer.index');
    Route::get('/transfers/create', [TransferController::class, 'create'])->name('transfer.create');
    Route::post('/transfers', [TransferController::class, 'store'])->name('transfer.store');
    Route::get('/transfers/warehouses', [TransferController::class, 'warehouses'])->name('transfer.warehouses');
    Route::get('/transfers/bins', [TransferController::class, 'bins'])->name('transfer.bins');
    Route::get('/transfers/stock-bins', [TransferController::class, 'stockBins'])->name('transfer.stockBins');
    Route::get('/transfers/{transfer}', [TransferController::class, 'show'])->name('transfer.show');
    Route::post('/transfers/{transfer}/ship', [TransferController::class, 'ship'])->name('transfer.ship');
    Route::post('/transfers/{transfer}/receive', [TransferController::class, 'receive'])->name('transfer.receive');
    Route::delete('/transfers/{transfer}', [TransferController::class, 'destroy'])->name('transfer.destroy');

    /* ------------------------------------------------------ Repack/Konversi */
    Route::get('/repack', [RepackController::class, 'index'])->name('repack.index');
    Route::get('/repack/create', [RepackController::class, 'create'])->name('repack.create');
    Route::post('/repack', [RepackController::class, 'store'])->name('repack.store');
    Route::get('/repack/bins', [RepackController::class, 'bins'])->name('repack.bins');
    Route::get('/repack/stocks', [RepackController::class, 'stocks'])->name('repack.stocks');
    Route::get('/repack/{repack}', [RepackController::class, 'show'])->name('repack.show');

    /* ------------------------------------------------------- Radar Summary SO */
    Route::get('/summary-so', [SummarySoController::class, 'index'])->name('summary.index');
    Route::get('/summary-so/{order}', [SummarySoController::class, 'show'])->name('summary.show');

    /* ------------------------------------------------ Picking & Packing List */
    Route::get('/packing', [PackingController::class, 'index'])->name('packing.index');
    Route::get('/packing/create', [PackingController::class, 'create'])->name('packing.create');
    Route::post('/packing', [PackingController::class, 'store'])->name('packing.store');
    Route::get('/packing/{packing}', [PackingController::class, 'show'])->name('packing.show');
    Route::get('/packing/{packing}/print', [PackingController::class, 'print'])->name('packing.print');
    Route::get('/packing/{packing}/print-boxes', [PackingController::class, 'printBoxes'])->name('packing.print-boxes');
    Route::delete('/packing/{packing}', [PackingController::class, 'destroy'])->name('packing.destroy');

    /* ------------------------------------- Pengiriman, Scan Out & Manifest */
    Route::get('/delivery', [DeliveryController::class, 'index'])->name('delivery.index');
    Route::get('/delivery/create', [DeliveryController::class, 'create'])->name('delivery.create');
    Route::post('/delivery', [DeliveryController::class, 'store'])->name('delivery.store');
    Route::get('/delivery/vehicles', [DeliveryController::class, 'openVehicles'])->name('delivery.vehicles');
    Route::get('/delivery/{delivery}', [DeliveryController::class, 'show'])->name('delivery.show');
    Route::post('/delivery/{delivery}/scan-out', [DeliveryController::class, 'scanOut'])->name('delivery.scan');
    Route::post('/delivery/{delivery}/scan-out/api', [DeliveryController::class, 'scanOutApi'])->name('delivery.scan.api');
    Route::post('/delivery/{delivery}/status', [DeliveryController::class, 'updateStatus'])->name('delivery.status');
    Route::get('/delivery/{delivery}/manifest', [DeliveryController::class, 'manifest'])->name('delivery.manifest');

    /* --------------------------------------------------------- Stok Opname */
    Route::get('/opname', [StockOpnameController::class, 'index'])->name('opname.index');
    Route::get('/opname/create', [StockOpnameController::class, 'create'])->name('opname.create');
    Route::post('/opname', [StockOpnameController::class, 'store'])->name('opname.store');
    Route::get('/opname/bins', [StockOpnameController::class, 'bins'])->name('opname.bins');
    Route::get('/opname/lookup', [StockOpnameController::class, 'lookup'])->name('opname.lookup');
    Route::get('/opname/{opname}', [StockOpnameController::class, 'show'])->name('opname.show');
    Route::post('/opname/{opname}/scan', [StockOpnameController::class, 'scan'])->name('opname.scan');
    Route::post('/opname/{opname}/approve', [StockOpnameController::class, 'approve'])->name('opname.approve');
    Route::delete('/opname/{opname}', [StockOpnameController::class, 'destroy'])->name('opname.destroy');

    /* ------------------------------------------------- Monitoring Pusat */
    Route::get('/monitoring', [MonitoringPusatController::class, 'index'])->name('monitoring.index');

    /* ------------------------------------------------ Mobile Warehouse */
    Route::get('/mobile', [MobileWarehouseController::class, 'index'])->name('mobile.index');
    Route::post('/mobile/lookup', [MobileWarehouseController::class, 'lookup'])->name('mobile.lookup');
});
