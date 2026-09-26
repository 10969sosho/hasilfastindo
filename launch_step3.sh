#!/usr/bin/env bash
PROMPT="Lanjutkan pembuatan ERP WMS Toko HASIL FASTINDO:
Database, 20+ Models, dan Seeder sudah 100% SUKSES dan aktif dengan data realistis!

Sekarang kerjakan LANGKAH 3:
1. Buat Middleware dan Controller lengkap:
   - AuthController: Login (superadmin/pusat/cabang), Logout, Session switch branch bila superadmin.
   - DashboardController: KPI overview, stok multi-cabang, grafik mutasi, status barang belum diambil dari SO, alert stok minimum.
   - Master Controllers:
     * ItemController: CRUD Item, kelola konversi satuan (KRT -> DUS -> PCS), cetak barcode barang.
     * BranchController & WarehouseController: kelola cabang, gudang, dan BIN CODE (area, rak, shelf, bin).
     * VehicleDriverController: kelola armada kendaraan, sopir & helper.
     * PartnerController: kelola Supplier & Customer.
   - InboundController (Penerimaan Barang):
     * List penerimaan, Form penerimaan baru (input supplier, nomor dokumen, source SO).
     * Input item & batch, konversi satuan otomatis ke PCS, generate Barcode/QR unik (HF-RCV-...).
     * Fitur Putaway: penempatan barang ke BIN CODE tujuan.
     * Print label barcode penerimaan.
   - OutboundController (Pengeluaran Barang):
     * List SO, detail pemenuhan, auto-select batch FIFO (First In First Out berdasarkan received_at paling awal).
     * Dukungan pemenuhan cross-branch: SO tercatat di Cabang A tapi dikeluarkan dari Cabang B.
   - TransferController (Transfer Antar Cabang):
     * Buat transfer keluar, pilih barang & BIN asal, kirim (in_transit).
     * Konfirmasi penerimaan di cabang tujuan, pilih BIN masuk, catat selisih/kerusakan jika ada.
   - RepackController (Repack & Konversi Satuan):
     * Form pecah satuan: potong stok asal (misal 1 Karton), konversi menjadi satuan baru (misal 24 Dus), generate barcode unik hasil repack dengan link histori ke batch asal.
   - SummarySoController (Radar SO & Barang Belum Diambil):
     * Tabel monitoring real-time: nomor SO, customer, cabang pemilik SO, barang dipesan, terpenuhi, belum diambil, lokasi stok tersedia, status picking/packing/pengiriman.
   - PackingController (Picking & Packing List Dus Ber-Barcode):
     * Proses picking SO, create Packing List per dus (Box #1, Box #2, dst).
     * Generate Barcode Dus unik (misal: HF-BOX-202609-001).
     * Print Packing List & Print Barcode Label dus.
   - DeliveryController (Serah Terima & Scan Out Armada Mobil):
     * Form serah terima pengiriman: pilih armada mobil (plat nomor), sopir, helper.
     * Scan Out: scan barcode dus/barang sebelum masuk ke mobil.
     * Manifest muatan kendaraan (melihat daftar semua dus/barang yang ada di dalam mobil tersebut).
     * Update timeline tracking pengiriman (Belum Scan Out -> Sudah Scan Out -> Dalam Pengiriman -> Tiba -> Selesai / Bermasalah dengan upload bukti).
   - StockOpnameController:
     * Buat sesi opname per BIN CODE, scan barcode barang, bandingkan sistem vs fisik, hitung selisih, approval rekonsiliasi.
   - MonitoringPusatController:
     * Matriks stok pusat: filter cabang, warehouse, BIN, kategori, batch, status stok (Ready, Reserved SO, In-Transit, On Delivery).
   - MobileWarehouseController (/mobile):
     * Interface mobile-first touch-friendly khusus staf gudang lapangan:
       - Mode Scan In (Penerimaan & Putaway ke BIN)
       - Mode Scan Out (Picking SO & Muat ke Armada Mobil)
       - Mode Scan Opname (Cek fisik BIN)
       - Terintegrasi kamera barcode scanner (HTML5-QRCode via CDN) & mendukung input scanner hardware USB/Bluetooth.
2. Buat Master Blade Layout (resources/views/layouts/app.blade.php):
   - Gunakan Tailwind CSS CDN + Google Fonts Plus Jakarta Sans.
   - Sidebar navigasi ERP modern warna Slate-900 / Indigo-600 / Emerald-600.
   - Icon SVG clean (Lucide style) di setiap menu.
   - Badge status warna modern (Emerald untuk selesai, Amber untuk in-transit/pending, Rose untuk selisih, Indigo untuk processing).
   - Responsive mobile: hamburger menu / off-canvas drawer di smartphone.
   - Header topbar dengan info user, nama cabang, dan switch cabang (untuk superadmin).
3. Buat seluruh Blade Views untuk semua modul di atas dan pastikan semua route terdaftar di routes/web.php.
"

ag-opencode --dir "/Users/10969sosho/PROJECTS/finance/PROJECT/PROJECT ANTIGRAVITY/HASILFASTINDO" "$PROMPT"
