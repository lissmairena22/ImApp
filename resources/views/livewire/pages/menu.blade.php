<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Mary\Traits\Toast;
use App\Models\CashRegister;
use App\Models\Invoice;
use App\Models\Order;

new class extends Component
{
    use Toast;

    public float $monto_inicial = 0;
    public float $tasa_cambio = 36.5;
    public string $fecha = '';
    public bool $cajaAbierta = false;
    public ?CashRegister $cajaActual = null;

    public function mount()
    {
        $this->fecha = now()->format('d/m/Y');

        $this->cajaActual = CashRegister::where('user_id', auth()->id())
            ->where('status', 'Abierta')
            ->first();

        $this->cajaAbierta = $this->cajaActual !== null;
    }

    public function abrirCaja()
    {
        $this->validate([
            'monto_inicial' => 'required|numeric|min:0',
            'tasa_cambio' => 'required|numeric|min:1',
        ]);

        if (CashRegister::where('user_id', auth()->id())->where('status', 'Abierta')->exists()) {
            $this->error('Ya existe una caja abierta para este usuario.', position: 'toast-top toast-center');
            return;
        }

        $this->cajaActual = CashRegister::create([
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

    #[Computed]
    public function ventasDelDia()
    {
        return Invoice::whereDate('created_at', today())
            ->where('status', '!=', 'Anulada')
            ->sum('total');
    }

    #[Computed]
    public function ordenesPendientes()
    {
        return Order::where('status', 'Pendiente')->count();
    }

    #[Computed]
    public function totalEstimadoCaja()
    {
        if (!$this->cajaActual) return 0;
        return $this->cajaActual->initial_balance + $this->ventasDelDia;
    }

    #[Computed]
    public function actividadReciente()
    {
        return Invoice::with('client')
            ->whereDate('created_at', today())
            ->where('status', '!=', 'Anulada')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
    }
};
?>

<div class="p-6">
    @if(session('cash_required'))
        <x-alert title="Caja requerida"
                 description="{{ session('cash_required') }}"
                 icon="o-exclamation-triangle"
                 class="alert-warning mb-6 shadow-sm" />
    @endif

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-base-content tracking-tight">Panel de Control</h1>
            <p class="text-gray-500 font-medium">Bienvenido de nuevo, <span class="text-primary">{{ auth()->user()->name }}</span></p>
        </div>
        <div class="stats shadow-sm bg-white border-l-4 border-secondary hover:shadow-md transition-all duration-300">
            <div class="stat py-2 px-6">
                <div class="stat-title text-xs uppercase font-black text-secondary tracking-widest">Fecha de Trabajo</div>
                <div class="stat-value text-xl">{{ $fecha }}</div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-1">
            @if(!$cajaAbierta)
                <x-card title="Apertura de Caja" subtitle="Configuración de turno" shadow class="border-t-4 border-primary bg-white hover:shadow-lg transition-shadow duration-300">
                    <x-form wire:submit="abrirCaja">
                        <x-input label="Monto Inicial (NIO)" wire:model="monto_inicial" prefix="C$" type="number" step="0.01" hint="Efectivo físico disponible" class="font-bold text-lg"/>
                        <x-input label="Tasa de Cambio (USD)" wire:model="tasa_cambio" prefix="$" type="number" step="0.01" />
                        <x-slot:actions>
                            <x-button label="Iniciar Operaciones" icon="o-rocket-launch" class="btn-primary w-full text-white shadow-md hover:-translate-y-1 transition-transform" type="submit" spinner="abrirCaja" />
                        </x-slot:actions>
                    </x-form>
                </x-card>
            @else
                <x-card shadow class="border-t-4 border-success bg-white hover:shadow-lg transition-all duration-300 overflow-hidden relative">
                    <div class="absolute top-0 right-0 -mr-8 -mt-8 opacity-10">
                        <x-icon name="o-banknotes" class="w-40 h-40 text-success" />
                    </div>

                    <div class="text-center mb-6 relative z-10">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-success/10 text-success mb-3">
                            <x-icon name="o-check-badge" class="w-10 h-10" />
                        </div>
                        <p class="font-black text-xl text-gray-800">Caja Abierta</p>
                        <p class="text-xs font-bold text-success uppercase tracking-widest mt-1 animate-pulse">● Turno Activo</p>
                    </div>

                    <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100 space-y-3 relative z-10">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-500 font-medium">Fondo Inicial:</span>
                            <span class="font-bold text-gray-700">C$ {{ number_format($cajaActual->initial_balance, 2) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-500 font-medium">Ventas (Día):</span>
                            <span class="font-bold text-success">+ C$ {{ number_format($this->ventasDelDia, 2) }}</span>
                        </div>
                        <hr class="border-dashed border-gray-200 my-2">
                        <div class="flex justify-between items-end">
                            <span class="text-gray-800 font-black text-sm uppercase">Total en Caja</span>
                            <span class="font-black text-2xl text-primary leading-none">C$ {{ number_format($this->totalEstimadoCaja, 2) }}</span>
                        </div>
                    </div>
                </x-card>
            @endif
        </div>

        <div class="lg:col-span-2 flex flex-col gap-6">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-stat
                    title="Ventas del Día"
                    value="C$ {{ number_format($this->ventasDelDia, 2) }}"
                    icon="o-currency-dollar"
                    class="bg-white shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-300 border-b-4 border-success cursor-pointer"
                    description="Ingresos registrados hoy" />

                <x-stat
                    title="Ordenes Pendientes"
                    value="{{ $this->ordenesPendientes }}"
                    icon="o-document-text"
                    class="bg-white shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-300 border-b-4 border-warning cursor-pointer"
                    description="Trabajos en cola de producción"/>
            </div>

            @if($cajaAbierta)
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

            <x-button
                link="/ventas"
                icon="o-shopping-cart"
                label="Venta Rápida"
                class="btn-success text-white btn-sm shadow hover:shadow-md hover:-translate-y-0.5 transition-all"
            />

            <x-button
                link="/pedidos"
                icon="o-document-plus"
                label="Nuevo Pedido"
                class="btn-info text-white btn-sm shadow hover:shadow-md hover:-translate-y-0.5 transition-all"
            />

            <x-button
                link="/egresos"
                icon="o-arrow-trending-down"
                label="Egreso"
                class="btn-neutral btn-sm shadow hover:shadow-md hover:-translate-y-0.5 transition-all"
            />

            <x-button
                link="/arqueo"
                icon="o-lock-closed"
                label="Cerrar Caja"
                class="btn-error text-white btn-sm shadow hover:shadow-md hover:-translate-y-0.5 transition-all"
            />

</div>

                <x-card title="Actividad Reciente" shadow class="bg-white hover:shadow-md transition-shadow duration-300">
                    <div class="space-y-1">
                        @forelse($this->actividadReciente as $actividad)
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-xl transition-colors border-b last:border-0 border-gray-100">
                                <div class="flex items-center gap-4">
                                    <div class="bg-success/10 p-3 rounded-full">
                                        <x-icon name="o-banknotes" class="w-6 h-6 text-success" />
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-800 text-sm">Venta #{{ $actividad->invoice_number }}</p>
                                        <p class="text-xs text-gray-500 flex items-center gap-1">
                                            <x-icon name="o-user" class="w-3 h-3" />
                                            {{ $actividad->client->name ?? 'Cliente General' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-black text-success">C$ {{ number_format($actividad->total, 2) }}</p>
                                    <p class="text-xs text-gray-400 font-medium">{{ $actividad->created_at->format('h:i A') }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-8 flex flex-col items-center justify-center">
                                <div class="bg-gray-100 p-4 rounded-full mb-3">
                                    <x-icon name="o-inbox" class="w-8 h-8 text-gray-400" />
                                </div>
                                <p class="font-bold text-gray-600">No hay movimientos recientes</p>
                                <p class="text-sm text-gray-400">Las ventas y pedidos de este turno aparecerán aquí.</p>
                            </div>
                        @endforelse
                    </div>
                </x-card>
            @else
                <div class="mt-2 p-12 border-2 border-dashed border-gray-200 bg-gray-50/50 rounded-3xl flex flex-col items-center justify-center text-gray-400 text-center">
                    <x-icon name="o-lock-closed" class="w-16 h-16 mb-4 text-gray-300" />
                    <h3 class="font-bold text-lg text-gray-600">Sistema Bloqueado</h3>
                    <p class="text-sm">Debes abrir la caja en el panel izquierdo para comenzar a operar.</p>
                </div>
            @endif

        </div>
    </div>
</div>
