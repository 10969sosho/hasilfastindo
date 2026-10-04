<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_serials', function (Blueprint $table) {
            $table->string('kode_uom_serialno', 160)->primary();
            $table->string('sku', 60)->index();
            $table->string('uom', 20);
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('receive_no', 60)->index();
            $table->string('po_no', 60)->nullable();
            $table->string('supplier', 120)->nullable();
            $table->unsignedInteger('qty_uom_in')->default(1);
            $table->decimal('qty_smallest_unit_in', 12, 2)->default(0);
            $table->string('bin_no', 40)->nullable();
            $table->unsignedBigInteger('input_receive_by')->nullable();
            $table->timestamp('input_receive_date_time')->nullable();
            $table->string('pick_no', 60)->nullable()->index();
            $table->string('so_no', 60)->nullable()->index();
            $table->unsignedInteger('qty_uom_out')->default(0);
            $table->decimal('qty_smallest_unit_out', 12, 2)->default(0);
            $table->unsignedBigInteger('input_pick_by')->nullable();
            $table->timestamp('input_pick_date_time')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_serials');
    }
};
