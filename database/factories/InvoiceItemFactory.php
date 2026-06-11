<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 25);
        $unitPrice = fake()->randomFloat(2, 5, 500);

        return [
            'invoice_id' => Invoice::query()->inRandomOrder()->value('id') ?? Invoice::factory(),
            'product_id' => Product::query()->inRandomOrder()->value('id') ?? Product::factory(),
            'description' => fake('es_ES')->sentence(4),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => round($quantity * $unitPrice, 2),
        ];
    }
}
