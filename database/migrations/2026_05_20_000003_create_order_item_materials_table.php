<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained('invoice_items')->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('material_name');
            $table->string('unit_name')->nullable();
            $table->decimal('available_stock_snapshot', 12, 2)->default(0);
            $table->decimal('quantity_per_service', 12, 2)->default(1);
            $table->decimal('quantity_used', 12, 2)->default(0);
            $table->decimal('material_lost', 12, 2)->default(0);
            $table->decimal('total_consumed', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_materials');
    }
};
