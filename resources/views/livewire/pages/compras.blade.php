<?php

use Livewire\Volt\Component;
use App\Models\Provider;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

   
    public string $numero_factura = '';
    public string $fecha          = '';

   
    public ?int   $proveedor_id            = null;
    public string $busqueda_proveedor      = '';
    public string $busqueda_ruc            = '';
    public string $busqueda_tel            = '';
    public bool   $mostrar_proveedores     = false;
    public bool   $mostrar_proveedores_ruc = false;
    public bool   $mostrar_proveedores_tel = false;

   
    public string $busqueda_producto = '';
    public ?int   $producto_id       = null;
    public string $codigo_producto   = '';
    public string $unidad_medida     = '';
    public string $cantidad          = '';
    public string $precio_compra     = '';
    public string $precio_venta      = '';
    public bool   $mostrar_productos = false;

    
    public array $items = [];

    
    public bool   $modalEditarItem  = false;
    public ?int   $editItemIndex    = null;
    public string $editCantidad     = '';
    public string $editPrecioCompra = '';

   
    public bool $modalCancelar = false;

   
    public function sugerenciasProveedor(): array
    {
        if (strlen($this->busqueda_proveedor) < 1) return [];
        return Provider::where('company_name', 'like', "%{$this->busqueda_proveedor}%")
            ->limit(6)->get()->toArray();
    }

   
    public function sugerenciasRuc(): array
    {
        if (strlen($this->busqueda_ruc) < 1) return [];
        return Provider::where('ruc', 'like', "%{$this->busqueda_ruc}%")
            ->limit(6)->get()->toArray();
    }

    
    public function sugerenciasTel(): array
    {
        if (strlen($this->busqueda_tel) < 1) return [];
        return Provider::where('phone', 'like', "%{$this->busqueda_tel}%")
            ->limit(6)->get()->toArray();
    }

    public function updatedBusquedaProveedor(): void
    {
        $this->mostrar_proveedores = true;
        $this->proveedor_id = null;
    }

    public function updatedBusquedaRuc(): void
    {
        $this->mostrar_proveedores_ruc = true;
        $this->proveedor_id = null;
    }

    public function updatedBusquedaTel(): void
    {
        $this->mostrar_proveedores_tel = true;
        $this->proveedor_id = null;
    }

   
    public function seleccionarProveedor(int $id): void
    {
        $p = Provider::findOrFail($id);
        $this->proveedor_id            = $p->id;
        $this->busqueda_proveedor      = $p->company_name;
        $this->busqueda_ruc            = $p->ruc   ?? '';
        $this->busqueda_tel            = $p->phone ?? '';
        $this->mostrar_proveedores     = false;
        $this->mostrar_proveedores_ruc = false;
        $this->mostrar_proveedores_tel = false;
    }

   
    public function sugerenciasProducto(): array
    {
        if (strlen($this->busqueda_producto) < 1) return [];
        return Product::where('name', 'like', "%{$this->busqueda_producto}%")
            ->where('is_active', true)
            ->with('unidad')
            ->limit(6)->get()->toArray();
    }

    public function updatedBusquedaProducto(): void
    {
        $this->mostrar_productos = true;
        $this->producto_id = null;
    }

    
    public function seleccionarProducto(int $id): void
    {
        $p = Product::with('unidad')->findOrFail($id);
        $this->producto_id       = $p->id;
        $this->busqueda_producto = $p->name;
        $this->codigo_producto   = (string) $p->id;
        $this->unidad_medida     = $p->unidad->name ?? '';
        $this->precio_compra     = '';
        $this->precio_venta      = (string) $p->sale_price;
        $this->mostrar_productos = false;
    }

    
    public function agregarProducto(): void
    {
        if (!$this->producto_id) {
            $this->addError('busqueda_producto', 'Selecciona un producto de la lista.');
            return;
        }
        if ($this->cantidad === '' || (float)$this->cantidad <= 0) {
            $this->addError('cantidad', 'La cantidad es obligatoria y debe ser mayor a 0.');
            return;
        }
        if ($this->precio_compra === '' || (float)$this->precio_compra <= 0) {
            $this->addError('precio_compra', 'El precio de compra es obligatorio.');
            return;
        }
        if ((float)$this->precio_compra > (float)$this->precio_venta) {
            $this->addError('precio_compra', 'El precio de compra no puede ser mayor al precio de venta.');
            return;
        }

        $this->items[] = [
            'producto_id'   => $this->producto_id,
            'producto'      => $this->busqueda_producto,
            'codigo'        => $this->codigo_producto,
            'unidad'        => $this->unidad_medida,
            'cantidad'      => (float)$this->cantidad,
            'precio_compra' => (float)$this->precio_compra,
            'precio_venta'  => (float)$this->precio_venta,
            'subtotal'      => (float)$this->cantidad * (float)$this->precio_compra,
        ];

        $this->resetProducto();
    }

    public function resetProducto(): void
    {
        $this->busqueda_producto = '';
        $this->producto_id       = null;
        $this->codigo_producto   = '';
        $this->unidad_medida     = '';
        $this->cantidad          = '';
        $this->precio_compra     = '';
        $this->precio_venta      = '';
        $this->mostrar_productos = false;
        $this->resetErrorBag();
    }

    // ── Eliminar item ─────────────────────────────
    public function eliminarItem(int $index): void
    {
        array_splice($this->items, $index, 1);
        $this->items = array_values($this->items);
    }

   
    public function abrirEditarItem(int $index): void
    {
        $this->editItemIndex    = $index;
        $this->editCantidad     = (string) $this->items[$index]['cantidad'];
        $this->editPrecioCompra = (string) $this->items[$index]['precio_compra'];
        $this->modalEditarItem  = true;
    }

    public function guardarEditarItem(): void
    {
        if ((float)$this->editCantidad <= 0) {
            $this->addError('editCantidad', 'La cantidad debe ser mayor a 0.');
            return;
        }
        if ((float)$this->editPrecioCompra > $this->items[$this->editItemIndex]['precio_venta']) {
            $this->addError('editPrecioCompra', 'El precio de compra no puede ser mayor al precio de venta.');
            return;
        }

        $this->items[$this->editItemIndex]['cantidad']      = (float)$this->editCantidad;
        $this->items[$this->editItemIndex]['precio_compra'] = (float)$this->editPrecioCompra;
        $this->items[$this->editItemIndex]['subtotal']      = (float)$this->editCantidad * (float)$this->editPrecioCompra;

        $this->modalEditarItem  = false;
        $this->editItemIndex    = null;
        $this->editCantidad     = '';
        $this->editPrecioCompra = '';
    }

    
    public function total(): float
    {
        return array_sum(array_column($this->items, 'subtotal'));
    }

   
    public function headers(): array
    {
        return [
            ['key' => 'producto',      'label' => 'Producto'],
            ['key' => 'codigo',        'label' => 'Código'],
            ['key' => 'unidad',        'label' => 'Unidad'],
            ['key' => 'cantidad',      'label' => 'Cantidad'],
            ['key' => 'precio_compra', 'label' => 'Precio Compra'],
            ['key' => 'subtotal',      'label' => 'Subtotal'],
            ['key' => 'acciones',      'label' => 'Acciones', 'sortable' => false],
        ];
    }

    
    public function confirmarCancelar(): void
    {
        $this->modalCancelar = true;
    }

    public function cancelarCompra(): void
    {
        $this->numero_factura          = '';
        $this->fecha                   = '';
        $this->proveedor_id            = null;
        $this->busqueda_proveedor      = '';
        $this->busqueda_ruc            = '';
        $this->busqueda_tel            = '';
        $this->items                   = [];
        $this->modalCancelar           = false;
        $this->resetProducto();
    }

  
    public function registrarCompra(): void
    {
        if (!$this->proveedor_id) {
            $this->addError('busqueda_proveedor', 'Selecciona un proveedor.');
            return;
        }
        if (empty($this->items)) {
            $this->addError('busqueda_producto', 'Agrega al menos un producto.');
            return;
        }
        if ($this->numero_factura === '') {
            $this->addError('numero_factura', 'El número de factura es obligatorio.');
            return;
        }
        if ($this->fecha === '') {
            $this->addError('fecha', 'La fecha es obligatoria.');
            return;
        }

        $purchase = Purchase::create([
            'provider_id'             => $this->proveedor_id,
            'user_id'                 => auth()->id(),
            'purchase_date'           => $this->fecha,
            'provider_invoice_number' => $this->numero_factura,
            'total'                   => $this->total(),
        ]);

        foreach ($this->items as $item) {
            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id'  => $item['producto_id'],
                'quantity'    => $item['cantidad'],
                'cost_price'  => $item['precio_compra'],
                'subtotal'    => $item['subtotal'],
            ]);

            $producto = Product::findOrFail($item['producto_id']);
            $producto->update([
                'stock'      => $producto->stock + $item['cantidad'],
                'cost_price' => $item['precio_compra'],
                'sale_price' => $item['precio_venta'],
            ]);
        }

        $this->cancelarCompra();
        $this->success('Compra registrada correctamente.', position: 'toast-bottom toast-end');
    }
};
?>

<div class="p-6">

    <x-header title="Registro de Compras" separator />

    <x-form wire:submit.prevent="registrarCompra">

        
        <x-card title="Datos de la Factura" shadow class="mb-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <x-input
                        label="N° de Factura"
                        wire:model="numero_factura"
                        icon="o-document-text"
                        class="input-sm"
                    />
                    @error('numero_factura') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-input
                        label="Fecha"
                        wire:model="fecha"
                        type="date"
                        icon="o-calendar"
                        class="input-sm"
                    />
                    @error('fecha') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-card>

        
        <x-card title="Datos del Proveedor" shadow class="mb-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">

                <div class="relative">
                    <x-input
                        label="Proveedor"
                        wire:model.live="busqueda_proveedor"
                        icon="o-building-office-2"
                        class="input-sm"
                        autocomplete="off"
                    />
                    @error('busqueda_proveedor') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                    @if($mostrar_proveedores && count($this->sugerenciasProveedor()) > 0)
                        <div class="absolute z-50 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg mt-1 max-h-48 overflow-y-auto">
                            @foreach($this->sugerenciasProveedor() as $prov)
                                <div wire:click="seleccionarProveedor({{ $prov['id'] }})"
                                     class="px-4 py-2 hover:bg-base-200 cursor-pointer text-sm">
                                    <span class="font-semibold">{{ $prov['company_name'] }}</span>
                                    <span class="text-gray-400 text-xs ml-2">{{ $prov['ruc'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="relative">
                    <x-input
                        label="Código RUC"
                        wire:model.live="busqueda_ruc"
                        icon="o-identification"
                        class="input-sm"
                        autocomplete="off"
                    />
                    @if($mostrar_proveedores_ruc && count($this->sugerenciasRuc()) > 0)
                        <div class="absolute z-50 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg mt-1 max-h-48 overflow-y-auto">
                            @foreach($this->sugerenciasRuc() as $prov)
                                <div wire:click="seleccionarProveedor({{ $prov['id'] }})"
                                     class="px-4 py-2 hover:bg-base-200 cursor-pointer text-sm">
                                    <span class="font-semibold">{{ $prov['ruc'] }}</span>
                                    <span class="text-gray-400 text-xs ml-2">{{ $prov['company_name'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="relative">
                    <x-input
                        label="Teléfono"
                        wire:model.live="busqueda_tel"
                        icon="o-phone"
                        class="input-sm"
                        autocomplete="off"
                    />
                    @if($mostrar_proveedores_tel && count($this->sugerenciasTel()) > 0)
                        <div class="absolute z-50 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg mt-1 max-h-48 overflow-y-auto">
                            @foreach($this->sugerenciasTel() as $prov)
                                <div wire:click="seleccionarProveedor({{ $prov['id'] }})"
                                     class="px-4 py-2 hover:bg-base-200 cursor-pointer text-sm">
                                    <span class="font-semibold">{{ $prov['phone'] }}</span>
                                    <span class="text-gray-400 text-xs ml-2">{{ $prov['company_name'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>
        </x-card>

       
        <x-card title="Agregar Producto" shadow class="mb-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">

                <div class="relative md:col-span-2">
                    <x-input
                        label="Producto"
                        wire:model.live="busqueda_producto"
                        icon="o-cube"
                        class="input-sm"
                        autocomplete="off"
                    />
                    @error('busqueda_producto') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                    @if($mostrar_productos && count($this->sugerenciasProducto()) > 0)
                        <div class="absolute z-50 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg mt-1 max-h-48 overflow-y-auto">
                            @foreach($this->sugerenciasProducto() as $prod)
                                <div wire:click="seleccionarProducto({{ $prod['id'] }})"
                                     class="px-4 py-2 hover:bg-base-200 cursor-pointer text-sm">
                                    <span class="font-semibold">{{ $prod['name'] }}</span>
                                    <span class="text-gray-400 text-xs ml-2">
                                        {{ $prod['unidad']['name'] ?? '' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div>
                    <x-input
                        label="Código del Producto"
                        wire:model="codigo_producto"
                        icon="o-qr-code"
                        class="input-sm"
                        readonly
                    />
                </div>

                <div>
                    <x-input
                        label="Unidad de Medida"
                        wire:model="unidad_medida"
                        icon="o-scale"
                        class="input-sm"
                        readonly
                    />
                </div>

                <div>
                    <x-input
                        label="Cantidad"
                        wire:model="cantidad"
                        type="number"
                        min="1"
                        icon="o-hashtag"
                        class="input-sm"
                    />
                    @error('cantidad') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-input
                        label="Precio de Compra (C$)"
                        wire:model="precio_compra"
                        type="number"
                        step="0.01"
                        prefix="C$"
                        icon="o-arrow-down-circle"
                        class="input-sm"
                    />
                    @error('precio_compra') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-input
                        label="Precio de Venta (C$)"
                        wire:model="precio_venta"
                        type="number"
                        step="0.01"
                        prefix="C$"
                        icon="o-arrow-up-circle"
                        class="input-sm"
                    />
                </div>

            </div>

            <div class="flex justify-end mt-3">
                <x-button
                    label="Agregar Producto"
                    icon="o-plus-circle"
                    class="btn-secondary btn-sm"
                    wire:click.prevent="agregarProducto"
                />
            </div>
        </x-card>

       
        <x-card title="Productos Agregados" shadow class="mb-4">
            <x-table :headers="$this->headers()" :rows="$this->items" striped>

                @scope('cell_precio_compra', $row)
                    C$ {{ number_format($row['precio_compra'], 2) }}
                @endscope

                @scope('cell_subtotal', $row)
                    C$ {{ number_format($row['subtotal'], 2) }}
                @endscope

                @scope('cell_acciones', $row)
                    <div class="flex gap-2">
                        <x-button
                            icon="o-pencil-square"
                            class="btn-sm btn-warning btn-soft"
                            tooltip="Editar"
                            wire:click="abrirEditarItem({{ $loop->index }})"
                        />
                        <x-button
                            icon="o-trash"
                            class="btn-sm btn-error btn-soft"
                            tooltip="Eliminar"
                            wire:click="eliminarItem({{ $loop->index }})"
                        />
                    </div>
                @endscope

            </x-table>

            <div class="flex justify-end mt-4">
                <div class="bg-primary/10 border border-primary/30 rounded-xl px-8 py-4 text-right">
                    <p class="text-sm text-gray-500 uppercase font-bold tracking-widest">Total a Pagar</p>
                    <p class="text-3xl font-black text-primary">
                        C$ {{ number_format($this->total(), 2) }}
                    </p>
                </div>
            </div>
        </x-card>

      
        <div class="flex justify-end gap-3 mt-2 mb-8">
            <x-button
                label="Cancelar Compra"
                icon="o-x-circle"
                class="btn-ghost btn-sm"
                wire:click.prevent="confirmarCancelar"
            />
            <x-button
                label="Registrar Compra"
                icon="o-check-circle"
                class="btn-primary"
                type="submit"
            />
        </div>

    </x-form>

    
    <x-modal wire:model="modalEditarItem" title="Editar Producto" separator>
        <div class="grid grid-cols-1 gap-4">
            <x-input
                label="Cantidad"
                wire:model="editCantidad"
                type="number"
                min="1"
                icon="o-hashtag"
            />
            @error('editCantidad') <p class="text-error text-xs -mt-3">{{ $message }}</p> @enderror
            <x-input
                label="Precio de Compra (C$)"
                wire:model="editPrecioCompra"
                type="number"
                step="0.01"
                prefix="C$"
                icon="o-arrow-down-circle"
            />
            @error('editPrecioCompra') <p class="text-error text-xs -mt-3">{{ $message }}</p> @enderror
        </div>
        <x-slot:actions>
            <x-button label="Cancelar" wire:click="$set('modalEditarItem', false)" />
            <x-button label="Guardar" icon="o-check" class="btn-primary" wire:click="guardarEditarItem" />
        </x-slot:actions>
    </x-modal>

    
    <x-modal wire:model="modalCancelar" title="¿Cancelar compra?" separator>
        <p class="text-gray-600">Se perderán todos los datos ingresados. ¿Deseas continuar?</p>
        <x-slot:actions>
            <x-button label="No, continuar" wire:click="$set('modalCancelar', false)" />
            <x-button label="Sí, cancelar" icon="o-trash" class="btn-error" wire:click="cancelarCompra" />
        </x-slot:actions>
    </x-modal>

</div>