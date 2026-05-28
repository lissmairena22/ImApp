<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake('es_ES')->name(),

            'dni' => fake()->unique()->numerify('########'),

            'phone' => fake()->phoneNumber(),

            'email' => fake()->unique()->safeEmail(),

            'address' => fake()->address(),

            'is_active' => fake()->boolean(90),
        ];
    }
}