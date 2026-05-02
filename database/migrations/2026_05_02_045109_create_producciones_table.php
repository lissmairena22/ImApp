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
        Schema::create('productions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained();
    $table->foreignId('user_id')->constrained(); // Operario
    $table->dateTime('start_date')->nullable();
    $table->dateTime('end_date')->nullable();
    $table->integer('estimated_time')->nullable();
    $table->integer('actual_time')->nullable();
    $table->decimal('production_cost', 12, 2)->default(0);
    $table->string('status');
    $table->text('observations')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producciones');
    }
};
