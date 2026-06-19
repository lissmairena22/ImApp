<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Order;
use Mary\Traits\Toast;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;
    use Toast;

    public string $search = '';
    public string $filter = 'Activas';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function setFilter($filterName)
    {
        $this->filter = $filterName;
        $this->resetPage();
    }

    public function deliverOrder($orderId)
    {
        $order = Order::findOrFail($orderId);

        if (in_array($order->status, ['Entregado', 'Cancelado'])) {
            $this->error('Esta orden ya no se puede modificar');
            return;
        }

        $order->update(['status' => 'Entregado']);
        $this->success('Orden marcada como entregada con éxito');
    }

    public function cancelOrder($orderId)
    {
        $order = Order::findOrFail($orderId);

        if (in_array($order->status, ['Terminado', 'Entregado', 'Cancelado'])) {
            $this->error('No se puede cancelar esta orden');
            return;
        }

        $order->update(['status' => 'Cancelado']);

        if ($order->invoice) {
            $order->invoice->update(['status' => 'Anulada']);
        }

        $this->success('Orden cancelada correctamente');
    }

    public function with(): array
    {
        $statusFilter = match($this->filter) {
            'Activas' => ['Pendiente', 'EnProceso'],
            'Entregadas' => ['Entregado'],
            'Canceladas' => ['Cancelado'],
            default => ['Pendiente', 'EnProceso', 'Entregado', 'Cancelado']
        };

        $orders = Order::query()
            ->with(['client', 'user', 'invoice'])
            ->whereIn('status', $statusFilter)
            ->when($this->search, function($q) {
                $q->whereHas('client', function($query) {
                    $query->where('name', 'like', "%{$this->search}%");
                })
                ->orWhere('id', 'like', "%{$this->search}%");
            })
            ->orderBy('estimated_delivery_date', 'asc')
            ->orderBy('id', 'desc')
            ->paginate(10);

        $stats = [
            'pendientes' => Order::where('status', 'Pendiente')->count(),
            'enProceso' => Order::where('status', 'EnProceso')->count(),
            'entregadas' => Order::where('status', 'Entregado')->whereDate('updated_at', today())->count(),
            'totalActivas' => Order::whereIn('status', ['Pendiente', 'EnProceso'])->count(),
        ];

        return [
            'orders' => $orders,
            'stats' => $stats,
            'headers' => [
                ['key' => 'id', 'label' => 'ORDEN'],
                ['key' => 'cliente', 'label' => 'CLIENTE'],
                ['key' => 'delivery_date', 'label' => 'ENTREGA'],
                ['key' => 'factura', 'label' => 'FACTURA / PAGO'],
                ['key' => 'total', 'label' => 'TOTAL'],
                ['key' => 'status', 'label' => 'ESTADO'],
                ['key' => 'actions', 'label' => '', 'sortable' => false]
            ]
        ];
    }
};
?>

<div>
    <x-header title="Control de Órdenes y Pedidos" subtitle="Seguimiento de producción y entregas">
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <x-stat title="En Espera" value="{{ $stats['pendientes'] }}" icon="o-clock" class="bg-white border-l-4 border-warning shadow-sm hover:shadow-md transition-shadow" />
        <x-stat title="En Producción" value="{{ $stats['enProceso'] }}" icon="o-cog" class="bg-white border-l-4 border-info shadow-sm hover:shadow-md transition-shadow" />
        <x-stat title="Entregadas Hoy" value="{{ $stats['entregadas'] }}" icon="o-gift" class="bg-white border-l-4 border-success shadow-sm hover:shadow-md transition-shadow" />
        <x-stat title="Total Activas" value="{{ $stats['totalActivas'] }}" icon="o-document-duplicate" class="bg-gray-50 border-l-4 border-gray-800 shadow-sm" />
    </div>

    <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-white p-4 rounded-t-2xl shadow-sm border-b border-gray-100">
        <div class="flex gap-2 w-full md:w-auto overflow-x-auto pb-2 md:pb-0">
            <x-button label="Todas" wire:click="setFilter('Todas')" class="btn-sm {{ $filter === 'Todas' ? 'btn-neutral' : 'btn-ghost border border-gray-200' }}" />
            <x-button label="Solo Activas" wire:click="setFilter('Activas')" class="btn-sm {{ $filter === 'Activas' ? 'btn-warning text-white' : 'btn-ghost border border-gray-200' }}" />
            <x-button label="Entregadas" wire:click="setFilter('Entregadas')" class="btn-sm {{ $filter === 'Entregadas' ? 'btn-success text-white' : 'btn-ghost border border-gray-200' }}" />
            <x-button label="Canceladas" wire:click="setFilter('Canceladas')" class="btn-sm {{ $filter === 'Canceladas' ? 'btn-error text-white' : 'btn-ghost border border-gray-200' }}" />
        </div>

        <div class="w-full md:w-96 relative">
            <x-input icon="o-magnifying-glass"
                     placeholder="Buscar cliente u orden..."
                     wire:model.live.debounce.500ms="search"
                     clearable
                     class="input-sm w-full bg-gray-50" />
        </div>
    </div>

    <x-card class="rounded-t-none shadow-sm border-t-0">
        <x-table :headers="$headers" :rows="$orders" with-pagination class="table-sm">

            @scope('cell_id', $order)
                <span class="font-black text-gray-700">ORD-{{ str_pad($order->id, 3, '0', STR_PAD_LEFT) }}</span>
            @endscope

            @scope('cell_cliente', $order)
                <div>
                    <div class="font-bold text-gray-800">{{ $order->client->name ?? 'Cliente General' }}</div>
                    <div class="text-xs text-gray-500 flex items-center gap-1 mt-0.5">
                        <x-icon name="o-phone" class="w-3 h-3" />
                        {{ $order->client->phone ?? 'Sin teléfono' }}
                    </div>
                </div>
            @endscope

            @scope('cell_delivery_date', $order)
                @php
                   $fechaEntrega = \Carbon\Carbon::parse($order->estimated_delivery_date);
                    $hoy = \Carbon\Carbon::today();
                    $esPasado = $fechaEntrega->isPast() && !$fechaEntrega->isToday();
                    $esHoy = $fechaEntrega->isToday();
                @endphp

                @if($order->status === 'Entregado' || $order->status === 'Cancelado')
                    <span class="text-gray-400 text-sm line-through">{{ $fechaEntrega->format('d/m/Y') }}</span>
                @elseif($esPasado)
                    <div class="flex items-center gap-1 text-error font-bold tooltip" data-tip="¡Pedido atrasado!">
                        <x-icon name="o-fire" class="w-4 h-4" />
                        {{ $fechaEntrega->format('d/m/Y') }}
                    </div>
                @elseif($esHoy)
                    <div class="flex items-center gap-1 text-warning font-bold tooltip" data-tip="Entregar hoy">
                        <x-icon name="o-exclamation-circle" class="w-4 h-4" />
                        Hoy
                    </div>
                @else
                    <span class="text-gray-600 font-medium">{{ $fechaEntrega->format('d/m/Y') }}</span>
                @endif
            @endscope

            @scope('cell_factura', $order)
                @if($order->invoice)
                    <div>
                        <div class="font-bold text-sm">{{ $order->invoice->invoice_number }}</div>
                        <div class="text-[10px] uppercase font-bold {{ $order->invoice->status === 'Pagada' ? 'text-success' : 'text-error' }}">
                            {{ $order->invoice->status }}
                        </div>
                    </div>
                @else
                    <span class="text-gray-400 text-sm italic">Sin factura</span>
                @endif
            @endscope

            @scope('cell_total', $order)
                <span class="font-black text-primary">
                    C$ {{ number_format($order->estimated_price, 2) }}
                </span>
            @endscope

            @scope('cell_status', $order)
                @if($order->status === 'Entregado')
                    <x-badge value="Entregado" class="badge-success text-white badge-sm font-bold" icon="o-check-circle" />
                @elseif($order->status === 'Cancelado')
                    <x-badge value="Cancelado" class="badge-error text-white badge-sm font-bold" icon="o-x-circle" />
                @elseif($order->status === 'EnProceso')
                    <x-badge value="En Producción" class="badge-info text-white badge-sm font-bold animate-pulse" icon="o-cog" />
                @else
                    <x-badge value="En Espera" class="badge-warning text-white badge-sm font-bold" icon="o-clock" />
                @endif
            @endscope

            @scope('cell_actions', $order)
                <div class="flex justify-end gap-1">
                    @if(!in_array($order->status, ['Entregado', 'Cancelado']))
                        <div class="tooltip tooltip-left" data-tip="Marcar como Entregado">
                            <x-button icon="o-check"
                                      wire:click="deliverOrder({{ $order->id }})"
                                      wire:confirm="¿Confirmas que el cliente ya recibió su pedido?"
                                      class="btn-sm btn-circle btn-success text-white shadow-sm hover:scale-110 transition-transform" />
                        </div>
                        <div class="tooltip tooltip-left" data-tip="Cancelar Orden">
                            <x-button icon="o-trash"
                                      wire:click="cancelOrder({{ $order->id }})"
                                      wire:confirm="ATENCIÓN: ¿Seguro que deseas cancelar esta orden y anular su factura?"
                                      class="btn-sm btn-circle btn-error text-white shadow-sm hover:scale-110 transition-transform" />
                        </div>
                    @else
                        <span class="text-[10px] text-gray-400 uppercase font-bold tracking-wider bg-gray-100 px-2 py-1 rounded-md">Cerrada</span>
                    @endif
                </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-10">
                    <x-icon name="o-document-magnifying-glass" class="w-16 h-16 text-gray-300 mx-auto mb-4" />
                    <p class="text-gray-500 font-bold">No se encontraron órdenes</p>
                    <p class="text-sm text-gray-400">Intenta cambiar los filtros o los términos de búsqueda.</p>
                </div>
            </x-slot:empty>

        </x-table>
    </x-card>
</div>
