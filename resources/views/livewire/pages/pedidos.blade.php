<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Order;
use Mary\Traits\Toast;

new class extends Component {
    use WithPagination;
    use Toast;

    public string $search = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function cancelOrder($orderId)
    {
        $order = Order::findOrFail($orderId);

        if (in_array($order->status, ['Terminado', 'Entregado', 'Cancelado'])) {
            $this->error('No se puede cancelar esta orden');
            return;
        }

        $order->update([
            'status' => 'Cancelado'
        ]);

        if ($order->factura) {
            $order->factura->update([
                'status' => 'Anulada'
            ]);
        }

        $this->success('Orden cancelada correctamente');
    }

    public function with(): array
    {
        $orders = Order::query()
            ->with(['cliente', 'usuario', 'factura'])
            ->whereIn('status', ['Pendiente', 'EnProceso'])
            ->when($this->search, function($q) {
                $q->whereHas('cliente', function($query) {
                    $query->where('name', 'like', "%{$this->search}%");
                })
                ->orWhere('id', 'like', "%{$this->search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10);

        return [
            'orders' => $orders,
            'totalOrders' => Order::whereIn('status', ['Pendiente', 'EnProceso'])->count(),
            'headers' => [
                ['key' => 'id', 'label' => 'ORDEN'],
                ['key' => 'cliente', 'label' => 'CLIENTE'],
                ['key' => 'factura', 'label' => 'FACTURA'],
                ['key' => 'total', 'label' => 'TOTAL'],
                ['key' => 'status', 'label' => 'ESTADO'],
                ['key' => 'actions', 'label' => '', 'sortable' => false]
            ]
        ];
    }
};
?>

<div>
    <x-header title="Órdenes en Seguimiento" subtitle="Pedidos en proceso o producción">
        <x-slot:middle class="justify-end!">
            <x-input icon="o-magnifying-glass"
                     placeholder="Buscar cliente o ID..."
                     wire:model.live.debounce.500ms="search"
                     clearable />
        </x-slot:middle>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-1 gap-4 mb-8">
        <x-stat title="ÓRDENES ACTIVAS"
                value="{{ $totalOrders }}"
                icon="o-clock"
                color="text-warning"
                class="bg-warning/10" />
    </div>

    <x-card>
        <x-table :headers="$headers" :rows="$orders" with-pagination>

            @scope('cell_id', $order)
                <strong>ORD-{{ str_pad($order->id, 3, '0', STR_PAD_LEFT) }}</strong>
            @endscope

            @scope('cell_cliente', $order)
                <div>
                    <div class="font-bold">{{ $order->cliente->name ?? 'Sin cliente' }}</div>
                    <div class="text-xs text-gray-500">
                        {{ $order->cliente->phone ?? 'Sin teléfono' }}
                    </div>
                </div>
            @endscope

            @scope('cell_factura', $order)
                @if($order->factura)
                    <div>
                        <div class="font-bold">{{ $order->factura->invoice_number }}</div>
                        <div class="text-xs text-gray-500">
                            {{ $order->factura->status }}
                        </div>
                    </div>
                @else
                    <span class="text-gray-400">Sin factura</span>
                @endif
            @endscope

            @scope('cell_total', $order)
                <span class="font-bold text-primary">
                    ${{ number_format($order->estimated_price, 2) }}
                </span>
            @endscope

            @scope('cell_status', $order)
                <x-badge value="{{ $order->status }}"
                         class="badge-warning"
                         icon="o-clock" />
            @endscope

            @scope('cell_actions', $order)
                <div class="flex gap-2">
                    <x-button icon="o-x-circle"
                              wire:click="cancelOrder({{ $order->id }})"
                              wire:confirm="¿Cancelar esta orden?"
                              class="btn-sm btn-circle btn-ghost text-error" />
                </div>
            @endscope

        </x-table>
    </x-card>
</div>
