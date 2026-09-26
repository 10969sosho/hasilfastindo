<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryTrack;
use App\Models\Driver;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Item;
use App\Models\ItemUomConversion;
use App\Models\Location;
use App\Models\PackingList;
use App\Models\PackingListBox;
use App\Models\PackingListItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\StockRepack;
use App\Models\StockRepackItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->resetTables();

        $uom = $this->seedUoms();
        $cats = $this->seedCategories();
        $branches = $this->seedBranches();
        $locations = $this->seedWarehouseAndLocations($branches);
        $this->seedPartners();
        $fleet = $this->seedFleet();
        $items = $this->seedItems($uom, $cats);
        $stocks = $this->seedStocks($items, $branches, $locations, $uom);
        $users = $this->seedUsers($branches);
        $this->seedTransactions($items, $branches, $locations, $uom, $stocks, $users, $fleet);
    }

    /**
     * Bersihkan data agar seeder bisa dijalankan ulang tanpa konflik unique key.
     */
    private function resetTables(): void
    {
        $tables = [
            'stock_opname_items', 'stock_opnames',
            'delivery_tracks', 'delivery_items', 'deliveries',
            'packing_list_items', 'packing_list_boxes', 'packing_lists',
            'stock_repack_items', 'stock_repacks',
            'stock_transfer_items', 'stock_transfers',
            'sales_order_items', 'sales_orders',
            'goods_receipt_items', 'goods_receipts',
            'stock_movements', 'stocks',
            'item_uom_conversions', 'batches', 'items', 'categories', 'uoms',
            'locations', 'warehouses',
            'vehicles', 'drivers', 'customers', 'suppliers',
            'users', 'branches',
        ];

        foreach ($tables as $table) {
            DB::table($table)->delete();
        }
    }

    private function seedUoms(): array
    {
        $rows = [
            ['code' => 'PCS', 'name' => 'Pieces'],
            ['code' => 'DUS', 'name' => 'Dus'],
            ['code' => 'KRT', 'name' => 'Karton'],
            ['code' => 'BTL', 'name' => 'Botol'],
            ['code' => 'PACK', 'name' => 'Pack'],
            ['code' => 'KG', 'name' => 'Kilogram'],
        ];

        $map = [];
        foreach ($rows as $row) {
            $u = Uom::create($row);
            $map[$u->code] = $u;
        }

        return $map;
    }

    private function seedCategories(): array
    {
        $rows = [
            ['code' => 'FST', 'name' => 'Fastener'],
            ['code' => 'PRT', 'name' => 'Spare Parts'],
            ['code' => 'PKG', 'name' => 'Kemasan'],
            ['code' => 'LBM', 'name' => 'Pelumas & Kimia'],
        ];

        $map = [];
        foreach ($rows as $row) {
            $c = Category::create($row);
            $map[$c->code] = $c;
        }

        return $map;
    }

    private function seedBranches(): array
    {
        $rows = [
            ['code' => 'SBY', 'name' => 'Cabang Surabaya', 'address' => 'Jl. Rungkut Industri III No. 12, Surabaya', 'phone' => '031-8432100', 'is_central' => false],
            ['code' => 'JKT', 'name' => 'Cabang Jakarta (Pusat)', 'address' => 'Jl. Industri Pulogadung Kav. 45, Jakarta Timur', 'phone' => '021-46801234', 'is_central' => true],
            ['code' => 'SMG', 'name' => 'Cabang Semarang', 'address' => 'Jl. Industri Ronggowarsito No. 8, Semarang', 'phone' => '024-6712345', 'is_central' => false],
        ];

        $map = [];
        foreach ($rows as $row) {
            $b = Branch::create($row);
            $map[$b->code] = $b;
        }

        return $map;
    }

    /**
     * @return array<string, array<int, Location>>
     */
    private function seedWarehouseAndLocations(array $branches): array
    {
        $locations = [];

        foreach ($branches as $code => $branch) {
            $warehouse = Warehouse::create([
                'branch_id' => $branch->id,
                'code' => "WH-{$code}-01",
                'name' => "Gudang Utama {$branch->name}",
                'is_default' => true,
            ]);

            $list = [];
            foreach (['A', 'B'] as $area) {
                foreach ([1, 2] as $rack) {
                    foreach (['01', '02'] as $shelf) {
                        $list[] = Location::create([
                            'warehouse_id' => $warehouse->id,
                            'code' => "BIN-{$code}-{$area}{$rack}-{$shelf}",
                            'area' => $area,
                            'rack' => (string) $rack,
                            'shelf' => $shelf,
                            'bin' => $shelf,
                            'is_active' => true,
                        ]);
                    }
                }
            }

            $locations[$branch->id] = $list;
        }

        return $locations;
    }

    private function seedPartners(): void
    {
        $suppliers = [
            ['code' => 'SUP-001', 'name' => 'PT Baja Nusantara Steel', 'phone' => '031-5678900', 'email' => 'sales@bajanusantara.co.id', 'address' => 'Kawasan Industri Wonokromo, Surabaya'],
            ['code' => 'SUP-002', 'name' => 'CV Anugerah Fastener', 'phone' => '021-8976543', 'email' => 'info@anugerahfastener.com', 'address' => 'Jl. Pergudangan Mutiara, Jakarta'],
            ['code' => 'SUP-003', 'name' => 'PT Sekrup Jaya Abadi', 'phone' => '024-7654321', 'email' => 'order@sekrupjaya.id', 'address' => 'Jl. Industri Tembalang, Semarang'],
            ['code' => 'SUP-004', 'name' => 'PT Sinar Mur Terkemuka', 'phone' => '031-7765432', 'email' => 'cs@sinarmur.co.id', 'address' => 'Jl. Kenanga Industri, Surabaya'],
        ];

        foreach ($suppliers as $row) {
            Supplier::create($row);
        }

        $customers = [
            ['code' => 'CUS-001', 'name' => 'Toko Bangunan Mandiri', 'phone' => '081234567890', 'email' => 'mandiri@gmail.com', 'address' => 'Jl. Margorejo No. 33, Surabaya', 'city' => 'Surabaya'],
            ['code' => 'CUS-002', 'name' => 'PT Sumber Bangun Persada', 'phone' => '081398765432', 'email' => 'procurement@sbbangun.co.id', 'address' => 'Jl. TB Simatupang Kav. 22, Jakarta', 'city' => 'Jakarta'],
            ['code' => 'CUS-003', 'name' => 'Bengkel Jaya Motor', 'phone' => '085711223344', 'email' => 'jayamotor@gmail.com', 'address' => 'Jl. Pemuda No. 120, Semarang', 'city' => 'Semarang'],
            ['code' => 'CUS-004', 'name' => 'Hardware Sejahtera', 'phone' => '082144556677', 'email' => 'sejahtera@hardware.id', 'address' => 'Jl. Dharmahusada No. 5, Surabaya', 'city' => 'Surabaya'],
            ['code' => 'CUS-005', 'name' => 'PT Kreasi Mebel Utama', 'phone' => '081533445566', 'email' => 'purchasing@kreasimebel.com', 'address' => 'Jl. Raya Bekasi Km. 21, Jakarta', 'city' => 'Jakarta'],
            ['code' => 'CUS-006', 'name' => 'Toko Sinar Teknik', 'phone' => '085622334455', 'email' => 'sinarteknik@gmail.com', 'address' => 'Jl. Gajahmada No. 77, Semarang', 'city' => 'Semarang'],
        ];

        foreach ($customers as $row) {
            Customer::create($row);
        }
    }

    private function seedFleet(): array
    {
        $drivers = [
            ['name' => 'Agus Setiawan', 'phone' => '081211112222', 'license_number' => 'B-1234567-SBY', 'role' => 'driver', 'is_active' => true],
            ['name' => 'Budi Hartono', 'phone' => '081333334444', 'license_number' => 'B-7654321-JKT', 'role' => 'driver', 'is_active' => true],
            ['name' => 'Candra Wijaya', 'phone' => '081555556666', 'license_number' => 'B-1122334-SMG', 'role' => 'helper', 'is_active' => true],
            ['name' => 'Dedi Kurniawan', 'phone' => '081777778888', 'license_number' => 'B-4455667-SBY', 'role' => 'helper', 'is_active' => true],
        ];

        $d = [];
        foreach ($drivers as $row) {
            $person = Driver::create($row);
            $d[] = $person;
        }

        $vehicles = [
            ['plate_number' => 'L 8812 AB', 'vehicle_name' => 'Truk Colt Diesel', 'type' => 'Truk', 'capacity_kg' => 8000, 'driver_id' => $d[0]->id, 'helper_id' => $d[2]->id, 'driver_name' => $d[0]->name, 'helper_name' => $d[2]->name, 'status' => 'in_use'],
            ['plate_number' => 'L 9021 CD', 'vehicle_name' => 'Gran Max Box', 'type' => 'Gran Max', 'capacity_kg' => 1500, 'driver_id' => $d[1]->id, 'helper_id' => $d[3]->id, 'driver_name' => $d[1]->name, 'helper_name' => $d[3]->name, 'status' => 'available'],
            ['plate_number' => 'B 1234 EF', 'vehicle_name' => 'Gran Max Blind Van', 'type' => 'Gran Max', 'capacity_kg' => 1200, 'driver_id' => null, 'helper_id' => null, 'driver_name' => null, 'helper_name' => null, 'status' => 'available'],
            ['plate_number' => 'H 4455 GH', 'vehicle_name' => 'Truk Fuso', 'type' => 'Truk', 'capacity_kg' => 12000, 'driver_id' => null, 'helper_id' => null, 'driver_name' => null, 'helper_name' => null, 'status' => 'maintenance'],
        ];

        $v = [];
        foreach ($vehicles as $row) {
            $v[] = Vehicle::create($row);
        }

        return ['drivers' => $d, 'vehicles' => $v];
    }

    /**
     * @return array<int, Item>
     */
    private function seedItems(array $uom, array $cats): array
    {
        $families = [
            ['code' => 'HXB', 'name' => 'Baut Hex', 'cat' => 'FST'],
            ['code' => 'HLB', 'name' => 'Baut Lepas', 'cat' => 'FST'],
            ['code' => 'SKP', 'name' => 'Skrup', 'cat' => 'FST'],
            ['code' => 'MUR', 'name' => 'Mur', 'cat' => 'FST'],
            ['code' => 'WSR', 'name' => 'Ring', 'cat' => 'FST'],
            ['code' => 'NLS', 'name' => 'Nut Lock', 'cat' => 'FST'],
            ['code' => 'RVT', 'name' => 'Rivet', 'cat' => 'PRT'],
            ['code' => 'BSH', 'name' => 'Bushing', 'cat' => 'PRT'],
            ['code' => 'PLG', 'name' => 'Baut Panel', 'cat' => 'PKG'],
            ['code' => 'BOL', 'name' => 'Bolt Drain', 'cat' => 'PKG'],
        ];

        $sizes = ['M6', 'M8', 'M10', 'M12', 'M14', 'M16', 'M18', 'M20', 'M22', 'M24'];

        $items = [];
        $seq = 0;

        foreach ($families as $fi => $family) {
            foreach ($sizes as $si => $size) {
                $seq++;
                $sku = "HF-{$family['code']}-{$size}";
                $cost = 400 + ($fi * 90) + ($si * 45);

                $item = Item::create([
                    'sku' => $sku,
                    'name' => "{$family['name']} {$size}",
                    'category_id' => $cats[$family['cat']]->id,
                    'base_uom_id' => $uom['PCS']->id,
                    'min_stock' => ($seq % 5) * 120,
                    'cost_price' => $cost,
                    'sell_price' => round($cost * 1.35, 2),
                    'barcode' => sprintf('HF%06d', $seq),
                    'description' => "{$family['name']} ukuran {$size}, material baja karbon, finishing zinc.",
                    'is_active' => true,
                ]);

                foreach ([['KRT', 'PCS', 24], ['DUS', 'PCS', 12], ['KRT', 'DUS', 2]] as [$from, $to, $multiplier]) {
                    ItemUomConversion::create([
                        'item_id' => $item->id,
                        'from_uom_id' => $uom[$from]->id,
                        'to_uom_id' => $uom[$to]->id,
                        'multiplier' => $multiplier,
                    ]);
                }

                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return array<int, array{stock: Stock, batch: Batch}>
     */
    private function seedStocks(array $items, array $branches, array $locations, array $uom): array
    {
        $branchIds = [$branches['SBY']->id, $branches['JKT']->id, $branches['SMG']->id];
        $stocks = [];

        foreach ($items as $i => $item) {
            $primaryBranch = $branchIds[$i % 3];
            $secondaryBranch = $branchIds[($i + 1) % 3];

            $batchA = Batch::create([
                'item_id' => $item->id,
                'batch_number' => str_replace('HF-', 'B26A-', $item->sku),
                'expired_date' => null,
                'cost_price' => $item->cost_price,
                'received_at' => now()->subDays(90 - ($i % 40))->startOfDay(),
            ]);

            $batchB = Batch::create([
                'item_id' => $item->id,
                'batch_number' => str_replace('HF-', 'B26B-', $item->sku),
                'expired_date' => null,
                'cost_price' => $item->cost_price,
                'received_at' => now()->subDays(30 - ($i % 20))->startOfDay(),
            ]);

            $rows = [
                ['branch' => $primaryBranch, 'batch' => $batchA, 'qty' => (($i % 6) + 1) * 120, 'offset' => 90],
            ];

            if ($i % 2 === 0) {
                $rows[] = ['branch' => $secondaryBranch, 'batch' => $batchB, 'qty' => (($i % 4) + 1) * 48, 'offset' => 30];
            }

            foreach ($rows as $row) {
                $binList = $locations[$row['branch']];
                $bin = $binList[$i % count($binList)];

                $stock = Stock::create([
                    'branch_id' => $row['branch'],
                    'warehouse_id' => $bin->warehouse_id,
                    'location_id' => $bin->id,
                    'item_id' => $item->id,
                    'batch_id' => $row['batch']->id,
                    'uom_id' => $uom['PCS']->id,
                    'quantity_pcs' => $row['qty'],
                    'original_barcode' => $item->barcode,
                    'received_at' => $row['batch']->received_at,
                ]);

                StockMovement::create([
                    'date' => $row['batch']->received_at->toDateString(),
                    'branch_id' => $row['branch'],
                    'warehouse_id' => $bin->warehouse_id,
                    'item_id' => $item->id,
                    'batch_id' => $row['batch']->id,
                    'location_id' => $bin->id,
                    'uom_id' => $uom['PCS']->id,
                    'type' => 'IN',
                    'reference_no' => 'GR-SEED-'.strtoupper(substr($item->sku, 3)),
                    'qty_in' => $row['qty'],
                    'qty_out' => 0,
                    'user_id' => null,
                    'notes' => 'Stok awal hasil seeder',
                ]);

                $stocks[] = ['stock' => $stock, 'batch' => $row['batch']];
            }
        }

        return $stocks;
    }

    private function seedUsers(array $branches): array
    {
        $rows = [
            ['name' => 'Super Admin', 'email' => 'superadmin@hasilfastindo.com', 'role' => User::ROLE_SUPER_ADMIN, 'branch_id' => null],
            ['name' => 'Admin Pusat', 'email' => 'pusat@hasilfastindo.com', 'role' => User::ROLE_CENTRAL, 'branch_id' => null],
            ['name' => 'Staff Surabaya', 'email' => 'cabang.sby@hasilfastindo.com', 'role' => User::ROLE_BRANCH_STAFF, 'branch_id' => $branches['SBY']->id],
            ['name' => 'Staff Jakarta', 'email' => 'cabang.jkt@hasilfastindo.com', 'role' => User::ROLE_BRANCH_STAFF, 'branch_id' => $branches['JKT']->id],
            ['name' => 'Staff Semarang', 'email' => 'cabang.smg@hasilfastindo.com', 'role' => User::ROLE_BRANCH_STAFF, 'branch_id' => $branches['SMG']->id],
        ];

        $map = [];
        foreach ($rows as $row) {
            $user = User::create($row + [
                'password' => Hash::make('password'),
                'phone' => '081200000000',
                'is_active' => true,
            ]);
            $map[$user->email] = $user;
        }

        return $map;
    }

    private function seedTransactions(array $items, array $branches, array $locations, array $uom, array $stocks, array $users, array $fleet): void
    {
        $sby = $branches['SBY'];
        $jkt = $branches['JKT'];
        $smg = $branches['SMG'];
        $suppliers = Supplier::orderBy('id')->get();
        $customers = Customer::orderBy('id')->get();
        $binSby = $locations[$sby->id];
        $binJkt = $locations[$jkt->id];
        $binSmg = $locations[$smg->id];

        $this->seedGoodsReceipts($items, $sby, $jkt, $binSby, $binJkt, $uom, $suppliers, $users);
        $this->seedSalesOrders($items, $customers, $sby, $jkt, $smg, $uom, $users);
        $this->seedTransfers($items, $sby, $jkt, $smg, $binSby, $binJkt, $binSmg, $uom, $users);
        $this->seedRepack($items, $sby, $binSby, $uom, $users);
        $this->seedPackingAndDelivery($items, $customers, $sby, $uom, $users, $fleet, $stocks);
        $this->seedOpname($items, $sby, $binSby, $uom, $users);
    }

    private function seedGoodsReceipts(array $items, $sby, $jkt, array $binSby, array $binJkt, array $uom, $suppliers, array $users): void
    {
        $docs = [
            [
                'doc_number' => 'GR-202609-0001',
                'supplier' => $suppliers[0],
                'branch' => $sby,
                'bin' => $binSby[0],
                'status' => 'completed',
                'received_at' => now()->subDays(5),
                'itemIdx' => [0, 3, 12],
                'qty' => [20, 15, 30],
            ],
            [
                'doc_number' => 'GR-202609-0002',
                'supplier' => $suppliers[1],
                'branch' => $jkt,
                'bin' => $binJkt[1],
                'status' => 'received',
                'received_at' => now()->subDays(2),
                'itemIdx' => [1, 14],
                'qty' => [25, 18],
            ],
        ];

        foreach ($docs as $doc) {
            $gr = GoodsReceipt::create([
                'doc_number' => $doc['doc_number'],
                'supplier_id' => $doc['supplier']->id,
                'branch_id' => $doc['branch']->id,
                'warehouse_id' => $doc['bin']->warehouse_id,
                'destination_location_id' => $doc['bin']->id,
                'source_so' => null,
                'received_at' => $doc['received_at'],
                'status' => $doc['status'],
                'user_id' => $users['cabang.sby@hasilfastindo.com']->id,
                'notes' => 'Penerimaan barang dari supplier.',
            ]);

            foreach ($doc['itemIdx'] as $k => $idx) {
                $item = $items[$idx];
                $qtyDus = $doc['qty'][$k];
                $totalPcs = $qtyDus * 12;
                $batchNo = 'RCV-'.$doc['received_at']->format('Ymd').'-'.str_pad((string) ($k + 1), 4, '0', STR_PAD_LEFT);

                $batch = Batch::create([
                    'item_id' => $item->id,
                    'batch_number' => $batchNo,
                    'expired_date' => null,
                    'cost_price' => $item->cost_price,
                    'received_at' => $doc['received_at'],
                ]);

                GoodsReceiptItem::create([
                    'goods_receipt_id' => $gr->id,
                    'item_id' => $item->id,
                    'batch_id' => $batch->id,
                    'batch_no' => $batchNo,
                    'received_uom_id' => $uom['DUS']->id,
                    'received_qty' => $qtyDus,
                    'conversion_multiplier' => 12,
                    'total_pcs' => $totalPcs,
                    'qty_placed' => $totalPcs,
                    'target_bin_id' => $doc['bin']->id,
                    'original_barcode' => $item->barcode,
                    'notes' => null,
                ]);

                if ($doc['status'] === 'completed') {
                    Stock::create([
                        'branch_id' => $doc['branch']->id,
                        'warehouse_id' => $doc['bin']->warehouse_id,
                        'location_id' => $doc['bin']->id,
                        'item_id' => $item->id,
                        'batch_id' => $batch->id,
                        'uom_id' => $uom['PCS']->id,
                        'quantity_pcs' => $totalPcs,
                        'original_barcode' => $item->barcode,
                        'received_at' => $doc['received_at'],
                    ]);

                    StockMovement::create([
                        'date' => $doc['received_at']->toDateString(),
                        'branch_id' => $doc['branch']->id,
                        'warehouse_id' => $doc['bin']->warehouse_id,
                        'item_id' => $item->id,
                        'batch_id' => $batch->id,
                        'location_id' => $doc['bin']->id,
                        'uom_id' => $uom['PCS']->id,
                        'type' => 'IN',
                        'reference_no' => $doc['doc_number'],
                        'qty_in' => $totalPcs,
                        'qty_out' => 0,
                        'user_id' => $gr->user_id,
                        'notes' => 'Goods receipt '.$doc['doc_number'],
                    ]);
                }
            }
        }
    }

    private function seedSalesOrders(array $items, $customers, $sby, $jkt, $smg, array $uom, array $users): void
    {
        $orders = [
            [
                'so_number' => 'SO-202609-0001',
                'customer' => $customers[0],
                'branch' => $sby,
                'fulfillment' => $sby,
                'status' => 'processing',
                'lines' => [[0, 48, 48, 48, 24], [1, 96, 60, 60, 0]],
            ],
            [
                'so_number' => 'SO-202609-0002',
                'customer' => $customers[1],
                'branch' => $smg,
                'fulfillment' => $jkt,
                'status' => 'partial',
                'lines' => [[2, 120, 72, 72, 0], [3, 60, 0, 0, 0]],
            ],
            [
                'so_number' => 'SO-202609-0003',
                'customer' => $customers[3],
                'branch' => $jkt,
                'fulfillment' => $jkt,
                'status' => 'draft',
                'lines' => [[4, 24, 0, 0, 0]],
            ],
        ];

        foreach ($orders as $order) {
            $total = 0;

            $so = SalesOrder::create([
                'so_number' => $order['so_number'],
                'customer_id' => $order['customer']->id,
                'branch_id' => $order['branch']->id,
                'fulfillment_branch_id' => $order['fulfillment']->id,
                'order_date' => now()->toDateString(),
                'status' => $order['status'],
                'total_amount' => 0,
                'notes' => 'SO hasil seeder untuk pengujian alur WMS.',
                'user_id' => $users['cabang.sby@hasilfastindo.com']->id,
            ]);

            foreach ($order['lines'] as [$idx, $requested, $fulfilled, $picked, $packed]) {
                $item = $items[$idx];
                $lineTotal = $requested * $item->sell_price;
                $total += $lineTotal;

                SalesOrderItem::create([
                    'sales_order_id' => $so->id,
                    'item_id' => $item->id,
                    'uom_id' => $uom['PCS']->id,
                    'requested_qty' => $requested,
                    'fulfilled_qty' => $fulfilled,
                    'picked_qty' => $picked,
                    'packed_qty' => $packed,
                    'unit_price' => $item->sell_price,
                    'notes' => null,
                ]);
            }

            $so->update(['total_amount' => $total]);
        }
    }

    private function seedTransfers(array $items, $sby, $jkt, $smg, array $binSby, array $binJkt, array $binSmg, array $uom, array $users): void
    {
        $transfers = [
            [
                'transfer_no' => 'TRF-202609-0001',
                'from' => $sby,
                'to' => $jkt,
                'status' => 'in_transit',
                'fromBin' => $binSby[0],
                'toBin' => $binJkt[2],
                'lines' => [[0, 5, 0], [5, 8, 0]],
                'shipped' => now()->subDay(),
                'received' => null,
            ],
            [
                'transfer_no' => 'TRF-202609-0002',
                'from' => $jkt,
                'to' => $smg,
                'status' => 'received',
                'fromBin' => $binJkt[1],
                'toBin' => $binSmg[1],
                'lines' => [[1, 10, 10], [2, 6, 5]],
                'shipped' => now()->subDays(3),
                'received' => now()->subDay(),
            ],
        ];

        foreach ($transfers as $row) {
            $transfer = StockTransfer::create([
                'transfer_no' => $row['transfer_no'],
                'from_branch_id' => $row['from']->id,
                'to_branch_id' => $row['to']->id,
                'from_warehouse_id' => $row['fromBin']->warehouse_id,
                'to_warehouse_id' => $row['toBin']->warehouse_id,
                'status' => $row['status'],
                'shipped_at' => $row['shipped'],
                'received_at' => $row['received'],
                'user_id' => $users['cabang.sby@hasilfastindo.com']->id,
                'notes' => 'Mutasi antar cabang.',
            ]);

            foreach ($row['lines'] as [$idx, $qty, $receivedQty]) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'item_id' => $items[$idx]->id,
                    'batch_id' => null,
                    'from_location_id' => $row['fromBin']->id,
                    'to_location_id' => $row['toBin']->id,
                    'qty' => $qty,
                    'received_qty' => $receivedQty,
                    'uom_id' => $uom['DUS']->id,
                    'notes' => null,
                ]);

                StockMovement::create([
                    'date' => $row['shipped']->toDateString(),
                    'branch_id' => $row['from']->id,
                    'warehouse_id' => $row['fromBin']->warehouse_id,
                    'item_id' => $items[$idx]->id,
                    'batch_id' => null,
                    'location_id' => $row['fromBin']->id,
                    'uom_id' => $uom['DUS']->id,
                    'type' => 'TRANSFER_OUT',
                    'reference_no' => $row['transfer_no'],
                    'qty_in' => 0,
                    'qty_out' => $qty,
                    'user_id' => $transfer->user_id,
                    'notes' => 'Keluar transfer '.$row['transfer_no'],
                ]);

                if ($receivedQty > 0) {
                    StockMovement::create([
                        'date' => $row['received']->toDateString(),
                        'branch_id' => $row['to']->id,
                        'warehouse_id' => $row['toBin']->warehouse_id,
                        'item_id' => $items[$idx]->id,
                        'batch_id' => null,
                        'location_id' => $row['toBin']->id,
                        'uom_id' => $uom['DUS']->id,
                        'type' => 'TRANSFER_IN',
                        'reference_no' => $row['transfer_no'],
                        'qty_in' => $receivedQty,
                        'qty_out' => 0,
                        'user_id' => $transfer->user_id,
                        'notes' => 'Masuk transfer '.$row['transfer_no'],
                    ]);
                }
            }
        }
    }

    private function seedRepack(array $items, $sby, array $binSby, array $uom, array $users): void
    {
        $repack = StockRepack::create([
            'repack_no' => 'RPK-202609-0001',
            'date' => now()->subDays(4)->toDateString(),
            'branch_id' => $sby->id,
            'warehouse_id' => $binSby[0]->warehouse_id,
            'user_id' => $users['cabang.sby@hasilfastindo.com']->id,
            'status' => 'completed',
            'notes' => 'Repack karton ke dus.',
        ]);

        StockRepackItem::create([
            'stock_repack_id' => $repack->id,
            'item_id' => $items[0]->id,
            'source_barcode' => $items[0]->barcode,
            'batch_no' => str_replace('HF-', 'B26A-', $items[0]->sku),
            'batch_id' => null,
            'source_uom_id' => $uom['KRT']->id,
            'source_qty' => 5,
            'source_qty_pcs' => 120,
            'target_item_id' => $items[3]->id,
            'target_barcode_new' => 'HF-RPK-202609-0001',
            'target_uom_id' => $uom['PCS']->id,
            'target_qty' => 120,
            'target_location_id' => $binSby[3]->id,
        ]);

        StockMovement::create([
            'date' => $repack->date->toDateString(),
            'branch_id' => $sby->id,
            'warehouse_id' => $binSby[0]->warehouse_id,
            'item_id' => $items[0]->id,
            'batch_id' => null,
            'location_id' => $binSby[0]->id,
            'uom_id' => $uom['KRT']->id,
            'type' => 'REPACK_OUT',
            'reference_no' => $repack->repack_no,
            'qty_in' => 0,
            'qty_out' => 5,
            'user_id' => $repack->user_id,
            'notes' => 'Sumber repack',
        ]);

        StockMovement::create([
            'date' => $repack->date->toDateString(),
            'branch_id' => $sby->id,
            'warehouse_id' => $binSby[3]->warehouse_id,
            'item_id' => $items[3]->id,
            'batch_id' => null,
            'location_id' => $binSby[3]->id,
            'uom_id' => $uom['PCS']->id,
            'type' => 'REPACK',
            'reference_no' => $repack->repack_no,
            'qty_in' => 120,
            'qty_out' => 0,
            'user_id' => $repack->user_id,
            'notes' => 'Hasil repack',
        ]);
    }

    private function seedPackingAndDelivery(array $items, $customers, $sby, array $uom, array $users, array $fleet, array $stocks): void
    {
        $so = SalesOrder::where('so_number', 'SO-202609-0001')->first();

        $pl = PackingList::create([
            'no_packing' => 'PL-202609-0001',
            'sales_order_id' => $so?->id,
            'customer_id' => $customers[0]->id,
            'branch_id' => $sby->id,
            'source_branch_id' => $sby->id,
            'total_box' => 2,
            'status' => 'packed',
            'user_id' => $users['cabang.sby@hasilfastindo.com']->id,
            'notes' => 'Packing list pengiriman SO-202609-0001.',
        ]);

        $boxes = [];
        foreach ([1, 2] as $n) {
            $boxes[] = PackingListBox::create([
                'packing_list_id' => $pl->id,
                'box_number' => $n,
                'box_barcode' => sprintf('HF-BOX-2026-%04d', $n),
                'weight_kg' => 12.5 * $n,
                'status' => 'packed',
            ]);
        }

        $soItems = $so ? $so->items()->orderBy('id')->get()->values() : collect();

        foreach ($boxes as $b => $box) {
            $line = $soItems[$b] ?? null;

            PackingListItem::create([
                'packing_list_id' => $pl->id,
                'packing_list_box_id' => $box->id,
                'item_id' => $line?->item_id ?? $items[$b]->id,
                'batch_id' => null,
                'sales_order_item_id' => $line?->id,
                'qty' => 24,
                'uom_id' => $uom['PCS']->id,
            ]);
        }

        $delivery = Delivery::create([
            'delivery_no' => 'DLV-202609-0001',
            'sales_order_id' => $so?->id,
            'packing_list_id' => $pl->id,
            'branch_id' => $sby->id,
            'vehicle_id' => $fleet['vehicles'][0]->id,
            'driver_id' => $fleet['drivers'][0]->id,
            'helper_id' => $fleet['drivers'][2]->id,
            'driver_name' => $fleet['drivers'][0]->name,
            'helper_name' => $fleet['drivers'][2]->name,
            'destination_address' => $customers[0]->address,
            'method' => 'armada',
            'departure_time' => now()->subHours(3),
            'arrival_time' => null,
            'status' => 'in_delivery',
            'user_id' => $users['cabang.sby@hasilfastindo.com']->id,
            'notes' => 'Pengiriman armada truk.',
        ]);

        foreach ($boxes as $b => $box) {
            $line = $soItems[$b] ?? null;

            DeliveryItem::create([
                'delivery_id' => $delivery->id,
                'packing_list_box_id' => $box->id,
                'item_id' => $line?->item_id ?? $items[$b]->id,
                'batch_id' => null,
                'qty' => 24,
                'uom_id' => $uom['PCS']->id,
                'barcode' => $box->box_barcode,
                'scanned_out_at' => now()->subHours(3),
                'status' => 'scanned_out',
            ]);
        }

        $tracks = [
            ['pending_scan', 'Surat jalan dibuat, menunggu scan out.', now()->subHours(4)],
            ['scanned_out', 'Barcode dus discan keluar gudang.', now()->subHours(3)],
            ['in_delivery', 'Kendaraan berangkat menuju tujuan.', now()->subHours(3)],
        ];

        foreach ($tracks as [$status, $description, $at]) {
            $track = DeliveryTrack::create([
                'delivery_id' => $delivery->id,
                'status' => $status,
                'description' => $description,
                'photo' => null,
                'user_id' => $users['cabang.sby@hasilfastindo.com']->id,
            ]);
            $track->created_at = $at;
            $track->updated_at = $at;
            $track->save();
        }

        $pickup = Delivery::create([
            'delivery_no' => 'DLV-202609-0002',
            'sales_order_id' => SalesOrder::where('so_number', 'SO-202609-0003')->first()?->id,
            'packing_list_id' => null,
            'branch_id' => $sby->id,
            'vehicle_id' => null,
            'driver_id' => null,
            'helper_id' => null,
            'driver_name' => null,
            'helper_name' => null,
            'destination_address' => $customers[3]->address,
            'method' => 'pickup_sendiri',
            'departure_time' => null,
            'arrival_time' => null,
            'status' => 'pending_scan',
            'user_id' => $users['cabang.jkt@hasilfastindo.com']->id,
            'notes' => 'Diambil sendiri oleh customer.',
        ]);

        DeliveryTrack::create([
            'delivery_id' => $pickup->id,
            'status' => 'pending_scan',
            'description' => 'Menunggu pengambilan barang oleh customer.',
            'user_id' => $pickup->user_id,
        ]);
    }

    private function seedOpname(array $items, $sby, array $binSby, array $uom, array $users): void
    {
        $opname = StockOpname::create([
            'opname_no' => 'OPN-202609-0001',
            'branch_id' => $sby->id,
            'warehouse_id' => $binSby[0]->warehouse_id,
            'location_id' => $binSby[0]->id,
            'title' => 'Opname Bulanan BIN-A1-01',
            'opname_date' => now()->toDateString(),
            'status' => 'in_progress',
            'created_by' => $users['cabang.sby@hasilfastindo.com']->id,
            'approved_by' => null,
            'notes' => 'Opname stok bulanan cabang Surabaya.',
        ]);

        $lines = [[0, 120, 118], [3, 48, 48]];

        foreach ($lines as [$idx, $system, $physical]) {
            $item = $items[$idx];
            $difference = $physical - $system;

            StockOpnameItem::create([
                'stock_opname_id' => $opname->id,
                'item_id' => $item->id,
                'batch_id' => null,
                'uom_id' => $uom['PCS']->id,
                'scanned_barcode' => $item->barcode,
                'system_qty' => $system,
                'physical_qty' => $physical,
                'difference_qty' => $difference,
                'status' => $difference == 0 ? 'matched' : 'discrepancy',
                'notes' => $difference == 0 ? null : 'Selisih fisik, menunggu approval.',
            ]);
        }
    }
}
