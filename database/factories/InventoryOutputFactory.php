<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryOutputFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::query()->inRandomOrder()->value('id') ?? Order::factory(),
            'user_id' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
            'output_date' => fake()->dateTimeBetween('-12 months', 'now'),
            'reason' => fake()->randomElement(['Venta', 'Producción', 'Devolución', 'Ajuste', 'Destrucción']),
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
