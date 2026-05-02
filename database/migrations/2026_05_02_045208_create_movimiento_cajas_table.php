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
        Schema::create('cash_movements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cash_register_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->enum('type', ['Egreso', 'IngresoExtra']);
    $table->string('concept');
    $table->decimal('amount', 12, 2);
    $table->dateTime('movement_date');
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimiento_cajas');
    }
};
