<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_serials', function (Blueprint $table) {
            $table->decimal('weight', 10, 3)->nullable()->after('bin_no');
        });

        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->decimal('weight', 10, 3)->nullable()->after('total_pcs');
            $table->string('bin_no', 80)->nullable()->after('target_bin_id');
            $table->text('unit_overrides')->nullable()->after('bin_no');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('ekspedisi', 100)->nullable()->after('method');
        });

        Schema::table('delivery_items', function (Blueprint $table) {
            $table->string('master_box_no', 60)->nullable()->after('barcode')->index();
        });

        Schema::create('delivery_packing_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('packing_list_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['delivery_id', 'packing_list_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_packing_lists');

        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropColumn(['master_box_no']);
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['ekspedisi']);
        });

        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropColumn(['weight', 'bin_no', 'unit_overrides']);
        });

        Schema::table('item_serials', function (Blueprint $table) {
            $table->dropColumn(['weight']);
        });
    }
};
