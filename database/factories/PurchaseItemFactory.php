<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 20);
        $costPrice = fake()->randomFloat(2, 5, 500);

        return [
            'purchase_id' => Purchase::query()->inRandomOrder()->value('id') ?? Purchase::factory(),
            'product_id' => Product::query()->inRandomOrder()->value('id') ?? Product::factory(),
            'quantity' => $quantity,
            'cost_price' => $costPrice,
            'subtotal' => round($quantity * $costPrice, 2),
        ];
    }
}
