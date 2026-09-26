#!/usr/bin/env bash
PROMPT="Lanjutkan pembuatan ERP WMS Toko HASIL FASTINDO:
Database migration sudah 100% SUKSES dijalankan di database/database.sqlite!

Sekarang kerjakan LANGKAH 2: Buat SELURUH Eloquent Models di app/Models lengkap dengan fillable, casts, dan relasi Eloquent (hasMany, belongsTo, dll):
1. Branch (warehouses, users, stocks, salesOrders)
2. Warehouse (branch, locations, stocks)
3. Location (BIN CODE: warehouse, stocks)
4. Uom
5. Category (items)
6. Item (category, baseUom, uomConversions, stocks, batches)
7. ItemUomConversion (item, fromUom, toUom)
8. Batch (item, stocks)
9. Stock (branch, warehouse, location, item, batch, uom)
10. StockMovement (kardeks mutasi: branch, item, batch, location, user)
11. Supplier & Customer
12. Vehicle (driver, helper, deliveries)
13. Driver (deliveriesAsDriver, deliveriesAsHelper)
14. GoodsReceipt & GoodsReceiptItem (supplier, branch, location, user)
15. SalesOrder & SalesOrderItem (customer, branch, fulfillmentBranch)
16. StockTransfer & StockTransferItem (fromBranch, toBranch, user)
17. StockRepack & StockRepackItem
18. PackingList & PackingListBox & PackingListItem
19. Delivery & DeliveryItem & DeliveryTrack (vehicle, driver, helper, branch)
20. StockOpname & StockOpnameItem (branch, warehouse, location, creator, approver)
21. Update User model (branch, role checks: isSuperAdmin(), isCentral(), isBranchStaff())

Buat method helper di Stock model untuk kalkulasi stok dan FIFO picking.
Buat DatabaseSeeder komprehensif di database/seeders/DatabaseSeeder.php dengan data master lengkap (Cabang SBY, JKT, SMG; BIN codes; Item Fastener Baut Hex, Mur, Skrup; Konversi KRT-DUS-PCS; Armada Truk & Gran Max; Users superadmin, pusat, cabang.sby, cabang.jkt; serta transaksi aktif di setiap modul).
Jalankan php artisan db:seed dan pastikan exit code 0!
"

ag-opencode --dir "/Users/10969sosho/PROJECTS/finance/PROJECT/PROJECT ANTIGRAVITY/HASILFASTINDO" "$PROMPT"
