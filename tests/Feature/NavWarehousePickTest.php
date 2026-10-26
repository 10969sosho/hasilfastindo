<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\NavWarehousePick;
use App\Models\NavWarehousePickItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavWarehousePickTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['code' => 'BR1', 'name' => 'Cabang Utama']);
        $this->user = User::create([
            'name' => 'QA Pick NAV',
            'email' => 'qa.pick@test.test',
            'password' => 'password',
            'role' => 'central',
        ]);
    }

    public function test_user_can_view_nav_picks_index(): void
    {
        $pick = NavWarehousePick::create([
            'pick_no' => 'W-P/H0/2605/08317',
            'type' => 'Pick',
            'location_code' => 'PST-MARGO',
            'source_so_no' => 'SOA/H0/2605/00066',
            'whse_shipment_no' => 'W-S/H0/2605/07818',
            'destination_no' => 'C00123',
            'status' => 'open',
        ]);

        NavWarehousePickItem::create([
            'nav_pick_id' => $pick->id,
            'action_type' => 'Take',
            'line_no' => 10000,
            'sku' => '04HMBNC-000077',
            'item_name' => 'TEST ITEM',
            'quantity' => 1200,
            'qty_base' => 1200,
            'bin_code' => 'A-01-01',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->get(route('nav-picks.index'));

        $response->assertOk();
        $response->assertSee('W-P/H0/2605/08317');
        $response->assertSee('SOA/H0/2605/00066');
    }

    public function test_user_can_view_nav_pick_detail(): void
    {
        $pick = NavWarehousePick::create([
            'pick_no' => 'W-P/H0/2605/08317',
            'type' => 'Pick',
            'location_code' => 'PST-MARGO',
            'source_so_no' => 'SOA/H0/2605/00066',
            'status' => 'open',
        ]);

        NavWarehousePickItem::create([
            'nav_pick_id' => $pick->id,
            'action_type' => 'Take',
            'line_no' => 10000,
            'sku' => '04HMBNC-000077',
            'item_name' => 'TEST ITEM',
            'quantity' => 1200,
            'qty_base' => 1200,
            'bin_code' => 'A-01-01',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->get(route('nav-picks.show', $pick));

        $response->assertOk();
        $response->assertSee('W-P/H0/2605/08317');
        $response->assertSee('04HMBNC-000077');
        $response->assertSee('A-01-01');
    }
}
