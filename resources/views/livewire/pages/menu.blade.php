<?php

use Livewire\Volt\Component;
use Mary\Traits\Toast;
use App\Models\CashRegister; // Asegúrate de tener el modelo creado

new class extends Component
{
    use Toast;

    // Inicializamos con valores por defecto para evitar el error de "Undefined"
    public float $monto_inicial = 0;
    public float $tasa_cambio = 36.65;
    public string $fecha = '';
    public bool $cajaAbierta = false;

    public function mount()
    {
        $this->fecha = now()->format('d/m/Y');

        // Verificamos si ya existe una caja abierta en tu BD real
        $this->cajaAbierta = CashRegister::where('user_id', auth()->id())
            ->where('status', 'Abierta')
            ->exists();
    }

    public function abrirCaja()
    {
        $this->validate([
            'monto_inicial' => 'required|numeric|min:0',
            'tasa_cambio' => 'required|numeric|min:1',
        ]);

        if (CashRegister::where('user_id', auth()->id())->where('status', 'Abierta')->exists()) {
            $this->cajaAbierta = true;
            $this->error('Ya existe una caja abierta para este usuario.', position: 'toast-top toast-center');
            return;
        }

        // PERSISTENCIA REAL EN TU BD
        CashRegister::create([
            'user_id' => auth()->id(),
            'opened_at' => now(),
            'initial_balance' => $this->monto_inicial,
            'system_balance' => $this->monto_inicial,
            'status' => 'Abierta',
            'notes' => 'Tasa de cambio aplicada: ' . $this->tasa_cambio
        ]);

        $this->cajaAbierta = true;
        $this->success("Caja abierta con C$ {$this->monto_inicial}", position: 'toast-top toast-center');
    }
};
?>

<div class="p-6">
    @if(session('cash_required'))
        <x-alert title="Caja requerida"
                 description="{{ session('cash_required') }}"
                 icon="o-exclamation-triangle"
                 class="alert-warning mb-6" />
    @endif

    {{-- Encabezado de Bienvenida --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-base-content italic">Panel de Control</h1>
            <p class="text-gray-500 text-sm">Bienvenido al sistema, {{ auth()->user()->name }}</p>
        </div>
        <div class="stats shadow bg-base-100 border-l-4 border-secondary">
            <div class="stat">
                <div class="stat-title text-xs uppercase font-bold text-secondary">Fecha de Trabajo</div>
                {{-- Aquí es donde daba el error; ahora $fecha siempre tiene valor --}}
                <div class="stat-value text-lg">{{ $fecha }}</div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Lado Izquierdo: Formulario o Estado --}}
        <div class="lg:col-span-1">
            @if(!$cajaAbierta)
                <x-card title="Apertura de Caja" subtitle="Configuración de turno" shadow class="border-t-4 border-primary bg-base-100">
                    <x-form wire:submit="abrirCaja">
                        <x-input
                            label="Monto Inicial (NIO)"
                            wire:model="monto_inicial"
                            prefix="C$"
                            type="number"
                            step="0.01"
                            hint="Efectivo disponible en caja"
                        />

                        <x-input
                            label="Tasa de Cambio (USD)"
                            wire:model="tasa_cambio"
                            prefix="$"
                            type="number"
                            step="0.01"
                        />

                        <x-slot:actions>
                            <x-button label="Iniciar Operaciones" icon="o-rocket-launch" class="btn-primary w-full" type="submit" spinner="abrirCaja" />
                        </x-slot:actions>
                    </x-form>
                </x-card>
            @else
                <x-card title="Caja en Operación" subtitle="Turno activo" shadow class="border-t-4 border-success bg-base-100 text-center">
                    <x-icon name="o-check-badge" class="w-16 h-16 text-success mb-4" />
                    <p class="font-bold">La caja se encuentra abierta.</p>
                    <p class="text-xs text-gray-500">Ya puedes proceder con las ventas y pedidos.</p>
                </x-card>
            @endif
        </div>

        {{-- Lado Derecho: Estadísticas --}}
        <div class="lg:col-span-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-stat title="Ventas del Día" value="C$ 0.00" icon="o-currency-dollar" class="bg-base-100 shadow border-b-4 border-success" />
                <x-stat title="Ordenes Pendientes" value="0" icon="o-document-text" class="bg-base-100 shadow border-b-4 border-warning" />
            </div>

            @if(!$cajaAbierta)
                <div class="mt-6 p-10 border-2 border-dashed border-base-300 rounded-2xl flex flex-col items-center justify-center text-gray-400">
                    <x-icon name="o-presentation-chart-bar" class="w-12 h-12 mb-2" />
                    <p>La caja debe estar abierta para visualizar estadísticas.</p>
                </div>
            @else
                {{-- Aquí puedes poner una tabla de ventas recientes o gráficos --}}
                <div class="mt-6">
                    <x-card title="Actividad Reciente" shadow>
                        <p class="text-sm text-gray-500 italic">No hay movimientos registrados en este turno.</p>
                    </x-card>
                </div>
            @endif
        </div>
    </div>
</div>
