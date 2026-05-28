<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Unidad', 'Metro', 'Metro cuadrado', 'Caja', 'Paquete', 'Kilogramo', 'Litro', 'Rollo']),
            'abbreviation' => '',
        ];
    }
}
