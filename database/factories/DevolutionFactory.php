<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DevolutionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::query()->inRandomOrder()->value('id') ?? Invoice::factory(),
            'user_id' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
            'devolution_date' => fake()->dateTimeBetween('-12 months', 'now'),
            'reason' => fake()->randomElement(['Defecto', 'Cambio de tamaño', 'Error de impresión', 'No conforme']),
            'amount_returned' => fake()->randomFloat(2, 5, 2500),
        ];
    }
}
