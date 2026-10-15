<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\NavPurchaseOrder;
use App\Models\NavPurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Services\BusinessCentralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NavPurchaseOrderSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'QA Sync',
            'email' => 'qa.sync@test.test',
            'password' => 'password',
            'role' => 'central',
        ]);
    }

    public function test_sync_pulls_purchase_orders_supplier_lines_and_item_master(): void
    {
        $this->fakeBusinessCentral([$this->apiOrder()]);

        $count = app(BusinessCentralService::class)->syncPurchaseOrders(10);

        $this->assertSame(1, $count);

        $po = NavPurchaseOrder::where('po_no', 'PO-2026-0007')->firstOrFail();
        $this->assertSame('PT Maju Jaya', $po->supplier_name);
        $this->assertSame(NavPurchaseOrder::STATUS_RELEASED, $po->status);
        $this->assertSame('2026-10-01', $po->order_date->toDateString());
        $this->assertSame('PO dari NAV', $po->notes);

        $supplier = Supplier::where('name', 'PT Maju Jaya')->firstOrFail();
        $this->assertSame('V001', $supplier->code);

        $this->assertCount(2, $po->items);

        $line = NavPurchaseOrderItem::where('po_id', $po->id)->where('sku', 'FG-001')->firstOrFail();
        $this->assertSame('Produk Jadi 1', $line->item_name);
        $this->assertSame('PCS', $line->uom_nav);
        $this->assertSame(24.0, (float) $line->nav_qty_pcs);

        $item = Item::where('sku', 'FG-001')->firstOrFail();
        $this->assertSame('Produk Jadi 1', $item->name);
        $this->assertTrue($item->is_active);
        $this->assertSame(Uom::where('code', 'PCS')->firstOrFail()->id, $item->base_uom_id);

        $this->assertTrue(Item::where('sku', 'FG-002')->exists());
        $this->assertTrue(Uom::where('code', 'DUS')->exists());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'login.microsoftonline.com')
                && $request['grant_type'] === 'client_credentials';
        });
        Http::assertSent(function ($request) {
            $url = urldecode($request->url());

            return str_contains($url, '/api/v2.0/companies(')
                && str_contains($url, 'purchaseOrders')
                && $request->hasHeader('Authorization', 'Bearer sync-token')
                && str_contains($url, '$expand=purchaseOrderLines')
                && str_contains($url, '$orderby=orderDate desc')
                && str_contains($url, '$top=10');
        });
    }

    public function test_resync_keeps_local_received_status_and_quantity(): void
    {
        $orders = [$this->apiOrder()];
        $this->fakeBusinessCentral(function () use (&$orders) {
            return $orders;
        });

        app(BusinessCentralService::class)->syncPurchaseOrders();

        $po = NavPurchaseOrder::where('po_no', 'PO-2026-0007')->firstOrFail();
        $po->update(['status' => NavPurchaseOrder::STATUS_PARTIAL]);
        $po->items()->where('sku', 'FG-001')->update([
            'received_qty_pcs' => 10,
            'status' => NavPurchaseOrder::STATUS_COMPLETED,
        ]);

        $orders = [$this->apiOrder([
            'status' => 'Open',
            'purchaseOrderLines' => [
                ['lineObjectNumber' => 'FG-001', 'description' => 'Produk Jadi 1', 'unitOfMeasureCode' => 'PCS', 'quantity' => 30],
            ],
        ])];

        $this->assertSame(1, app(BusinessCentralService::class)->syncPurchaseOrders());

        $po->refresh();
        $this->assertSame(NavPurchaseOrder::STATUS_PARTIAL, $po->status);

        $line = $po->items()->where('sku', 'FG-001')->firstOrFail();
        $this->assertSame(30.0, (float) $line->nav_qty_pcs);
        $this->assertSame(10.0, (float) $line->received_qty_pcs);
        $this->assertSame(NavPurchaseOrder::STATUS_COMPLETED, $line->status);
    }

    public function test_sync_route_requires_auth_and_toasts_result(): void
    {
        $this->fakeBusinessCentral([$this->apiOrder()]);

        $this->post(route('nav-po.sync'))->assertRedirect(route('login'));

        $this->actingAs($this->user)
            ->post(route('nav-po.sync'))
            ->assertRedirect()
            ->assertSessionHas('toast', '1 Purchase Order berhasil disinkronkan dari Microsoft Dynamics NAV');

        $this->assertTrue(NavPurchaseOrder::where('po_no', 'PO-2026-0007')->exists());

        $list = $this->actingAs($this->user)->get(route('nav-po.index'))->assertOk();
        $this->assertStringContainsString('Sinkronisasi dari NAV', $list->getContent());
    }

    public function test_sync_command_reports_synced_count(): void
    {
        $this->fakeBusinessCentral([$this->apiOrder(), $this->apiOrder(['number' => 'PO-2026-0008'])]);

        $this->artisan('nav:sync-po', ['--limit' => 5])
            ->expectsOutputToContain('2 Purchase Order berhasil disinkronkan dari Microsoft Dynamics NAV')
            ->assertSuccessful();

        $this->assertSame(2, NavPurchaseOrder::count());
    }

    /**
     * @param  array<int, array<string, mixed>>|callable(): array<int, array<string, mixed>>  $orders
     */
    private function fakeBusinessCentral(array|callable $orders): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'sync-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            'api.businesscentral.dynamics.com/*' => is_array($orders)
                ? Http::response(['value' => $orders])
                : fn ($request, $options) => Http::response(['value' => $orders($request, $options)]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function apiOrder(array $overrides = []): array
    {
        return array_merge([
            'id' => 'a1f1a1a1-0000-4000-8000-000000000001',
            'number' => 'PO-2026-0007',
            'orderDate' => '2026-10-01',
            'vendorNumber' => 'V001',
            'vendorName' => 'PT Maju Jaya',
            'status' => 'Released',
            'notes' => 'PO dari NAV',
            'purchaseOrderLines' => [
                [
                    'lineObjectNumber' => 'FG-001',
                    'description' => 'Produk Jadi 1',
                    'unitOfMeasureCode' => 'PCS',
                    'quantity' => 24,
                ],
                [
                    'lineObjectNumber' => 'FG-002',
                    'description' => 'Produk Jadi 2',
                    'unitOfMeasureCode' => 'DUS',
                    'quantity' => 5,
                ],
            ],
        ], $overrides);
    }
}
