<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\ItemSerial;
use App\Models\ItemUomConversion;
use App\Models\Location;
use App\Models\PackingList;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WarehouseFlowRevisionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Warehouse $warehouse;

    private Location $bin;

    private Uom $pcs;

    private Uom $box;

    private Item $item;

    private Supplier $supplier;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['code' => 'BR1', 'name' => 'Cabang Utama']);
        $this->warehouse = Warehouse::create(['branch_id' => $this->branch->id, 'code' => 'WH1', 'name' => 'Gudang Pusat']);
        $this->bin = Location::create(['warehouse_id' => $this->warehouse->id, 'code' => 'BIN-A1']);
        $category = Category::create(['code' => 'CAT1', 'name' => 'Umum']);
        $this->pcs = Uom::create(['code' => 'PCS', 'name' => 'Piece']);
        $this->box = Uom::create(['code' => 'BOX', 'name' => 'Box']);
        $this->item = Item::create(['sku' => 'ABC123', 'name' => 'Item A', 'category_id' => $category->id, 'base_uom_id' => $this->pcs->id, 'is_active' => true]);
        ItemUomConversion::create([
            'item_id' => $this->item->id,
            'from_uom_id' => $this->box->id,
            'to_uom_id' => $this->pcs->id,
            'multiplier' => 50,
        ]);
        $this->supplier = Supplier::create(['code' => 'SUP1', 'name' => 'PT Contoh']);
        $this->user = User::create([
            'name' => 'QA Revision',
            'email' => 'qa.revision@test.test',
            'password' => 'password',
            'role' => 'central',
        ]);
    }

    public function test_repack_routes_and_menu_removed(): void
    {
        $repackRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'repack'));

        $this->assertCount(0, $repackRoutes);

        $this->get('/repack')->assertNotFound();

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Repack &amp; Konversi', false)
            ->assertDontSee('Repack & Konversi', false)
            ->assertDontSee('/repack', false);
    }

    public function test_per_uom_conversion_computes_total_and_blocks_nav_overage(): void
    {
        $payload = $this->receivePayload([
            'conversion_mode' => 'per_uom',
            'weight' => 12.5,
            'bin_no' => 'R-01',
            'items' => [[
                'item_id' => $this->item->id,
                'received_uom_id' => $this->box->id,
                'received_qty' => 3,
                'pcs_per_uom' => 50,
                'nav_qty' => 100,
                'target_bin_id' => $this->bin->id,
            ]],
        ]);

        $this->actingAs($this->user)->post(route('inbound.store'), $payload)
            ->assertSessionHasErrors('items.0.pcs_per_uom');
        $this->assertSame(0, GoodsReceipt::count());

        $payload['items'][0]['nav_qty'] = 200;
        $this->actingAs($this->user)->post(route('inbound.store'), $payload)->assertRedirect();
        $gr = GoodsReceipt::firstOrFail();
        $line = $gr->items()->firstOrFail();

        $this->assertSame(150.0, (float) $line->total_pcs);
        $this->assertSame(50.0, (float) $line->conversion_multiplier);
        $this->assertSame(12.5, (float) $line->weight);
        $this->assertSame('R-01', $line->bin_no);
        $this->assertStringContainsString('Selisih NAV', (string) $gr->notes);

        $this->actingAs($this->user)->post(route('inbound.receive', $gr))->assertRedirect();

        $serials = ItemSerial::orderBy('kode_uom_serialno')->get();
        $this->assertCount(3, $serials);
        foreach ($serials as $serial) {
            $this->assertSame(12.5, (float) $serial->weight);
            $this->assertSame('R-01', $serial->bin_no);
        }

        $this->actingAs($this->user)->get(route('inbound.print', $gr))
            ->assertOk()
            ->assertSee('12.5')
            ->assertSee('R-01');
    }

    public function test_per_unit_weight_and_bin_overrides_each_serial(): void
    {
        $this->actingAs($this->user)->post(route('inbound.store'), $this->receivePayload([
            'weight_bin_mode' => 'per_unit',
            'weight' => 10,
            'bin_no' => 'STG-1',
            'items' => [[
                'item_id' => $this->item->id,
                'received_uom_id' => $this->box->id,
                'received_qty' => 3,
                'nav_qty' => 200,
                'target_bin_id' => $this->bin->id,
                'units' => [
                    ['weight' => 1.5, 'bin_no' => 'A-1'],
                    ['weight' => 2.5, 'bin_no' => 'B-2'],
                    ['weight' => '', 'bin_no' => ''],
                ],
            ]],
        ]))->assertRedirect();

        $gr = GoodsReceipt::firstOrFail();
        $this->assertSame([
            ['weight' => 1.5, 'bin_no' => 'A-1'],
            ['weight' => 2.5, 'bin_no' => 'B-2'],
            ['weight' => null, 'bin_no' => null],
        ], $gr->items()->firstOrFail()->unit_overrides);

        $this->actingAs($this->user)->post(route('inbound.receive', $gr))->assertRedirect();

        $serials = ItemSerial::orderBy('kode_uom_serialno')->get();
        $this->assertCount(3, $serials);
        $this->assertEqualsCanonicalizing([1.5, 2.5, 10.0], $serials->pluck('weight')->map(fn ($w) => (float) $w)->all());
        $this->assertEqualsCanonicalizing(['A-1', 'B-2', 'STG-1'], $serials->pluck('bin_no')->all());
    }

    public function test_so_confirmation_gates_picking_and_packing(): void
    {
        $gr = $this->receiveReceipt();
        $serial = ItemSerial::orderBy('kode_uom_serialno')->firstOrFail();
        $so = $this->createSalesOrder('SO-202610-0001');

        $this->actingAs($this->user)->get(route('packing.create'))
            ->assertOk()
            ->assertDontSee($so->so_number, false);

        $this->actingAs($this->user)->post(route('outbound.pick', $so), [
            'barcode' => $serial->kode_uom_serialno,
        ])->assertStatus(422);

        $this->actingAs($this->user)->post(route('outbound.confirm', $so))->assertRedirect();
        $this->assertSame('confirmed', $so->fresh()->status);

        $this->actingAs($this->user)->get(route('packing.create'))
            ->assertOk()
            ->assertSee($so->so_number, false);

        $this->actingAs($this->user)->post(route('outbound.pick', $so), [
            'barcode' => $serial->kode_uom_serialno,
        ])->assertOk()->assertJsonPath('payload.picked', true);
    }

    public function test_surat_jalan_gabung_banyak_packing_list_dan_konsolidasi_koli(): void
    {
        $packingLists = collect([$this->createPackingList('SO-202610-0001'), $this->createPackingList('SO-202610-0002')]);

        $this->actingAs($this->user)->get(route('delivery.create', ['packing_list_ids' => $packingLists->pluck('id')->all()]))
            ->assertOk()
            ->assertSee($packingLists->first()->no_packing)
            ->assertSee($packingLists->last()->no_packing)
            ->assertSee('Ekspedisi');

        $this->actingAs($this->user)->post(route('delivery.store'), [
            'packing_list_ids' => $packingLists->pluck('id')->all(),
            'method' => 'armada',
            'ekspedisi' => 'JNE Reguler',
            'destination_address' => 'Jl. Contoh No. 1',
            'notes' => 'Gabung dua packing list',
        ])->assertRedirect();

        $delivery = Delivery::firstOrFail();

        $this->assertSame(2, $delivery->packingLists()->count());
        $this->assertSame('JNE Reguler', $delivery->ekspedisi);
        $this->assertEqualsCanonicalizing(
            $packingLists->pluck('id')->sort()->values()->all(),
            $delivery->packingLists()->pluck('packing_lists.id')->sort()->values()->all()
        );
        $this->assertSame(2, $delivery->items()->count());
        $this->assertSame('loaded', $packingLists->first()->fresh()->status);

        $this->actingAs($this->user)->get(route('delivery.index'))
            ->assertOk()
            ->assertSee($packingLists->first()->no_packing)
            ->assertSee($packingLists->last()->no_packing);

        $this->actingAs($this->user)->get(route('delivery.show', $delivery))
            ->assertOk()
            ->assertSee($packingLists->first()->no_packing)
            ->assertSee($packingLists->last()->no_packing)
            ->assertSee('Konsolidasi Koli')
            ->assertSee('JNE Reguler');

        // Gabung semua dus (beda barcode) jadi satu master box — persis seperti tombol di view (id dipisah koma).
        $this->actingAs($this->user)->post(route('delivery.consolidate', $delivery), [
            'item_ids' => $delivery->items()->pluck('id')->map(fn ($id) => (string) $id)->all(),
        ])->assertRedirect();

        $masterBox = $delivery->items()->pluck('master_box_no')->unique()->values();
        $this->assertCount(1, $masterBox);
        $this->assertMatchesRegularExpression('/^HF-MB-\d{6}-\d{3}$/', (string) $masterBox->first());
        $this->assertTrue($delivery->tracks()->where('description', 'like', '%master%')->exists());

        // Scan out pakai nomor master box: seluruh muatan di dalamnya ikut ter-scan.
        $this->actingAs($this->user)->post(route('delivery.scan', $delivery), [
            'barcode' => $masterBox->first(),
        ])->assertRedirect();

        $this->assertSame(0, $delivery->items()->where('status', 'pending')->count());
        $this->assertSame('scanned_out', $delivery->fresh()->status);

        $this->actingAs($this->user)->get(route('delivery.manifest', $delivery))
            ->assertOk()
            ->assertSee($masterBox->first())
            ->assertSee($packingLists->first()->salesOrder->so_number);
    }

    public function test_putaway_and_bin_transfer_move_serial_between_bins(): void
    {
        $target = Location::create(['warehouse_id' => $this->warehouse->id, 'code' => 'BIN-B2']);

        $this->receiveReceipt();
        $serial = ItemSerial::firstOrFail();

        $this->actingAs($this->user)->get(route('putaway.lookup', ['barcode' => $serial->kode_uom_serialno]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('payload.sku', 'ABC123');

        $this->actingAs($this->user)->post(route('putaway.store'), [
            'kode_uom_serialno' => $serial->kode_uom_serialno,
            'location_id' => $target->id,
            'weight' => 3.5,
        ])->assertRedirect();

        $serial->refresh();
        $this->assertSame('BIN-B2', $serial->bin_no);
        $this->assertSame((int) $target->id, (int) $serial->location_id);
        $this->assertSame(3.5, (float) $serial->weight);
        $this->assertTrue(StockMovement::where('type', 'PUTAWAY')->where('item_id', $this->item->id)->exists());

        $qtyBefore = (float) Stock::sum('quantity_pcs');

        $this->actingAs($this->user)->post(route('bin-transfers.store'), [
            'kode_uom_serialno' => $serial->kode_uom_serialno,
            'location_id' => $this->bin->id,
        ])->assertRedirect();

        $serial->refresh();
        $this->assertSame('BIN-A1', $serial->bin_no);
        $this->assertTrue(StockMovement::where('type', 'TRANSFER_BIN')->where('item_id', $this->item->id)->exists());
        $this->assertSame($qtyBefore, (float) Stock::sum('quantity_pcs'));

        // BIN sama dengan posisi sekarang -> ditolak.
        $this->actingAs($this->user)->post(route('bin-transfers.store'), [
            'kode_uom_serialno' => $serial->kode_uom_serialno,
            'location_id' => $this->bin->id,
        ])->assertSessionHasErrors('barcode');
    }

    public function test_perintah_transfer_wajib_scan_verifikasi_sebelum_diterima(): void
    {
        $branchUser = User::create([
            'name' => 'Ops Cabang',
            'email' => 'ops.cabang@test.test',
            'password' => 'password',
            'role' => 'branch',
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAs($branchUser)->get(route('transfer.create'))
            ->assertRedirect(route('transfer.index'));

        $toBranch = Branch::create(['code' => 'BR2', 'name' => 'Cabang Timur']);
        $toWarehouse = Warehouse::create(['branch_id' => $toBranch->id, 'code' => 'WH2', 'name' => 'Gudang Timur']);
        $toBin = Location::create(['warehouse_id' => $toWarehouse->id, 'code' => 'BIN-T1']);

        $this->receiveReceipt();
        $serial = ItemSerial::firstOrFail();

        $this->actingAs($this->user)->post(route('transfer.store'), [
            'from_branch_id' => $this->branch->id,
            'to_branch_id' => $toBranch->id,
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'items' => [[
                'item_id' => $this->item->id,
                'uom_id' => $this->box->id,
                'qty' => 1,
                'from_location_id' => $this->bin->id,
                'to_location_id' => $toBin->id,
            ]],
        ])->assertRedirect();

        $transfer = StockTransfer::firstOrFail();

        // Sebelum dikirim: scan ditolak.
        $this->actingAs($this->user)->postJson(route('transfer.scan', $transfer), [
            'barcode' => $serial->kode_uom_serialno,
        ])->assertStatus(422);

        $this->actingAs($this->user)->post(route('transfer.ship', $transfer))->assertRedirect();
        $this->assertSame('in_transit', $transfer->fresh()->status);

        // Penerimaan tanpa scan verifikasi ditolak.
        $this->actingAs($this->user)->post(route('transfer.receive', $transfer), [
            'lines' => [[
                'stock_transfer_item_id' => $transfer->items()->firstOrFail()->id,
                'received_qty' => 1,
                'to_location_id' => $toBin->id,
            ]],
        ])->assertSessionHas('toast');
        $this->assertSame('in_transit', $transfer->fresh()->status);

        $this->postJson(route('transfer.scan', $transfer), ['barcode' => 'TIDAK-ADA'])
            ->assertStatus(404);

        $this->postJson(route('transfer.scan', $transfer), ['barcode' => $serial->kode_uom_serialno])
            ->assertOk()
            ->assertJsonPath('scanned', 1);

        $this->postJson(route('transfer.scan', $transfer), ['barcode' => $serial->kode_uom_serialno])
            ->assertOk();

        $this->post(route('transfer.receive', $transfer), [
            'lines' => [[
                'stock_transfer_item_id' => $transfer->items()->firstOrFail()->id,
                'received_qty' => 1,
                'to_location_id' => $toBin->id,
            ]],
        ])->assertRedirect();

        $this->assertSame('received', $transfer->fresh()->status);
        $this->assertSame(1, $transfer->scans()->count());
    }

    public function test_portal_sopir_scan_muat_lalu_scan_tiba(): void
    {
        $packing = $this->createPackingList('SO-202610-0001');

        $this->actingAs($this->user)->post(route('delivery.store'), [
            'packing_list_ids' => [$packing->id],
            'method' => 'armada',
        ])->assertRedirect();

        $delivery = Delivery::firstOrFail();
        $barcode = $delivery->items()->firstOrFail()->barcode;

        $this->actingAs($this->user)->get(route('driver.index'))->assertOk()->assertSee($delivery->delivery_no);
        $this->actingAs($this->user)->get(route('driver.show', $delivery))->assertOk()->assertSee('Scan Muat');

        $this->postJson(route('driver.load', $delivery), ['barcode' => 'TIDAK-ADA'])
            ->assertStatus(422);

        $this->postJson(route('driver.load', $delivery), ['barcode' => $barcode])
            ->assertOk()
            ->assertJsonPath('status', 'in_delivery');

        $this->assertSame('in_delivery', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->departure_time);

        $this->postJson(route('driver.arrive', $delivery), ['barcode' => $barcode])
            ->assertOk()
            ->assertJsonPath('status', 'arrived');

        $delivery->refresh();
        $this->assertSame('arrived', $delivery->status);
        $this->assertNotNull($delivery->arrival_time);
        $this->assertTrue($delivery->tracks()->where('status', 'arrived')->exists());
    }

    private function receivePayload(array $overrides = []): array
    {
        return array_merge([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->bin->id,
            'received_at' => now()->toDateString(),
            'items' => [],
        ], $overrides);
    }

    private function receiveReceipt(): GoodsReceipt
    {
        $this->actingAs($this->user)->post(route('inbound.store'), $this->receivePayload([
            'items' => [[
                'item_id' => $this->item->id,
                'received_uom_id' => $this->box->id,
                'received_qty' => 2,
                'nav_qty' => 100,
                'target_bin_id' => $this->bin->id,
            ]],
        ]));
        $gr = GoodsReceipt::firstOrFail();

        $this->actingAs($this->user)->post(route('inbound.receive', $gr))->assertRedirect();

        return $gr;
    }

    private function createSalesOrder(string $number): SalesOrder
    {
        $customer = Customer::firstOrCreate(
            ['code' => 'CUS-'.md5($number)],
            ['name' => 'Customer '.$number]
        );

        $so = SalesOrder::create([
            'so_number' => $number,
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfillment_branch_id' => $this->branch->id,
            'order_date' => now()->toDateString(),
            'status' => 'pending',
            'total_amount' => 0,
            'user_id' => $this->user->id,
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $so->id,
            'item_id' => $this->item->id,
            'uom_id' => $this->box->id,
            'requested_qty' => 1,
            'fulfilled_qty' => 0,
            'picked_qty' => 0,
            'unit_price' => 0,
        ]);

        return $so;
    }

    private function createPackingList(string $soNumber): PackingList
    {
        $so = $this->createSalesOrder($soNumber);
        $this->actingAs($this->user)->post(route('outbound.confirm', $so))->assertRedirect();

        $this->actingAs($this->user)->post(route('packing.store'), [
            'sales_order_id' => $so->id,
            'total_box' => 1,
            'lines' => [[
                'sales_order_item_id' => $so->items()->firstOrFail()->id,
                'qty' => 1,
            ]],
        ])->assertRedirect();

        return PackingList::where('sales_order_id', $so->id)->firstOrFail();
    }
}
