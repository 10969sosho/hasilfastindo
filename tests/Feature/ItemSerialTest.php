<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\ItemSerial;
use App\Models\ItemUomConversion;
use App\Models\Location;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemSerialTest extends TestCase
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
            'name' => 'QA Serial',
            'email' => 'qa.serial@test.test',
            'password' => 'password',
            'role' => 'central',
        ]);
    }

    public function test_receiving_generates_serials_and_nav_diff_note(): void
    {
        $response = $this->actingAs($this->user)->post(route('inbound.store'), [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->bin->id,
            'po_no' => 'PO-NAV-001',
            'received_at' => now()->toDateString(),
            'notes' => 'Dokumen dari NAV',
            'items' => [
                ['item_id' => $this->item->id, 'received_uom_id' => $this->box->id, 'received_qty' => 2, 'nav_qty' => 100, 'target_bin_id' => $this->bin->id],
                ['item_id' => $this->item->id, 'received_uom_id' => $this->pcs->id, 'received_qty' => 1, 'target_bin_id' => $this->bin->id],
            ],
        ]);

        $response->assertRedirect();
        $gr = GoodsReceipt::firstOrFail();

        $this->assertSame('PO-NAV-001', $gr->po_no);
        $this->assertStringContainsString('Selisih NAV: ABC123 NAV 100 PCS vs fisik 101 PCS (+1)', (string) $gr->notes);
        $this->assertSame(1, $gr->items()->whereNotNull('nav_qty')->count());
        $this->assertSame(100.0, (float) $gr->items()->whereNotNull('nav_qty')->value('nav_qty'));

        $this->actingAs($this->user)->get(route('inbound.show', $gr))
            ->assertOk()
            ->assertSee('Selisih', false);

        $this->actingAs($this->user)->post(route('inbound.receive', $gr))->assertRedirect();
        $this->assertSame('received', $gr->fresh()->status);

        $doc = $gr->doc_number;
        $serials = ItemSerial::orderBy('kode_uom_serialno')->get();

        $this->assertCount(3, $serials);
        $this->assertEqualsCanonicalizing([
            "ABC123-BOX-{$doc}-001",
            "ABC123-BOX-{$doc}-002",
            "ABC123-PCS-{$doc}-001",
        ], $serials->pluck('kode_uom_serialno')->all());

        $boxSerial = ItemSerial::find("ABC123-BOX-{$doc}-001");
        $this->assertSame(1, $boxSerial->qty_uom_in);
        $this->assertSame(50.0, $boxSerial->qty_smallest_unit_in);
        $this->assertSame('BOX', $boxSerial->uom);
        $this->assertSame($doc, $boxSerial->receive_no);
        $this->assertSame('PO-NAV-001', $boxSerial->po_no);
        $this->assertSame('PT Contoh', $boxSerial->supplier);
        $this->assertSame('BIN-A1', $boxSerial->bin_no);
        $this->assertSame($this->bin->id, $boxSerial->location_id);
        $this->assertSame($this->user->id, $boxSerial->input_receive_by);
        $this->assertNotNull($boxSerial->input_receive_date_time);
        $this->assertSame(0, $boxSerial->qty_uom_out);
        $this->assertNull($boxSerial->pick_no);

        $pcsSerial = ItemSerial::find("ABC123-PCS-{$doc}-001");
        $this->assertSame(1.0, $pcsSerial->qty_smallest_unit_in);
        $this->assertSame('PCS', $pcsSerial->uom);

        $this->assertSame(0, ItemSerial::generateForReceipt($gr->fresh()));
        $this->assertCount(3, ItemSerial::all());
    }

    public function test_scan_lookup_returns_serial_detail_and_picking_updates_record(): void
    {
        $gr = $this->receiveReceipt();
        $serial = ItemSerial::orderBy('kode_uom_serialno')->firstOrFail();

        $this->actingAs($this->user)->get(route('inbound.lookup', ['barcode' => $serial->kode_uom_serialno]))
            ->assertOk()
            ->assertJsonPath('payload.type', 'serial')
            ->assertJsonPath('payload.sku', 'ABC123')
            ->assertJsonPath('payload.bin_no', 'BIN-A1')
            ->assertJsonPath('payload.receive_no', $gr->doc_number)
            ->assertJsonPath('payload.picked', false);

        $this->actingAs($this->user)->post(route('mobile.lookup'), [
            'type' => 'scanin',
            'barcode' => $serial->kode_uom_serialno,
        ])
            ->assertOk()
            ->assertJsonPath('payload.type', 'serial')
            ->assertJsonPath('payload.item', 'Item A');

        $this->actingAs($this->user)->get(route('inbound.print', $gr))
            ->assertOk()
            ->assertSee($serial->kode_uom_serialno, false);

        $this->actingAs($this->user)->get(route('inbound.print', ['inbound' => $gr, 'sku' => $serial->sku]))
            ->assertOk()
            ->assertSee($serial->kode_uom_serialno, false);

        $this->actingAs($this->user)->get(route('inbound.print', ['inbound' => $gr, 'serial' => $serial->kode_uom_serialno]))
            ->assertOk()
            ->assertSee($serial->kode_uom_serialno, false);

        $this->actingAs($this->user)->get(route('inbound.print', ['inbound' => $gr, 'sku' => 'TIDAK-ADA']))
            ->assertOk()
            ->assertSee('Tidak ada serial untuk filter ini', false);

        $so = $this->createSalesOrder();

        // Picking hanya setelah konfirmasi admin.
        $this->actingAs($this->user)->post(route('outbound.pick', $so), [
            'barcode' => $serial->kode_uom_serialno,
        ])->assertStatus(422);

        $this->actingAs($this->user)->post(route('outbound.confirm', $so))->assertRedirect();
        $this->assertSame('confirmed', $so->fresh()->status);

        $this->actingAs($this->user)->post(route('outbound.pick', $so), [
            'barcode' => $serial->kode_uom_serialno,
        ])
            ->assertOk()
            ->assertJsonPath('payload.picked', true)
            ->assertJsonPath('payload.so_no', $so->so_number);

        $serial->refresh();
        $this->assertSame(1, $serial->qty_uom_out);
        $this->assertSame($serial->qty_smallest_unit_in, $serial->qty_smallest_unit_out);
        $this->assertSame('PK-'.$so->so_number, $serial->pick_no);
        $this->assertSame($so->so_number, $serial->so_no);
        $this->assertSame($this->user->id, $serial->input_pick_by);
        $this->assertNotNull($serial->input_pick_date_time);

        $this->actingAs($this->user)->post(route('outbound.pick', $so), [
            'barcode' => $serial->kode_uom_serialno,
        ])
            ->assertStatus(422)
            ->assertJsonPath('payload.picked', true);

        $otherItem = Item::create(['sku' => 'ZZZ999', 'name' => 'Item Lain', 'category_id' => $this->item->category_id, 'base_uom_id' => $this->pcs->id, 'is_active' => true]);
        $other = $this->createSalesOrder('SO-202610-9999', $otherItem);
        $this->actingAs($this->user)->post(route('outbound.confirm', $other))->assertRedirect();

        $this->actingAs($this->user)->post(route('outbound.pick', $other), [
            'barcode' => $serial->kode_uom_serialno,
        ])
            ->assertStatus(422)
            ->assertJsonPath('payload.picked', true);

        $this->actingAs($this->user)->post(route('outbound.pick', $other), [
            'barcode' => 'TIDAK-ADA-000',
        ])->assertStatus(404);
    }

    public function test_related_pages_render(): void
    {
        $gr = $this->receiveReceipt();
        $so = $this->createSalesOrder();

        $this->actingAs($this->user)->get(route('inbound.create'))
            ->assertOk()
            ->assertSee('Qty NAV', false)
            ->assertSee('navSummary', false);

        $this->actingAs($this->user)->get(route('inbound.show', $gr))
            ->assertOk()
            ->assertSee('Identitas Serial Unit', false);

        $this->actingAs($this->user)->get(route('mobile.index'))
            ->assertOk()
            ->assertSee('serialResult', false);

        $this->actingAs($this->user)->get(route('outbound.show', $so))
            ->assertOk()
            ->assertSee('pickBarcode', false);
    }

    private function receiveReceipt(): GoodsReceipt
    {
        $this->actingAs($this->user)->post(route('inbound.store'), [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->bin->id,
            'received_at' => now()->toDateString(),
            'items' => [
                ['item_id' => $this->item->id, 'received_uom_id' => $this->box->id, 'received_qty' => 2, 'nav_qty' => 100, 'target_bin_id' => $this->bin->id],
            ],
        ]);

        $gr = GoodsReceipt::firstOrFail();

        $this->actingAs($this->user)->post(route('inbound.receive', $gr))->assertRedirect();

        return $gr;
    }

    private function createSalesOrder(string $number = 'SO-202610-0001', ?Item $item = null): SalesOrder
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
            'item_id' => ($item ?? $this->item)->id,
            'uom_id' => $this->box->id,
            'requested_qty' => 1,
            'fulfilled_qty' => 0,
            'picked_qty' => 0,
            'unit_price' => 0,
        ]);

        return $so;
    }
}
