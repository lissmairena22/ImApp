<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY type ENUM('Rapido', 'Produccion', 'Mixto') NOT NULL");
        DB::statement("ALTER TABLE orders MODIFY status ENUM('Pendiente', 'EnProceso', 'Terminado', 'Entregado', 'Cancelado', 'Parcial') NOT NULL DEFAULT 'Pendiente'");
    }

    public function down(): void
    {
        DB::table('orders')->where('type', 'Mixto')->update(['type' => 'Produccion']);
        DB::table('orders')->where('status', 'Parcial')->update(['status' => 'Pendiente']);

        DB::statement("ALTER TABLE orders MODIFY type ENUM('Rapido', 'Produccion') NOT NULL");
        DB::statement("ALTER TABLE orders MODIFY status ENUM('Pendiente', 'EnProceso', 'Terminado', 'Entregado', 'Cancelado') NOT NULL DEFAULT 'Pendiente'");
    }
};
