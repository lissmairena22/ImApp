<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cash_register_id' => CashRegister::query()->inRandomOrder()->value('id') ?? CashRegister::factory(),
            'user_id' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
            'type' => fake()->randomElement(['Egreso', 'IngresoExtra']),
            'concept' => fake('es_ES')->randomElement(['Apertura', 'Venta', 'Compra', 'Pago proveedor', 'Retiro', 'Ajuste']),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'movement_date' => fake()->dateTimeBetween('-12 months', 'now'),
        ];
    }
}
