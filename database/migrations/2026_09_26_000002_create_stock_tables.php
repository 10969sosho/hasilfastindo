<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uom_id')->constrained('uoms');
            $table->decimal('quantity_pcs', 18, 3)->default(0);
            $table->string('original_barcode', 80)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'warehouse_id', 'location_id', 'item_id']);
            $table->index(['item_id', 'received_at']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();
            $table->string('type', 20); // IN, OUT, TRANSFER_IN, TRANSFER_OUT, ADJUSTMENT, REPACK, REPACK_OUT
            $table->string('reference_no', 60)->nullable()->index();
            $table->decimal('qty_in', 18, 3)->default(0);
            $table->decimal('qty_out', 18, 3)->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'item_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stocks');
    }
};
