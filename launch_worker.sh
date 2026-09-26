#!/usr/bin/env bash
PROMPT="Kamu adalah worker fullstack senior untuk aplikasi Toko HASIL FASTINDO berbasis Laravel 12.
Buka dan baca file WMS_REQUIREMENTS.md di root project ini sebagai acuan absolut.

Tugas kamu adalah membangun SELURUH sistem ERP WMS Hasil Fastindo sampai 100% tuntas, rapi, dan fungsional tanpa ada fitur bolong:
1. Buat seluruh Migration database lengkap:
   - branches, warehouses, locations (BIN CODE: area, rack, shelf, bin), uoms, categories, items, item_uom_conversions
   - suppliers, customers, vehicles, drivers, batches
   - stocks (menyimpan branch_id, warehouse_id, location_id, item_id, batch_id, uom_id, qty, received_at untuk FIFO)
   - goods_receipts & goods_receipt_items (inbound penerimaan barang + barcode generation)
   - sales_orders & sales_order_items (termasuk fulfillment_branch_id untuk lintas cabang)
   - stock_transfers & stock_transfer_items (transfer antar cabang)
   - stock_repacks & stock_repack_items (repack konversi satuan & cetak barcode baru)
   - packing_lists & packing_list_boxes & packing_list_items (packing dus ber-barcode)
   - deliveries, delivery_items, delivery_tracks (serah terima, scan out kendaraan, manifest armada mobil, tracking status sampai tiba/diterima)
   - stock_opnames & stock_opname_items (sesi opname per lokasi/BIN, scan barcode vs fisik, adjustment approval)
   - stock_movements (kartu stok kardeks mutasi barang)
2. Buat Models dengan relasi Eloquent lengkap, accessors, dan business logic method yang kuat (FIFO allocation, stock deduction, conversion helper).
3. Buat DatabaseSeeder dengan data realistis:
   - Cabang: Surabaya (Pusat & Gudang Utama), Jakarta Barat, Semarang.
   - Gudang & puluhan BIN CODE (R-A1-01, R-A1-02, BIN-B2-01, dsb).
   - UOM: PCS, DUS, KRT, PACK, KG.
   - Konversi: Baut Hex M8 (1 KRT = 20 DUS = 2000 PCS), Mur M8, Dinabolt, Skrup Baja, dsb.
   - Kendaraan operasional (Truk Engkel B 9812 SAA, Daihatsu Gran Max L 1402 HF, dsb), sopir & helper.
   - User: superadmin@hasilfastindo.com, pusat@hasilfastindo.com, cabang.sby@hasilfastindo.com, cabang.jkt@hasilfastindo.com (password: password).
   - Data transaksi contoh di setiap status: Inbound, SO pending/partial, Transfer in-transit, Packing List ber-barcode, Pengiriman aktif dengan tracking log, dan Sesi Opname.
4. Buat Controllers lengkap:
   - Auth & Role middleware (Super Admin, Pusat, Cabang).
   - Dashboard & Monitoring Pusat (Overview stok antar cabang, filter warehouse/BIN, radar barang belum diambil).
   - Master Data (Items + konversi UOM, Cabang, Warehouse, BIN CODE, Kendaraan/Sopir, Supplier, Customer).
   - Inbound / Goods Receipt (Penerimaan, generate barcode unik, multi-BIN putaway).
   - Outbound / Pengeluaran (Berdasarkan SO, FIFO auto-select batch, cross-branch release).
   - Stock Transfer (Kirim -> Terima / Discrepancy report).
   - Repack / Conversion (Pecah satuan KRT ke PCS/DUS, generate barcode anak baru).
   - SO Summary & Unpicked items monitor.
   - Picking & Packing List (Packing per dus, generate Box Barcode label).
   - Delivery / Scan Out (Pilih armada mobil/sopir, scan out dus, manifest muatan kendaraan, pelacakan live status).
   - Stock Opname (Sesi opname per BIN, scan barcode, rekonsiliasi selisih).
   - Mobile Warehouse View (/mobile):
     Interface khusus smartphone dengan Navigasi Bawah (Bottom Bar), Touch-friendly, Terintegrasi Scanner Barcode Kamera (HTML5-QRCode via CDN) & input fisik scanner, menu cepat: Scan In / Putaway / Picking / Opname / Scan Out Mobil.
5. Buat Blade Views yang super modern ERP look:
   - Gunakan Tailwind CSS (via CDN atau compiled) dengan palet profesional Slate-900 / Slate-50 / Indigo-600 / Emerald-600.
   - Font: Inter / Plus Jakarta Sans.
   - Sidebar navigasi dengan icon SVG (Lucide style) yang tajam dan elegan.
   - Tampilan print untuk Barcode Label (menggunakan JsBarcode / QR Code), Surat Jalan, Packing List Dus, dan Bukti Serah Terima.
   - Responsive Mobile di semua halaman, plus rute khusus /mobile untuk scanner lapangan.
6. Jalankan php artisan migrate:fresh --seed dan verifikasi tidak ada error syntax pada file mana pun.
"

ag-opencode --dir "/Users/10969sosho/PROJECTS/finance/PROJECT/PROJECT ANTIGRAVITY/HASILFASTINDO" "$PROMPT"
