<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->string('conversion_no', 40)->unique();
            $table->foreignId('item_id')->constrained();
            $table->foreignId('from_uom_id')->constrained('uoms');
            $table->foreignId('to_uom_id')->constrained('uoms');
            $table->decimal('planned_qty', 12, 2)->default(0);
            $table->decimal('executed_qty', 12, 2)->default(0);
            $table->string('status', 20)->default('open');
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->json('results')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_conversions');
    }
};
