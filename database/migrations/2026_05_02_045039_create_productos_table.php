<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('category_id')->constrained();
    $table->foreignId('unit_id')->constrained();
    $table->string('name');
    $table->enum('type', ['Producto', 'Servicio']);
    $table->decimal('sale_price', 12, 2);
    $table->decimal('cost_price', 12, 2)->default(0);
    $table->integer('stock')->default(0);
    $table->integer('min_stock')->default(5);
    $table->boolean('manage_stock')->default(true);
    $table->integer('estimated_production_time')->nullable(); // en minutos
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
