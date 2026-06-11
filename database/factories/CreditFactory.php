<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-12 months', 'now');
        $dueDate = fake()->dateTimeBetween($startDate, '+12 months');
        $totalAmount = fake()->randomFloat(2, 100, 7000);

        return [
            'invoice_id' => Invoice::query()->inRandomOrder()->value('id') ?? Invoice::factory(),
            'total_amount' => $totalAmount,
            'pending_balance' => fake()->randomFloat(2, 0, $totalAmount),
            'interest_rate' => fake()->randomFloat(2, 0, 15),
            'start_date' => $startDate->format('Y-m-d'),
            'due_date' => $dueDate->format('Y-m-d'),
            'status' => fake()->randomElement(['Vigente', 'Pagado', 'Mora']),
        ];
    }
}
