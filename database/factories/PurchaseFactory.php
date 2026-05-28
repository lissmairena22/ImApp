<?php

namespace Database\Factories;

use App\Models\Provider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseFactory extends Factory
{
    public function definition(): array
    {
        $purchaseDate = fake()->dateTimeBetween('-24 months', 'now');

        return [
            'provider_id' => Provider::query()->inRandomOrder()->value('id') ?? Provider::factory(),
            'user_id' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
            'purchase_date' => $purchaseDate,
            'provider_invoice_number' => fake()->unique()->regexify('FAC-[0-9]{6}'),
            'total' => fake()->randomFloat(2, 50, 5000),
            'payment_method' => fake()->randomElement(['cordobas', 'dolares', 'mixto']),
            'amount_cordobas' => fake()->randomFloat(2, 50, 5000),
            'amount_dolares' => fake()->randomFloat(2, 10, 1000),
            'exchange_rate' => fake()->randomFloat(2, 35, 40),
        ];
    }
}
