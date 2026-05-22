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
            // Agregamos la columna que se relaciona consigo misma (con la misma tabla products)
            $table->foreignId('parent_id')
                  ->nullable()
                  ->after('id') // La coloca justo después del ID para mantener el orden
                  ->constrained('products')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Para revertir, primero se borra la relación foránea y luego la columna
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
