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
        Schema::table('products', function (Blueprint $table) {
            // Agregamos la columna 'is_sellable'
            // 'default(true)' hace que por defecto los productos sí se puedan vender
            $table->boolean('is_sellable')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Si alguna vez revertimos la migración, borramos la columna
            $table->dropColumn('is_sellable');
        });
    }
};
