<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 25);
        $unitPrice = fake()->randomFloat(2, 5, 500);

        return [
            'order_id' => Order::query()->inRandomOrder()->value('id') ?? Order::factory(),
            'product_id' => Product::query()->inRandomOrder()->value('id') ?? Product::factory(),
            'description' => fake('es_ES')->sentence(4),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => round($quantity * $unitPrice, 2),
            'measurements' => fake()->optional()->randomElement(['A4', 'A5', '10x15', '20x30', '30x40']),
            'material' => fake()->optional()->word(),
            'print_type' => fake()->optional()->randomElement(['BN', 'Color']),
            'finish' => fake()->optional()->randomElement(['Mate', 'Brillo', 'Laminado']),
        ];
    }
}
