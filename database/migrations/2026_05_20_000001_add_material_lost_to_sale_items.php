<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('material_lost', 12, 2)->default(0)->after('material');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('measurements')->nullable()->after('subtotal');
            $table->string('material')->nullable()->after('measurements');
            $table->decimal('material_lost', 12, 2)->default(0)->after('material');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['measurements', 'material', 'material_lost']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('material_lost');
        });
    }
};
