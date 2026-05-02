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
        Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->string('invoice_number')->unique();
    $table->foreignId('client_id')->constrained();
    $table->foreignId('order_id')->nullable()->constrained();
    $table->foreignId('user_id')->constrained();
    $table->dateTime('invoice_date');
    $table->decimal('subtotal', 12, 2);
    $table->decimal('tax', 12, 2);
    $table->decimal('discount', 12, 2)->default(0);
    $table->decimal('total', 12, 2);
    $table->enum('status', ['Pagada', 'Credito', 'Anulada']);
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
