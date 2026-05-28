<?php

namespace Database\Factories;

use App\Models\InvoiceItem;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemMaterialFactory extends Factory
{
    public function definition(): array
    {
        $quantityPerService = fake()->randomFloat(2, 1, 5);
        $quantityUsed = fake()->randomFloat(2, 1, 10);

        return [
            'order_item_id' => OrderItem::query()->inRandomOrder()->value('id') ?? OrderItem::factory(),
            'invoice_item_id' => InvoiceItem::query()->inRandomOrder()->value('id'),
            'material_id' => Product::query()->inRandomOrder()->value('id') ?? Product::factory(),
            'material_name' => fake('es_ES')->words(2, true),
            'unit_name' => fake()->randomElement(['Unidad', 'Metro', 'Caja', 'Paquete']),
            'available_stock_snapshot' => fake()->randomFloat(2, 5, 100),
            'quantity_per_service' => $quantityPerService,
            'quantity_used' => $quantityUsed,
            'material_lost' => fake()->randomFloat(2, 0, 5),
            'total_consumed' => round($quantityPerService * $quantityUsed, 2),
        ];
    }
}
