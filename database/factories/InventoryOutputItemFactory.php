<?php

namespace Database\Factories;

use App\Models\InventoryOutput;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryOutputItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 30);

        return [
            'inventory_output_id' => InventoryOutput::query()->inRandomOrder()->value('id') ?? InventoryOutput::factory(),
            'product_id' => Product::query()->inRandomOrder()->value('id') ?? Product::factory(),
            'description' => fake('es_ES')->sentence(4),
            'source_type' => fake()->randomElement(['Venta', 'Producción', 'Devolución', 'Inventario']),
            'quantity' => $quantity,
            'unit_name' => fake()->randomElement(['Unidad', 'Metro', 'Caja', 'Paquete']),
            'material_lost' => fake()->randomFloat(2, 0, 10),
            'affects_stock' => fake()->boolean(80),
        ];
    }
}
