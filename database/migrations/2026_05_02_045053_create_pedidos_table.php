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
        Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('client_id')->constrained();
    $table->foreignId('user_id')->constrained(); // Quién tomó el pedido
    $table->dateTime('order_date');
    $table->dateTime('estimated_delivery_date');
    $table->dateTime('actual_delivery_date')->nullable();
    $table->enum('type', ['Rapido', 'Produccion']);
    $table->enum('priority', ['Normal', 'Urgente'])->default('Normal');
    $table->text('job_description')->nullable();
    $table->enum('status', ['Pendiente', 'EnProceso', 'Terminado', 'Entregado', 'Cancelado'])->default('Pendiente');
    $table->decimal('estimated_price', 12, 2);
    $table->decimal('final_price', 12, 2)->nullable();
    $table->decimal('advance_payment', 12, 2)->default(0);
    $table->text('notes')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
