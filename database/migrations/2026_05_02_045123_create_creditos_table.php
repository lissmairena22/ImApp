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
        Schema::create('credits', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained();
    $table->decimal('total_amount', 12, 2);
    $table->decimal('pending_balance', 12, 2);
    $table->decimal('interest_rate', 5, 2)->default(0);
    $table->date('start_date');
    $table->date('due_date');
    $table->enum('status', ['Vigente', 'Pagado', 'Mora'])->default('Vigente');
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creditos');
    }
};
