<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\{InventoryOutput, InventoryOutputItem, Order};
use Illuminate\Support\Facades\DB;
use Mary\Traits\Toast;

new class extends Component {
    use WithPagination;
    use Toast;

    public string $search = '';
    public string $reason = 'Pedido cancelado';
    public string $notes = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function registerOutput(int $orderId): void
    {
        $order = Order::with([
                'client',
                'items.product.unit',
                'items.materialConsumptions.material',
            ])
            ->where('status', 'Cancelado')
            ->findOrFail($orderId);

        if (InventoryOutput::where('order_id', $order->id)->exists()) {
            $this->error('Este pedido ya tiene una salida registrada.', position: 'toast-top toast-center');
            return;
        }

        try {
            DB::transaction(function () use ($order) {
                $output = InventoryOutput::create([
                    'order_id' => $order->id,
                    'user_id' => auth()->id() ?? 1,
                    'output_date' => now(),
                    'reason' => $this->reason ?: 'Pedido cancelado',
                    'notes' => $this->notes,
                ]);

                foreach ($order->items as $item) {
                    if (($item->product?->type ?? null) === 'Producto') {
                        InventoryOutputItem::create([
                            'inventory_output_id' => $output->id,
                            'product_id' => $item->product_id,
                            'description' => $item->description,
                            'source_type' => 'Producto cancelado',
                            'quantity' => $item->quantity,
                            'unit_name' => $item->product->unit->name ?? 'Und',
                            'material_lost' => 0,
                            'affects_stock' => false,
                        ]);
                    }

                    foreach ($item->materialConsumptions as $material) {
                        InventoryOutputItem::create([
                            'inventory_output_id' => $output->id,
                            'product_id' => $material->material_id,
                            'description' => $material->material_name,
                            'source_type' => 'Material de servicio cancelado',
                            'quantity' => $material->total_consumed,
                            'unit_name' => $material->unit_name,
                            'material_lost' => $material->material_lost,
                            'affects_stock' => false,
                        ]);
                    }
                }
            });

            $this->reset(['notes']);
            $this->success('Salida de inventario registrada.', position: 'toast-top toast-center');
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage(), position: 'toast-top toast-center');
        }
    }

    public function with(): array
    {
        $cancelledOrders = Order::query()
            ->with([
                'client',
                'invoice',
                'items.product.unit',
                'items.materialConsumptions.material',
            ])
            ->where('status', 'Cancelado')
            ->whereDoesntHave('inventoryOutput')
            ->when($this->search, function ($query) {
                $query->where('id', 'like', "%{$this->search}%")
                    ->orWhereHas('client', fn($q) => $q->where('name', 'like', "%{$this->search}%"));
            })
            ->orderBy('id', 'desc')
            ->paginate(8);

        return [
            'cancelledOrders' => $cancelledOrders,
            'recentOutputs' => InventoryOutput::with(['order.client', 'items'])->latest()->limit(8)->get(),
            'pendingOutputs' => Order::where('status', 'Cancelado')->whereDoesntHave('inventoryOutput')->count(),
            'registeredOutputs' => InventoryOutput::count(),
        ];
    }
}; ?>

<div class="space-y-6">
    <x-header title="Salidas de Inventario" subtitle="Bajas documentales por pedidos cancelados">
        <x-slot:middle class="justify-end!">
            <x-input icon="o-magnifying-glass"
                     placeholder="Buscar pedido o cliente..."
                     wire:model.live.debounce.500ms="search"
                     clearable />
        </x-slot:middle>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-stat title="PENDIENTES DE SALIDA" value="{{ $pendingOutputs }}" icon="o-exclamation-triangle" color="text-warning" class="bg-warning/10" />
        <x-stat title="SALIDAS REGISTRADAS" value="{{ $registeredOutputs }}" icon="o-archive-box-x-mark" color="text-error" class="bg-error/10" />
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-input label="Motivo general" wire:model="reason" icon="o-document-text" />
            <x-input label="Notas" wire:model="notes" icon="o-pencil" placeholder="Ej: trabajo personalizado no reutilizable" />
        </div>
        <p class="text-xs text-gray-500 mt-3">
            Esta salida documenta material o producto asociado a pedidos cancelados. No descuenta stock otra vez, porque ventas ya registro el consumo al facturar. El material perdido se muestra como parte de la merma del servicio.
        </p>
    </div>

    <div class="space-y-4">
        @forelse($cancelledOrders as $order)
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 bg-gray-50 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-black text-indigo-900">ORD-{{ str_pad($order->id, 3, '0', STR_PAD_LEFT) }}</span>
                            <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-[10px] font-black uppercase">Cancelado</span>
                        </div>
                        <p class="text-sm text-gray-600 font-medium">{{ $order->client->name ?? 'Sin cliente' }}</p>
                        <p class="text-[10px] text-gray-400 uppercase font-bold">
                            {{ $order->invoice->invoice_number ?? 'Sin factura' }} · C$ {{ number_format($order->estimated_price, 2) }}
                        </p>
                    </div>
                    <x-button label="Registrar salida"
                              icon="o-archive-box-x-mark"
                              wire:click="registerOutput({{ $order->id }})"
                              wire:confirm="¿Registrar la salida documental de este pedido cancelado?"
                              class="btn-error text-white"
                              spinner />
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-[10px] uppercase text-gray-400 font-black border-b">
                                <th class="px-5 py-3 text-left">Concepto</th>
                                <th class="px-5 py-3 text-left">Tipo</th>
                                <th class="px-5 py-3 text-right">Cantidad</th>
                                <th class="px-5 py-3 text-right">Merma</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($order->items as $item)
                                @if(($item->product?->type ?? null) === 'Producto')
                                    <tr>
                                        <td class="px-5 py-3 font-bold text-gray-700">{{ $item->description }}</td>
                                        <td class="px-5 py-3">
                                            <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 text-[10px] font-bold">Producto cancelado</span>
                                        </td>
                                        <td class="px-5 py-3 text-right font-black">{{ number_format((float) $item->quantity, 2) }} {{ $item->product->unit->name ?? 'Und' }}</td>
                                        <td class="px-5 py-3 text-right text-gray-400">0.00</td>
                                    </tr>
                                @endif

                                @foreach($item->materialConsumptions as $material)
                                    <tr>
                                        <td class="px-5 py-3">
                                            <p class="font-bold text-gray-700">{{ $material->material_name }}</p>
                                            <p class="text-[10px] text-gray-400">Servicio: {{ $item->description }}</p>
                                        </td>
                                        <td class="px-5 py-3">
                                            <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-700 text-[10px] font-bold">Material consumido</span>
                                        </td>
                                        <td class="px-5 py-3 text-right font-black">{{ number_format((float) $material->total_consumed, 2) }} {{ $material->unit_name ?? 'Und' }}</td>
                                        <td class="px-5 py-3 text-right font-bold text-red-500">{{ number_format((float) $material->material_lost, 2) }} {{ $material->unit_name ?? 'Und' }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-400 font-bold">
                No hay pedidos cancelados pendientes de salida.
            </div>
        @endforelse
    </div>

    {{ $cancelledOrders->links() }}

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 bg-gray-50 border-b">
            <h3 class="font-black text-gray-800 uppercase text-sm">Historial reciente</h3>
            <p class="text-xs text-gray-500">Ultimas salidas documentales registradas.</p>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($recentOutputs as $output)
                <div class="p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <p class="font-black text-gray-800">
                            ORD-{{ str_pad($output->order_id, 3, '0', STR_PAD_LEFT) }}
                            <span class="text-xs text-gray-400 font-bold">· {{ $output->order->client->name ?? 'Sin cliente' }}</span>
                        </p>
                        <p class="text-xs text-gray-500">{{ $output->reason }} · {{ $output->items->count() }} conceptos</p>
                    </div>
                    <span class="text-xs font-bold text-gray-400">{{ $output->output_date }}</span>
                </div>
            @empty
                <div class="p-6 text-center text-gray-400 font-bold">Todavia no hay salidas registradas.</div>
            @endforelse
        </div>
    </div>
</div>
