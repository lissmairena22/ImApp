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
        Schema::create('cash_registers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->dateTime('opened_at');
    $table->dateTime('closed_at')->nullable();
    $table->decimal('initial_balance', 12, 2);
    $table->decimal('cash_sales', 12, 2)->default(0);
    $table->decimal('cash_out', 12, 2)->default(0);
    $table->decimal('system_balance', 12, 2)->default(0);
    $table->decimal('physical_balance', 12, 2)->nullable();
    $table->decimal('difference', 12, 2)->nullable();
    $table->enum('status', ['Abierta', 'Cerrada'])->default('Abierta');
    $table->text('notes')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cajas');
    }
};
