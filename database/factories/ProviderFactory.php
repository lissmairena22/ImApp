<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_name' => fake('es_ES')->unique()->company(),
            'ruc' => fake()->unique()->numerify('###########'),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'is_active' => fake()->boolean(95),
        ];
    }
}
