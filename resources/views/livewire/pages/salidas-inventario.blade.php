<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\InventoryOutput;
use Livewire\WithPagination;

new class extends Component {
    use Toast, WithPagination;

    public string $motivo = '';
    public string $fecha = '';
    public string $observaciones = '';

    public string $busqueda_producto = '';
    public ?int $producto_seleccionado_id = null;
    public string $nombre_producto_seleccionado = '';
    public int $stock_actual_seleccionado = 0;
    public string $cantidad_salida = '';

    public array $items = [];

    public function mount()
    {
        $this->fecha = date('Y-m-d');
    }

    #[Computed]
    public function productosBuscados()
    {
        if (strlen($this->busqueda_producto) < 2) {
            return [];
        }

        return Product::query()
            ->where('name', 'like', "%{$this->busqueda_producto}%")
            ->take(5)
            ->get();
    }

    public function seleccionarProducto($id)
    {
        $producto = Product::find($id);
        if ($producto) {
            $this->producto_seleccionado_id = $producto->id;
            $this->nombre_producto_seleccionado = $producto->name;
            $this->stock_actual_seleccionado = $producto->stock;
            $this->busqueda_producto = '';
            $this->cantidad_salida = '';
        }
    }

    #[Computed]
    public function salidasHistoricas()
    {
        return InventoryOutput::query()
            ->orderBy('output_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(10);
    }

    public function with(): array
    {
        // Estadísticas para el Mini-Dashboard
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $totalSalidas = InventoryOutput::whereMonth('output_date', $currentMonth)
                                        ->whereYear('output_date', $currentYear)
                                        ->count();

        $totalItems = DB::table('inventory_output_items')
                        ->join('inventory_outputs', 'inventory_output_items.inventory_output_id', '=', 'inventory_outputs.id')
                        ->whereMonth('inventory_outputs.output_date', $currentMonth)
                        ->whereYear('inventory_outputs.output_date', $currentYear)
                        ->sum('quantity');

        return [
            'stats' => [
                'total_salidas' => $totalSalidas,
                'total_items' => $totalItems
            ],
            'headers' => [
                ['key' => 'id', 'label' => 'CÓDIGO'],
                ['key' => 'output_date', 'label' => 'FECHA'],
                ['key' => 'reason', 'label' => 'MOTIVO'],
                ['key' => 'notes', 'label' => 'OBSERVACIONES / JUSTIFICACIÓN'],
            ]
        ];
    }

    public function agregarItem()
    {
        if (!$this->producto_seleccionado_id) {
            $this->error('Por favor, busque y seleccione un producto primero.');
            return;
        }

        if (!is_numeric($this->cantidad_salida) || $this->cantidad_salida <= 0) {
            $this->error('La cantidad debe ser un número mayor a cero.');
            return;
        }

        if ($this->cantidad_salida > $this->stock_actual_seleccionado) {
            $this->error("No puedes retirar más de lo que hay en stock. Stock disponible: {$this->stock_actual_seleccionado}");
            return;
        }

        foreach ($this->items as $item) {
            if ($item['product_id'] == $this->producto_seleccionado_id) {
                $this->error('Este producto ya está en la lista. Elimínelo si desea corregir la cantidad.');
                return;
            }
        }

        $this->items[] = [
            'product_id' => $this->producto_seleccionado_id,
            'nombre' => $this->nombre_producto_seleccionado,
            'stock_actual' => $this->stock_actual_seleccionado,
            'cantidad' => (int)$this->cantidad_salida
        ];

        $this->producto_seleccionado_id = null;
        $this->nombre_producto_seleccionado = '';
        $this->stock_actual_seleccionado = 0;
        $this->cantidad_salida = '';
    }

    public function eliminarItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->success('Producto removido de la lista.');
    }

    public function guardarSalida()
    {
        $this->validate([
            'motivo' => 'required',
            'fecha' => 'required|date|before_or_equal:today',
            'observaciones' => 'required|min:10',
        ], [
            'motivo.required' => 'Debe seleccionar un motivo de salida.',
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.before_or_equal' => 'La fecha de salida no puede ser futura.',
            'observaciones.required' => 'Debe justificar detalladamente la salida (mínimo 10 caracteres).',
        ]);

        if (empty($this->items)) {
            $this->error('Debe agregar al menos un producto a la lista de salida.');
            return;
        }

       try {
            DB::transaction(function () {
                $salidaId = DB::table('inventory_outputs')->insertGetId([
                    'reason' => $this->motivo,
                    'output_date' => $this->fecha,
                    'notes' => $this->observaciones,
                    'user_id' => auth()->id() ?? 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($this->items as $item) {
                    DB::table('inventory_output_items')->insert([
                        'inventory_output_id' => $salidaId,
                        'product_id' => $item['product_id'],
                        'description' => $item['nombre'],
                        'source_type' => 'Inventario',
                        'quantity' => $item['cantidad'],
                        'material_lost' => 0,
                        'affects_stock' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('products')
                        ->where('id', $item['product_id'])
                        ->decrement('stock', $item['cantidad']);
                }
            });
            $this->reset(['motivo', 'observaciones', 'items', 'busqueda_producto', 'producto_seleccionado_id']);
            $this->fecha = date('Y-m-d');

            $this->success('Salida de inventario procesada y stock actualizado correctamente.', position: 'toast-top toast-center');

        } catch (\Exception $e) {
            $this->error('Ocurrió un error crítico al procesar la salida: ' . $e->getMessage());
        }
    }
}; ?>

<div class="p-4 bg-gray-50/50 min-h-screen">
    <div class="max-w-[1400px] mx-auto">

        <x-header title="Salidas y Ajustes" subtitle="Registro de mermas, consumo interno y pérdidas" separator class="mb-6" />

        {{-- Mini-Dashboard Estadístico --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <x-stat title="Ajustes este Mes"
                    value="{{ $stats['total_salidas'] }}"
                    icon="o-document-minus"
                    class="bg-white border-l-4 border-orange-500 shadow-sm hover:shadow-md transition-shadow" />

            <x-stat title="Ítems Retirados (Mes)"
                    value="{{ number_format($stats['total_items']) }}"
                    icon="o-arrow-trending-down"
                    class="bg-white border-l-4 border-error shadow-sm hover:shadow-md transition-shadow" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            {{-- LADO IZQUIERDO: CONFIGURACIÓN DE LA SALIDA --}}
            <div class="lg:col-span-4 flex flex-col gap-6">

                {{-- Datos Generales --}}
                <div class="bg-white p-5 rounded-3xl shadow-xl border border-gray-100 overflow-hidden relative">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-error rounded-full blur-3xl opacity-5 -mr-10 -mt-10"></div>

                    <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2 mb-4 relative z-10">
                        <x-icon name="o-clipboard-document-check" class="w-4 h-4 text-error" /> Detalles del Movimiento
                    </h4>

                    <div class="space-y-4 relative z-10">
                        <div>
                            <x-select
                                label="Motivo del Despacho *"
                                wire:model="motivo"
                                icon="o-exclamation-triangle"
                                placeholder="Seleccione un motivo..."
                                :options="[
                                    ['id' => 'Merma', 'name' => 'Merma / Desecho de Material'],
                                    ['id' => 'Uso Interno', 'name' => 'Uso Interno / Pruebas'],
                                    ['id' => 'Ajuste', 'name' => 'Ajuste por Auditoría'],
                                    ['id' => 'Robo o Pérdida', 'name' => 'Robo o Pérdida Confirmada']
                                ]"
                                class="bg-gray-50 focus:bg-white font-bold text-gray-700"
                            />
                        </div>

                        <div>
                            <x-input type="date" label="Fecha del Movimiento *" wire:model="fecha" icon="o-calendar" max="{{ date('Y-m-d') }}" class="bg-gray-50 focus:bg-white" />
                        </div>

                        <div>
                            <x-textarea
                                label="Justificación u Observaciones *"
                                wire:model="observaciones"
                                placeholder="Describa qué ocurrió y por qué se retira del sistema..."
                                rows="3"
                                class="bg-gray-50 focus:bg-white"
                            />
                        </div>
                    </div>
                </div>

                {{-- Buscador de Productos --}}
                <div class="bg-indigo-50/50 p-5 rounded-3xl border border-indigo-100 relative z-40">
                    <h4 class="text-[10px] font-black text-indigo-400 uppercase tracking-widest flex items-center gap-2 mb-4">
                        <x-icon name="o-magnifying-glass-plus" class="w-4 h-4" /> Agregar Producto
                    </h4>

                    <div class="space-y-4 relative">
                        <div class="relative">
                            <x-input
                                label="Buscar por Nombre"
                                wire:model.live="busqueda_producto"
                                placeholder="Escribe al menos 2 letras..."
                                icon="o-magnifying-glass"
                                class="bg-white shadow-sm"
                                autocomplete="off"
                            />

                            @if(!empty($this->productosBuscados))
                                <div class="absolute z-50 w-full bg-white shadow-2xl rounded-xl border border-gray-100 mt-1 max-h-56 overflow-y-auto">
                                    @foreach($this->productosBuscados as $prod)
                                        @if($prod)
                                            <div wire:click="seleccionarProducto({{ $prod->id }})" class="p-3 hover:bg-indigo-50 cursor-pointer flex justify-between items-center border-b border-gray-50 last:border-0 transition-colors">
                                                <div>
                                                    <span class="font-bold text-sm text-gray-800 block">{{ $prod?->name }}</span>
                                                </div>
                                                <span class="badge badge-indigo badge-sm font-bold shadow-sm">Stock: {{ $prod?->stock }}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Producto Seleccionado --}}
                        @if($producto_seleccionado_id)
                            <div class="p-4 bg-white rounded-2xl border-2 border-indigo-200 space-y-3 animate-fade-in shadow-sm">
                                <div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Ítem Seleccionado</p>
                                    <p class="text-sm font-black text-indigo-700 leading-tight">{{ $nombre_producto_seleccionado }}</p>
                                </div>

                                <div class="flex justify-between items-center bg-gray-50 p-2 rounded-lg border border-gray-100">
                                    <span class="text-xs font-bold text-gray-500">Stock Actual:</span>
                                    <span class="font-black text-gray-800">{{ $stock_actual_seleccionado }} uds.</span>
                                </div>

                                <div>
                                    <x-input
                                        type="number"
                                        label="Cantidad a Retirar"
                                        wire:model="cantidad_salida"
                                        placeholder="Ej: 5"
                                        icon="o-minus-circle"
                                        class="bg-red-50 text-error font-black border-red-200 focus:border-error focus:ring-error"
                                    />
                                </div>

                                <x-button
                                    label="Agregar a la Lista"
                                    wire:click="agregarItem"
                                    icon="o-plus"
                                    class="btn-indigo w-full shadow-sm text-white font-bold mt-1"
                                />
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- LADO DERECHO: LISTA DE DESPACHO --}}
            <div class="lg:col-span-8 flex flex-col gap-6">

                <div class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden flex flex-col min-h-[400px]">
                    <div class="bg-gray-50/50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                        <div>
                            <h3 class="font-black text-gray-800 text-lg">Mesa de Despacho</h3>
                            <p class="text-xs text-gray-500 font-medium">Verifique bien las cantidades que se descontarán del sistema.</p>
                        </div>
                        <span class="badge badge-error badge-sm text-white font-bold shadow-sm">{{ count($items) }} Ítems</span>
                    </div>

                    <div class="overflow-x-auto flex-1 p-3">
                        <table class="w-full text-left">
                            <thead class="text-[10px] font-black text-gray-400 uppercase tracking-wider border-b border-gray-100">
                                <tr>
                                    <th class="px-4 py-3">Producto</th>
                                    <th class="px-4 py-3 text-center">Stock Actual</th>
                                    <th class="px-4 py-3 text-center text-error">Salida</th>
                                    <th class="px-4 py-3 text-center">Stock Final</th>
                                    <th class="px-4 py-3 text-center"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @forelse($items as $index => $item)
                                    <tr class="hover:bg-red-50/30 transition-colors group">
                                        <td class="px-4 py-4 font-bold text-gray-800 text-sm">{{ $item['nombre'] }}</td>
                                        <td class="px-4 py-4 text-center text-gray-500 font-medium">{{ $item['stock_actual'] }}</td>
                                        <td class="px-4 py-4 text-center">
                                            <span class="bg-red-100 text-error font-black px-3 py-1 rounded-lg">
                                                - {{ $item['cantidad'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 text-center font-black text-gray-800">
                                            {{ $item['stock_actual'] - $item['cantidad'] }}
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            <button
                                                wire:click="eliminarItem({{ $index }})"
                                                class="p-2 text-gray-300 hover:text-error hover:bg-error/10 rounded-xl transition-all opacity-0 group-hover:opacity-100"
                                                title="Quitar"
                                            >
                                                <x-icon name="o-trash" class="w-5 h-5" />
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-16 text-center">
                                            <div class="flex flex-col items-center justify-center text-gray-300 space-y-3">
                                                <div class="p-4 bg-gray-50 rounded-full border border-dashed border-gray-200">
                                                    <x-icon name="o-archive-box-x-mark" class="w-10 h-10 text-gray-300" />
                                                </div>
                                                <p class="font-bold text-gray-400">No hay productos en la lista</p>
                                                <p class="text-xs">Utiliza el buscador de la izquierda para agregar ítems.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Botón de Acción Principal --}}
                    @if(!empty($items))
                        <div class="p-6 bg-gray-50/50 border-t border-gray-100">
                            <button
                                wire:click="guardarSalida"
                                wire:loading.attr="disabled"
                                class="w-full py-4 bg-error hover:bg-red-600 active:bg-red-700 text-white font-black text-lg tracking-widest rounded-2xl shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all flex items-center justify-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <x-icon name="o-arrow-up-tray" class="w-6 h-6" /> PROCESAR SALIDA DE INVENTARIO
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- HISTORIAL DE SALIDAS --}}
        <div class="mt-8">
            <x-card title="Historial de Movimientos" shadow separator class="bg-white rounded-3xl border-gray-100">
                <x-table :headers="$headers" :rows="$this->salidasHistoricas" with-pagination class="table-sm">

                    @scope('cell_id', $salida)
                        <span class="font-black text-gray-400">
                            SAL-{{ str_pad($salida->id, 4, '0', STR_PAD_LEFT) }}
                        </span>
                    @endscope

                    @scope('cell_output_date', $salida)
                        <div class="flex flex-col">
                            <span class="text-sm font-bold text-gray-700">{{ \Carbon\Carbon::parse($salida->output_date)->format('d/m/Y') }}</span>
                            <span class="text-[10px] text-gray-400 font-medium">{{ \Carbon\Carbon::parse($salida->created_at)->format('h:i A') }}</span>
                        </div>
                    @endscope

                    @scope('cell_reason', $salida)
                        @php
                            $color = match($salida->reason) {
                                'Merma' => 'badge-warning',
                                'Robo o Pérdida' => 'badge-error text-white',
                                'Ajuste' => 'badge-info text-white',
                                default => 'badge-neutral'
                            };
                        @endphp
                        <x-badge value="{{ $salida->reason }}" class="{{ $color }} font-bold shadow-sm" />
                    @endscope

                    @scope('cell_notes', $salida)
                        <div class="flex items-start gap-2">
                            <x-icon name="o-chat-bubble-bottom-center-text" class="w-4 h-4 text-gray-300 mt-0.5" />
                            <div class="text-sm text-gray-600 font-medium max-w-md line-clamp-2" title="{{ $salida->notes }}">
                                {{ $salida->notes ?? 'Sin observaciones' }}
                            </div>
                        </div>
                    @endscope

                    <x-slot:empty>
                        <div class="text-center py-10 text-gray-400">
                            <x-icon name="o-document-magnifying-glass" class="w-16 h-16 inline mb-4 text-gray-200" />
                            <h3 class="text-lg font-bold text-gray-500">Historial vacío</h3>
                            <p class="text-sm">No se han registrado salidas en el sistema.</p>
                        </div>
                    </x-slot:empty>
                </x-table>
            </x-card>
        </div>

    </div>
</div>
