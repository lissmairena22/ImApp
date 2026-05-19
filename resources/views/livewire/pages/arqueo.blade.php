<?php

use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use Toast;

    // Variables dinámicas del sistema (se calculan en vivo en el método with)
    public float $initial_balance = 0.00;
    public float $total_incomes = 0.00;
    public float $total_expenses = 0.00;

    public $exchange_rate = 36.65;
    public string $notes = '';

    // Arreglo para Billetes y Monedas Locales (Córdobas/Pesos)
    public array $nio = [
        '1000' => '', '500' => '', '200' => '', '100' => '',
        '50' => '', '20' => '', '10' => '', '5' => '', '1' => ''
    ];

    // Arreglo para Dólares (USD)
    public array $usd = [
        '100' => '', '50' => '', '20' => '', '10' => '', '5' => '', '1' => ''
    ];

    // Propiedad Computada: Calcula el balance esperado según el sistema
    public function getSystemBalanceProperty(): float
    {
        return $this->initial_balance + $this->total_incomes - $this->total_expenses;
    }

    // Procesa el cierre definitivo guardando los datos en la base de datos
    public function closeRegister()
    {
        $this->validate([
            'exchange_rate' => 'required|numeric|min:1',
        ], [
            'exchange_rate.required' => 'La tasa de cambio oficial es obligatoria.',
            'exchange_rate.numeric' => 'La tasa de cambio debe ser un número válido.'
        ]);

        $physical_balance = $this->getPhysicalBalanceProperty();
        $difference = $physical_balance - $this->system_balance;

        // Validación de descuadres obligatorios
        if (round($difference, 2) !== 0.00 && empty(trim($this->notes))) {
            $this->addError('notes', 'Es obligatorio justificar el motivo del sobrante o faltante en caja.');
            $this->error('Falta justificación del descuadre.');
            return;
        }

        // 1. Buscamos la sesión de caja que esté abierta actualmente
        $activeRegister = DB::table('cash_registers')->where('status', 'abierta')->first();

        if ($activeRegister) {
            // 2. Actualizamos la fila con los totales reales calculados
            DB::table('cash_registers')->where('id', $activeRegister->id)->update([
                'closed_at' => now(),
                'cash_sales' => $this->total_incomes,
                'cash_out' => $this->total_expenses,
                'system_balance' => $this->system_balance,
                'physical_balance' => $physical_balance,
                'difference' => $difference,
                'status' => 'cerrada',
                'notes' => $this->notes,
                'updated_at' => now()
            ]);

            $this->success('Caja cerrada con éxito. Turno finalizado.');
        } else {
            $this->error('No se encontró ninguna caja abierta para cerrar.');
        }

        // Limpieza de todos los campos para el siguiente turno
        $this->nio = [
            '1000' => '', '500' => '', '200' => '', '100' => '',
            '50' => '', '20' => '', '10' => '', '5' => '', '1' => ''
        ];
        $this->usd = [
            '100' => '', '50' => '', '20' => '', '10' => '', '5' => '', '1' => ''
        ];
        $this->notes = '';
        $this->initial_balance = 0.00;
        $this->total_incomes = 0.00;
        $this->total_expenses = 0.00;
    }

    public function getTotalNioProperty(): float
    {
        $total = 0;
        foreach ($this->nio as $value => $qty) {
            $total += ((float)$value * (int)($qty ?: 0));
        }
        return $total;
    }

    public function getTotalUsdProperty(): float
    {
        $total = 0;
        foreach ($this->usd as $value => $qty) {
            $total += ((float)$value * (int)($qty ?: 0));
        }
        return $total;
    }

    public function getPhysicalBalanceProperty(): float
    {
        return $this->getTotalNioProperty() + ($this->getTotalUsdProperty() * (float)$this->exchange_rate);
    }

    public function with(): array
    {
        // --- CONEXIÓN COMPLETA A LA BASE DE DATOS EN VIVO ---

        // 1. Buscamos la caja activa del turno actual
        $activeRegister = DB::table('cash_registers')->where('status', 'abierta')->first();

        if ($activeRegister) {
            // 2. Cargamos dinámicamente el monto con el que abrieron la caja (Apertura)
            $this->initial_balance = (float)$activeRegister->initial_balance;

            // 3. Sumamos los ingresos (ventas, anticipos, cancelaciones) vinculados a esta caja
            $this->total_incomes = DB::table('cash_movements')
                ->where('cash_register_id', $activeRegister->id)
                ->whereIn('type', ['Ingreso', 'Abono'])
                ->sum('amount');

            // 4. Sumamos los egresos (gastos rápidos de caja) vinculados a esta caja
            $this->total_expenses = DB::table('cash_movements')
                ->where('cash_register_id', $activeRegister->id)
                ->where('type', 'Egreso')
                ->sum('amount');
        } else {
            // Si no hay ninguna caja abierta en el sistema, todo se mantiene en cero
            $this->initial_balance = 0.00;
            $this->total_incomes = 0.00;
            $this->total_expenses = 0.00;
        }
        // --- FIN CONEXIÓN ---

        $system = $this->getSystemBalanceProperty();
        $physical = $this->getPhysicalBalanceProperty();
        $difference = $physical - $system;

        return [
            'system_balance' => $system,
            'total_nio' => $this->getTotalNioProperty(),
            'total_usd' => $this->getTotalUsdProperty(),
            'physical_balance' => $physical,
            'difference' => $difference,
            'isBoxOpen' => $activeRegister ? true : false
        ];
    }
}; ?>

<div>
    <x-header title="Arqueo y Cierre de Caja" subtitle="Conteo físico de billetes y monedas" separator />

    @if(!$isBoxOpen)
        <x-alert title="No hay ningún turno activo" description="La caja está actualmente cerrada. Debes abrir una nueva caja desde tu Panel de Control antes de realizar el arqueo." icon="o-exclamation-triangle" class="alert-warning mb-6" />
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <x-card title="Córdobas (C$)" icon="o-banknotes" class="border-t-4 border-t-primary bg-base-200/50">
            <div class="grid grid-cols-2 gap-4">
                @foreach($nio as $denominacion => $cantidad)
                    <x-input
                        label="{{ $denominacion >= 10 ? 'Billete' : 'Moneda' }} de {{ $denominacion }}"
                        wire:model.live="nio.{{ $denominacion }}"
                        type="number"
                        min="0"
                        placeholder="0"
                        icon="o-currency-dollar"
                        :disabled="!$isBoxOpen"
                    />
                @endforeach
            </div>
            <div class="mt-4 text-right text-lg font-bold text-primary">
                Subtotal: C$ {{ number_format($total_nio, 2) }}
            </div>
        </x-card>

        <x-card title="Dólares (USD)" icon="o-currency-dollar" class="border-t-4 border-t-success bg-base-200/50">
            <div class="grid grid-cols-2 gap-4">
                @foreach($usd as $denominacion => $cantidad)
                    <x-input
                        label="Billete de ${{ $denominacion }}"
                        wire:model.live="usd.{{ $denominacion }}"
                        type="number"
                        min="0"
                        placeholder="0"
                        :disabled="!$isBoxOpen"
                    />
                @endforeach
            </div>
            <div class="mt-4 text-right text-lg font-bold text-success">
                Subtotal: $ {{ number_format($total_usd, 2) }}
            </div>

            <hr class="my-4 border-gray-300" />
            <x-input label="Tasa de Cambio Oficial" wire:model.live="exchange_rate" type="number" step="0.01" icon="o-arrows-right-left" :disabled="!$isBoxOpen" />
        </x-card>

        <div class="space-y-4">

            <x-card title="Resumen del Sistema" icon="o-computer-desktop" class="bg-base-100 shadow-sm">

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between items-center text-gray-500">
                        <span>Fondo de Apertura (Real):</span>
                        <span class="font-bold">C$ {{ number_format($initial_balance, 2) }}</span>
                    </div>

                    <div class="flex justify-between items-center text-success">
                        <span>(+) Ingresos / Ventas (Real):</span>
                        <span class="font-bold">C$ {{ number_format($total_incomes, 2) }}</span>
                    </div>

                    <div class="flex justify-between items-center text-error border-b pb-2">
                        <span>(-) Egresos / Gastos (Real):</span>
                        <span class="font-bold">C$ {{ number_format($total_expenses, 2) }}</span>
                    </div>

                    <div class="flex justify-between items-center pt-2">
                        <span class="font-bold text-gray-600">Balance Esperado:</span>
                        <span class="font-bold text-lg">C$ {{ number_format($system_balance, 2) }}</span>
                    </div>
                </div>

            </x-card>

            <x-card title="Resultado del Conteo" icon="o-calculator" class="bg-base-100 shadow-sm">

                <div class="flex justify-between items-center mb-4 border-b pb-2">
                    <span class="text-gray-500">Total Físico (Cajas + USD):</span>
                    <span class="font-bold text-xl text-primary">C$ {{ number_format($physical_balance, 2) }}</span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-gray-500">Diferencia:</span>
                    <span class="font-bold text-2xl {{ $difference == 0 ? 'text-success' : ($difference > 0 ? 'text-info' : 'text-error') }}">
                        C$ {{ number_format($difference, 2) }}
                    </span>
                </div>

                <div class="mt-2 text-right">
                    @if($difference == 0)
                        <x-badge value="Cuadre Perfecto" class="badge-success" />
                    @elseif($difference > 0)
                        <x-badge value="Sobrante en caja" class="badge-info" />
                    @else
                        <x-badge value="Faltante en caja" class="badge-error" />
                    @endif
                </div>

            </x-card>

            <x-card title="Finalizar" class="bg-base-100">
                <x-textarea
                    label="Observaciones"
                    wire:model="notes"
                    placeholder="Obligatorio si hay descuadre en el turno..."
                    rows="2"
                    class="mb-4"
                    :disabled="!$isBoxOpen"
                />

                <x-button
                    label="Procesar Cierre de Turno"
                    icon="o-lock-closed"
                    class="btn-primary w-full"
                    wire:click="closeRegister"
                    wire:confirm="¿Estás seguro de cerrar la caja definitivamente? El estado del turno pasará a CERRADO."
                    :disabled="!$isBoxOpen"
                />
            </x-card>

        </div>
    </div>
</div>
