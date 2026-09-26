<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('doc_number', 60)->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('destination_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('source_so', 60)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('status', 20)->default('draft'); // draft, received, putaway, completed
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_no', 60)->nullable();
            $table->foreignId('received_uom_id')->constrained('uoms');
            $table->decimal('received_qty', 18, 3)->default(0);
            $table->decimal('conversion_multiplier', 18, 4)->default(1);
            $table->decimal('total_pcs', 18, 3)->default(0);
            $table->decimal('qty_placed', 18, 3)->default(0);
            $table->foreignId('target_bin_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('original_barcode', 80)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
    }
};
