# SPEC & MASTER INSTRUCTION: SISTEM WMS & PENGIRIMAN TOKO HASIL FASTINDO

Dokumentasi lengkap WMS Enterprise untuk PT/Toko HASIL FASTINDO berbasis Laravel.

## 1. Master & Arsitektur Database
- **Branches / Cabang**: code, name, address, phone, is_central (boolean).
- **Warehouses**: branch_id, code, name.
- **Locations (BIN CODE)**: warehouse_id, code (e.g. BIN-A1-01), area, rack, shelf, bin.
- **UOMs**: code (PCS, DUS, KRT, BTL, PACK, KG), name.
- **Categories**: code, name.
- **Items (Barang)**: sku, name, category_id, base_uom_id, min_stock, description.
- **Item Uom Conversions**: item_id, from_uom_id, to_uom_id, multiplier (contoh: 1 KRT = 24 PCS, 1 DUS = 12 PCS).
- **Suppliers**: code, name, phone, address.
- **Customers**: code, name, phone, address.
- **Vehicles (Kendaraan)**: plate_number, vehicle_name, driver_name, helper_name, type, status.
- **Drivers & Helpers**: name, phone, license_number, role (driver/helper).
- **Users**: name, email, password, role ('super_admin', 'central', 'branch_staff'), branch_id.

## 2. Inventory & Batch/Serial & FIFO
- **Batches**: item_id, batch_number, expired_date, cost_price.
- **Stocks (Inventaris per BIN & Batch)**:
  - branch_id, warehouse_id, location_id, item_id, batch_id, uom_id, quantity_pcs (basis konversi), original_barcode, received_at (untuk FIFO).
- **Stock Movements / Kardeks**:
  - date, branch_id, item_id, batch_id, location_id, type ('IN', 'OUT', 'TRANSFER_IN', 'TRANSFER_OUT', 'ADJUSTMENT', 'REPACK'), reference_no, qty_in, qty_out, uom_id, user_id, notes.

## 3. Modul Penerimaan (Goods Receipt / Inbound)
- Form create penerimaan cabang & pusat: supplier_id, branch_id, destination_location_id (BIN), doc_number, source_so (optional), received_at.
- Detail items: item_id, batch_no, received_uom_id, received_qty, conversion_multiplier, total_pcs, target_bin_id, notes.
- Fitur Generate Barcode/QR: cetak label barcode unik per batch/penerimaan (contoh: `HF-RCV-YYYYMMDD-XXXX`). Label bisa diprint (HTML print view barcode/QR).
- Multi-location: 1 barang bisa disebar ke beberapa BIN CODE.

## 4. Modul Putaway / Penempatan Lokasi
- Daftar barang yang baru diterima belum ditaruh / relokasi antar BIN.
- Input / Scan barcode barang -> Scan BIN CODE tujuan -> Update stock location.

## 5. Modul Sales Order (SO) & Pengeluaran Barang (Goods Issue / Outbound)
- **Sales Orders**: so_number, customer_id, branch_id (cabang pencatat SO), fulfillment_branch_id (cabang sumber barang keluar - cross branch requirement!), order_date, status ('draft', 'pending', 'processing', 'partial', 'completed', 'cancelled'), notes.
- **SO Items**: so_id, item_id, requested_qty, uom_id, fulfilled_qty.
- Pengeluaran Barang / Outbound:
  - Validasi stok otomatis.
  - Rekomendasi otomatis FIFO (ambil batch paling awal masuk `received_at` ASC).
  - Cross-cabang: SO dari Cabang A bisa dikeluarkan dari Gudang Cabang B (mencatat pemenuhan antar cabang).

## 6. Modul Transfer Antar Cabang
- Transaksi transfer: transfer_no, from_branch_id, to_branch_id, status ('draft', 'pending_approval', 'in_transit', 'received', 'discrepancy'), notes.
- Items: item_id, batch_id, from_location_id, to_location_id, qty, uom_id.
- Alur:
  1. Cabang Asal buat transfer keluar (stok asal langsung berkurang / status in_transit).
  2. Cabang Tujuan melakukan penerimaan transfer (stok tujuan bertambah, catat selisih jika ada rusak/hilang).
  3. Pusat bisa monitoring semua transfer real-time.

## 7. Modul Repack & Konversi Satuan
- Catat repack: date, branch_id, user_id, notes.
- Sumber (Asal): item_id, source_barcode, batch_no, source_uom, source_qty.
- Hasil (Tujuan): target_item_id, target_barcode_new, target_uom, target_qty, target_location_id.
- Sistem otomatis memotong stok asal dan menerbitkan stok + barcode baru dengan riwayat keterikatan batch.

## 8. Modul Summary SO & Status Barang Belum Diambil
- Dashboard tabel monitoring SO: nomor SO, customer, cabang pemilik SO, barang, qty pesan, qty terpenuhi, sisa belum diambil, lokasi stok tersedia, status picking, packing, & serah terima.

## 9. Modul Picking & Packing List (Dus / Kemasan Ber-Barcode)
- Proses Picking berdasarkan SO.
- Pembuatan Packing List: no_packing, so_id, customer, source_branch, total_box.
- Detail Tiap Dus: Box #1, Box #2, dsb.
- Masing-masing Box memiliki BARCODE/QR internal sendiri (misal: `HF-BOX-2026-0001`).
- Cetak Packing List & Label Dus ber-barcode profesional.

## 10. Modul Serah Terima & Scan Out Pengiriman
- Surat Jalan / Pengiriman (Delivery Order): delivery_no, so_id / packing_list_id, branch_id, destination_address.
- Metode: Dikirim armada kendaraan ATAU Diambil sendiri.
- Info armada: Vehicle (plat nomor, tipe mobil), driver, helper, departure_time.
- Scan Out: memindai barcode dus/barang sebelum masuk mobil.
- Manifest mobil: melihat seluruh muatan barang/dus yang ada di dalam mobil X.

## 11. Tracking Pengiriman
- Status: Belum Scan Out -> Sudah Scan Out -> Dalam Pengiriman -> Tiba -> Dikonfirmasi Penerima (dengan foto bukti/tanda tangan & catatan penerima) / Bermasalah.
- Log timeline tracking pengiriman.

## 12. Modul Stok Opname
- Sesi Opname: branch_id, location_id (BIN CODE), title, date, status ('open', 'completed').
- Fitur Scan Barcode: scan barcode barang di BIN tersebut -> otomatis muncul stok sistem -> input fisik -> hitung selisih -> approval penyesuaian stok.

## 13. Modul Monitoring Stok Pusat
- Tampilan matriks stok komprehensif: filter Cabang, Warehouse, BIN CODE, Kategori, Barcode/Batch, Status (Ready, In-Transit, Reserved SO, Ready Packing, On Delivery).

## 14. UI / UX Standar ERP Modern
- Sidebar navigasi profesional dengan icon SVG rapi (Lucide / Tabler style clean).
- Color palette: Slate / Indigo / Emerald ERP modern (tidak pasaran).
- Font: Inter / Plus Jakarta Sans (bersih, standar industri, tanpa font aneh-aneh).
- Responsive Mobile View:
  - Navbar ringkas dengan drawer mobile.
  - Khusus mode mobile gudang: Mobile Scanner view dengan tombol scan besar, UI kartu ringkas, navigasi bawah (bottom navigation) untuk petugas gudang: Scan In, Putaway, Picking/Scan Out, Stok Opname.
  - Integrasi kamera scanner QR (HTML5-QRCode / JsBarcode via CDN) sehingga scanner berfungsi di HP maupun input barcode scanner fisik USB/Bluetooth.

## 15. User & Role Permission
- Super Admin: Akses semua menu & master cabang/pengaturan.
- Login Pusat: Akses monitoring stok nasional, transfer pusat, tracking, SO summary.
- Login Cabang: Otomatis dibatasi melihat dan mengeksekusi transaksi cabang miliknya (Gudang Cabang).
- Seeder lengkap dengan akun:
  - superadmin@hasilfastindo.com / password
  - pusat@hasilfastindo.com / password
  - cabang.sby@hasilfastindo.com / password
  - cabang.jkt@hasilfastindo.com / password
- Data dummy realistis: Cabang Surabaya, Jakarta, Semarang, ratusan data master barang fastener/baut/mur/fastindo, BIN code, batch, konversi KRT -> DUS -> PCS, transaksi aktif.
