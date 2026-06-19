<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\{CashRegister, Order};
use Illuminate\Support\Facades\DB;
use Mary\Traits\Toast;

new class extends Component {
    use WithPagination;
    use Toast;

    public string $search = '';
    public bool $orderNotesModal = false;
    public string $orderNotesTitle = '';
    public array $orderNotes = [];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    // Marks the order as delivered and collects any pending invoice balance.
    public function deliverOrder($orderId)
    {
        $order = Order::with('invoice')->findOrFail($orderId);

        if (in_array($order->status, ['Entregado', 'Cancelado'])) {
            $this->error('Esta orden ya no se puede modificar');
            return;
        }

        if ($order->invoice?->status === 'Anulada') {
            $this->error('No se puede entregar una orden con factura anulada.');
            return;
        }

        $pendingBalance = $this->pendingBalanceFor($order);

        if ($pendingBalance > 0 && !$this->openRegister()) {
            $this->error('No hay caja abierta para cobrar el saldo pendiente.');
            return;
        }

        try {
            DB::transaction(function () use ($orderId) {
                $order = Order::with('invoice')->lockForUpdate()->findOrFail($orderId);

                if (in_array($order->status, ['Entregado', 'Cancelado'])) {
                    throw new \Exception('Esta orden ya no se puede modificar.');
                }

                if ($order->invoice?->status === 'Anulada') {
                    throw new \Exception('No se puede entregar una orden con factura anulada.');
                }

                $pendingBalance = $this->pendingBalanceFor($order);

                if ($pendingBalance > 0) {
                    $register = $this->openRegister(lock: true);

                    if (!$register) {
                        throw new \Exception('No hay caja abierta para cobrar el saldo pendiente.');
                    }

                    $register->increment('cash_sales', $pendingBalance);
                    $register->increment('system_balance', $pendingBalance);

                    DB::table('cash_movements')->insert([
                        'cash_register_id' => $register->id,
                        'user_id' => auth()->id() ?? 1,
                        'type' => 'IngresoExtra',
                        'concept' => 'Pago saldo factura: ' . $order->invoice->invoice_number,
                        'amount' => $pendingBalance,
                        'movement_date' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $order->update(['advance_payment' => (float) $order->advance_payment + $pendingBalance]);
                    $order->refresh();
                }

                if ($order->invoice) {
                    $order->invoice->update(['status' => 'Pagada']);
                }

                $order->update(['status' => 'Entregado']);
            });
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return;
        }


        $message = $pendingBalance > 0
            ? 'Orden entregada y saldo cobrado: C$ ' . number_format($pendingBalance, 2)
            : 'Orden marcada como entregada con exito';

        $this->success($message);
    }

    private function pendingBalanceFor(Order $order): float
    {
        if (!$order->invoice || $order->invoice->status === 'Pagada') {
            return 0;
        }

        return round(max(0, (float) $order->invoice->total - (float) $order->advance_payment), 2);
    }

    private function openRegister(bool $lock = false): ?CashRegister
    {
        $query = CashRegister::where('user_id', auth()->id() ?? 1)
            ->where('status', 'Abierta');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
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

        if ($order->invoice) {
            $order->invoice->update([
                'status' => 'Anulada'
            ]);
        }

        $this->success('Orden cancelada correctamente');
    }

    public function openOrderNotes($orderId): void
    {
        $order = Order::with(['items.product'])->findOrFail($orderId);

        $this->orderNotesTitle = 'ORD-' . str_pad($order->id, 3, '0', STR_PAD_LEFT);
        $this->orderNotes = $order->items
            ->filter(fn($item) => ($item->product->type ?? null) === 'Servicio')
            ->map(fn($item) => [
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'measurements' => trim((string) ($item->measurements ?? '')),
            ])
            ->values()
            ->all();
        $this->orderNotesModal = true;
    }

    public function with(): array
    {
        // Modificamos el whereIn para incluir 'Entregado' y 'Cancelado' y que no desaparezcan
        $orders = Order::query()
            ->with(['client', 'user', 'invoice', 'items.product'])
            ->whereIn('status', ['Pendiente', 'EnProceso', 'Entregado', 'Cancelado'])
            ->when($this->search, function($q) {
                $q->whereHas('client', function($query) {
                    $query->where('name', 'like', "%{$this->search}%");
                })
                ->orWhere('id', 'like', "%{$this->search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10);

        return [
            'orders' => $orders,
            // El contador sigue midiendo solo las activas (producción/espera)
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
                    <div class="font-bold">{{ $order->client->name ?? 'Sin cliente' }}</div>
                    <div class="text-xs text-gray-500">
                        {{ $order->client->phone ?? 'Sin teléfono' }}
                    </div>
                </div>
            @endscope

            @scope('cell_factura', $order)
                @if($order->invoice)
                    <div>
                        <div class="font-bold">{{ $order->invoice->invoice_number }}</div>
                        <div class="text-xs text-gray-500">
                            {{ $order->invoice->status }}
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

            {{-- Modificado: Badges dinámicos de color según el estado --}}
            @scope('cell_status', $order)
                @if($order->status === 'Entregado')
                    <x-badge value="Entregado" class="badge-success text-white" icon="o-check-circle" />
                @elseif($order->status === 'Cancelado')
                    <x-badge value="Cancelado" class="badge-error text-white" icon="o-x-circle" />
                @else
                    <x-badge value="{{ $order->status }}" class="badge-warning" icon="o-clock" />
                @endif
            @endscope

            {{-- Modificado: Acciones condicionales --}}
            @scope('cell_actions', $order)
                @php
                    $hasServiceItems = $order->type === 'Produccion'
                        && $order->items->contains(fn($item) => ($item->product->type ?? null) === 'Servicio');
                @endphp

                <div class="flex flex-wrap gap-2 items-center">
                    @if($hasServiceItems)
                        <x-button label="Nota"
                                  icon="o-pencil-square"
                                  wire:click="openOrderNotes({{ $order->id }})"
                                  class="btn-sm btn-ghost text-info" />
                    @endif
                    @if(!in_array($order->status, ['Entregado', 'Cancelado']))
                        {{-- Botón para Entregar --}}
                        <x-button icon="o-check"
                                  wire:click="deliverOrder({{ $order->id }})"
                                  wire:confirm="¿Marcar esta orden como ENTREGADA y cobrar el saldo pendiente si existe?"
                                  class="btn-sm btn-circle btn-ghost text-success" />

                        {{-- Botón para Cancelar --}}
                        <x-button icon="o-x-circle"
                                  wire:click="cancelOrder({{ $order->id }})"
                                  wire:confirm="¿Cancelar esta orden?"
                                  class="btn-sm btn-circle btn-ghost text-error" />
                    @else
                        <span class="text-xs text-gray-400 italic p-1">Finalizada</span>
                    @endif
                </div>
            @endscope

        </x-table>
    </x-card>

    <x-modal wire:model="orderNotesModal" title="Notas de taller" subtitle="{{ $orderNotesTitle }}" separator box-class="max-w-2xl">
        <div class="space-y-3">
            @forelse($orderNotes as $note)
                <div class="border border-gray-200 rounded-xl p-4 bg-gray-50">
                    <div class="flex justify-between gap-3">
                        <div>
                            <p class="font-bold text-gray-800">{{ $note['description'] }}</p>
                            <p class="text-[10px] uppercase font-bold text-gray-400">Cantidad: {{ number_format($note['quantity'], 2) }}</p>
                        </div>
                    </div>

                    @if($note['measurements'] !== '')
                        <p class="mt-3 text-sm text-gray-700 whitespace-pre-line">{{ $note['measurements'] }}</p>
                    @else
                        <p class="mt-3 text-sm text-gray-400 italic">Sin nota registrada.</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-400 text-center py-6">Esta orden no tiene servicios con nota.</p>
            @endforelse
        </div>

        <x-slot:actions>
            <x-button label="Cerrar" @click="$wire.orderNotesModal = false" class="btn-primary" />
        </x-slot:actions>
    </x-modal>
</div>
