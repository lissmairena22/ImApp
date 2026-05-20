<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->enum('payment_method', ['cordobas', 'dolares', 'mixto'])
                  ->default('cordobas')
                  ->after('total');
            $table->decimal('amount_cordobas', 12, 2)
                  ->nullable()
                  ->after('payment_method');
            $table->decimal('amount_dolares', 12, 2)
                  ->nullable()
                  ->after('amount_cordobas');
            $table->decimal('exchange_rate', 10, 4)
                  ->nullable()
                  ->after('amount_dolares');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'amount_cordobas',
                'amount_dolares',
                'exchange_rate',
            ]);
        });
    }
};