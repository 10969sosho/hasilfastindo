<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->string('kode_uom_serialno', 160);
            $table->string('action', 20)->default('receive');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['stock_transfer_id', 'kode_uom_serialno']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_scans');
    }
};
