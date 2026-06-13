<?php

use Livewire\Volt\Component;
use App\Models\{Provider, Product, Purchase, PurchaseItem, Category, Unit};
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    // Propiedades agrupadas
    public string $numero_factura = '', $fecha = '', $busqueda_proveedor = '', $busqueda_ruc = '', $busqueda_tel = '';
    public ?int $proveedor_id = null, $producto_id = null, $editItemIndex = null;
    public bool $mostrar_proveedores = false, $mostrar_proveedores_ruc = false, $mostrar_proveedores_tel = false, $mostrar_productos = false;

    public string $busqueda_producto = '', $codigo_producto = '', $unidad_medida = '', $cantidad = '', $precio_compra = '', $precio_venta = '';
    public array $items = [];
    public bool $modalEditarItem = false, $modalCancelar = false;
    public string $editCantidad = '', $editPrecioCompra = '';

    public string $forma_pago = 'contado', $metodo_pago = 'cordobas', $monto_cordobas = '', $monto_dolares = '', $tasa_cambio = '36.50', $ref_transferencia = '';

    public bool $modalNuevoProveedor = false, $np_activo = true, $modalNuevoProducto = false;
    public string $np_nombre = '', $np_ruc = '', $np_direccion = '', $np_telefono = '';
    public string $nprod_nombre = '', $nprod_categoria_id = '', $nprod_unit_id = '', $nprod_precio_venta = '', $nprod_precio_compra = '', $nprod_stock = '0', $nprod_min_stock = '5';

    public function mount(): void
    {
        $saved = session('compra_en_curso', []);
        if (!empty($saved)) {
            foreach ($saved as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        } else {
            $this->numero_factura = 'C-' . str_pad((Purchase::max('id') ?? 0) + 1, 5, '0', STR_PAD_LEFT);
            $this->fecha = now()->format('Y-m-d');
        }
    }

    public function guardarSesion(): void
    {
        session([
            'compra_en_curso' => collect([
                'numero_factura', 'fecha', 'proveedor_id', 'busqueda_proveedor', 'busqueda_ruc',
                'busqueda_tel', 'items', 'forma_pago', 'metodo_pago', 'monto_cordobas',
                'monto_dolares', 'tasa_cambio', 'ref_transferencia'
            ])->mapWithKeys(fn($k) => [$k => $this->$k])->toArray()
        ]);
    }

    public function updated($property): void
    {
        $sessionProps = ['numero_factura', 'fecha', 'forma_pago', 'metodo_pago', 'monto_cordobas', 'monto_dolares', 'tasa_cambio', 'ref_transferencia'];
        if (in_array($property, $sessionProps)) {
            $this->guardarSesion();
        }

        if ($property === 'busqueda_proveedor') { $this->mostrar_proveedores = true; $this->proveedor_id = null; }
        if ($property === 'busqueda_ruc') { $this->mostrar_proveedores_ruc = true; $this->proveedor_id = null; }
        if ($property === 'busqueda_tel') { $this->mostrar_proveedores_tel = true; $this->proveedor_id = null; }
        if ($property === 'busqueda_producto') { $this->mostrar_productos = true; $this->producto_id = null; }
    }

    public function sugerenciasProveedor(): array {
        return strlen($this->busqueda_proveedor) < 1 ? [] : Provider::where('is_active', true)->where('company_name', 'like', "%{$this->busqueda_proveedor}%")->limit(6)->get()->toArray();
    }

    public function sugerenciasRuc(): array {
        return strlen($this->busqueda_ruc) < 1 ? [] : Provider::where('is_active', true)->where('ruc', 'like', "%{$this->busqueda_ruc}%")->limit(6)->get()->toArray();
    }

    public function sugerenciasTel(): array {
        return strlen($this->busqueda_tel) < 1 ? [] : Provider::where('is_active', true)->where('phone', 'like', "%{$this->busqueda_tel}%")->limit(6)->get()->toArray();
    }

    public function seleccionarProveedor(int $id): void
    {
        $p = Provider::where('is_active', true)->findOrFail($id);
        $this->proveedor_id = $p->id;
        $this->busqueda_proveedor = $p->company_name;
        $this->busqueda_ruc = $p->ruc ?? '';
        $this->busqueda_tel = $p->phone ?? '';
        $this->mostrar_proveedores = $this->mostrar_proveedores_ruc = $this->mostrar_proveedores_tel = false;
        $this->guardarSesion();
    }

    public function sugerenciasProducto(): array
    {
        return strlen($this->busqueda_producto) < 1 ? [] : Product::where('is_active', true)
            ->where('type', 'Producto')
            ->where('name', 'like', "%{$this->busqueda_producto}%")
            ->with('unit')->limit(6)->get()->toArray();
    }

    public function seleccionarProducto(int $id): void
    {
        $p = Product::where('is_active', true)->with('unit')->findOrFail($id);
        $this->producto_id = $p->id;
        $this->busqueda_producto = $p->name;
        $this->codigo_producto = (string) $p->id;
        $this->unidad_medida = $p->unit->name ?? '';
        $this->precio_compra = '';
        $this->precio_venta = (string) $p->sale_price;
        $this->mostrar_productos = false;
    }

    public function agregarProducto(): void
    {
        if (!$this->producto_id) { $this->addError('busqueda_producto', 'Selecciona un producto.'); return; }
        if ((float)$this->cantidad <= 0) { $this->addError('cantidad', 'Mayor a 0.'); return; }
        if ((float)$this->precio_compra <= 0) { $this->addError('precio_compra', 'Obligatorio.'); return; }
        if ((float)$this->precio_compra > (float)$this->precio_venta) { $this->addError('precio_compra', 'No mayor a P. Venta.'); return; }

        $this->items[] = [
            'producto_id' => $this->producto_id,
            'producto' => $this->busqueda_producto,
            'codigo' => $this->codigo_producto,
            'unidad' => $this->unidad_medida,
            'cantidad' => (float)$this->cantidad,
            'precio_compra' => (float)$this->precio_compra,
            'precio_venta' => (float)$this->precio_venta,
            'subtotal' => (float)$this->cantidad * (float)$this->precio_compra
        ];

        $this->guardarSesion();
        $this->resetProducto();
    }

    public function resetProducto(): void {
        $this->reset(['busqueda_producto', 'producto_id', 'codigo_producto', 'unidad_medida', 'cantidad', 'precio_compra', 'precio_venta', 'mostrar_productos']);
        $this->resetErrorBag();
    }

    public function eliminarItem(int $index): void {
        array_splice($this->items, $index, 1);
        $this->items = array_values($this->items);
        $this->guardarSesion();
    }

    public function abrirEditarItem(int $index): void {
        $this->editItemIndex = $index;
        $this->editCantidad = (string) $this->items[$index]['cantidad'];
        $this->editPrecioCompra = (string) $this->items[$index]['precio_compra'];
        $this->modalEditarItem = true;
    }

    public function guardarEditarItem(): void
    {
        if ((float)$this->editCantidad <= 0) { $this->addError('editCantidad', 'Mayor a 0.'); return; }
        if ((float)$this->editPrecioCompra > $this->items[$this->editItemIndex]['precio_venta']) { $this->addError('editPrecioCompra', 'No mayor a P. Venta.'); return; }

        $this->items[$this->editItemIndex]['cantidad'] = (float)$this->editCantidad;
        $this->items[$this->editItemIndex]['precio_compra'] = (float)$this->editPrecioCompra;
        $this->items[$this->editItemIndex]['subtotal'] = (float)$this->editCantidad * (float)$this->editPrecioCompra;

        $this->reset(['modalEditarItem', 'editItemIndex', 'editCantidad', 'editPrecioCompra']);
        $this->guardarSesion();
    }

    public function total(): float {
        return array_sum(array_column($this->items, 'subtotal'));
    }

    public function vuelto(): float
    {
        $t = $this->total();
        $tasa = (float)($this->tasa_cambio ?: 36.50);
        $pC = $this->monto_cordobas !== '' ? (float)$this->monto_cordobas : 0;
        $pD = $this->monto_dolares !== '' ? (float)$this->monto_dolares : 0;

        if ($this->metodo_pago === 'cordobas') return $this->monto_cordobas === '' ? 0.0 : max(0.0, $pC - $t);
        if ($this->metodo_pago === 'dolares') return $this->monto_dolares === '' ? 0.0 : max(0.0, ($pD * $tasa) - $t);

        return max(0.0, ($pC + ($pD * $tasa)) - $t);
    }

    public function headers(): array {
        return [
            ['key'=>'producto','label'=>'Producto'],
            ['key'=>'codigo','label'=>'Código'],
            ['key'=>'unidad','label'=>'Unidad'],
            ['key'=>'cantidad','label'=>'Cant.'],
            ['key'=>'precio_compra','label'=>'P. Compra'],
            ['key'=>'subtotal','label'=>'Subtotal'],
            ['key'=>'acciones','label'=>'Acciones','sortable'=>false]
        ];
    }

    public function confirmarCancelar(): void {
        $this->modalCancelar = true;
    }

    public function cancelarCompra(): void
    {
        session()->forget('compra_en_curso');
        $this->reset(['proveedor_id','busqueda_proveedor','busqueda_ruc','busqueda_tel','items','monto_cordobas','monto_dolares','ref_transferencia','modalCancelar']);
        $this->numero_factura = 'C-' . str_pad((Purchase::max('id') ?? 0) + 1, 5, '0', STR_PAD_LEFT);
        $this->fecha = now()->format('Y-m-d');
        $this->forma_pago = 'contado';
        $this->metodo_pago = 'cordobas';
        $this->tasa_cambio = '36.50';
        $this->resetProducto();
    }

    public function registrarCompra(): void
    {
        if (!$this->proveedor_id) { $this->addError('busqueda_proveedor', 'Selecciona un proveedor.'); return; }
        if (empty($this->items)) { $this->addError('busqueda_producto', 'Agrega un producto.'); return; }
        if ($this->numero_factura === '') { $this->addError('numero_factura', 'Obligatorio.'); return; }
        if ($this->forma_pago === 'transferencia' && $this->ref_transferencia === '') { $this->addError('ref_transferencia', 'Ingresa referencia.'); return; }
        if ($this->metodo_pago === 'mixto' && $this->monto_cordobas === '' && $this->monto_dolares === '') { $this->addError('monto_cordobas', 'Ingresa un monto.'); return; }

        $tasa = (float)($this->tasa_cambio ?: 36.50);
        $t = $this->total();
        $aC = $this->metodo_pago === 'cordobas' ? ($this->monto_cordobas !== '' ? (float)$this->monto_cordobas : $t) : ($this->metodo_pago === 'mixto' ? (float)$this->monto_cordobas : 0.0);
        $aD = $this->metodo_pago === 'dolares' ? ($this->monto_dolares !== '' ? (float)$this->monto_dolares : ($t / $tasa)) : ($this->metodo_pago === 'mixto' ? (float)$this->monto_dolares : 0.0);

        $purchase = Purchase::create([
            'provider_id' => $this->proveedor_id,
            'user_id' => auth()->id(),
            'purchase_date' => $this->fecha,
            'provider_invoice_number' => $this->numero_factura,
            'total' => $t,
            'payment_method' => $this->forma_pago === 'transferencia' ? 'transferencia' : $this->metodo_pago,
            'amount_cordobas' => $aC,
            'amount_dolares' => $aD,
            'exchange_rate' => $tasa
        ]);

        foreach ($this->items as $item) {
            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $item['producto_id'],
                'quantity' => $item['cantidad'],
                'cost_price' => $item['precio_compra'],
                'subtotal' => $item['subtotal']
            ]);

            $prod = Product::findOrFail($item['producto_id']);
            $prod->update([
                'stock' => $prod->stock + $item['cantidad'],
                'cost_price' => $item['precio_compra'],
                'sale_price' => $item['precio_venta']
            ]);
        }

        $this->cancelarCompra();
        $this->success('Compra registrada correctamente.', position: 'toast-bottom toast-end');
    }

    public function categoriasOpciones(): array {
        return Category::where('is_active', true)->get()->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->toArray();
    }

    public function unidadesOpciones(): array {
        return Unit::all()->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->toArray();
    }

    public function guardarNuevoProveedor(): void
    {
        $this->validate([
            'np_nombre' => 'required|regex:/^[^\d]+$/',
            'np_ruc' => 'required|alpha_num|unique:providers,ruc',
            'np_direccion' => 'required|string',
            'np_telefono' => 'required|regex:/^[0-9\s\+\-]+$/'
        ]);

        $p = Provider::create([
            'company_name' => $this->np_nombre,
            'ruc' => $this->np_ruc,
            'address' => $this->np_direccion,
            'phone' => $this->np_telefono,
            'is_active' => $this->np_activo
        ]);

        $this->seleccionarProveedor($p->id);
        $this->reset(['np_nombre', 'np_ruc', 'np_direccion', 'np_telefono', 'modalNuevoProveedor']);
        $this->np_activo = true;

        $this->success('Proveedor creado.', position: 'toast-bottom toast-end');
    }

    public function guardarNuevoProducto(): void
    {
        $this->validate([
            'nprod_nombre' => 'required|string',
            'nprod_categoria_id' => 'required',
            'nprod_unit_id' => 'required',
            'nprod_precio_venta' => 'required|numeric|min:0'
        ]);

        $prod = Product::create([
            'name' => $this->nprod_nombre,
            'category_id' => $this->nprod_categoria_id,
            'unit_id' => $this->nprod_unit_id,
            'type' => 'Producto',
            'sale_price' => (float)$this->nprod_precio_venta,
            'cost_price' => (float)($this->nprod_precio_compra ?: 0),
            'stock' => (int)$this->nprod_stock,
            'min_stock' => (int)$this->nprod_min_stock,
            'is_active' => true
        ]);

        $this->seleccionarProducto($prod->id);
        $this->reset(['nprod_nombre', 'nprod_categoria_id', 'nprod_unit_id', 'nprod_precio_venta', 'nprod_precio_compra', 'modalNuevoProducto']);
        $this->nprod_stock = '0';
        $this->nprod_min_stock = '5';

        $this->success('Producto creado.', position: 'toast-bottom toast-end');
    }
};
?>

<div class="p-6">
    <x-header title="Registro de Compras" subtitle="Gestión de inventario y facturación de proveedores" separator class="mb-6" />

    <x-form wire:submit.prevent="registrarCompra">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            {{-- PANEL PRINCIPAL (8 Columnas) --}}
            <div class="lg:col-span-8 flex flex-col gap-6">

                {{-- SECCIÓN: DATOS DE FACTURA --}}
                <x-card shadow class="border border-base-200">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input label="N° de Compra / Factura" wire:model="numero_factura" icon="o-document-text" class="input-md" />
                            @error('numero_factura') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-input label="Fecha de Registro" wire:model="fecha" type="date" icon="o-calendar" class="input-md" />
                            @error('fecha') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </x-card>

                {{-- SECCIÓN: PROVEEDOR --}}
                <x-card shadow class="border border-base-200">
                    <x-slot:title>
                        <div class="flex items-center justify-between">
                            <span class="text-lg font-bold">Datos del Proveedor</span>
                            <x-button label="Nuevo Proveedor" icon="o-plus" class="btn-sm btn-outline btn-primary" wire:click.prevent="$set('modalNuevoProveedor', true)" />
                        </div>
                    </x-slot:title>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2">
                        <div class="relative">
                            <x-input label="Nombre de Empresa" wire:model.live="busqueda_proveedor" icon="o-building-office-2" class="input-md" autocomplete="off" placeholder="Buscar..." />
                            @error('busqueda_proveedor') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror

                            @if($mostrar_proveedores && count($this->sugerenciasProveedor()) > 0)
                                <div class="absolute z-50 w-full bg-base-100 border border-base-200 rounded-xl shadow-xl mt-2 max-h-60 overflow-y-auto">
                                    @foreach($this->sugerenciasProveedor() as $prov)
                                        <div wire:click="seleccionarProveedor({{ $prov['id'] }})" class="px-4 py-3 hover:bg-base-200 cursor-pointer border-b border-base-100 last:border-0 transition-colors">
                                            <div class="font-bold text-sm">{{ $prov['company_name'] }}</div>
                                            <div class="text-xs text-gray-500">RUC: {{ $prov['ruc'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="relative">
                            <x-input label="RUC" wire:model.live="busqueda_ruc" icon="o-identification" class="input-md" autocomplete="off" placeholder="Buscar RUC..." />
                            @if($mostrar_proveedores_ruc && count($this->sugerenciasRuc()) > 0)
                                <div class="absolute z-50 w-full bg-base-100 border border-base-200 rounded-xl shadow-xl mt-2 max-h-60 overflow-y-auto">
                                    @foreach($this->sugerenciasRuc() as $prov)
                                        <div wire:click="seleccionarProveedor({{ $prov['id'] }})" class="px-4 py-3 hover:bg-base-200 cursor-pointer border-b border-base-100 last:border-0 transition-colors">
                                            <div class="font-bold text-sm">{{ $prov['ruc'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $prov['company_name'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="relative">
                            <x-input label="Teléfono" wire:model.live="busqueda_tel" icon="o-phone" class="input-md" autocomplete="off" placeholder="Buscar Teléfono..." />
                            @if($mostrar_proveedores_tel && count($this->sugerenciasTel()) > 0)
                                <div class="absolute z-50 w-full bg-base-100 border border-base-200 rounded-xl shadow-xl mt-2 max-h-60 overflow-y-auto">
                                    @foreach($this->sugerenciasTel() as $prov)
                                        <div wire:click="seleccionarProveedor({{ $prov['id'] }})" class="px-4 py-3 hover:bg-base-200 cursor-pointer border-b border-base-100 last:border-0 transition-colors">
                                            <div class="font-bold text-sm">{{ $prov['phone'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $prov['company_name'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </x-card>

                {{-- SECCIÓN: AGREGAR PRODUCTOS --}}
                <x-card shadow class="border border-base-200">
                    <x-slot:title>
                        <div class="flex items-center justify-between">
                            <span class="text-lg font-bold">Insumos y Productos</span>
                            <x-button label="Nuevo Producto" icon="o-plus" class="btn-sm btn-outline btn-secondary" wire:click.prevent="$set('modalNuevoProducto', true)" />
                        </div>
                    </x-slot:title>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-2 items-start">
                        <div class="relative md:col-span-4">
                            <x-input label="Buscar Producto" wire:model.live="busqueda_producto" icon="o-cube" class="input-md" autocomplete="off" placeholder="Escribe el nombre..." />
                            @error('busqueda_producto') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror

                            @if($mostrar_productos && count($this->sugerenciasProducto()) > 0)
                                <div class="absolute z-50 w-full bg-base-100 border border-base-200 rounded-xl shadow-xl mt-2 max-h-60 overflow-y-auto">
                                    @foreach($this->sugerenciasProducto() as $prod)
                                        <div wire:click="seleccionarProducto({{ $prod['id'] }})" class="px-4 py-3 hover:bg-base-200 cursor-pointer border-b border-base-100 last:border-0 transition-colors">
                                            <div class="font-bold text-sm">{{ $prod['name'] }}</div>
                                            <div class="text-xs text-gray-500">Unidad: {{ $prod['unidad']['name'] ?? 'N/A' }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="md:col-span-2">
                            <x-input label="Código" wire:model="codigo_producto" icon="o-qr-code" class="input-md bg-base-200" readonly />
                        </div>

                        <div class="md:col-span-2">
                            <x-input label="Cantidad" wire:model="cantidad" type="number" min="1" icon="o-hashtag" class="input-md" />
                            @error('cantidad') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <x-input label="P. Compra" wire:model="precio_compra" type="number" step="0.01" prefix="C$" class="input-md text-primary font-bold" />
                            @error('precio_compra') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2 pt-6">
                            <x-button label="Agregar" icon="o-plus-circle" class="btn-secondary w-full" wire:click.prevent="agregarProducto" />
                        </div>
                    </div>
                </x-card>

                {{-- SECCIÓN: TABLA DE CARRITO --}}
                <x-card shadow class="border border-base-200 p-0 overflow-hidden">
                    <x-table :headers="$this->headers()" :rows="$this->items" striped class="bg-base-100">
                        @scope('cell_producto', $row)
                            <span class="font-bold">{{ $row['producto'] }}</span>
                        @endscope

                        @scope('cell_precio_compra', $row)
                            <span class="text-gray-600">C$ {{ number_format($row['precio_compra'], 2) }}</span>
                        @endscope

                        @scope('cell_subtotal', $row)
                            <span class="font-bold text-primary">C$ {{ number_format($row['subtotal'], 2) }}</span>
                        @endscope

                        @scope('cell_acciones', $row)
                            <div class="flex gap-2">
                                <x-button icon="o-pencil-square" class="btn-sm btn-circle btn-warning btn-soft" tooltip="Editar" wire:click="abrirEditarItem({{ $loop->index }})" />
                                <x-button icon="o-trash" class="btn-sm btn-circle btn-error btn-soft" tooltip="Eliminar" wire:click="eliminarItem({{ $loop->index }})" />
                            </div>
                        @endscope

                        <x-slot:empty>
                            <div class="text-center py-8 text-gray-400">
                                <x-icon name="o-shopping-cart" class="w-12 h-12 mx-auto mb-3 opacity-50" />
                                <p>No hay productos en la lista de compras.</p>
                            </div>
                        </x-slot:empty>
                    </x-table>
                </x-card>
            </div>

            {{-- PANEL LATERAL (4 Columnas) --}}
            <div class="lg:col-span-4 flex flex-col gap-6">

                {{-- SECCIÓN: RESUMEN FINANCIERO --}}
                <div class="bg-primary border border-primary text-primary-content rounded-2xl p-6 shadow-lg text-right">
                    <p class="text-sm uppercase font-bold tracking-widest opacity-80 mb-1">Total a Pagar</p>
                    <p class="text-4xl font-black mb-2">C$ {{ number_format($this->total(), 2) }}</p>

                    @if($this->vuelto() > 0)
                        <hr class="border-primary-content/20 my-4" />
                        <p class="text-sm uppercase font-bold tracking-widest opacity-80 mb-1">Vuelto / Sobrante</p>
                        <p class="text-2xl font-bold text-success-content">C$ {{ number_format($this->vuelto(), 2) }}</p>
                    @endif
                </div>

                {{-- SECCIÓN: MÉTODO DE PAGO --}}
                <x-card shadow class="border border-base-200">
                    <x-slot:title>
                        <span class="text-lg font-bold">Detalles de Pago</span>
                    </x-slot:title>

                    <div class="mt-4">
                        <p class="font-semibold text-sm text-gray-500 mb-3 uppercase tracking-wider">Forma de Pago</p>
                        <div class="grid grid-cols-2 gap-3 mb-6">
                            <label class="flex items-center gap-2 cursor-pointer p-3 border border-base-300 rounded-lg hover:bg-base-200 transition-colors {{ $forma_pago === 'contado' ? 'bg-primary/10 border-primary' : '' }}">
                                <input type="radio" wire:model.live="forma_pago" value="contado" class="radio radio-primary radio-sm" />
                                <span class="text-sm font-bold">Contado</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer p-3 border border-base-300 rounded-lg hover:bg-base-200 transition-colors {{ $forma_pago === 'transferencia' ? 'bg-primary/10 border-primary' : '' }}">
                                <input type="radio" wire:model.live="forma_pago" value="transferencia" class="radio radio-primary radio-sm" />
                                <span class="text-sm font-bold">Transferencia</span>
                            </label>
                        </div>

                        @if($forma_pago === 'transferencia')
                            <div class="mb-6 animate-fade-in-down">
                                <x-input label="N° de Referencia" wire:model="ref_transferencia" icon="o-hashtag" class="input-md" placeholder="Ej: 00129348" />
                                @error('ref_transferencia') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <hr class="border-base-200 my-4" />

                        <p class="font-semibold text-sm text-gray-500 mb-3 uppercase tracking-wider">Moneda de Pago</p>
                        <div class="grid grid-cols-3 gap-2 mb-6">
                            <label class="flex items-center justify-center gap-2 cursor-pointer p-2 border border-base-300 rounded-lg hover:bg-base-200 transition-colors {{ $metodo_pago === 'cordobas' ? 'bg-primary/10 border-primary' : '' }}">
                                <input type="radio" wire:model.live="metodo_pago" value="cordobas" class="radio radio-primary radio-sm hidden" />
                                <span class="text-sm font-bold">Córdobas</span>
                            </label>
                            <label class="flex items-center justify-center gap-2 cursor-pointer p-2 border border-base-300 rounded-lg hover:bg-base-200 transition-colors {{ $metodo_pago === 'dolares' ? 'bg-primary/10 border-primary' : '' }}">
                                <input type="radio" wire:model.live="metodo_pago" value="dolares" class="radio radio-primary radio-sm hidden" />
                                <span class="text-sm font-bold">Dólares</span>
                            </label>
                            <label class="flex items-center justify-center gap-2 cursor-pointer p-2 border border-base-300 rounded-lg hover:bg-base-200 transition-colors {{ $metodo_pago === 'mixto' ? 'bg-primary/10 border-primary' : '' }}">
                                <input type="radio" wire:model.live="metodo_pago" value="mixto" class="radio radio-primary radio-sm hidden" />
                                <span class="text-sm font-bold">Mixto</span>
                            </label>
                        </div>

                        <div class="space-y-4">
                            @if($metodo_pago === 'cordobas' || $metodo_pago === 'mixto')
                                <x-input label="Efectivo Recibido (C$)" wire:model.live="monto_cordobas" type="number" step="0.01" prefix="C$" icon="o-banknotes" class="input-md text-lg font-bold" />
                                @error('monto_cordobas') <p class="text-error text-xs">{{ $message }}</p> @enderror
                            @endif

                            @if($metodo_pago === 'dolares' || $metodo_pago === 'mixto')
                                <x-input label="Efectivo Recibido ($)" wire:model.live="monto_dolares" type="number" step="0.01" prefix="$" icon="o-currency-dollar" class="input-md text-lg font-bold text-success" />
                                <x-input label="Tasa de Cambio Oficial" wire:model.live="tasa_cambio" type="number" step="0.01" icon="o-arrows-right-left" class="input-sm" />
                            @endif
                        </div>
                    </div>
                </x-card>

                {{-- BOTONES DE ACCIÓN FINAL --}}
                <div class="flex flex-col gap-3 mt-2">
                    <x-button label="Procesar Compra" icon="o-check-circle" class="btn-primary w-full text-lg shadow-lg" type="submit" />
                    <x-button label="Cancelar y Limpiar Todo" icon="o-trash" class="btn-ghost text-error w-full" wire:click.prevent="confirmarCancelar" />
                </div>
            </div>
        </div>
    </x-form>

    {{-- ============================== --}}
    {{-- MODALES SECUNDARIOS Y ALERTAS  --}}
    {{-- ============================== --}}

    <x-modal wire:model="modalEditarItem" title="Editar Cantidad y Precio" separator>
        <div class="grid gap-6 py-4">
            <div>
                <x-input label="Nueva Cantidad" wire:model="editCantidad" type="number" min="1" icon="o-hashtag" class="input-md" />
                @error('editCantidad') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-input label="Nuevo Precio de Compra (C$)" wire:model="editPrecioCompra" type="number" step="0.01" prefix="C$" icon="o-arrow-down-circle" class="input-md" />
                @error('editPrecioCompra') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <x-slot:actions>
            <x-button label="Cancelar" wire:click="$set('modalEditarItem', false)" class="btn-ghost" />
            <x-button label="Guardar Cambios" class="btn-primary" icon="o-check" wire:click="guardarEditarItem" />
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="modalCancelar" title="⚠️ Atención: ¿Anular Compra?" separator>
        <div class="py-4">
            <p class="text-gray-600 text-lg">Se perderán todos los datos ingresados en el carrito y el proveedor seleccionado.</p>
            <p class="font-bold mt-2">¿Estás seguro de que deseas vaciar el registro?</p>
        </div>
        <x-slot:actions>
            <x-button label="No, mantener datos" wire:click="$set('modalCancelar', false)" class="btn-ghost" />
            <x-button label="Sí, anular registro" class="btn-error" icon="o-trash" wire:click="cancelarCompra" />
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="modalNuevoProveedor" title="Crear Nuevo Proveedor" separator>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 py-4">
            <x-input label="Nombre de Empresa" wire:model="np_nombre" icon="o-building-office" class="input-md" />
            <x-input label="RUC" wire:model="np_ruc" icon="o-identification" class="input-md" />
            <div class="md:col-span-2">
                <x-input label="Dirección Completa" wire:model="np_direccion" icon="o-map-pin" class="input-md" />
            </div>
            <x-input label="Teléfono / Celular" wire:model="np_telefono" icon="o-phone" class="input-md" />
            <div class="flex items-center pt-6">
                <x-toggle label="Proveedor Activo" wire:model="np_activo" class="toggle-primary" right />
            </div>
        </div>
        <x-slot:actions>
            <x-button label="Cancelar" wire:click="$set('modalNuevoProveedor', false)" class="btn-ghost" />
            <x-button label="Guardar Proveedor" class="btn-primary" icon="o-check" wire:click="guardarNuevoProveedor" />
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="modalNuevoProducto" title="Crear Insumo / Producto" separator>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 py-4">
            <div class="md:col-span-2">
                <x-input label="Nombre del Producto" wire:model="nprod_nombre" icon="o-cube" class="input-md" />
            </div>
            <x-select label="Categoría" wire:model="nprod_categoria_id" :options="$this->categoriasOpciones()" icon="o-tag" class="select-md" placeholder="Seleccione..." />
            <x-select label="Unidad de Medida" wire:model="nprod_unit_id" :options="$this->unidadesOpciones()" icon="o-scale" class="select-md" placeholder="Seleccione..." />
            <x-input label="Precio Costo Sugerido" wire:model="nprod_precio_compra" type="number" step="0.01" prefix="C$" class="input-md" />
            <x-input label="Precio Venta al Público" wire:model="nprod_precio_venta" type="number" step="0.01" prefix="C$" class="input-md" />
            <x-input label="Stock Inicial" wire:model="nprod_stock" type="number" class="input-md" />
            <x-input label="Alerta de Stock Mínimo" wire:model="nprod_min_stock" type="number" class="input-md" />
        </div>
        <x-slot:actions>
            <x-button label="Cancelar" wire:click="$set('modalNuevoProducto', false)" class="btn-ghost" />
            <x-button label="Guardar Producto" class="btn-primary" icon="o-check" wire:click="guardarNuevoProducto" />
        </x-slot:actions>
    </x-modal>
</div>
