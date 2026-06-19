<?php

use Livewire\Volt\Component;
use App\Models\CashRegister;
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
            $this->error('No puedes registrar egresos porque no hay ninguna caja abierta.');
            return;
        }

        $this->validate([
            'concept' => 'required|string|max:255|min:5',
            'amount' => 'required|numeric|min:1',
        ], [
            'concept.required' => 'Debes especificar el concepto del gasto.',
            'amount.min' => 'El monto debe ser mayor a 0.'
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

        $this->success('Egreso registrado correctamente.', position: 'toast-top toast-center');
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
            $this->error('Egreso no encontrado o ya no se puede eliminar.');
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
                ['key' => 'id', 'label' => 'ID'],
                ['key' => 'concept', 'label' => 'CONCEPTO / DESCRIPCIÓN'],
                ['key' => 'amount', 'label' => 'MONTO'],
                ['key' => 'date', 'label' => 'HORA'],
                ['key' => 'actions', 'label' => '', 'sortable' => false]
            ]
        ];
    }
}; ?>

<div class="p-6 bg-gray-50/50 min-h-screen">
    <x-header title="Salidas de Dinero (Egresos)" subtitle="Registrar gastos operativos de Imprenta Minerva" separator class="mb-6" />

    @if(!$isBoxOpen)
        <x-alert title="Caja Cerrada" description="No hay turnos activos para registrar gastos." icon="o-lock-closed" class="alert-error mb-6 shadow-sm" />
    @endif

    {{-- Resumen de Gastos --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-stat title="Total Gastos (Turno)"
                value="C$ {{ number_format($totalExpenses, 2) }}"
                icon="o-arrow-trending-down"
                class="bg-white border-l-4 border-error shadow-sm hover:shadow-md transition-shadow" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Formulario de Egreso --}}
        <x-card title="Registrar nuevo egreso" icon="o-minus-circle" shadow class="bg-white border-t-4 border-error">
            <x-form wire:submit="saveExpense">
                <x-input label="Concepto / Descripción *" wire:model="concept" placeholder="Ej: Compra de materiales..." icon="o-pencil-square" :disabled="!$isBoxOpen" class="bg-gray-50" />
                <x-input label="Monto gastado *" wire:model="amount" type="number" step="0.01" prefix="C$" placeholder="0.00" :disabled="!$isBoxOpen" class="bg-gray-50 font-black text-error" />
                <x-slot:actions>
                    <x-button label="Confirmar Salida de Dinero" type="submit" icon="o-check-circle" class="btn-error text-white w-full shadow-lg hover:scale-105 transition-transform" spinner="saveExpense" :disabled="!$isBoxOpen" />
                </x-slot:actions>
            </x-form>
        </x-card>

        {{-- Tabla de Movimientos --}}
        <div class="lg:col-span-2">
            <x-card title="Movimientos Recientes" icon="o-list-bullet" shadow class="bg-white min-h-[400px]">
                <x-table :headers="$headers" :rows="$expenses" class="table-sm">
                    @scope('cell_id', $expense)
                        <span class="text-[10px] font-black text-gray-400">#{{ str_pad($expense->id, 3, '0', STR_PAD_LEFT) }}</span>
                    @endscope
                    @scope('cell_concept', $expense)
                        <div class="font-bold text-gray-800">{{ $expense->concept }}</div>
                    @endscope
                    @scope('cell_amount', $expense)
                        <span class="font-black text-error">- C$ {{ number_format($expense->amount, 2) }}</span>
                    @endscope
                    @scope('cell_date', $expense)
                        <span class="text-xs font-medium text-gray-500">{{ date('h:i A', strtotime($expense->movement_date)) }}</span>
                    @endscope
                    @scope('cell_actions', $expense)
                        <x-button icon="o-trash" wire:click="deleteExpense({{ $expense->id }})" wire:confirm="¿Seguro que deseas eliminar este egreso?" class="btn-sm btn-circle btn-ghost text-gray-400 hover:text-error" spinner />
                    @endscope
                    <x-slot:empty>
                        <div class="text-center py-10 text-gray-400">
                            <x-icon name="o-check-badge" class="w-10 h-10 inline mb-2 text-success" />
                            <p>No hay gastos registrados en este turno.</p>
                        </div>
                    </x-slot:empty>
                </x-table>
            </x-card>
        </div>
    </div>
</div>
