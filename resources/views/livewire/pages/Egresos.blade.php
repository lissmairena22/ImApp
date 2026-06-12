<?php

use Livewire\Volt\Component;
use App\Models\CashRegister;
use App\Models\Product;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use Toast;

    public string $concept = '';
    public $amount;

    public function saveExpense()
    {
        $activeRegister = DB::table('cash_registers')
            ->where('user_id', auth()->id())
            ->where('status', 'Abierta')
            ->first();

        if (!$activeRegister) {
            $this->error('No puedes registrar egresos porque no hay ninguna caja abierta actualmente.');
            return;
        }

        $this->validate([
            'concept' => 'required|string|max:255|min:5',
            'amount' => 'required|numeric|min:1',
        ], [
            'concept.required' => 'Debes especificar en qué se gastó el dinero.',
            'concept.min' => 'El concepto debe ser más descriptivo (mínimo 5 letras).',
            'amount.required' => 'El monto del gasto es obligatorio.',
            'amount.min' => 'El monto debe ser mayor a C$ 0.'
        ]);

        DB::transaction(function () use ($activeRegister) {
            DB::table('cash_movements')->insert([
                'cash_register_id' => $activeRegister->id,
                'user_id' => auth()->id() ?: 1,
                'type' => 'Egreso',
                'concept' => $this->concept,
                'amount' => $this->amount,
                'movement_date' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('cash_registers')->where('id', $activeRegister->id)->update([
                'cash_out' => DB::raw('cash_out + ' . (float) $this->amount),
                'system_balance' => DB::raw('system_balance - ' . (float) $this->amount),
                'updated_at' => now(),
            ]);
        });

        $this->success('Egreso registrado correctamente y descontado de la caja.');
         $this->reset(['concept', 'amount']);
    }

    public function deleteExpense($id)
    {
        $expense = DB::table('cash_movements')
            ->join('cash_registers', 'cash_registers.id', '=', 'cash_movements.cash_register_id')
            ->where('cash_movements.id', $id)
            ->where('cash_movements.type', 'Egreso')
            ->where('cash_registers.user_id', auth()->id())
            ->where('cash_registers.status', 'Abierta')
            ->select('cash_movements.*')
            ->first();

        if (!$expense) {
            $this->error('Egreso no encontrado.');
            return;
        }

        DB::transaction(function () use ($expense) {
            DB::table('cash_movements')->where('id', $expense->id)->delete();
            DB::table('cash_registers')->where('id', $expense->cash_register_id)->update([
                'cash_out' => DB::raw('cash_out - ' . (float) $expense->amount),
                'system_balance' => DB::raw('system_balance + ' . (float) $expense->amount),
                'updated_at' => now(),
            ]);
        });

        $this->success('Egreso eliminado del sistema.');
    }

    public function with(): array
    {
        $activeRegister = DB::table('cash_registers')
            ->where('user_id', auth()->id())
            ->where('status', 'Abierta')
            ->first();

        $expenses = [];
        $totalExpenses = 0;

        if ($activeRegister) {
            $expenses = DB::table('cash_movements')
                ->where('cash_register_id', $activeRegister->id)
                ->where('type', 'Egreso')
                ->orderBy('id', 'desc')
                ->get();

            $totalExpenses = $expenses->sum('amount');
        }

        return [
            'expenses' => $expenses,
            'totalExpenses' => $totalExpenses,
            'isBoxOpen' => $activeRegister ? true : false,
            'headers' => [
                ['key' => 'id', 'label' => 'N°'],
                ['key' => 'concept', 'label' => 'CONCEPTO / DESCRIPCIÓN'],
                ['key' => 'amount', 'label' => 'MONTO'],
                ['key' => 'date', 'label' => 'HORA'],
                ['key' => 'actions', 'label' => '', 'sortable' => false]
            ]
        ];
    }
}; ?>

<div>
    <x-header title="Salidas de Dinero (Egresos)" subtitle="Registrar gastos rápidos de caja de Imprenta Minerva" separator />

    @if(!$isBoxOpen)
        <x-alert title="Caja Cerrada" description="Debes abrir el turno en el Panel de Control antes de poder registrar salidas de dinero." icon="o-exclamation-triangle" class="alert-warning mb-6" />
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <x-card title="Nuevo Gasto" subtitle="El monto se restará del efectivo final" icon="o-minus-circle" class="h-fit">
            <x-form wire:submit="saveExpense">
                <x-input label="¿En qué se gastó el dinero?" wire:model="concept" placeholder="Ej: Compra de café y azúcar para personal" icon="o-shopping-bag" :disabled="!$isBoxOpen" />

                <x-input label="Monto gastado (C$)" wire:model="amount" type="number" step="0.01" prefix="C$" placeholder="0.00" :disabled="!$isBoxOpen" />

                <x-slot:actions>
                    <x-button label="Registrar Salida" type="submit" icon="o-check" class="btn-primary w-full" spinner="saveExpense" :disabled="!$isBoxOpen" />
                </x-slot:actions>
            </x-form>
        </x-card>

        <div class="lg:col-span-2">
            <x-card title="Gastos del Turno Actual" subtitle="Total acumulado en salidas: C$ {{ number_format($totalExpenses, 2) }}" icon="o-list-bullet">

                <x-table :headers="$headers" :rows="$expenses">
                    @scope('cell_id', $expense)
                        <span class="text-xs text-gray-400">#{{ $expense->id }}</span>
                    @endscope

                    @scope('cell_concept', $expense)
                        <div class="font-bold text-gray-700">{{ $expense->concept }}</div>
                    @endscope

                    @scope('cell_amount', $expense)
                        <span class="font-bold text-error">
                            - C$ {{ number_format($expense->amount, 2) }}
                        </span>
                    @endscope

                    @scope('cell_date', $expense)
                        <span class="text-xs text-gray-500">
                            {{ date('g:i A', strtotime($expense->movement_date)) }}
                        </span>
                    @endscope

                    @scope('cell_actions', $expense)
                        <x-button icon="o-trash" wire:click="deleteExpense({{ $expense->id }})" wire:confirm="¿Seguro que deseas eliminar este registro de gasto?" class="btn-xs btn-circle btn-ghost text-error" spinner />
                    @endscope

                    <x-slot:empty>
                        <div class="text-center p-4 text-gray-400">
                            <x-icon name="o-check-badge" class="w-8 h-8 inline mb-2 text-success" />
                            <p>No se han registrado salidas de dinero en este turno.</p>
                        </div>
                    </x-slot:empty>
                </x-table>

            </x-card>
        </div>

    </div>
</div>
