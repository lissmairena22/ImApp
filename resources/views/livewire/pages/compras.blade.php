<?php

use Livewire\Volt\Component;
use App\Models\Provider;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Category;
use App\Models\Unit;
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

   
    public string $forma_pago     = 'contado';
    public string $metodo_pago    = 'cordobas';
    public string $monto_cordobas = '';
    public string $monto_dolares  = '';
    public string $tasa_cambio    = '36.50';
    public string $ref_transferencia = '';

    
    public bool   $modalNuevoProveedor = false;
    public string $np_nombre           = '';
    public string $np_ruc              = '';
    public string $np_direccion        = '';
    public string $np_telefono         = '';
    public bool   $np_activo           = true;

    
    public bool   $modalNuevoProducto  = false;
    public string $nprod_nombre        = '';
    public string $nprod_tipo          = 'Producto';
    public string $nprod_categoria_id  = '';
    public string $nprod_unit_id       = '';
    public string $nprod_precio_venta  = '';
    public string $nprod_precio_compra = '';
    public string $nprod_stock         = '0';
    public string $nprod_min_stock     = '5';

    
    public function mount(): void
    {
        $saved = session('compra_en_curso', []);
        if (!empty($saved)) {
            $this->numero_factura      = $saved['numero_factura']      ?? '';
            $this->fecha               = $saved['fecha']               ?? '';
            $this->proveedor_id        = $saved['proveedor_id']        ?? null;
            $this->busqueda_proveedor  = $saved['busqueda_proveedor']  ?? '';
            $this->busqueda_ruc        = $saved['busqueda_ruc']        ?? '';
            $this->busqueda_tel        = $saved['busqueda_tel']        ?? '';
            $this->items               = $saved['items']               ?? [];
            $this->forma_pago          = $saved['forma_pago']          ?? 'contado';
            $this->metodo_pago         = $saved['metodo_pago']         ?? 'cordobas';
            $this->monto_cordobas      = $saved['monto_cordobas']      ?? '';
            $this->monto_dolares       = $saved['monto_dolares']       ?? '';
            $this->tasa_cambio         = $saved['tasa_cambio']         ?? '36.50';
            $this->ref_transferencia   = $saved['ref_transferencia']   ?? '';
        } else {
            $ultimo = Purchase::max('id') ?? 0;
            $this->numero_factura = 'C-' . str_pad($ultimo + 1, 5, '0', STR_PAD_LEFT);
            $this->fecha          = now()->format('Y-m-d');
        }
    }

    
    public function guardarSesion(): void
    {
        session(['compra_en_curso' => [
            'numero_factura'    => $this->numero_factura,
            'fecha'             => $this->fecha,
            'proveedor_id'      => $this->proveedor_id,
            'busqueda_proveedor'=> $this->busqueda_proveedor,
            'busqueda_ruc'      => $this->busqueda_ruc,
            'busqueda_tel'      => $this->busqueda_tel,
            'items'             => $this->items,
            'forma_pago'        => $this->forma_pago,
            'metodo_pago'       => $this->metodo_pago,
            'monto_cordobas'    => $this->monto_cordobas,
            'monto_dolares'     => $this->monto_dolares,
            'tasa_cambio'       => $this->tasa_cambio,
            'ref_transferencia' => $this->ref_transferencia,
        ]]);
    }

    public function updatedNumeroFactura():    void { $this->guardarSesion(); }
    public function updatedFecha():            void { $this->guardarSesion(); }
    public function updatedFormaPago():        void { $this->guardarSesion(); }
    public function updatedMetodoPago():       void { $this->guardarSesion(); }
    public function updatedMontoCordobas():    void { $this->guardarSesion(); }
    public function updatedMontoDolares():     void { $this->guardarSesion(); }
    public function updatedTasaCambio():       void { $this->guardarSesion(); }
    public function updatedRefTransferencia(): void { $this->guardarSesion(); }

    // ── Sugerencias proveedor (solo activos) ──────
    public function sugerenciasProveedor(): array
    {
        if (strlen($this->busqueda_proveedor) < 1) return [];
        return Provider::where('is_active', true)
            ->where('company_name', 'like', "%{$this->busqueda_proveedor}%")
            ->limit(6)->get()->toArray();
    }

    public function sugerenciasRuc(): array
    {
        if (strlen($this->busqueda_ruc) < 1) return [];
        return Provider::where('is_active', true)
            ->where('ruc', 'like', "%{$this->busqueda_ruc}%")
            ->limit(6)->get()->toArray();
    }

    public function sugerenciasTel(): array
    {
        if (strlen($this->busqueda_tel) < 1) return [];
        return Provider::where('is_active', true)
            ->where('phone', 'like', "%{$this->busqueda_tel}%")
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
        $p = Provider::where('is_active', true)->findOrFail($id);
        $this->proveedor_id            = $p->id;
        $this->busqueda_proveedor      = $p->company_name;
        $this->busqueda_ruc            = $p->ruc   ?? '';
        $this->busqueda_tel            = $p->phone ?? '';
        $this->mostrar_proveedores     = false;
        $this->mostrar_proveedores_ruc = false;
        $this->mostrar_proveedores_tel = false;
        $this->guardarSesion();
    }

   
    public function sugerenciasProducto(): array
    {
        if (strlen($this->busqueda_producto) < 1) return [];
        return Product::where('is_active', true)
            ->where('name', 'like', "%{$this->busqueda_producto}%")
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
        $p = Product::where('is_active', true)->with('unidad')->findOrFail($id);
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
            $this->addError('cantidad', 'La cantidad debe ser mayor a 0.');
            return;
        }
        if ($this->precio_compra === '' || (float)$this->precio_compra <= 0) {
            $this->addError('precio_compra', 'El precio de compra es obligatorio.');
            return;
        }
        if ((float)$this->precio_compra > (float)$this->precio_venta) {
            $this->addError('precio_compra', 'No puede ser mayor al precio de venta.');
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

        $this->guardarSesion();
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

    public function eliminarItem(int $index): void
    {
        array_splice($this->items, $index, 1);
        $this->items = array_values($this->items);
        $this->guardarSesion();
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
            $this->addError('editPrecioCompra', 'No puede ser mayor al precio de venta.');
            return;
        }

        $this->items[$this->editItemIndex]['cantidad']      = (float)$this->editCantidad;
        $this->items[$this->editItemIndex]['precio_compra'] = (float)$this->editPrecioCompra;
        $this->items[$this->editItemIndex]['subtotal']      = (float)$this->editCantidad * (float)$this->editPrecioCompra;

        $this->modalEditarItem  = false;
        $this->editItemIndex    = null;
        $this->editCantidad     = '';
        $this->editPrecioCompra = '';
        $this->guardarSesion();
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
            ['key' => 'cantidad',      'label' => 'Cant.'],
            ['key' => 'precio_compra', 'label' => 'P. Compra'],
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
        session()->forget('compra_en_curso');
        $ultimo = Purchase::max('id') ?? 0;
        $this->numero_factura    = 'C-' . str_pad($ultimo + 1, 5, '0', STR_PAD_LEFT);
        $this->fecha             = now()->format('Y-m-d');
        $this->proveedor_id      = null;
        $this->busqueda_proveedor = '';
        $this->busqueda_ruc      = '';
        $this->busqueda_tel      = '';
        $this->items             = [];
        $this->forma_pago        = 'contado';
        $this->metodo_pago       = 'cordobas';
        $this->monto_cordobas    = '';
        $this->monto_dolares     = '';
        $this->tasa_cambio       = '36.50';
        $this->ref_transferencia = '';
        $this->modalCancelar     = false;
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
        if ($this->forma_pago === 'transferencia' && $this->ref_transferencia === '') {
            $this->addError('ref_transferencia', 'Ingresa la referencia de la transferencia.');
            return;
        }
        if ($this->metodo_pago === 'mixto') {
            if ($this->monto_cordobas === '' && $this->monto_dolares === '') {
                $this->addError('monto_cordobas', 'Ingresa al menos un monto.');
                return;
            }
        }

       
        $amountCordobas = null;
        $amountDolares  = null;
        $exchangeRate   = null;

        if ($this->metodo_pago === 'cordobas') {
            $amountCordobas = $this->monto_cordobas !== ''
                ? (float)$this->monto_cordobas
                : $this->total();
        } elseif ($this->metodo_pago === 'dolares') {
            $amountDolares = $this->monto_dolares !== ''
                ? (float)$this->monto_dolares
                : null;
            $exchangeRate  = (float)$this->tasa_cambio;
        } elseif ($this->metodo_pago === 'mixto') {
            $amountCordobas = $this->monto_cordobas !== '' ? (float)$this->monto_cordobas : null;
            $amountDolares  = $this->monto_dolares  !== '' ? (float)$this->monto_dolares  : null;
            $exchangeRate   = (float)$this->tasa_cambio;
        } else {
           
            $amountCordobas = $this->total();
        }

        $paymentMethod = $this->forma_pago === 'transferencia'
            ? 'transferencia'
            : $this->metodo_pago;

        $purchase = Purchase::create([
            'provider_id'             => $this->proveedor_id,
            'user_id'                 => auth()->id(),
            'purchase_date'           => $this->fecha,
            'provider_invoice_number' => $this->numero_factura,
            'total'                   => $this->total(),
            'payment_method'          => $paymentMethod,
            'amount_cordobas'         => $amountCordobas,
            'amount_dolares'          => $amountDolares,
            'exchange_rate'           => $exchangeRate,
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

    public function categoriasOpciones(): array
    {
        return Category::where('is_active', true)
            ->get()->map(fn($c) => ['id' => $c->id, 'name' => $c->name])
            ->toArray();
    }

    public function unidadesOpciones(): array
    {
        return Unit::all()
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->name])
            ->toArray();
    }

    public function guardarNuevoProveedor(): void
    {
        $this->validate([
            'np_nombre'    => ['required', 'regex:/^[^\d]+$/'],
            'np_ruc'       => ['required', 'alpha_num', 'unique:providers,ruc'],
            'np_direccion' => ['required', 'string'],
            'np_telefono'  => ['required', 'regex:/^[0-9\s\+\-]+$/'],
        ], [
            'np_nombre.required'    => 'El nombre es obligatorio.',
            'np_nombre.regex'       => 'El nombre no debe contener números.',
            'np_ruc.required'       => 'El RUC es obligatorio.',
            'np_ruc.alpha_num'      => 'El RUC solo puede contener letras y números.',
            'np_ruc.unique'         => 'Este RUC ya está registrado.',
            'np_direccion.required' => 'La dirección es obligatoria.',
            'np_telefono.required'  => 'El teléfono es obligatorio.',
            'np_telefono.regex'     => 'El teléfono solo debe contener números.',
        ]);

        $p = Provider::create([
            'company_name' => $this->np_nombre,
            'ruc'          => $this->np_ruc,
            'address'      => $this->np_direccion,
            'phone'        => $this->np_telefono,
            'is_active'    => $this->np_activo,
        ]);

        $this->seleccionarProveedor($p->id);
        $this->np_nombre = $this->np_ruc = $this->np_direccion = $this->np_telefono = '';
        $this->np_activo = true;
        $this->modalNuevoProveedor = false;
        $this->success('Proveedor creado y seleccionado.', position: 'toast-bottom toast-end');
    }

    public function guardarNuevoProducto(): void
    {
        $this->validate([
            'nprod_nombre'       => ['required', 'string'],
            'nprod_categoria_id' => ['required'],
            'nprod_unit_id'      => ['required'],
            'nprod_tipo'         => ['required'],
            'nprod_precio_venta' => ['required', 'numeric', 'min:0'],
        ], [
            'nprod_nombre.required'       => 'El nombre es obligatorio.',
            'nprod_categoria_id.required' => 'Selecciona una categoría.',
            'nprod_unit_id.required'      => 'Selecciona una unidad de medida.',
            'nprod_precio_venta.required' => 'El precio de venta es obligatorio.',
        ]);

        $prod = Product::create([
            'name'        => $this->nprod_nombre,
            'category_id' => $this->nprod_categoria_id,
            'unit_id'     => $this->nprod_unit_id,
            'type'        => $this->nprod_tipo,
            'sale_price'  => (float)$this->nprod_precio_venta,
            'cost_price'  => (float)($this->nprod_precio_compra ?: 0),
            'stock'       => (int)$this->nprod_stock,
            'min_stock'   => (int)$this->nprod_min_stock,
            'is_active'   => true,
        ]);

        $this->seleccionarProducto($prod->id);
        $this->nprod_nombre = $this->nprod_categoria_id = $this->nprod_unit_id = '';
        $this->nprod_precio_venta = $this->nprod_precio_compra = '';
        $this->nprod_tipo = 'Producto';
        $this->nprod_stock = '0';
        $this->nprod_min_stock = '5';
        $this->modalNuevoProducto = false;
        $this->success('Producto creado y seleccionado.', position: 'toast-bottom toast-end');
    }
};
?>

<div class="p-4">

    <x-header title="Registro de Compras" separator class="mb-3" />

    <x-form wire:submit.prevent="registrarCompra">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">

           
            <div class="lg:col-span-2 flex flex-col gap-3">

                {{-- FACTURA --}}
                <x-card shadow class="p-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <x-input
                                label="N° de Compra"
                                wire:model="numero_factura"
                                icon="o-document-text"
                                class="input-xs"
                            />
                            @error('numero_factura') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-input
                                label="Fecha"
                                wire:model="fecha"
                                type="date"
                                icon="o-calendar"
                                class="input-xs"
                            />
                            @error('fecha') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </x-card>

               
                <x-card shadow class="p-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-semibold text-sm">Proveedor</span>
                        <x-button label="+ Nuevo" class="btn-xs btn-outline btn-primary"
                            wire:click.prevent="$set('modalNuevoProveedor', true)" />
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="relative">
                            <x-input label="Nombre" wire:model.live="busqueda_proveedor"
                                icon="o-building-office-2" class="input-xs" autocomplete="off" />
                            @error('busqueda_proveedor') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                            @if($mostrar_proveedores && count($this->sugerenciasProveedor()) > 0)
                                <div class="absolute z-50 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg mt-1 max-h-40 overflow-y-auto">
                                    @foreach($this->sugerenciasProveedor() as $prov)
                                        <div wire:click="seleccionarProveedor({{ $prov['id'] }})"
                                             class="px-3 py-1.5 hover:bg-base-200 cursor-pointer text-xs">
                                            <span class="font-semibold">{{ $prov['company_name'] }}</span>
                                            <span class="text-gray-400 ml-1">{{ $prov['ruc'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="relative">
                            <x-input label="RUC" wire:model.live="busqueda_ruc"
                                icon="o-identification" class="input-xs" autocomplete="off" />
                            @if($mostrar_proveedores_ruc && count($this->sugerenciasRuc()) > 0)
                                <div class="absolute z-50 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg mt-1 max-h-40 overflow-y-auto">
                                    @foreach($this->sugerenciasRuc() as $prov)
                                        <div wire:click="seleccionarProveedor({{ $prov['id'] }})"
                                             class="px-3 py-1.5 hover:bg-base-200 cursor-pointer text-xs">
                                            <span class="font-semibold">{{ $prov['ruc'] }}</span>
                                            <span class="text-gray-400 ml-1">{{ $prov['company_name'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="relative">
                            <x-input label="Teléfono" wire:model.live="busqueda_tel"
                                icon="o-phone" class="input-xs" autocomplete="off" />
                            @if($mostrar_proveedores_tel && count($this->sugerenciasTel()) > 0)
                                <div class="absolute z-50 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg mt-1 max-h-40 overflow-y-auto">
                                    @foreach($this->sugerenciasTel() as $prov)
                                        <div wire:click="seleccionarProveedor({{ $prov['id'] }})"
                                             class="px-3 py-1.5 hover:bg-base-200 cursor-pointer text-xs">
                                            <span class="font-semibold">{{ $prov['phone'] }}</span>
                                            <span class="text-gray-400 ml-1">{{ $prov['company_name'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </x-card>

               
                <x-card shadow class="p-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-semibold text-sm">Agregar Producto</span>
                        <x-button label="+ Nuevo" class="btn-xs btn-outline btn-secondary"
                            wire:click.prevent="$set('modalNuevoProducto', true)" />
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        <div class="relative col-span-2">
                            <x-input label="Producto" wire:model.live="busqueda_producto"
                                icon="o-cube" class="input-xs" autocomplete="off" />
                            @error('busqueda_producto') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                            @if($mostrar_productos && count($this->sugerenciasProducto()) > 0)
                                <div class="absolute z-50 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg mt-1 max-h-40 overflow-y-auto">
                                    @foreach($this->sugerenciasProducto() as $prod)
                                        <div wire:click="seleccionarProducto({{ $prod['id'] }})"
                                             class="px-3 py-1.5 hover:bg-base-200 cursor-pointer text-xs">
                                            <span class="font-semibold">{{ $prod['name'] }}</span>
                                            <span class="text-gray-400 ml-1">{{ $prod['unidad']['name'] ?? '' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div>
                            <x-input label="Código" wire:model="codigo_producto"
                                icon="o-qr-code" class="input-xs" readonly />
                        </div>
                        <div>
                            <x-input label="Unidad" wire:model="unidad_medida"
                                icon="o-scale" class="input-xs" readonly />
                        </div>
                        <div>
                            <x-input label="Cantidad" wire:model="cantidad"
                                type="number" min="1" icon="o-hashtag" class="input-xs" />
                            @error('cantidad') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-input label="P. Compra (C$)" wire:model="precio_compra"
                                type="number" step="0.01" prefix="C$"
                                icon="o-arrow-down-circle" class="input-xs" />
                            @error('precio_compra') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-input label="P. Venta (C$)" wire:model="precio_venta"
                                type="number" step="0.01" prefix="C$"
                                icon="o-arrow-up-circle" class="input-xs" />
                        </div>
                        <div class="flex items-end">
                            <x-button label="Agregar" icon="o-plus-circle"
                                class="btn-secondary btn-xs w-full"
                                wire:click.prevent="agregarProducto" />
                        </div>
                    </div>
                </x-card>

                
                <x-card shadow class="p-3">
                    <x-table :headers="$this->headers()" :rows="$this->items" striped>
                        @scope('cell_precio_compra', $row)
                            C$ {{ number_format($row['precio_compra'], 2) }}
                        @endscope
                        @scope('cell_subtotal', $row)
                            C$ {{ number_format($row['subtotal'], 2) }}
                        @endscope
                        @scope('cell_acciones', $row)
                            <div class="flex gap-1">
                                <x-button icon="o-pencil-square" class="btn-xs btn-warning btn-soft"
                                    tooltip="Editar" wire:click="abrirEditarItem({{ $loop->index }})" />
                                <x-button icon="o-trash" class="btn-xs btn-error btn-soft"
                                    tooltip="Eliminar" wire:click="eliminarItem({{ $loop->index }})" />
                            </div>
                        @endscope
                    </x-table>
                </x-card>

            </div>

           
            <div class="flex flex-col gap-3">

               
                <div class="bg-primary/10 border border-primary/30 rounded-xl px-4 py-3 text-right">
                    <p class="text-xs text-gray-500 uppercase font-bold tracking-widest">Total a Pagar</p>
                    <p class="text-2xl font-black text-primary">
                        C$ {{ number_format($this->total(), 2) }}
                    </p>
                </div>

               
                <x-card shadow class="p-3">
                    <p class="font-semibold text-sm mb-2">Forma de Pago</p>

                    
                    <div class="flex gap-4 mb-3">
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" wire:model.live="forma_pago" value="contado"
                                class="radio radio-primary radio-xs" />
                            <span class="text-xs font-medium">Al Contado</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" wire:model.live="forma_pago" value="transferencia"
                                class="radio radio-primary radio-xs" />
                            <span class="text-xs font-medium">Transferencia</span>
                        </label>
                    </div>

                  
                    @if($forma_pago === 'transferencia')
                        <div class="mb-3">
                            <x-input label="Referencia / Código" wire:model="ref_transferencia"
                                icon="o-hashtag" class="input-xs" />
                            @error('ref_transferencia') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <p class="font-semibold text-sm mb-2">Moneda</p>
                    <div class="flex flex-col gap-1.5 mb-3">
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" wire:model.live="metodo_pago" value="cordobas"
                                class="radio radio-primary radio-xs" />
                            <span class="text-xs font-medium">Córdobas (C$)</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" wire:model.live="metodo_pago" value="dolares"
                                class="radio radio-primary radio-xs" />
                            <span class="text-xs font-medium">Dólares ($)</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" wire:model.live="metodo_pago" value="mixto"
                                class="radio radio-primary radio-xs" />
                            <span class="text-xs font-medium">Mixto (C$ + $)</span>
                        </label>
                    </div>

                    
                    @if($metodo_pago === 'cordobas')
                        <x-input label="Monto C$" wire:model="monto_cordobas"
                            type="number" step="0.01" prefix="C$"
                            icon="o-banknotes" class="input-xs" />
                    @endif

                  
                    @if($metodo_pago === 'dolares')
                        <div class="flex flex-col gap-2">
                            <x-input label="Monto $" wire:model="monto_dolares"
                                type="number" step="0.01" prefix="$"
                                icon="o-currency-dollar" class="input-xs" />
                            <x-input label="Tasa de Cambio" wire:model="tasa_cambio"
                                type="number" step="0.01"
                                icon="o-arrows-right-left" class="input-xs" />
                        </div>
                    @endif

                    
                    @if($metodo_pago === 'mixto')
                        <div class="flex flex-col gap-2">
                            <x-input label="Monto C$" wire:model="monto_cordobas"
                                type="number" step="0.01" prefix="C$"
                                icon="o-banknotes" class="input-xs" />
                            <x-input label="Monto $" wire:model="monto_dolares"
                                type="number" step="0.01" prefix="$"
                                icon="o-currency-dollar" class="input-xs" />
                            <x-input label="Tasa de Cambio" wire:model="tasa_cambio"
                                type="number" step="0.01"
                                icon="o-arrows-right-left" class="input-xs" />
                        </div>
                        @error('monto_cordobas') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                    @endif
                </x-card>

               
                <div class="flex flex-col gap-2">
                    <x-button
                        label="Registrar Compra"
                        icon="o-check-circle"
                        class="btn-primary btn-sm w-full"
                        type="submit"
                    />
                    <x-button
                        label="Cancelar Compra"
                        icon="o-x-circle"
                        class="btn-ghost btn-sm w-full"
                        wire:click.prevent="confirmarCancelar"
                    />
                </div>

            </div>
        </div>

    </x-form>

   
    <x-modal wire:model="modalEditarItem" title="Editar Producto" separator>
        <div class="grid grid-cols-1 gap-4">
            <x-input label="Cantidad" wire:model="editCantidad"
                type="number" min="1" icon="o-hashtag" />
            @error('editCantidad') <p class="text-error text-xs -mt-3">{{ $message }}</p> @enderror
            <x-input label="Precio de Compra (C$)" wire:model="editPrecioCompra"
                type="number" step="0.01" prefix="C$" icon="o-arrow-down-circle" />
            @error('editPrecioCompra') <p class="text-error text-xs -mt-3">{{ $message }}</p> @enderror
        </div>
        <x-slot:actions>
            <x-button label="Cancelar" wire:click="$set('modalEditarItem', false)" />
            <x-button label="Guardar" icon="o-check" class="btn-primary" wire:click="guardarEditarItem" />
        </x-slot:actions>
    </x-modal>

   
    <x-modal wire:model="modalCancelar" title="¿Cancelar compra?" separator>
        <p class="text-gray-600">Se perderán todos los datos. ¿Deseas continuar?</p>
        <x-slot:actions>
            <x-button label="No, continuar" wire:click="$set('modalCancelar', false)" />
            <x-button label="Sí, cancelar" icon="o-trash" class="btn-error" wire:click="cancelarCompra" />
        </x-slot:actions>
    </x-modal>

    
    <x-modal wire:model="modalNuevoProveedor" title="Registrar Nuevo Proveedor" separator>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-input label="Nombre"    wire:model="np_nombre"    icon="o-building-office" />
            <x-input label="RUC"       wire:model="np_ruc"       icon="o-identification" />
            <x-input label="Dirección" wire:model="np_direccion" icon="o-map-pin" class="md:col-span-2" />
            <x-input label="Teléfono"  wire:model="np_telefono"  icon="o-phone" />
            <div class="flex items-center gap-3 mt-2">
                <x-toggle label="Activo" wire:model="np_activo" />
            </div>
        </div>
        @error('np_nombre')    <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
        @error('np_ruc')       <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
        @error('np_direccion') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
        @error('np_telefono')  <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
        <x-slot:actions>
            <x-button label="Cancelar" wire:click="$set('modalNuevoProveedor', false)" />
            <x-button label="Guardar Proveedor" icon="o-check" class="btn-primary" wire:click="guardarNuevoProveedor" />
        </x-slot:actions>
    </x-modal>

    
    <x-modal wire:model="modalNuevoProducto" title="Registrar Nuevo Producto" separator>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-input label="Nombre" wire:model="nprod_nombre" icon="o-cube" class="md:col-span-2" />
            <x-select label="Categoría" wire:model="nprod_categoria_id"
                :options="$this->categoriasOpciones()" placeholder="Seleccionar" icon="o-tag" />
            <x-select label="Unidad de Medida" wire:model="nprod_unit_id"
                :options="$this->unidadesOpciones()" placeholder="Seleccionar" icon="o-scale" />
            <x-select label="Tipo" wire:model="nprod_tipo"
                :options="[['id'=>'Producto','name'=>'Producto'],['id'=>'Servicio','name'=>'Servicio']]"
                icon="o-tag" />
            <x-input label="Precio de Venta (C$)" wire:model="nprod_precio_venta"
                type="number" step="0.01" prefix="C$" icon="o-arrow-up-circle" />
            <x-input label="Precio de Compra (C$)" wire:model="nprod_precio_compra"
                type="number" step="0.01" prefix="C$" icon="o-arrow-down-circle" />
            <x-input label="Stock Inicial" wire:model="nprod_stock" type="number" icon="o-archive-box" />
            <x-input label="Stock Mínimo"  wire:model="nprod_min_stock" type="number" icon="o-exclamation-triangle" />
        </div>
        @error('nprod_nombre')       <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
        @error('nprod_categoria_id') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
        @error('nprod_unit_id')      <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
        @error('nprod_precio_venta') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
        <x-slot:actions>
            <x-button label="Cancelar" wire:click="$set('modalNuevoProducto', false)" />
            <x-button label="Guardar Producto" icon="o-check" class="btn-primary" wire:click="guardarNuevoProducto" />
        </x-slot:actions>
    </x-modal>

</div>