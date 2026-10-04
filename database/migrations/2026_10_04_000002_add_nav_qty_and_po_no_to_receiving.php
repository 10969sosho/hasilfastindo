<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->string('po_no', 60)->nullable()->after('source_so');
        });

        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->decimal('nav_qty', 18, 3)->nullable()->after('total_pcs');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('goods_receipts', 'po_no')) {
            Schema::table('goods_receipts', fn (Blueprint $table) => $table->dropColumn('po_no'));
        }

        if (Schema::hasColumn('goods_receipt_items', 'nav_qty')) {
            Schema::table('goods_receipt_items', fn (Blueprint $table) => $table->dropColumn('nav_qty'));
        }
    }
};
