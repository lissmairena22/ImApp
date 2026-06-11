<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashRegisterFactory extends Factory
{
    public function definition(): array
    {
        $openedAt = fake()->dateTimeBetween('-18 months', 'now');
        $closedAt = fake()->optional(0.6)->dateTimeBetween($openedAt, 'now');

        return [
            'user_id' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
            'opened_at' => $openedAt,
            'closed_at' => $closedAt,
            'initial_balance' => fake()->randomFloat(2, 100, 10000),
            'cash_sales' => fake()->randomFloat(2, 0, 5000),
            'cash_out' => fake()->randomFloat(2, 0, 2000),
            'system_balance' => fake()->randomFloat(2, 100, 15000),
            'physical_balance' => fake()->randomFloat(2, 100, 15000),
            'difference' => fake()->randomFloat(2, -200, 200),
            'status' => $closedAt ? 'Cerrada' : 'Abierta',
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
