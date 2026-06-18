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
          //  ->orWhere('code', 'like', "%{$this->busqueda_producto}%")
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
        return [
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
            'fecha' => 'required|date',
            'observaciones' => 'required|min:10',
        ], [
            'motivo.required' => 'Debe seleccionar un motivo de salida.',
            'fecha.required' => 'La fecha es obligatoria.',
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
                    'user_id' => auth()->id(),
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

            $this->success('Salida de inventario procesada y stock actualizado correctamente.');

        } catch (\Exception $e) {
            $this->error('Ocurrió un error crítico al procesar la salida: ' . $e->getMessage());
        }
    }
}; ?>

<div class="p-6 space-y-6 bg-base-200 min-h-screen">
    <div class="flex justify-between items-center bg-white p-4 rounded-xl shadow-sm border">
        <div>
            <h1 class="text-2xl font-black text-gray-800 flex items-center gap-2">
                <x-icon name="o-arrow-up-tray" class="w-7 h-7 text-error" />
                Salidas de Inventario / Ajustes
            </h1>
            <p class="text-xs text-gray-500">Registra mermas, pérdidas o consumos internos y actualiza el stock al instante.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-1 space-y-6">
            <x-card title="Datos de la Salida" shadow separator class="bg-white">
                <div class="space-y-4">
                    <x-select
                        label="Motivo del Despacho / Salida"
                        wire:model="motivo"
                        icon="o-exclamation-triangle"
                        placeholder="Seleccione un motivo..."
                        :options="[
                            ['id' => 'Merma', 'name' => 'Merma / Desecho de Material'],
                            ['id' => 'Uso Interno', 'name' => 'Uso Interno / Pruebas de Impresión'],
                            ['id' => 'Ajuste', 'name' => 'Ajuste por Auditoría / Conteo'],
                            ['id' => 'Robo o Pérdida', 'name' => 'Robo o Pérdida Confirmada']
                        ]"
                    />

                    <x-input type="date"  label="Fecha del Movimiento"  wire:model="fecha" icon="o-calendar"  max="{{ date('Y-m-d') }}"
/>

                    <x-textarea
                        label="Justificación u Observaciones"
                        wire:model="observaciones"
                        placeholder="Ej: Se dañaron 5 láminas en la guillotina..."
                        rows="3"
                        hint="Obligatorio para auditoría interna."
                    />
                </div>
            </x-card>

            <x-card title="Agregar Producto" shadow separator class="bg-white">
                <div class="space-y-4 relative">
                    <x-input
                        label="Buscar por Nombre o Código"
                        wire:model.live="busqueda_producto"
                        placeholder="Escribe al menos 2 letras..."
                        icon="o-magnifying-glass"
                    />

                    @if(!empty($this->productosBuscados))
                        <div class="absolute z-50 w-full bg-white shadow-xl rounded-lg border border-gray-200 mt-1 max-h-56 overflow-y-auto">
                            @foreach($this->productosBuscados as $prod)
                                @if($prod)
                                    <div wire:click="seleccionarProducto({{ $prod->id }})" class="p-3 hover:bg-red-50 cursor-pointer flex justify-between items-center border-b last:border-0 transition-all">
                                        <div>
                                            <span class="font-bold text-sm text-gray-800 block">{{ $prod?->name }}</span>
                                            <span class="text-xs text-gray-400">Código: {{ $prod?->code ?? 'S/C' }}</span>
                                        </div>
                                        <span class="badge badge-error text-white font-semibold p-2">Stock: {{ $prod?->stock }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    @if($producto_seleccionado_id)
                        <div class="p-3 bg-gray-50 rounded-lg border border-dashed border-gray-300 space-y-2">
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Seleccionado actualmente:</p>
                            <p class="text-sm font-semibold text-gray-800">{{ $nombre_producto_seleccionado }}</p>
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-500">Stock Actual en Sistema:</span>
                                <span class="font-bold text-error">{{ $stock_actual_seleccionado }} uds.</span>
                            </div>

                            <div class="pt-2">
                                <x-input
                                    type="number"
                                    label="Cantidad a Retirar"
                                    wire:model="cantidad_salida"
                                    placeholder="Ej: 5"
                                    icon="o-minus-circle"
                                />
                            </div>

                            <div class="pt-2">
                                <x-button
                                    label="Agregar a la Lista"
                                    wire:click="agregarItem"
                                    icon="o-plus"
                                    class="btn-error text-white w-full btn-sm"
                                />
                            </div>
                        </div>
                    @endif
                </div>
            </x-card>
        </div>

        <div class="lg:col-span-2">
            <x-card shadow class="bg-white min-h-[400px]">
                <div class="flex justify-between items-center mb-4 border-b pb-2">
                    <div>
                        <h3 class="text-lg font-bold text-gray-800">Detalle de Productos a Despachar</h3>
                        <p class="text-xs text-gray-400">Verifique bien las cantidades antes de confirmar la salida.</p>
                    </div>
                    <span class="badge badge-neutral font-bold">{{ count($items) }} Items</span>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-100">
                    <table class="w-full text-left border-collapse bg-white">
                        <thead>
                            <tr class="bg-gray-100 text-gray-700 text-xs font-bold uppercase tracking-wider border-b">
                                <th class="p-4">Producto</th>
                                <th class="p-4 text-center">Stock Actual</th>
                                <th class="p-4 text-center">Cantidad Salida</th>
                                <th class="p-4 text-center">Nuevo Stock Simulado</th>
                                <th class="p-4 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            @forelse($items as $index => $item)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 font-medium text-gray-900">{{ $item['nombre'] }}</td>
                                    <td class="p-4 text-center text-gray-500 font-semibold">{{ $item['stock_actual'] }}</td>
                                    <td class="p-4 text-center text-error font-bold text-base bg-red-50/50">
                                        - {{ $item['cantidad'] }}
                                    </td>
                                    <td class="p-4 text-center font-bold text-success">
                                        {{ $item['stock_actual'] - $item['cantidad'] }}
                                    </td>
                                    <td class="p-4 text-center">
                                        <button
                                            wire:click="eliminarItem({{ $index }})"
                                            class="p-2 text-gray-400 hover:text-error rounded-lg hover:bg-red-50 transition-colors"
                                            title="Eliminar de la lista"
                                        >
                                            <x-icon name="o-trash" class="w-5 h-5" />
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-gray-400">
                                        <x-icon name="o-archive-box-x-mark" class="w-12 h-12 mx-auto text-gray-300 mb-2" />
                                        No hay productos añadidos a la lista todavía. Use el buscador de la izquierda.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(!empty($items))
                    <div class="mt-6 flex justify-end pt-4 border-t border-gray-100">
                        <x-button
                            label="Procesar Salida de Inventario"
                            wire:click="guardarSalida"
                            icon="o-check-circle"
                            class="btn-error text-white btn-lg shadow-md font-black"
                            wire:loading.attr="disabled"
                        />
                    </div>
                @endif
            </x-card>
        </div>

    </div>
    <div class="mt-8">
        <x-card title="Historial de Salidas Registradas" shadow separator class="bg-white">
            <x-table :headers="$headers" :rows="$this->salidasHistoricas" with-pagination>

                @scope('cell_id', $salida)
                    <span class="font-bold text-gray-700">
                        SAL-{{ str_pad($salida->id, 4, '0', STR_PAD_LEFT) }}
                    </span>
                @endscope

                @scope('cell_output_date', $salida)
                    <span class="text-sm font-medium text-gray-600">
                        {{ date('d/m/Y', strtotime($salida->output_date)) }}
                    </span>
                @endscope

                @scope('cell_reason', $salida)
                    <span class="badge badge-ghost font-bold">{{ $salida->reason }}</span>
                @endscope

                @scope('cell_notes', $salida)
                    <div class="max-w-md truncate text-sm text-gray-500" title="{{ $salida->notes }}">
                        {{ $salida->notes ?? 'Sin observaciones' }}
                    </div>
                @endscope

                <x-slot:empty>
                    <div class="text-center p-8 text-gray-400">
                        <x-icon name="o-archive-box-x-mark" class="w-12 h-12 inline mb-2 text-gray-300" />
                        <p class="font-medium">No se han registrado salidas en el sistema.</p>
                    </div>
                </x-slot:empty>
            </x-table>
        </x-card>
    </div>
</div>
