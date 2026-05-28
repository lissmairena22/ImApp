<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(['Producto', 'Servicio']);

        return [
            'category_id' => Category::query()->inRandomOrder()->value('id') ?? Category::factory(),
            'unit_id' => Unit::query()->inRandomOrder()->value('id') ?? Unit::factory(),
            'name' => fake()->randomElement(['Etiqueta', 'Tarjeta', 'Bolsas', 'Cartón', 'Tazón', 'Portada', 'Banner', 'Calcomanía', 'Póster', 'Rótulo']) . ' ' . fake()->randomElement(['Premium', 'Express', 'Standard', 'Personalizado', 'Color', 'Rollo']),
            'type' => $type,
            'sale_price' => fake()->randomFloat(2, 5, 1500),
            'cost_price' => fake()->randomFloat(2, 2, 1200),
            'stock' => fake()->numberBetween(0, 300),
            'min_stock' => fake()->numberBetween(1, 20),
            'manage_stock' => fake()->boolean(90),
            'estimated_production_time' => $type === 'Servicio' ? fake()->numberBetween(15, 240) : null,
            'is_active' => fake()->boolean(95),
        ];
    }
}
