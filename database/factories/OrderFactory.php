<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        $orderDate = fake()->dateTimeBetween('-18 months', 'now');
        $deliveryDate = (clone $orderDate)->modify('+' . fake()->numberBetween(1, 20) . ' days');

        return [
            'client_id' => Client::query()->inRandomOrder()->value('id') ?? Client::factory(),
            'user_id' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
            'order_date' => $orderDate,
            'estimated_delivery_date' => $deliveryDate,
            'actual_delivery_date' => fake()->optional(0.35)->dateTimeBetween($orderDate, $deliveryDate),
            'type' => fake()->randomElement(['Rapido', 'Produccion']),
            'priority' => fake()->randomElement(['Normal', 'Urgente']),
            'job_description' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(['Pendiente', 'EnProceso', 'Terminado', 'Entregado', 'Cancelado']),
            'estimated_price' => fake()->randomFloat(2, 20, 8000),
            'final_price' => fake()->optional(0.5)->randomFloat(2, 20, 8000),
            'advance_payment' => fake()->randomFloat(2, 0, 2000),
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
