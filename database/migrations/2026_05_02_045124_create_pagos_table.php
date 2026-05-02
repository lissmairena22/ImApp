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
       Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->foreignId('credit_id')->nullable()->constrained();
    $table->dateTime('payment_date');
    $table->decimal('amount', 12, 2);
    $table->enum('payment_method', ['Efectivo', 'Transferencia', 'Tarjeta']);
    $table->text('notes')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
