<?php

use Livewire\Volt\Component;
use App\Models\{Provider, Product, Purchase, PurchaseItem, Category, Unit};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Mary\Traits\Toast;
use Livewire\Attributes\Computed;

new class extends Component {
    use Toast;

    public string $numero_factura = '', $fecha = '', $busqueda_proveedor = '', $busqueda_ruc = '', $busqueda_tel = '';
    public ?int $proveedor_id = null, $producto_id = null, $editItemIndex = null;
    public bool $mostrar_proveedores = false, $mostrar_proveedores_ruc = false, $mostrar_proveedores_tel = false, $mostrar_productos = false;

    public string $busqueda_producto = '', $codigo_producto = '', $unit_name = '', $cantidad = '', $precio_compra = '', $precio_venta = '';
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
        $dataToSave = collect([
            'numero_factura', 'fecha', 'proveedor_id', 'busqueda_proveedor', 'busqueda_ruc', 'busqueda_tel',
            'items', 'forma_pago', 'metodo_pago', 'monto_cordobas', 'monto_dolares', 'tasa_cambio', 'ref_transferencia'
        ])->mapWithKeys(fn($k) => [$k => $this->$k])->toArray();

        session(['compra_en_curso' => $dataToSave]);
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

    #[Computed]
    public function sugerenciasProveedor(): array
    {
        return strlen($this->busqueda_proveedor) < 1 ? [] : Provider::where('is_active', true)->where('company_name', 'like', "%{$this->busqueda_proveedor}%")->limit(6)->get()->toArray();
    }

    #[Computed]
    public function sugerenciasRuc(): array
    {
        return strlen($this->busqueda_ruc) < 1 ? [] : Provider::where('is_active', true)->where('ruc', 'like', "%{$this->busqueda_ruc}%")->limit(6)->get()->toArray();
    }

    #[Computed]
    public function sugerenciasTel(): array
    {
        return strlen($this->busqueda_tel) < 1 ? [] : Provider::where('is_active', true)->where('phone', 'like', "%{$this->busqueda_tel}%")->limit(6)->get()->toArray();
    }

    public function seleccionarProveedor(int $id): void
    {
        $p = Provider::where('is_active', true)->findOrFail($id);
        $this->proveedor_id = $p->id;
        $this->busqueda_proveedor = $p->company_name;
        $this->busqueda_ruc = $p->ruc ?? '';
        $this->busqueda_tel = $p->phone ?? '';

        $this->mostrar_proveedores = false;
        $this->mostrar_proveedores_ruc = false;
        $this->mostrar_proveedores_tel = false;

        $this->guardarSesion();
    }

    #[Computed]
    public function sugerenciasProducto(): array
    {
        return strlen($this->busqueda_producto) < 1 ? [] : Product::where('is_active', true)
            ->where('type', 'Producto')
            ->where('name', 'like', "%{$this->busqueda_producto}%")
            ->with('unit')
            ->limit(6)
            ->get()
            ->toArray();
    }

    public function seleccionarProducto(int $id): void
    {
        $p = Product::where('is_active', true)->with('unit')->findOrFail($id);
        $this->producto_id = $p->id;
        $this->busqueda_producto = $p->name;
        $this->codigo_producto = (string) $p->id;
        $this->unit_name = $p->unit->name ?? '';
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
            'unit' => $this->unit_name,
            'cantidad' => (float)$this->cantidad,
            'precio_compra' => (float)$this->precio_compra,
            'precio_venta' => (float)$this->precio_venta,
            'subtotal' => (float)$this->cantidad * (float)$this->precio_compra
        ];

        $this->guardarSesion();
        $this->resetProducto();
    }

    public function resetProducto(): void
    {
        $this->reset(['busqueda_producto', 'producto_id', 'codigo_producto', 'unit_name', 'cantidad', 'precio_compra', 'precio_venta', 'mostrar_productos']);
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
        $this->editItemIndex = $index;
        $this->editCantidad = (string) $this->items[$index]['cantidad'];
        $this->editPrecioCompra = (string) $this->items[$index]['precio_compra'];
        $this->modalEditarItem = true;
    }

    public function guardarEditarItem(): void
    {
        if ((float)$this->editCantidad <= 0) { $this->addError('editCantidad', 'Mayor a 0.'); return; }
        if ((float)$this->editPrecioCompra <= 0) { $this->addError('editPrecioCompra', 'Mayor a 0.'); return; }
        if ((float)$this->editPrecioCompra > $this->items[$this->editItemIndex]['precio_venta']) { $this->addError('editPrecioCompra', 'No mayor a P. Venta.'); return; }

        $this->items[$this->editItemIndex]['cantidad'] = (float)$this->editCantidad;
        $this->items[$this->editItemIndex]['precio_compra'] = (float)$this->editPrecioCompra;
        $this->items[$this->editItemIndex]['subtotal'] = (float)$this->editCantidad * (float)$this->editPrecioCompra;

        $this->reset(['modalEditarItem', 'editItemIndex', 'editCantidad', 'editPrecioCompra']);
        $this->guardarSesion();
    }

    public function total(): float
    {
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

    #[Computed]
    public function headers(): array
    {
        return [
            ['key'=>'producto','label'=>'Producto'],
            ['key'=>'codigo','label'=>'Código'],
            ['key'=>'unit','label'=>'Unidad'],
            ['key'=>'cantidad','label'=>'Cant.'],
            ['key'=>'precio_compra','label'=>'P. Compra'],
            ['key'=>'subtotal','label'=>'Subtotal'],
            ['key'=>'acciones','label'=>'Acciones','sortable'=>false]
        ];
    }

    public function confirmarCancelar(): void
    {
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
        $this->validate([
            'numero_factura' => 'required|string|max:255',
            'fecha' => 'required|date',
            'forma_pago' => 'required|in:contado,transferencia',
            'metodo_pago' => 'required|in:cordobas,dolares,mixto',
            'monto_cordobas' => 'nullable|numeric|min:0',
            'monto_dolares' => 'nullable|numeric|min:0',
            'tasa_cambio' => 'required|numeric|min:0.01',
            'ref_transferencia' => $this->forma_pago === 'transferencia' ? 'required|string|max:255' : 'nullable|string|max:255',
        ]);

        if (!$this->proveedor_id) { $this->addError('busqueda_proveedor', 'Selecciona un proveedor.'); return; }
        if (empty($this->items)) { $this->addError('busqueda_producto', 'Agrega un producto.'); return; }
        if ($this->metodo_pago === 'mixto' && $this->monto_cordobas === '' && $this->monto_dolares === '') { $this->addError('monto_cordobas', 'Ingresa un monto.'); return; }

        $tasa = (float)($this->tasa_cambio ?: 36.50);
        $t = $this->total();
        if ($t <= 0) { $this->addError('busqueda_producto', 'El total de la compra debe ser mayor a cero.'); return; }

        foreach ($this->items as $index => $item) {
            if (empty($item['producto_id']) || (float)$item['cantidad'] <= 0 || (float)$item['precio_compra'] <= 0 || (float)$item['subtotal'] <= 0) {
                $this->addError('busqueda_producto', 'Revisa el producto #' . ($index + 1) . ': cantidad, precio y subtotal deben ser mayores a cero.');
                return;
            }
        }

        $aC = $this->metodo_pago === 'cordobas' ? ($this->monto_cordobas !== '' ? (float)$this->monto_cordobas : $t) : ($this->metodo_pago === 'mixto' ? (float)$this->monto_cordobas : 0.0);
        $aD = $this->metodo_pago === 'dolares' ? ($this->monto_dolares !== '' ? (float)$this->monto_dolares : ($t / $tasa)) : ($this->metodo_pago === 'mixto' ? (float)$this->monto_dolares : 0.0);
        $paid = $aC + ($aD * $tasa);

        if ($paid < $t) {
            $this->addError('monto_cordobas', 'El pago no cubre el total de la compra.');
            return;
        }

        DB::transaction(function () use ($tasa, $t, $aC, $aD) {
            $purchase = Purchase::create([
                'provider_id' => $this->proveedor_id,
                'user_id' => auth()->id() ?? 1,
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

                $prod = Product::where('id', $item['producto_id'])->lockForUpdate()->firstOrFail();
                $prod->update([
                    'stock' => $prod->stock + $item['cantidad'],
                    'cost_price' => $item['precio_compra'],
                    'sale_price' => $item['precio_venta']
                ]);
            }
        });

        $this->cancelarCompra();
        $this->success('Compra registrada correctamente e inventario actualizado.', position: 'toast-top toast-center');
    }

    #[Computed]
    public function categoryOptions(): array
    {
        return Cache::remember('categorias_activas', 86400, fn() => Category::where('is_active', true)->get()->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->toArray());
    }

    #[Computed]
    public function unitOptions(): array
    {
        return Cache::remember('unidades_activas', 86400, fn() => Unit::all()->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->toArray());
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

        $this->success('Proveedor creado.', position: 'toast-top toast-center');
    }

    public function guardarNuevoProducto(): void
    {
        $this->validate([
            'nprod_nombre' => 'required|string|max:255',
            'nprod_categoria_id' => 'required|exists:categories,id',
            'nprod_unit_id' => 'required|exists:units,id',
            'nprod_precio_venta' => 'required|numeric|min:0',
            'nprod_precio_compra' => 'nullable|numeric|min:0',
            'nprod_stock' => 'nullable|numeric|min:0',
            'nprod_min_stock' => 'nullable|numeric|min:0'
        ]);

        $prod = Product::create([
            'name' => $this->nprod_nombre,
            'category_id' => $this->nprod_categoria_id,
            'unit_id' => $this->nprod_unit_id,
            'type' => 'Producto',
            'sale_price' => (float)$this->nprod_precio_venta,
            'cost_price' => (float)($this->nprod_precio_compra ?: 0),
            'stock' => (float)($this->nprod_stock ?: 0),
            'min_stock' => (float)($this->nprod_min_stock ?: 0),
            'is_active' => true
        ]);

        $this->seleccionarProducto($prod->id);
        $this->reset(['nprod_nombre', 'nprod_categoria_id', 'nprod_unit_id', 'nprod_precio_venta', 'nprod_precio_compra', 'modalNuevoProducto']);
        $this->nprod_stock = '0';
        $this->nprod_min_stock = '5';

        $this->success('Producto creado.', position: 'toast-top toast-center');
    }
};
?>

<div class="p-4 bg-gray-50/50 min-h-screen">
    <div class="max-w-[1400px] mx-auto">
        <x-header title="Registro de Compras" subtitle="Ingreso de insumos y materiales al inventario" separator class="mb-6" />

        <x-form wire:submit.prevent="registrarCompra">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                {{-- LADO IZQUIERDO: DETALLES DE COMPRA --}}
                <div class="lg:col-span-8 flex flex-col gap-6">

                    {{-- SECCIÓN: DATOS DE FACTURA Y PROVEEDOR --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Factura --}}
                        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 space-y-4">
                            <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2 mb-2">
                                <x-icon name="o-document-text" class="w-4 h-4" /> Datos del Documento
                            </h4>
                            <x-input label="N° Compra / Factura *" wire:model="numero_factura" icon="o-hashtag" class="bg-gray-50 focus:bg-white transition-colors" />
                            @error('numero_factura') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror

                            <x-input label="Fecha de Compra *" wire:model="fecha" type="date" icon="o-calendar" class="bg-gray-50 focus:bg-white transition-colors" />
                            @error('fecha') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Proveedor --}}
                        <div class="bg-indigo-50/50 p-5 rounded-2xl border border-indigo-50 space-y-4">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-[10px] font-black text-indigo-400 uppercase tracking-widest flex items-center gap-2">
                                    <x-icon name="o-building-storefront" class="w-4 h-4" /> Proveedor
                                </h4>
                                <x-button label="Nuevo Proveedor" class="btn-xs btn-ghost text-indigo-600 font-bold" wire:click.prevent="$set('modalNuevoProveedor', true)" />
                            </div>

                            <div class="space-y-3 relative z-40">
                                <div class="relative">
                                    <x-input label="Nombre Comercial" wire:model.live="busqueda_proveedor" icon="o-magnifying-glass" class="bg-white shadow-sm" autocomplete="off" placeholder="Buscar..." />
                                    @if($mostrar_proveedores && count($this->sugerenciasProveedor) > 0)
                                        <div class="absolute z-50 w-full bg-white border border-gray-100 rounded-xl shadow-xl mt-1 max-h-40 overflow-y-auto">
                                            @foreach($this->sugerenciasProveedor as $prov)
                                                <div wire:click="seleccionarProveedor({{ $prov['id'] }})" class="px-4 py-2 hover:bg-indigo-50 cursor-pointer border-b border-gray-50 last:border-0 transition-colors">
                                                    <span class="font-bold text-gray-800 text-sm block">{{ $prov['company_name'] }}</span>
                                                    <span class="text-[10px] text-gray-400 font-mono">{{ $prov['ruc'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                    @error('busqueda_proveedor') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div class="relative z-30">
                                        <x-input label="RUC" wire:model.live="busqueda_ruc" class="bg-white font-mono text-sm shadow-sm" autocomplete="off" />
                                        @if($mostrar_proveedores_ruc && count($this->sugerenciasRuc) > 0)
                                            <div class="absolute z-50 w-full bg-white border border-gray-100 rounded-xl shadow-xl mt-1 max-h-40 overflow-y-auto">
                                                @foreach($this->sugerenciasRuc as $prov)
                                                    <div wire:click="seleccionarProveedor({{ $prov['id'] }})" class="px-4 py-2 hover:bg-indigo-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                        <span class="font-bold text-gray-800 text-sm block font-mono">{{ $prov['ruc'] }}</span>
                                                        <span class="text-[10px] text-gray-400">{{ $prov['company_name'] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    <div class="relative z-20">
                                        <x-input label="Teléfono" wire:model.live="busqueda_tel" icon="o-phone" class="bg-white text-sm shadow-sm" autocomplete="off" />
                                        @if($mostrar_proveedores_tel && count($this->sugerenciasTel) > 0)
                                            <div class="absolute z-50 w-full bg-white border border-gray-100 rounded-xl shadow-xl mt-1 max-h-40 overflow-y-auto">
                                                @foreach($this->sugerenciasTel as $prov)
                                                    <div wire:click="seleccionarProveedor({{ $prov['id'] }})" class="px-4 py-2 hover:bg-indigo-50 cursor-pointer border-b border-gray-50 last:border-0">
                                                        <span class="font-bold text-gray-800 text-sm block">{{ $prov['phone'] }}</span>
                                                        <span class="text-[10px] text-gray-400">{{ $prov['company_name'] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SECCIÓN: AGREGAR PRODUCTOS --}}
                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between mb-4 border-b border-gray-100 pb-3">
                            <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                                <x-icon name="o-cube" class="w-4 h-4" /> Agregar Ítems a la Compra
                            </h4>
                            <x-button label="Nuevo Producto" class="btn-xs btn-outline btn-success" wire:click.prevent="$set('modalNuevoProducto', true)" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                            <div class="relative md:col-span-5">
                                <x-input label="Buscar Producto" wire:model.live="busqueda_producto" icon="o-magnifying-glass" class="bg-gray-50" autocomplete="off" placeholder="Nombre..." />
                                @if($mostrar_productos && count($this->sugerenciasProducto) > 0)
                                    <div class="absolute z-50 w-full bg-white border border-gray-100 rounded-xl shadow-xl mt-1 max-h-48 overflow-y-auto">
                                        @foreach($this->sugerenciasProducto as $prod)
                                            <div wire:click="seleccionarProducto({{ $prod['id'] }})" class="px-4 py-2 hover:bg-green-50 cursor-pointer border-b border-gray-50 last:border-0 flex justify-between items-center">
                                                <div>
                                                    <span class="font-bold text-gray-800 text-sm block">{{ $prod['name'] }}</span>
                                                    <span class="text-[10px] text-gray-400 uppercase font-bold">{{ $prod['unit']['name'] ?? 'Und' }}</span>
                                                </div>
                                                <span class="text-xs font-bold text-success">C$ {{ number_format($prod['sale_price'], 2) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                @error('busqueda_producto') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-2">
                                <x-input label="Cant." wire:model="cantidad" type="number" min="1" class="text-center font-bold text-lg bg-gray-50 text-indigo-600" placeholder="0" />
                                @error('cantidad') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-2">
                                <x-input label="Costo (C$)" wire:model="precio_compra" type="number" step="0.01" class="font-bold bg-gray-50" placeholder="0.00" />
                                @error('precio_compra') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-2">
                                <x-input label="P. Venta Ref." wire:model="precio_venta" type="number" step="0.01" class="bg-gray-50/50 text-gray-400" />
                            </div>

                            <div class="md:col-span-1 flex justify-end">
                                <button class="btn btn-primary btn-square btn-md text-white shadow-sm hover:scale-105 transition-transform w-full" wire:click.prevent="agregarProducto" title="Agregar a la lista">
                                    <x-icon name="o-plus" class="w-6 h-6" />
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- TABLA CARRITO DE COMPRAS --}}
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex-1">
                        <div class="bg-gray-50/50 px-5 py-3 border-b border-gray-100 flex justify-between items-center">
                            <h3 class="font-black text-gray-600 uppercase tracking-widest text-xs flex items-center gap-2">
                                <x-icon name="o-clipboard-document-list" class="w-4 h-4" /> Detalle de Insumos
                            </h3>
                            <span class="badge badge-primary badge-sm font-bold">{{ count($items) }} Ítems</span>
                        </div>

                        <div class="overflow-x-auto p-2">
                            <table class="w-full text-sm">
                                <thead class="text-[10px] font-black text-gray-400 uppercase tracking-wider border-b border-gray-100">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Producto</th>
                                        <th class="px-4 py-3 text-center">Unidad</th>
                                        <th class="px-4 py-3 text-center">Cant.</th>
                                        <th class="px-4 py-3 text-right">Costo Unit.</th>
                                        <th class="px-4 py-3 text-right">Subtotal</th>
                                        <th class="px-2 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @forelse($items as $index => $item)
                                        <tr class="hover:bg-gray-50 transition-colors group">
                                            <td class="px-4 py-3 font-bold text-gray-800">{{ $item['producto'] }}</td>
                                            <td class="px-4 py-3 text-center text-xs text-gray-500 uppercase">{{ $item['unit'] }}</td>
                                            <td class="px-4 py-3 text-center font-black text-indigo-600">{{ $item['cantidad'] }}</td>
                                            <td class="px-4 py-3 text-right text-gray-600 font-medium">C$ {{ number_format($item['precio_compra'], 2) }}</td>
                                            <td class="px-4 py-3 text-right font-black text-gray-800">C$ {{ number_format($item['subtotal'], 2) }}</td>
                                            <td class="px-2 py-3 text-center">
                                                <div class="flex items-center justify-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <button wire:click.prevent="abrirEditarItem({{ $index }})" class="p-1.5 text-gray-400 hover:text-warning hover:bg-warning/10 rounded-md transition-colors" title="Editar">
                                                        <x-icon name="o-pencil-square" class="w-4 h-4" />
                                                    </button>
                                                    <button wire:click.prevent="eliminarItem({{ $index }})" class="p-1.5 text-gray-400 hover:text-error hover:bg-error/10 rounded-md transition-colors" title="Eliminar">
                                                        <x-icon name="o-trash" class="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="py-12 text-center text-gray-400">
                                                <x-icon name="o-shopping-bag" class="w-12 h-12 mx-auto mb-3 text-gray-200" />
                                                <p class="font-bold">No has agregado productos a la compra</p>
                                                <p class="text-xs mt-1">Busca un producto arriba y haz clic en "+"</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- LADO DERECHO: PANEL DE PAGOS (TICKET) --}}
                <div class="lg:col-span-4 flex flex-col gap-6">

                    <div class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden sticky top-4 flex flex-col">

                        {{-- Resumen Total (Ticket Oscuro) --}}
                        <div class="bg-[#1e1b4b] p-6 text-white shadow-inner relative overflow-hidden">
                            <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-500 rounded-full blur-3xl opacity-20 -mr-10 -mt-10"></div>
                            <div class="relative z-10 flex flex-col items-center justify-center text-center">
                                <span class="text-[10px] font-black text-indigo-300 uppercase tracking-widest mb-1">Total Compra</span>
                                <span class="text-5xl font-black tracking-tight text-white mb-4">C$ {{ number_format($this->total(), 2) }}</span>

                                @if($this->vuelto() > 0)
                                    <div class="w-full bg-emerald-500/20 border border-emerald-500/30 rounded-xl p-3 flex justify-between items-center mt-2 animate-fade-in">
                                        <span class="text-[10px] font-black text-emerald-300 uppercase tracking-widest">Vuelto / A Favor</span>
                                        <span class="text-xl font-black text-emerald-400">C$ {{ number_format($this->vuelto(), 2) }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="p-6 space-y-6">
                            {{-- Forma de Pago --}}
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-3">Forma de Pago</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="flex items-center justify-center gap-2 border-2 rounded-xl py-3 cursor-pointer transition-all {{ $forma_pago === 'contado' ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-bold' : 'border-gray-100 text-gray-500 hover:bg-gray-50' }}">
                                        <input type="radio" wire:model.live="forma_pago" value="contado" class="hidden" />
                                        <x-icon name="o-banknotes" class="w-5 h-5" /> Contado
                                    </label>
                                    <label class="flex items-center justify-center gap-2 border-2 rounded-xl py-3 cursor-pointer transition-all {{ $forma_pago === 'transferencia' ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-bold' : 'border-gray-100 text-gray-500 hover:bg-gray-50' }}">
                                        <input type="radio" wire:model.live="forma_pago" value="transferencia" class="hidden" />
                                        <x-icon name="o-device-phone-mobile" class="w-5 h-5" /> Transf.
                                    </label>
                                </div>
                                @if($forma_pago === 'transferencia')
                                    <div class="mt-3 animate-fade-in">
                                        <x-input label="N° de Referencia *" wire:model="ref_transferencia" icon="o-hashtag" class="bg-gray-50 font-mono text-sm" placeholder="Ref. bancaria..." />
                                        @error('ref_transferencia') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                                    </div>
                                @endif
                            </div>

                            {{-- Moneda --}}
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-3">Moneda del Pago</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <label class="flex flex-col items-center justify-center border-2 rounded-xl py-2 cursor-pointer transition-all {{ $metodo_pago === 'cordobas' ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-bold' : 'border-gray-100 text-gray-500 hover:bg-gray-50' }}">
                                        <input type="radio" wire:model.live="metodo_pago" value="cordobas" class="hidden" />
                                        <span class="text-lg">C$</span>
                                        <span class="text-[9px] uppercase">NIO</span>
                                    </label>
                                    <label class="flex flex-col items-center justify-center border-2 rounded-xl py-2 cursor-pointer transition-all {{ $metodo_pago === 'dolares' ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-bold' : 'border-gray-100 text-gray-500 hover:bg-gray-50' }}">
                                        <input type="radio" wire:model.live="metodo_pago" value="dolares" class="hidden" />
                                        <span class="text-lg">$</span>
                                        <span class="text-[9px] uppercase">USD</span>
                                    </label>
                                    <label class="flex flex-col items-center justify-center border-2 rounded-xl py-2 cursor-pointer transition-all {{ $metodo_pago === 'mixto' ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-bold' : 'border-gray-100 text-gray-500 hover:bg-gray-50' }}">
                                        <input type="radio" wire:model.live="metodo_pago" value="mixto" class="hidden" />
                                        <span class="text-lg text-center">C$ + $</span>
                                        <span class="text-[9px] uppercase">Mixto</span>
                                    </label>
                                </div>
                            </div>

                            {{-- Montos Entregados --}}
                            <div class="space-y-3 bg-gray-50/50 p-4 rounded-2xl border border-gray-100">
                                @if($metodo_pago === 'cordobas' || $metodo_pago === 'mixto')
                                    <x-input label="Efectivo C$" wire:model.live="monto_cordobas" type="number" step="0.01" class="font-black text-lg bg-white" placeholder="0.00" />
                                    @error('monto_cordobas') <p class="text-error text-xs -mt-2">{{ $message }}</p> @enderror
                                @endif

                                @if($metodo_pago === 'dolares' || $metodo_pago === 'mixto')
                                    <div class="grid grid-cols-3 gap-2">
                                        <div class="col-span-2">
                                            <x-input label="Efectivo $" wire:model.live="monto_dolares" type="number" step="0.01" class="font-black text-lg bg-white" placeholder="0.00" />
                                        </div>
                                        <div class="col-span-1">
                                            <x-input label="Tasa" wire:model.live="tasa_cambio" type="number" step="0.01" class="font-bold text-center bg-white text-gray-500" />
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Botones de Acción --}}
                        <div class="p-6 pt-0 space-y-3">
                            <button wire:click="registrarCompra" class="w-full py-4 font-black text-white text-sm tracking-widest transition-all bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 rounded-xl shadow-lg hover:shadow-xl flex items-center justify-center gap-2">
                                <x-icon name="o-check-circle" class="w-5 h-5" /> REGISTRAR COMPRA
                            </button>
                            <button wire:click.prevent="confirmarCancelar" class="w-full py-3 font-bold text-gray-500 hover:text-error transition-colors rounded-xl flex items-center justify-center gap-2">
                                <x-icon name="o-trash" class="w-4 h-4" /> Cancelar / Limpiar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </x-form>

        {{-- MODALES SECUNDARIOS (Sin cambios funcionales, solo estilizados) --}}
        <x-modal wire:model="modalEditarItem" title="Modificar Cantidad / Costo" separator class="backdrop-blur-sm">
            <div class="grid gap-4 mt-2">
                <x-input label="Cantidad" wire:model="editCantidad" type="number" min="1" icon="o-hashtag" class="font-bold text-indigo-600 text-center" />
                @error('editCantidad') <p class="text-error text-xs -mt-3">{{ $message }}</p> @enderror

                <x-input label="Precio Compra (C$)" wire:model="editPrecioCompra" type="number" step="0.01" prefix="C$" icon="o-currency-dollar" class="font-bold" />
                @error('editPrecioCompra') <p class="text-error text-xs -mt-3">{{ $message }}</p> @enderror
            </div>
            <x-slot:actions>
                <x-button label="Cancelar" wire:click="$set('modalEditarItem', false)" class="btn-ghost" />
                <x-button label="Actualizar Ítem" class="btn-primary" wire:click="guardarEditarItem" />
            </x-slot:actions>
        </x-modal>

        <x-modal wire:model="modalCancelar" title="¿Cancelar registro de compra?" separator class="backdrop-blur-sm">
            <div class="flex flex-col items-center justify-center py-4 space-y-3 text-center">
                <x-icon name="o-exclamation-triangle" class="w-16 h-16 text-error opacity-80" />
                <p class="text-gray-600 font-medium">Se perderán todos los datos y productos agregados al carrito actual.</p>
            </div>
            <x-slot:actions>
                <x-button label="No, seguir editando" wire:click="$set('modalCancelar', false)" class="btn-ghost" />
                <x-button label="Sí, descartar todo" class="btn-error text-white" wire:click="cancelarCompra" />
            </x-slot:actions>
        </x-modal>

        <x-modal wire:model="modalNuevoProveedor" title="Registro Rápido de Proveedor" separator class="backdrop-blur-sm">
            <div class="grid grid-cols-2 gap-4 mt-2">
                <x-input label="Nombre / Razón Social" wire:model="np_nombre" icon="o-building-office" class="col-span-2" required />
                <x-input label="RUC" wire:model="np_ruc" icon="o-identification" required />
                <x-input label="Teléfono" wire:model="np_telefono" icon="o-phone" required />
                <x-input label="Dirección" wire:model="np_direccion" icon="o-map-pin" class="col-span-2" />

                <div class="col-span-2 bg-gray-50 p-3 rounded-xl mt-2 border border-gray-100">
                    <x-toggle label="Estado del Proveedor (Activo)" wire:model="np_activo" class="toggle-success" right />
                </div>
            </div>
            <x-slot:actions>
                <x-button label="Cancelar" wire:click="$set('modalNuevoProveedor', false)" class="btn-ghost" />
                <x-button label="Guardar Proveedor" class="btn-primary" wire:click="guardarNuevoProveedor" />
            </x-slot:actions>
        </x-modal>

        <x-modal wire:model="modalNuevoProducto" title="Registro Rápido de Insumo" separator class="backdrop-blur-sm">
            <div class="grid grid-cols-2 gap-4 mt-2">
                <x-input label="Nombre del Insumo" wire:model="nprod_nombre" icon="o-cube" class="col-span-2" required />
                <x-select label="Categoría" wire:model="nprod_categoria_id" :options="$this->categoryOptions" icon="o-tag" required />
                <x-select label="Unidad Principal" wire:model="nprod_unit_id" :options="$this->unitOptions" icon="o-scale" required />
                <x-input label="P. Compra Ref." wire:model="nprod_precio_compra" type="number" step="0.01" prefix="C$" />
                <x-input label="P. Venta Ref." wire:model="nprod_precio_venta" type="number" step="0.01" prefix="C$" required />

                <div class="col-span-2 grid grid-cols-2 gap-4 bg-orange-50/50 p-3 rounded-xl border border-orange-100">
                    <x-input label="Stock Inicial" wire:model="nprod_stock" type="number" class="bg-white" />
                    <x-input label="Alerta Mínima" wire:model="nprod_min_stock" type="number" class="bg-white" />
                </div>
            </div>
            <x-slot:actions>
                <x-button label="Cancelar" wire:click="$set('modalNuevoProducto', false)" class="btn-ghost" />
                <x-button label="Guardar Insumo" class="btn-primary" wire:click="guardarNuevoProducto" />
            </x-slot:actions>
        </x-modal>
    </div>
</div>
