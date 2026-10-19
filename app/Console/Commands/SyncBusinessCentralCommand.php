<?php

namespace App\Console\Commands;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemUomConversion;
use App\Models\Location;
use App\Models\Stock;
use App\Models\Uom;
use App\Models\Warehouse;
use App\Services\BusinessCentralService;
use Illuminate\Console\Command;

class SyncBusinessCentralCommand extends Command
{
    protected $signature = 'bc:sync {--limit} {--refresh}';

    protected $description = 'Sinkronisasi data dari Business Central API ke database WMS';

    protected BusinessCentralService $bc;

    public function handle(BusinessCentralService $bc): int
    {
        $this->info('=== Memulai sinkronisasi Business Central ===');

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        // Step 1: Sync UOMs
        $this->syncUoms($bc);

        // Step 2: Sync Categories
        $this->syncCategories($bc);

        // Step 3: Sync Customers
        $this->syncCustomers($bc);

        // Step 4: Sync Items
        $this->syncItems($bc, $limit);

        // Step 5: Sync Item UOM Conversions
        $this->syncItemUomConversions($bc);

        // Step 6: Initialize initial stock
        $this->syncInitialStock($bc);

        $this->info('=== Sinkronisasi selesai ===');

        return Command::SUCCESS;
    }

    protected function syncUoms(BusinessCentralService $bc): void
    {
        $this->info('Sinkronisasi UOMs...');

        // Ambil dari ItemCardOnly (Base_Unit_of_Measure)
        $uoms = $bc->get('ItemCardOnly');

        $uomCodes = [];
        foreach ($uoms as $uom) {
            $code = $uom['Base_Unit_of_Measure'] ?? null;
            if ($code && ! in_array($code, $uomCodes)) {
                $uomCodes[] = $code;
            }
        }

        // Ambil dari Item_Units_of_Measure_Excel
        $itemUnits = $bc->get('Item_Units_of_Measure_Excel');
        foreach ($itemUnits as $unit) {
            $code = $unit['Code'] ?? null;
            if ($code && ! in_array($code, $uomCodes)) {
                $uomCodes[] = $code;
            }
        }

        // Simpan UOMs ke database
        foreach ($uomCodes as $code) {
            if (! $code) {
                continue;
            }
            $existing = Uom::where('code', $code)->first();
            if (! $existing) {
                Uom::create(['code' => $code, 'name' => $code]);
                $this->info("  UOM dibuat: {$code}");
            }
        }

        $this->info('  Total UOMs: '.count($uomCodes));
    }

    protected function syncCategories(BusinessCentralService $bc): void
    {
        $this->info('Sinkronisasi Categories...');

        $items = $bc->get('ItemCardOnly');
        $categoryCodes = $items->pluck('Item_Category_Code')->unique()->filter()->toArray();

        foreach ($categoryCodes as $code) {
            if (! $code) {
                continue;
            }
            $existing = Category::where('code', $code)->first();
            if (! $existing) {
                Category::create(['code' => $code, 'name' => $code]);
                $this->info("  Category dibuat: {$code}");
            }
        }

        $this->info('  Total Categories: '.count($categoryCodes));
    }

    protected function syncCustomers(BusinessCentralService $bc): void
    {
        $this->info('Sinkronisasi Customers...');

        $customers = $bc->get('Customer');

        foreach ($customers as $customer) {
            $no = $customer['No_'] ?? null;
            if (! $no) {
                continue;
            }
            $existing = Customer::where('code', $no)->first();
            if (! $existing) {
                Customer::create([
                    'code' => $no,
                    'name' => $customer['Name'] ?? '',
                    'phone' => $customer['Phone_No_'] ?? '',
                    'address' => $customer['Address'] ?? '',
                    'email' => $customer['E_mail'] ?? null,
                    'city' => $customer['City'] ?? null,
                ]);
                $this->info("  Customer dibuat: {$no}");
            }
        }

        $this->info('  Total Customers: '.count($customers));
    }

    protected function syncItems(BusinessCentralService $bc, ?int $limit): void
    {
        $this->info('Sinkronisasi Items...');

        $query = $bc->get('ItemCardOnly');

        if ($limit) {
            $query = $query->take($limit);
        }

        $categories = Category::pluck('id', 'code')->toArray();
        $uoms = Uom::pluck('id', 'code')->toArray();

        $synced = 0;

        foreach ($query as $item) {
            $sku = $item['No_'] ?? null;
            if (! $sku) {
                continue;
            }

            $categoryCode = $item['Item_Category_Code'] ?? null;
            $categoryId = $categories[$categoryCode] ?? null;
            if (! $categoryId) {
                $category = Category::firstOrCreate(
                    ['code' => $categoryCode],
                    ['name' => $categoryCode ?? 'Tanpa kategori']
                );
                $categoryId = $category->id;
                $categories[$categoryCode] = $categoryId;
            }

            $baseUomCode = $item['Base_Unit_of_Measure'] ?? null;
            $baseUomId = $uoms[$baseUomCode] ?? null;
            if (! $baseUomId) {
                $uom = Uom::firstOrCreate(
                    ['code' => $baseUomCode],
                    ['name' => $baseUomCode ?? 'PCS']
                );
                $baseUomId = $uom->id;
                $uoms[$baseUomCode] = $baseUomId;
            }

            $existing = Item::where('sku', $sku)->first();
            if (! $existing) {
                Item::create([
                    'sku' => $sku,
                    'name' => $item['Description'] ?? '',
                    'category_id' => $categoryId,
                    'base_uom_id' => $baseUomId,
                    'min_stock' => 0,
                    'is_active' => true,
                    'description' => $item['Description'] ?? null,
                ]);
                $synced++;
                $this->info("  Item dibuat: {$sku} - {$item['Description']}");
            }
        }

        $this->info("  Total Items disinkronisasi: {$synced}");
    }

    protected function syncItemUomConversions(BusinessCentralService $bc): void
    {
        $this->info('Sinkronisasi Konversi UOM Item...');

        $itemConversions = $bc->get('Item_Units_of_Measure_Excel');

        $items = Item::pluck('id', 'sku')->toArray();
        $uomCodes = Uom::pluck('id', 'code')->toArray();

        $synced = 0;

        foreach ($itemConversions as $conv) {
            $itemSku = $conv['Item_No'] ?? null;
            $fromUomCode = $conv['Code'] ?? null;
            $qtyPerUnit = $conv['Qty_per_Unit_of_Measure'] ?? 1;

            $itemId = $items[$itemSku] ?? null;
            if (! $itemId) {
                $this->warn("  Item tidak ditemukan di BC: {$itemSku}, lewati konversi");

                continue;
            }

            $fromUomId = null;
            if ($fromUomCode && isset($uomCodes[$fromUomCode])) {
                $fromUomId = $uomCodes[$fromUomCode];
            } else {
                $fromUom = Uom::where('code', $fromUomCode)->first();
                if ($fromUom) {
                    $fromUomId = $fromUom->id;
                    $uomCodes[$fromUomCode] = $fromUom->id;
                }
            }

            // Base UOM dari item
            $item = Item::find($itemId);
            $toUomId = $item ? $item->base_uom_id : null;

            if (! $toUomId) {
                $this->warn("  Base UOM tidak ditemukan untuk item {$itemSku}, lewati konversi");

                continue;
            }

            $existing = ItemUomConversion::where('item_id', $itemId)
                ->where('from_uom_id', $fromUomId)
                ->where('to_uom_id', $toUomId)
                ->first();

            if (! $existing) {
                ItemUomConversion::create([
                    'item_id' => $itemId,
                    'from_uom_id' => $fromUomId,
                    'to_uom_id' => $toUomId,
                    'multiplier' => (float) $qtyPerUnit,
                ]);
                $synced++;
                $baseUomCode = $item->base_uom ? $item->base_uom->code : 'null';
                $this->info("  Konversi dibuat: {$itemSku} {$fromUomCode} -> {$baseUomCode}");
            }
        }

        $this->info("  Total Konversi UOM disinkronisasi: {$synced}");
    }

    protected function syncInitialStock(BusinessCentralService $bc): void
    {
        $this->info('Inisialisasi stok awal...');

        $items = Item::where('is_active', true)->get();

        foreach ($items as $item) {
            // Cek apakah stok sudah ada untuk branch/warehouse/default
            $branch = Branch::where('is_central', true)->first() ?? Branch::first();
            $warehouse = $branch ? Warehouse::where('branch_id', $branch->id)->where('is_default', true)->first() : null;
            $location = $warehouse ? Location::where('warehouse_id', $warehouse->id)->where('is_active', true)->first() : null;

            if (! $branch || ! $warehouse || ! $location) {
                $this->warn("  Lewati item {$item->sku}: branch/warehouse/location tidak tersedia");

                continue;
            }

            $existingStock = $item->stocks()->where('branch_id', $branch->id)
                ->where('warehouse_id', $warehouse->id)
                ->where('location_id', $location->id)
                ->where('uom_id', $item->base_uom_id)
                ->first();

            if (! $existingStock) {
                // Pastikan ada default batch untuk item ini (karena stocks.batch_id NOT NULL)
                $batch = Batch::firstOrCreate(
                    ['item_id' => $item->id, 'batch_number' => 'INIT-'.$item->sku],
                    ['expired_date' => null, 'cost_price' => 0]
                );

                // Buat stok awal 0, nanti bisa diupdate via operations
                Stock::create([
                    'branch_id' => $branch->id,
                    'warehouse_id' => $warehouse->id,
                    'location_id' => $location->id,
                    'item_id' => $item->id,
                    'batch_id' => $batch->id,
                    'uom_id' => $item->base_uom_id,
                    'quantity_pcs' => 0,
                ]);
                $this->info("  Stock awal dibuat: {$item->sku} di {$branch->code}/{$warehouse->code}/{$location->code}");
            }
        }

        $this->info('  Inisialisasi stok selesai');
    }
}
