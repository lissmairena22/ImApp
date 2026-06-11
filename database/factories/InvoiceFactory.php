<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 20, 8000);
        $tax = round($subtotal * 0.15, 2);
        $discount = fake()->randomFloat(2, 0, 200);
        $total = round($subtotal + $tax - $discount, 2);

        return [
            'invoice_number' => fake()->unique()->regexify('INV-[0-9]{8}'),
            'client_id' => Client::query()->inRandomOrder()->value('id') ?? Client::factory(),
            'order_id' => Order::query()->inRandomOrder()->value('id') ?? Order::factory(),
            'user_id' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
            'invoice_date' => fake()->dateTimeBetween('-18 months', 'now'),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'total' => $total,
            'status' => fake()->randomElement(['Pagada', 'Credito', 'Anulada']),
        ];
    }
}
