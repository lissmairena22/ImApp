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
            // Agregamos la columna si no existe
            if (!Schema::hasColumn('products', 'items_per_unit')) {
                $table->decimal('items_per_unit', 10, 2)->default(1)->after('min_stock');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Eliminamos la columna si revertimos la migración
            if (Schema::hasColumn('products', 'items_per_unit')) {
                $table->dropColumn('items_per_unit');
            }
        });
    }
};
