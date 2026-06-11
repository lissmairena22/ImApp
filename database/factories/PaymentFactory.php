<?php

namespace Database\Factories;

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::query()->inRandomOrder()->value('id') ?? Invoice::factory(),
            'user_id' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
            'credit_id' => Credit::query()->inRandomOrder()->value('id'),
            'payment_date' => fake()->dateTimeBetween('-12 months', 'now'),
            'amount' => fake()->randomFloat(2, 20, 5000),
            'payment_method' => fake()->randomElement(['Efectivo', 'Transferencia', 'Tarjeta']),
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
