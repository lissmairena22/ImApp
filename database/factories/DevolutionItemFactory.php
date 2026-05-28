<?php

namespace Database\Factories;

use App\Models\Devolution;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class DevolutionItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 20);
        $unitPrice = fake()->randomFloat(2, 5, 500);

        return [
            'devolution_id' => Devolution::query()->inRandomOrder()->value('id') ?? Devolution::factory(),
            'invoice_item_id' => InvoiceItem::query()->inRandomOrder()->value('id'),
            'product_id' => Product::query()->inRandomOrder()->value('id') ?? Product::factory(),
            'description' => fake('es_ES')->sentence(4),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount_returned' => round($quantity * $unitPrice, 2),
            'returned_to_stock' => fake()->boolean(70),
            'materials_restored' => fake()->boolean(40),
        ];
    }
}
