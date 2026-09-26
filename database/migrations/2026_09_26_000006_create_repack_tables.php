<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_repacks', function (Blueprint $table) {
            $table->id();
            $table->string('repack_no', 60)->unique();
            $table->date('date');
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft, completed
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_repack_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_repack_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('source_barcode', 80)->nullable();
            $table->string('batch_no', 60)->nullable();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_uom_id')->constrained('uoms');
            $table->decimal('source_qty', 18, 3)->default(0);
            $table->decimal('source_qty_pcs', 18, 3)->default(0);
            $table->foreignId('target_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('target_barcode_new', 80)->nullable();
            $table->foreignId('target_uom_id')->constrained('uoms');
            $table->decimal('target_qty', 18, 3)->default(0);
            $table->foreignId('target_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_repack_items');
        Schema::dropIfExists('stock_repacks');
    }
};
