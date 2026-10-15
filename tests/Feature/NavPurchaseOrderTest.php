<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\ItemSerial;
use App\Models\ItemUomConversion;
use App\Models\Location;
use App\Models\NavPurchaseOrder;
use App\Models\NavPurchaseOrderItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavPurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Warehouse $warehouse;

    private Location $bin;

    private Uom $pcs;

    private Uom $dus;

    private Item $item;

    private Item $secondItem;

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
        $this->dus = Uom::create(['code' => 'DUS', 'name' => 'Dus']);
        $this->item = Item::create(['sku' => 'ABC123', 'name' => 'Item A', 'category_id' => $category->id, 'base_uom_id' => $this->pcs->id, 'is_active' => true]);
        $this->secondItem = Item::create(['sku' => 'XYZ789', 'name' => 'Item B', 'category_id' => $category->id, 'base_uom_id' => $this->pcs->id, 'is_active' => true]);
        ItemUomConversion::create([
            'item_id' => $this->item->id,
            'from_uom_id' => $this->dus->id,
            'to_uom_id' => $this->pcs->id,
            'multiplier' => 50,
        ]);
        $this->supplier = Supplier::create(['code' => 'SUP1', 'name' => 'PT Contoh']);
        $this->user = User::create([
            'name' => 'QA Nav PO',
            'email' => 'qa.navpo@test.test',
            'password' => 'password',
            'role' => 'central',
        ]);
    }

    public function test_nav_po_list_detail_and_menu_render(): void
    {
        $po = $this->makeNavPo();

        $this->get('/nav-purchase-orders')->assertRedirect(route('login'));

        $this->actingAs($this->user)->get(route('nav-po.index'))
            ->assertOk()
            ->assertSee('Daftar PO NAV', false)
            ->assertSee($po->po_no, false)
            ->assertSee($po->supplier_name, false)
            ->assertSee('Proses Penerimaan', false);

        $this->actingAs($this->user)->get(route('nav-po.show', $po))
            ->assertOk()
            ->assertSee($po->po_no, false)
            ->assertSee($this->item->sku, false)
            ->assertSee('Qty NAV (PCS)', false);

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('/nav-purchase-orders', false);
    }

    public function test_process_action_prefills_inbound_form_from_nav_po(): void
    {
        $po = $this->makeNavPo();

        // Tombol "Proses Penerimaan" mengarah ke form penerimaan dengan param nav_po_id.
        $list = $this->actingAs($this->user)->get(route('nav-po.index'))->assertOk();
        $this->assertStringContainsString(
            'inbound/create?nav_po_id='.$po->id,
            $list->getContent()
        );

        $form = $this->actingAs($this->user)->get(route('inbound.create', ['nav_po_id' => $po->id]))
            ->assertOk()
            ->assertSee('value="'.$po->po_no.'"', false)
            ->assertSee('name="items[0][item_id]"', false)
            ->assertSee('value="'.$this->item->id.'"', false)
            ->assertSee('name="items[0][received_qty]" value="2"', false)
            ->assertSee('name="items[0][total_pcs]" value="100"', false)
            ->assertSee('name="items[0][nav_qty]" value="100"', false)
            ->assertSee($this->supplier->name, false);

        // Form biasa (tanpa parameter) tetap menawarkan pemilihan PO NAV.
        $this->actingAs($this->user)->get(route('inbound.create'))
            ->assertOk()
            ->assertSee('Tarik dari PO NAV', false)
            ->assertSee($po->po_no, false);

        // Alternatif: prefill juga bisa dipanggil lewat ?po_no=.
        $this->actingAs($this->user)->get(route('inbound.create', ['po_no' => $po->po_no]))
            ->assertOk()
            ->assertSee('value="'.$po->po_no.'"', false)
            ->assertSee('name="items[0][nav_qty]" value="100"', false);
    }

    public function test_receiving_from_nav_po_updates_status_stock_and_serials(): void
    {
        $po = $this->makeNavPo();

        $this->actingAs($this->user)->post(route('inbound.store'), [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->bin->id,
            'po_no' => $po->po_no,
            'received_at' => now()->toDateString(),
            'items' => [[
                'item_id' => $this->item->id,
                'received_uom_id' => $this->dus->id,
                'received_qty' => 2,
                'total_pcs' => 100,
                'nav_qty' => 100,
                'target_bin_id' => $this->bin->id,
            ]],
        ])->assertRedirect();

        $gr = GoodsReceipt::firstOrFail();
        $this->assertSame($po->po_no, $gr->po_no);

        $this->actingAs($this->user)->post(route('inbound.receive', $gr))->assertRedirect();

        $doc = $gr->doc_number;
        $serials = ItemSerial::orderBy('kode_uom_serialno')->get();
        $this->assertCount(2, $serials);
        $this->assertEqualsCanonicalizing([
            "ABC123-DUS-{$doc}-001",
            "ABC123-DUS-{$doc}-002",
        ], $serials->pluck('kode_uom_serialno')->all());

        $this->assertSame(100.0, (float) Stock::where('item_id', $this->item->id)->sum('quantity_pcs'));
        $this->assertSame(100.0, (float) StockMovement::where('reference_no', $gr->doc_number)->sum('qty_in'));

        $po->refresh();
        $line = $po->items()->firstOrFail();

        $this->assertSame(NavPurchaseOrder::STATUS_COMPLETED, $po->status);
        $this->assertSame(100.0, (float) $line->received_qty_pcs);
        $this->assertSame(NavPurchaseOrder::STATUS_COMPLETED, $line->status);
    }

    public function test_partial_receiving_marks_nav_po_partially_received(): void
    {
        $po = $this->makeNavPo([
            ['sku' => 'ABC123', 'name' => 'Item A', 'uom' => 'DUS', 'nav' => 100, 'received' => 0],
            ['sku' => 'XYZ789', 'name' => 'Item B', 'uom' => 'PCS', 'nav' => 500, 'received' => 0],
        ]);

        $this->actingAs($this->user)->post(route('inbound.store'), [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'destination_location_id' => $this->bin->id,
            'po_no' => $po->po_no,
            'received_at' => now()->toDateString(),
            'items' => [[
                'item_id' => $this->item->id,
                'received_uom_id' => $this->dus->id,
                'received_qty' => 2,
                'total_pcs' => 100,
                'nav_qty' => 100,
                'target_bin_id' => $this->bin->id,
            ]],
        ])->assertRedirect();

        $gr = GoodsReceipt::firstOrFail();
        $this->actingAs($this->user)->post(route('inbound.receive', $gr))->assertRedirect();

        $po->refresh();
        $this->assertSame(NavPurchaseOrder::STATUS_PARTIAL, $po->status);

        $lineA = $po->items()->where('sku', 'ABC123')->firstOrFail();
        $lineB = $po->items()->where('sku', 'XYZ789')->firstOrFail();

        $this->assertSame(100.0, (float) $lineA->received_qty_pcs);
        $this->assertSame(NavPurchaseOrder::STATUS_COMPLETED, $lineA->status);
        $this->assertSame(0.0, (float) $lineB->received_qty_pcs);
        $this->assertSame(NavPurchaseOrder::STATUS_OPEN, $lineB->status);
    }

    /**
     * @param  array<int, array{sku: string, name: string, uom: string, nav: float, received: float}>  $lines
     */
    private function makeNavPo(array $lines = []): NavPurchaseOrder
    {
        $po = NavPurchaseOrder::create([
            'po_no' => 'PO-NAV-2026-0142',
            'order_date' => now()->subDay()->toDateString(),
            'supplier_name' => $this->supplier->name,
            'status' => NavPurchaseOrder::STATUS_RELEASED,
            'notes' => 'PO NAV untuk pengujian integrasi penerimaan.',
        ]);

        $lines = $lines ?: [['sku' => 'ABC123', 'name' => 'Item A', 'uom' => 'DUS', 'nav' => 100, 'received' => 0]];

        foreach ($lines as $line) {
            NavPurchaseOrderItem::create([
                'po_id' => $po->id,
                'sku' => $line['sku'],
                'item_name' => $line['name'],
                'uom_nav' => $line['uom'],
                'nav_qty_pcs' => $line['nav'],
                'received_qty_pcs' => $line['received'],
                'status' => NavPurchaseOrder::STATUS_OPEN,
            ]);
        }

        return $po;
    }
}
