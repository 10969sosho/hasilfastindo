<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_no', 60)->unique();
            $table->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('packing_list_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('helper_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->string('driver_name')->nullable();
            $table->string('helper_name')->nullable();
            $table->text('destination_address')->nullable();
            $table->string('method', 20)->default('armada'); // armada / pickup_sendiri
            $table->timestamp('departure_time')->nullable();
            $table->timestamp('arrival_time')->nullable();
            $table->string('status', 25)->default('pending_scan');
            $table->string('received_by')->nullable();
            $table->text('received_notes')->nullable();
            $table->string('received_photo')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('packing_list_box_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('qty', 18, 3)->default(0);
            $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();
            $table->string('barcode', 80)->nullable();
            $table->timestamp('scanned_out_at')->nullable();
            $table->string('status', 20)->default('pending'); // pending, scanned_out, delivered
            $table->timestamps();
        });

        Schema::create('delivery_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->string('status', 25);
            $table->string('description')->nullable();
            $table->string('photo')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_tracks');
        Schema::dropIfExists('delivery_items');
        Schema::dropIfExists('deliveries');
    }
};
