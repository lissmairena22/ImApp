<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->unique()->constrained('orders')->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->dateTime('output_date');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_output_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_output_id')->constrained('inventory_outputs')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description');
            $table->string('source_type');
            $table->decimal('quantity', 12, 2);
            $table->string('unit_name')->nullable();
            $table->decimal('material_lost', 12, 2)->default(0);
            $table->boolean('affects_stock')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_output_items');
        Schema::dropIfExists('inventory_outputs');
    }
};
