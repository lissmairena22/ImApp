<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-12 months', 'now');
        $endDate = fake()->optional(0.6)->dateTimeBetween($startDate, 'now');

        return [
            'order_id' => Order::query()->inRandomOrder()->value('id') ?? Order::factory(),
            'user_id' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'estimated_time' => fake()->numberBetween(30, 480),
            'actual_time' => fake()->numberBetween(15, 500),
            'production_cost' => fake()->randomFloat(2, 50, 5000),
            'status' => fake()->randomElement(['Pendiente', 'En proceso', 'Terminado', 'Cancelado']),
            'observations' => fake()->optional()->paragraph(),
        ];
    }
}
