<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nav_warehouse_picks', function (Blueprint $table) {
            $table->id();
            $table->string('pick_no', 60)->unique();
            $table->string('source_so_no', 60)->nullable()->index();
            $table->string('whse_shipment_no', 60)->nullable()->index();
            $table->string('location_code', 60)->nullable();
            $table->string('destination_no', 60)->nullable(); // customer no
            $table->string('assigned_user_id', 60)->nullable();
            $table->date('assignment_date')->nullable();
            $table->string('status', 30)->default('open'); // open, in_progress, completed
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('nav_warehouse_pick_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nav_pick_id')->constrained('nav_warehouse_picks')->cascadeOnDelete();
            $table->string('line_no', 30)->nullable();
            $table->string('action_type', 30)->default('Take'); // Take, Place
            $table->string('sku', 60)->index();
            $table->string('variant_code', 30)->nullable();
            $table->string('item_name');
            $table->string('uom_code', 20)->default('PCS');
            $table->string('bin_code', 60)->nullable();
            $table->string('zone_code', 60)->nullable();
            $table->decimal('quantity', 18, 3)->default(0);
            $table->decimal('qty_base', 18, 3)->default(0);
            $table->decimal('qty_to_handle', 18, 3)->default(0);
            $table->decimal('qty_handled', 18, 3)->default(0);
            $table->decimal('qty_outstanding', 18, 3)->default(0);
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->index(['nav_pick_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nav_warehouse_pick_items');
        Schema::dropIfExists('nav_warehouse_picks');
    }
};
