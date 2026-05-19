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
        Schema::create('service_materials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_id')->constrained('products')->cascadeOnDelete();

            $table->foreignId('material_id')->constrained('products')->cascadeOnDelete();

            $table->decimal('quantity', 8, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_materials');
    }
};
