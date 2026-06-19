<?php

use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use Toast;

    public float $initial_balance = 0.00;
    public float $total_incomes = 0.00;
    public float $total_expenses = 0.00;

    public $exchange_rate = 36.5;
    public string $notes = '';

    public array $nio = [
        '1000' => '', '500' => '', '200' => '', '100' => '',
        '50' => '', '20' => '', '10' => '', '5' => '', '1' => '',
        '0_50' => '', '0_25' => ''
    ];
    public array $usd = [
        '100' => '', '50' => '', '20' => '', '10' => '', '5' => '', '1' => ''
    ];

    public function getSystemBalanceProperty(): float
    {
        return $this->initial_balance + $this->total_incomes - $this->total_expenses;
    }

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

        if (round($difference, 2) !== 0.00 && empty(trim($this->notes))) {
            $this->addError('notes', 'Es obligatorio justificar el motivo del sobrante o faltante en caja.');
            $this->error('Falta justificación del descuadre.');
            return;
        }

        $activeRegister = DB::table('cash_registers')
            ->where('user_id', auth()->id())
            ->where('status', 'Abierta')
            ->first();

        if ($activeRegister) {
            DB::table('cash_registers')->where('id', $activeRegister->id)->update([
                'closed_at' => now(),
                'cash_sales' => $this->total_incomes,
                'cash_out' => $this->total_expenses,
                'system_balance' => $this->system_balance,
                'physical_balance' => $physical_balance,
                'difference' => $difference,
                'status' => 'Cerrada',
                'notes' => $this->notes,
                'updated_at' => now()
            ]);

            $this->success('Caja cerrada con éxito. Turno finalizado.');
        } else {
            $this->error('No se encontró ninguna caja abierta para cerrar.');
        }

       $this->nio = [
            '1000' => '', '500' => '', '200' => '', '100' => '',
            '50' => '', '20' => '', '10' => '', '5' => '', '1' => '',
            '0_50' => '', '0_25' => ''
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
            $valorMatematico = (float) str_replace('_', '.', $value);

            $total += ($valorMatematico * (int)($qty ?: 0));
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

        $activeRegister = DB::table('cash_registers')
            ->where('user_id', auth()->id())
            ->where('status', 'Abierta')
            ->first();

        if ($activeRegister) {
            $this->initial_balance = (float)$activeRegister->initial_balance;
            $this->total_incomes = (float) $activeRegister->cash_sales;
            $this->total_expenses = (float) $activeRegister->cash_out;

        } else {
            $this->initial_balance = 0.00;
            $this->total_incomes = 0.00;
            $this->total_expenses = 0.00;
        }

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

<div class="p-6 bg-gray-50/50 min-h-screen">
    <x-header title="Arqueo y Cierre de Caja" subtitle="Conteo físico de efectivo y cuadre de turno" separator class="mb-6" />

    @if(!$isBoxOpen)
        <x-alert title="Caja cerrada" description="No hay turnos activos para auditar." icon="o-lock-closed" class="alert-warning mb-6 shadow-sm" />
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- LADO IZQUIERDO: CONTEO FÍSICO --}}
        <div class="lg:col-span-8 grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- Córdobas --}}
            <x-card title="Billetes y Monedas (NIO)" shadow class="bg-white border-t-4 border-indigo-500">
                <div class="grid grid-cols-2 gap-3">
                    @foreach($nio as $llave => $cantidad)
                        @php
                            $valorReal = (float) str_replace('_', '.', $llave);
                            $textoEtiqueta = $valorReal < 1 ? ($valorReal * 100) . ' Centavos' : 'C$ ' . $valorReal;
                        @endphp
                        <x-input label="{{ $textoEtiqueta }}" wire:model.live="nio.{{ $llave }}" type="number" min="0" placeholder="0" :disabled="!$isBoxOpen" class="bg-gray-50" />
                    @endforeach
                </div>
            </x-card>

            {{-- Dólares --}}
            <x-card title="Dólares (USD)" shadow class="bg-white border-t-4 border-success">
                <div class="grid grid-cols-1 gap-3">
                    @foreach($usd as $denominacion => $cantidad)
                        <x-input label="Billete de ${{ $denominacion }}" wire:model.live="usd.{{ $denominacion }}" type="number" min="0" placeholder="0" :disabled="!$isBoxOpen" class="bg-gray-50" />
                    @endforeach
                </div>
                <div class="mt-6 p-4 bg-green-50 rounded-xl flex justify-between items-center border border-green-100">
                    <span class="font-bold text-green-700 text-sm">Subtotal USD:</span>
                    <span class="font-black text-green-700">$ {{ number_format($total_usd, 2) }}</span>
                </div>
                <div class="mt-4">
                    <x-input label="Tasa de Cambio Oficial" wire:model.live="exchange_rate" type="number" step="0.01" icon="o-arrows-right-left" :disabled="!$isBoxOpen" class="font-bold" />
                </div>
            </x-card>
        </div>

        {{-- LADO DERECHO: RESUMEN Y CIERRE --}}
        <div class="lg:col-span-4 space-y-6">

            {{-- Panel de Resultados --}}
            <div class="bg-white rounded-3xl shadow-xl border border-gray-100 p-6 space-y-6 sticky top-4">

                <div class="space-y-4">
                    <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest">Resumen del Sistema</h3>
                    <div class="space-y-2 text-sm font-medium">
                        <div class="flex justify-between"><span>Apertura:</span> <span>C$ {{ number_format($initial_balance, 2) }}</span></div>
                        <div class="flex justify-between text-success"><span>(+) Ventas:</span> <span>C$ {{ number_format($total_incomes, 2) }}</span></div>
                        <div class="flex justify-between text-error border-b border-gray-100 pb-2"><span>(-) Gastos:</span> <span>C$ {{ number_format($total_expenses, 2) }}</span></div>
                        <div class="flex justify-between pt-2 text-lg font-black text-gray-800"><span>Esperado:</span> <span>C$ {{ number_format($system_balance, 2) }}</span></div>
                    </div>
                </div>

                <div class="p-6 rounded-2xl {{ $difference == 0 ? 'bg-success/10 border-2 border-success/20' : 'bg-error/10 border-2 border-error/20' }}">
                    <p class="text-[10px] font-black uppercase text-gray-400 mb-1">Diferencia Final</p>
                    <p class="text-4xl font-black {{ $difference == 0 ? 'text-success' : 'text-error' }}">
                        C$ {{ number_format($difference, 2) }}
                    </p>
                    <p class="text-xs font-bold mt-2">{{ $difference == 0 ? '¡Caja Cuadrada!' : ($difference > 0 ? 'Sobrante en caja' : 'Faltante en caja') }}</p>
                </div>

                <x-textarea label="Observaciones (Obligatorio si hay descuadre)" wire:model="notes" rows="2" class="bg-gray-50" :disabled="!$isBoxOpen" />

                <x-button label="Procesar Cierre de Turno"
                          icon="o-lock-closed"
                          class="btn-primary w-full py-4 font-black text-white shadow-lg hover:scale-105 transition-transform"
                          wire:click="closeRegister"
                          wire:confirm="¿Confirmas el cierre de caja? Esta acción no se puede deshacer."
                          :disabled="!$isBoxOpen" />
            </div>
        </div>
    </div>
</div>
