<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\NavPurchaseOrder;
use App\Models\NavPurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * Contoh PO NAV yang realistis agar halaman Daftar PO NAV langsung terisi.
 * Jalankan sendiri: php artisan db:seed --class=NavPurchaseOrderSeeder
 */
class NavPurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        NavPurchaseOrderItem::query()->delete();
        NavPurchaseOrder::query()->delete();

        $items = Item::where('is_active', true)->orderBy('sku')->get();
        $suppliers = Supplier::orderBy('id')->pluck('name');

        if ($items->isEmpty()) {
            $this->command?->warn('Belum ada Master Item — PO NAV dibuat tanpa baris item.');

            return;
        }

        $samples = [
            [
                'po_no' => 'PO-NAV-2026-0142',
                'days_ago' => 2,
                'status' => NavPurchaseOrder::STATUS_RELEASED,
                'supplier' => $suppliers[0] ?? 'PT Baja Nusantara Steel',
                'notes' => 'Kiriman reguler, menunggu penerimaan di gudang.',
                'lines' => [[0, 'KRT', 480, 0], [5, 'DUS', 240, 0], [12, 'PCS', 1500, 0]],
            ],
            [
                'po_no' => 'PO-NAV-2026-0143',
                'days_ago' => 1,
                'status' => NavPurchaseOrder::STATUS_OPEN,
                'supplier' => $suppliers[1] ?? 'CV Anugerah Fastener',
                'notes' => 'PO baru dari NAV, barang belum dikirim supplier.',
                'lines' => [[3, 'KRT', 720, 0], [8, 'DUS', 360, 0]],
            ],
            [
                'po_no' => 'PO-NAV-2026-0138',
                'days_ago' => 6,
                'status' => NavPurchaseOrder::STATUS_PARTIAL,
                'supplier' => $suppliers[2] ?? 'PT Sekrup Jaya Abadi',
                'notes' => 'Penerimaan sebagian, sisanya menyusul.',
                'lines' => [[2, 'DUS', 600, 240], [15, 'PCS', 2000, 800]],
            ],
            [
                'po_no' => 'PO-NAV-2026-0131',
                'days_ago' => 12,
                'status' => NavPurchaseOrder::STATUS_COMPLETED,
                'supplier' => $suppliers[3] ?? 'PT Sinar Mur Terkemuka',
                'notes' => 'Seluruh item sudah diterima dan di-putaway.',
                'lines' => [[1, 'KRT', 360, 360], [10, 'DUS', 120, 120]],
            ],
        ];

        foreach ($samples as $sample) {
            $po = NavPurchaseOrder::create([
                'po_no' => $sample['po_no'],
                'order_date' => now()->subDays($sample['days_ago'])->toDateString(),
                'supplier_name' => $sample['supplier'],
                'status' => $sample['status'],
                'notes' => $sample['notes'],
            ]);

            foreach ($sample['lines'] as [$index, $uom, $navQty, $receivedQty]) {
                $item = $items[$index % $items->count()];

                NavPurchaseOrderItem::create([
                    'po_id' => $po->id,
                    'sku' => $item->sku,
                    'item_name' => $item->name,
                    'uom_nav' => $uom,
                    'nav_qty_pcs' => $navQty,
                    'received_qty_pcs' => $receivedQty,
                    'status' => $receivedQty <= 0
                        ? NavPurchaseOrder::STATUS_OPEN
                        : ($receivedQty >= $navQty ? NavPurchaseOrder::STATUS_COMPLETED : NavPurchaseOrder::STATUS_PARTIAL),
                ]);
            }
        }

        $this->command?->info('PO NAV dibuat: '.count($samples));
    }
}
