<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nav_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_no', 60)->unique();
            $table->date('order_date')->nullable();
            $table->string('supplier_name');
            $table->string('status', 30)->default('open'); // open, released, partially_received, completed
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('nav_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('po_id')->constrained('nav_purchase_orders')->cascadeOnDelete();
            $table->string('sku', 60);
            $table->string('item_name');
            $table->string('uom_nav', 20)->default('PCS');
            $table->decimal('nav_qty_pcs', 18, 3)->default(0);
            $table->decimal('received_qty_pcs', 18, 3)->default(0);
            $table->string('status', 30)->default('open');
            $table->timestamps();
            $table->index(['po_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nav_purchase_order_items');
        Schema::dropIfExists('nav_purchase_orders');
    }
};
