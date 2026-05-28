<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Papelería', 'Empaque', 'Sublimación', 'Rotulación', 'Impresión Digital', 'Etiquetas', 'Packaging', 'Servicios']),
            'is_active' => fake()->boolean(90),
        ];
    }
}
