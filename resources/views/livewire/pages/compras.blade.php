<?php

use Livewire\Volt\Component;
use App\Models\{Provider, Product, Purchase, PurchaseItem, Category, Unit};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Mary\Traits\Toast;
use Livewire\Attributes\Computed;

new class extends Component {
    use Toast;

    // Propiedades agrupadas
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
            foreach ($saved as $key => $value) if (property_exists($this, $key)) $this->$key = $value;
        } else {
            $this->numero_factura = 'C-' . str_pad((Purchase::max('id') ?? 0) + 1, 5, '0', STR_PAD_LEFT);
            $this->fecha = now()->format('Y-m-d');
        }
    }

    public function guardarSesion(): void
    {
        session(['compra_en_curso' => collect(['numero_factura','fecha','proveedor_id','busqueda_proveedor','busqueda_ruc','busqueda_tel','items','forma_pago','metodo_pago','monto_cordobas','monto_dolares','tasa_cambio','ref_transferencia'])->mapWithKeys(fn($k) => [$k => $this->$k])->toArray()]);
    }

    public function updated($property): void
    {
        $sessionProps = ['numero_factura','fecha','forma_pago','metodo_pago','monto_cordobas','monto_dolares','tasa_cambio','ref_transferencia'];
        if (in_array($property, $sessionProps)) $this->guardarSesion();

        if ($property === 'busqueda_proveedor') { $this->mostrar_proveedores = true; $this->proveedor_id = null; }
        if ($property === 'busqueda_ruc') { $this->mostrar_proveedores_ruc = true; $this->proveedor_id = null; }
        if ($property === 'busqueda_tel') { $this->mostrar_proveedores_tel = true; $this->proveedor_id = null; }
        if ($property === 'busqueda_producto') { $this->mostrar_productos = true; $this->producto_id = null; }
    }

    #[Computed]
    public function sugerenciasProveedor(): array { return strlen($this->busqueda_proveedor) < 1 ? [] : Provider::where('is_active', true)->where('company_name', 'like', "%{$this->busqueda_proveedor}%")->limit(6)->get()->toArray(); }

    #[Computed]
    public function sugerenciasRuc(): array { return strlen($this->busqueda_ruc) < 1 ? [] : Provider::where('is_active', true)->where('ruc', 'like', "%{$this->busqueda_ruc}%")->limit(6)->get()->toArray(); }

    #[Computed]
    public function sugerenciasTel(): array { return strlen($this->busqueda_tel) < 1 ? [] : Provider::where('is_active', true)->where('phone', 'like', "%{$this->busqueda_tel}%")->limit(6)->get()->toArray(); }

    public function seleccionarProveedor(int $id): void
    {
        $p = Provider::where('is_active', true)->findOrFail($id);
        $this->proveedor_id = $p->id; $this->busqueda_proveedor = $p->company_name; $this->busqueda_ruc = $p->ruc ?? ''; $this->busqueda_tel = $p->phone ?? '';
        $this->mostrar_proveedores = $this->mostrar_proveedores_ruc = $this->mostrar_proveedores_tel = false;
        $this->guardarSesion();
    }

    #[Computed]
    public function sugerenciasProducto(): array
    {
        return strlen($this->busqueda_producto) < 1 ? [] : Product::where('is_active', true)
            ->where('type', 'Producto')
            ->where('name', 'like', "%{$this->busqueda_producto}%")->with('unit')->limit(6)->get()->toArray();
    }

    public function seleccionarProducto(int $id): void
    {
        $p = Product::where('is_active', true)->with('unit')->findOrFail($id);
        $this->producto_id = $p->id; $this->busqueda_producto = $p->name; $this->codigo_producto = (string) $p->id;
        $this->unit_name = $p->unit->name ?? ''; $this->precio_compra = ''; $this->precio_venta = (string) $p->sale_price;
        $this->mostrar_productos = false;
    }

    public function agregarProducto(): void
    {
        if (!$this->producto_id) { $this->addError('busqueda_producto', 'Selecciona un producto.'); return; }
        if ((float)$this->cantidad <= 0) { $this->addError('cantidad', 'Mayor a 0.'); return; }
        if ((float)$this->precio_compra <= 0) { $this->addError('precio_compra', 'Obligatorio.'); return; }
        if ((float)$this->precio_compra > (float)$this->precio_venta) { $this->addError('precio_compra', 'No mayor a P. Venta.'); return; }

        $this->items[] = ['producto_id' => $this->producto_id, 'producto' => $this->busqueda_producto, 'codigo' => $this->codigo_producto, 'unit' => $this->unit_name, 'cantidad' => (float)$this->cantidad, 'precio_compra' => (float)$this->precio_compra, 'precio_venta' => (float)$this->precio_venta, 'subtotal' => (float)$this->cantidad * (float)$this->precio_compra];
        $this->guardarSesion(); $this->resetProducto();
    }

    public function resetProducto(): void { $this->reset(['busqueda_producto', 'producto_id', 'codigo_producto', 'unit_name', 'cantidad', 'precio_compra', 'precio_venta', 'mostrar_productos']); $this->resetErrorBag(); }
    public function eliminarItem(int $index): void { array_splice($this->items, $index, 1); $this->items = array_values($this->items); $this->guardarSesion(); }

    public function abrirEditarItem(int $index): void { $this->editItemIndex = $index; $this->editCantidad = (string) $this->items[$index]['cantidad']; $this->editPrecioCompra = (string) $this->items[$index]['precio_compra']; $this->modalEditarItem = true; }

    public function guardarEditarItem(): void
    {
        if ((float)$this->editCantidad <= 0) { $this->addError('editCantidad', 'Mayor a 0.'); return; }
        if ((float)$this->editPrecioCompra <= 0) { $this->addError('editPrecioCompra', 'Mayor a 0.'); return; }
        if ((float)$this->editPrecioCompra > $this->items[$this->editItemIndex]['precio_venta']) { $this->addError('editPrecioCompra', 'No mayor a P. Venta.'); return; }
        $this->items[$this->editItemIndex]['cantidad'] = (float)$this->editCantidad; $this->items[$this->editItemIndex]['precio_compra'] = (float)$this->editPrecioCompra; $this->items[$this->editItemIndex]['subtotal'] = (float)$this->editCantidad * (float)$this->editPrecioCompra;
        $this->reset(['modalEditarItem', 'editItemIndex', 'editCantidad', 'editPrecioCompra']); $this->guardarSesion();
    }

    public function total(): float { return array_sum(array_column($this->items, 'subtotal')); }

    public function vuelto(): float
    {
        $t = $this->total(); $tasa = (float)($this->tasa_cambio ?: 36.50);
        $pC = $this->monto_cordobas !== '' ? (float)$this->monto_cordobas : 0; $pD = $this->monto_dolares !== '' ? (float)$this->monto_dolares : 0;
        if ($this->metodo_pago === 'cordobas') return $this->monto_cordobas === '' ? 0.0 : max(0.0, $pC - $t);
        if ($this->metodo_pago === 'dolares') return $this->monto_dolares === '' ? 0.0 : max(0.0, ($pD * $tasa) - $t);
        return max(0.0, ($pC + ($pD * $tasa)) - $t);
    }

    #[Computed]
    public function headers(): array { return [['key'=>'producto','label'=>'Producto'], ['key'=>'codigo','label'=>'Código'], ['key'=>'unit','label'=>'Unidad'], ['key'=>'cantidad','label'=>'Cant.'], ['key'=>'precio_compra','label'=>'P. Compra'], ['key'=>'subtotal','label'=>'Subtotal'], ['key'=>'acciones','label'=>'Acciones','sortable'=>false]]; }

    public function confirmarCancelar(): void { $this->modalCancelar = true; }

    public function cancelarCompra(): void
    {
        session()->forget('compra_en_curso');
        $this->reset(['proveedor_id','busqueda_proveedor','busqueda_ruc','busqueda_tel','items','monto_cordobas','monto_dolares','ref_transferencia','modalCancelar']);
        $this->numero_factura = 'C-' . str_pad((Purchase::max('id') ?? 0) + 1, 5, '0', STR_PAD_LEFT);
        $this->fecha = now()->format('Y-m-d'); $this->forma_pago = 'contado'; $this->metodo_pago = 'cordobas'; $this->tasa_cambio = '36.50'; $this->resetProducto();
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

        $tasa = (float)($this->tasa_cambio ?: 36.50); $t = $this->total();
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
            $purchase = Purchase::create(['provider_id' => $this->proveedor_id, 'user_id' => auth()->id(), 'purchase_date' => $this->fecha, 'provider_invoice_number' => $this->numero_factura, 'total' => $t, 'payment_method' => $this->forma_pago === 'transferencia' ? 'transferencia' : $this->metodo_pago, 'amount_cordobas' => $aC, 'amount_dolares' => $aD, 'exchange_rate' => $tasa]);

            foreach ($this->items as $item) {
                PurchaseItem::create(['purchase_id' => $purchase->id, 'product_id' => $item['producto_id'], 'quantity' => $item['cantidad'], 'cost_price' => $item['precio_compra'], 'subtotal' => $item['subtotal']]);
                $prod = Product::where('id', $item['producto_id'])->lockForUpdate()->firstOrFail();
                $prod->update(['stock' => $prod->stock + $item['cantidad'], 'cost_price' => $item['precio_compra'], 'sale_price' => $item['precio_venta']]);
            }
        });
        $this->cancelarCompra(); $this->success('Compra registrada correctamente.', position: 'toast-bottom toast-end');
    }

    #[Computed]
    public function categoryOptions(): array {
        return Cache::remember('categorias_activas', 86400, fn() => Category::where('is_active', true)->get()->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->toArray());
    }

    #[Computed]
    public function unitOptions(): array {
        return Cache::remember('unidades_activas', 86400, fn() => Unit::all()->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->toArray());
    }

    public function guardarNuevoProveedor(): void
    {
        $this->validate(['np_nombre' => 'required|regex:/^[^\d]+$/', 'np_ruc' => 'required|alpha_num|unique:providers,ruc', 'np_direccion' => 'required|string', 'np_telefono' => 'required|regex:/^[0-9\s\+\-]+$/']);
        $p = Provider::create(['company_name' => $this->np_nombre, 'ruc' => $this->np_ruc, 'address' => $this->np_direccion, 'phone' => $this->np_telefono, 'is_active' => $this->np_activo]);
        $this->seleccionarProveedor($p->id); $this->reset(['np_nombre', 'np_ruc', 'np_direccion', 'np_telefono', 'modalNuevoProveedor']); $this->np_activo = true;
        $this->success('Proveedor creado.', position: 'toast-bottom toast-end');
    }

    public function guardarNuevoProducto(): void
    {
        $this->validate(['nprod_nombre' => 'required|string|max:255', 'nprod_categoria_id' => 'required|exists:categories,id', 'nprod_unit_id' => 'required|exists:units,id', 'nprod_precio_venta' => 'required|numeric|min:0', 'nprod_precio_compra' => 'nullable|numeric|min:0', 'nprod_stock' => 'nullable|numeric|min:0', 'nprod_min_stock' => 'nullable|numeric|min:0']);
        $prod = Product::create(['name' => $this->nprod_nombre, 'category_id' => $this->nprod_categoria_id, 'unit_id' => $this->nprod_unit_id, 'type' => 'Producto', 'sale_price' => (float)$this->nprod_precio_venta, 'cost_price' => (float)($this->nprod_precio_compra ?: 0), 'stock' => (float)($this->nprod_stock ?: 0), 'min_stock' => (float)($this->nprod_min_stock ?: 0), 'is_active' => true]);
        $this->seleccionarProducto($prod->id); $this->reset(['nprod_nombre', 'nprod_categoria_id', 'nprod_unit_id', 'nprod_precio_venta', 'nprod_precio_compra', 'modalNuevoProducto']); $this->nprod_stock = '0'; $this->nprod_min_stock = '5';
        $this->success('Producto creado.', position: 'toast-bottom toast-end');
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
                        <div><x-input label="N° Compra" wire:model="numero_factura" icon="o-document-text" class="input-xs" /> @error('numero_factura') <p class="text-error text-xs">{{ $message }}</p> @enderror</div>
                        <div><x-input label="Fecha" wire:model="fecha" type="date" icon="o-calendar" class="input-xs" /> @error('fecha') <p class="text-error text-xs">{{ $message }}</p> @enderror</div>
                    </div>
                </x-card>

                {{-- PROVEEDOR --}}
                <x-card shadow class="p-3">
                    <div class="flex items-center justify-between mb-2"><span class="font-semibold text-sm">Proveedor</span><x-button label="+ Nuevo" class="btn-xs btn-outline btn-primary" wire:click.prevent="$set('modalNuevoProveedor', true)" /></div>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="relative"><x-input label="Nombre" wire:model.live="busqueda_proveedor" icon="o-building-office-2" class="input-xs" autocomplete="off" /> @error('busqueda_proveedor') <p class="text-error text-xs">{{ $message }}</p> @enderror
                            @if($mostrar_proveedores && count($this->sugerenciasProveedor) > 0)
                                <div class="absolute z-50 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg mt-1 max-h-40 overflow-y-auto">
                                    @foreach($this->sugerenciasProveedor as $prov) <div wire:click="seleccionarProveedor({{ $prov['id'] }})" class="px-3 py-1.5 hover:bg-base-200 cursor-pointer text-xs"><span class="font-semibold">{{ $prov['company_name'] }}</span> <span class="text-gray-400">{{ $prov['ruc'] }}</span></div> @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="relative"><x-input label="RUC" wire:model.live="busqueda_ruc" icon="o-identification" class="input-xs" autocomplete="off" />
                            @if($mostrar_proveedores_ruc && count($this->sugerenciasRuc) > 0)
                                <div class="absolute z-50 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg mt-1 max-h-40 overflow-y-auto">
                                    @foreach($this->sugerenciasRuc as $prov) <div wire:click="seleccionarProveedor({{ $prov['id'] }})" class="px-3 py-1.5 hover:bg-base-200 cursor-pointer text-xs"><span class="font-semibold">{{ $prov['ruc'] }}</span> <span class="text-gray-400">{{ $prov['company_name'] }}</span></div> @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="relative"><x-input label="Teléfono" wire:model.live="busqueda_tel" icon="o-phone" class="input-xs" autocomplete="off" />
                            @if($mostrar_proveedores_tel && count($this->sugerenciasTel) > 0)
                                <div class="absolute z-50 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg mt-1 max-h-40 overflow-y-auto">
                                    @foreach($this->sugerenciasTel as $prov) <div wire:click="seleccionarProveedor({{ $prov['id'] }})" class="px-3 py-1.5 hover:bg-base-200 cursor-pointer text-xs"><span class="font-semibold">{{ $prov['phone'] }}</span> <span class="text-gray-400">{{ $prov['company_name'] }}</span></div> @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </x-card>

                {{-- PRODUCTO --}}
                <x-card shadow class="p-3">
                    <div class="flex items-center justify-between mb-2"><span class="font-semibold text-sm">Agregar Producto</span><x-button label="+ Nuevo" class="btn-xs btn-outline btn-secondary" wire:click.prevent="$set('modalNuevoProducto', true)" /></div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        <div class="relative col-span-2"><x-input label="Producto" wire:model.live="busqueda_producto" icon="o-cube" class="input-xs" autocomplete="off" /> @error('busqueda_producto') <p class="text-error text-xs">{{ $message }}</p> @enderror
                            @if($mostrar_productos && count($this->sugerenciasProducto) > 0)
                                <div class="absolute z-50 w-full bg-base-100 border border-base-300 rounded-lg shadow-lg mt-1 max-h-40 overflow-y-auto">
                                    @foreach($this->sugerenciasProducto as $prod) <div wire:click="seleccionarProducto({{ $prod['id'] }})" class="px-3 py-1.5 hover:bg-base-200 cursor-pointer text-xs"><span class="font-semibold">{{ $prod['name'] }}</span> <span class="text-gray-400">{{ $prod['unit']['name'] ?? '' }}</span></div> @endforeach
                                </div>
                            @endif
                        </div>
                        <div><x-input label="Código" wire:model="codigo_producto" icon="o-qr-code" class="input-xs" readonly /></div>
                        <div><x-input label="Unidad" wire:model="unit_name" icon="o-scale" class="input-xs" readonly /></div>
                        <div><x-input label="Cantidad" wire:model="cantidad" type="number" min="1" icon="o-hashtag" class="input-xs" /> @error('cantidad') <p class="text-error text-xs">{{ $message }}</p> @enderror</div>
                        <div><x-input label="P. Compra (C$)" wire:model="precio_compra" type="number" step="0.01" prefix="C$" icon="o-arrow-down-circle" class="input-xs" /> @error('precio_compra') <p class="text-error text-xs">{{ $message }}</p> @enderror</div>
                        <div><x-input label="P. Venta (C$)" wire:model="precio_venta" type="number" step="0.01" prefix="C$" icon="o-arrow-up-circle" class="input-xs" /></div>
                        <div class="flex items-end"><x-button label="Agregar" icon="o-plus-circle" class="btn-secondary btn-xs w-full" wire:click.prevent="agregarProducto" /></div>
                    </div>
                </x-card>

                {{-- TABLA --}}
                <x-card shadow class="p-3">
                    <x-table :headers="$this->headers" :rows="$this->items" striped>
                        @scope('cell_precio_compra', $row) C$ {{ number_format($row['precio_compra'], 2) }} @endscope
                        @scope('cell_subtotal', $row) C$ {{ number_format($row['subtotal'], 2) }} @endscope
                        @scope('cell_acciones', $row) <div class="flex gap-1"><x-button icon="o-pencil-square" class="btn-xs btn-warning btn-soft" tooltip="Editar" wire:click="abrirEditarItem({{ $loop->index }})" /><x-button icon="o-trash" class="btn-xs btn-error btn-soft" tooltip="Eliminar" wire:click="eliminarItem({{ $loop->index }})" /></div> @endscope
                    </x-table>
                </x-card>
            </div>

            <div class="flex flex-col gap-3">
                {{-- RESUMEN --}}
                <div class="bg-primary/10 border border-primary/30 rounded-xl px-4 py-3 text-right">
                    <p class="text-xs text-gray-500 uppercase font-bold tracking-widest">Total</p><p class="text-2xl font-black text-primary mb-1">C$ {{ number_format($this->total(), 2) }}</p>
                    @if($this->vuelto() > 0) <hr class="border-primary/20 my-1" /><p class="text-xs text-success uppercase font-bold tracking-widest">Vuelto</p><p class="text-lg font-bold text-success">C$ {{ number_format($this->vuelto(), 2) }}</p> @endif
                </div>

                {{-- PAGO --}}
                <x-card shadow class="p-3">
                    <p class="font-semibold text-sm mb-2">Forma</p>
                    <div class="flex gap-4 mb-3">
                        <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" wire:model.live="forma_pago" value="contado" class="radio radio-primary radio-xs" /><span class="text-xs font-medium">Contado</span></label>
                        <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" wire:model.live="forma_pago" value="transferencia" class="radio radio-primary radio-xs" /><span class="text-xs font-medium">Transf.</span></label>
                    </div>
                    @if($forma_pago === 'transferencia') <div class="mb-3"><x-input label="Referencia" wire:model="ref_transferencia" icon="o-hashtag" class="input-xs" /> @error('ref_transferencia') <p class="text-error text-xs">{{ $message }}</p> @enderror</div> @endif

                    <p class="font-semibold text-sm mb-2">Moneda</p>
                    <div class="flex flex-col gap-1.5 mb-3">
                        <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" wire:model.live="metodo_pago" value="cordobas" class="radio radio-primary radio-xs" /><span class="text-xs font-medium">C$</span></label>
                        <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" wire:model.live="metodo_pago" value="dolares" class="radio radio-primary radio-xs" /><span class="text-xs font-medium">$</span></label>
                        <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" wire:model.live="metodo_pago" value="mixto" class="radio radio-primary radio-xs" /><span class="text-xs font-medium">Mixto</span></label>
                    </div>

                    @if($metodo_pago === 'cordobas') <x-input label="Monto C$" wire:model.live="monto_cordobas" type="number" step="0.01" prefix="C$" icon="o-banknotes" class="input-xs" /> @endif
                    @if($metodo_pago === 'dolares') <div class="flex flex-col gap-2"><x-input label="Monto $" wire:model.live="monto_dolares" type="number" step="0.01" prefix="$" icon="o-currency-dollar" class="input-xs" /><x-input label="TC" wire:model.live="tasa_cambio" type="number" step="0.01" icon="o-arrows-right-left" class="input-xs" /></div> @endif
                    @if($metodo_pago === 'mixto') <div class="flex flex-col gap-2"><x-input label="Monto C$" wire:model.live="monto_cordobas" type="number" step="0.01" prefix="C$" icon="o-banknotes" class="input-xs" /><x-input label="Monto $" wire:model.live="monto_dolares" type="number" step="0.01" prefix="$" icon="o-currency-dollar" class="input-xs" /><x-input label="TC" wire:model.live="tasa_cambio" type="number" step="0.01" icon="o-arrows-right-left" class="input-xs" /></div> @error('monto_cordobas') <p class="text-error text-xs">{{ $message }}</p> @enderror @endif
                </x-card>

                <div class="flex flex-col gap-2"><x-button label="Registrar Compra" icon="o-check-circle" class="btn-primary btn-sm w-full" type="submit" /><x-button label="Cancelar" icon="o-x-circle" class="btn-ghost btn-sm w-full" wire:click.prevent="confirmarCancelar" /></div>
            </div>
        </div>
    </x-form>

    {{-- MODALES SECUNDARIOS --}}
    <x-modal wire:model="modalEditarItem" title="Editar Producto" separator>
        <div class="grid gap-4"><x-input label="Cantidad" wire:model="editCantidad" type="number" min="1" icon="o-hashtag" /> @error('editCantidad') <p class="text-error text-xs -mt-3">{{ $message }}</p> @enderror <x-input label="Precio Compra (C$)" wire:model="editPrecioCompra" type="number" step="0.01" prefix="C$" icon="o-arrow-down-circle" /> @error('editPrecioCompra') <p class="text-error text-xs -mt-3">{{ $message }}</p> @enderror</div>
        <x-slot:actions><x-button label="Cancelar" wire:click="$set('modalEditarItem', false)" /><x-button label="Guardar" class="btn-primary" wire:click="guardarEditarItem" /></x-slot:actions>
    </x-modal>

    <x-modal wire:model="modalCancelar" title="¿Cancelar compra?" separator>
        <p class="text-gray-600">Se perderán todos los datos.</p>
        <x-slot:actions><x-button label="No" wire:click="$set('modalCancelar', false)" /><x-button label="Sí, cancelar" class="btn-error" wire:click="cancelarCompra" /></x-slot:actions>
    </x-modal>

    <x-modal wire:model="modalNuevoProveedor" title="Nuevo Proveedor" separator>
        <div class="grid grid-cols-2 gap-4"><x-input label="Nombre" wire:model="np_nombre" icon="o-building-office" /><x-input label="RUC" wire:model="np_ruc" icon="o-identification" /><x-input label="Dirección" wire:model="np_direccion" icon="o-map-pin" class="col-span-2" /><x-input label="Teléfono" wire:model="np_telefono" icon="o-phone" /> <div class="mt-2"><x-toggle label="Activo" wire:model="np_activo" /></div></div>
        <x-slot:actions><x-button label="Cancelar" wire:click="$set('modalNuevoProveedor', false)" /><x-button label="Guardar" class="btn-primary" wire:click="guardarNuevoProveedor" /></x-slot:actions>
    </x-modal>

    <x-modal wire:model="modalNuevoProducto" title="Nuevo Producto" separator>
        <div class="grid grid-cols-2 gap-4"><x-input label="Nombre" wire:model="nprod_nombre" icon="o-cube" class="col-span-2" /><x-select label="Categoría" wire:model="nprod_categoria_id" :options="$this->categoryOptions" icon="o-tag" /><x-select label="Unidad" wire:model="nprod_unit_id" :options="$this->unitOptions" icon="o-scale" /><x-input label="P. Venta" wire:model="nprod_precio_venta" type="number" step="0.01" prefix="C$" /><x-input label="P. Compra" wire:model="nprod_precio_compra" type="number" step="0.01" prefix="C$" /><x-input label="Stock Inicial" wire:model="nprod_stock" type="number" /><x-input label="Stock Mínimo" wire:model="nprod_min_stock" type="number" /></div>
        <x-slot:actions><x-button label="Cancelar" wire:click="$set('modalNuevoProducto', false)" /><x-button label="Guardar" class="btn-primary" wire:click="guardarNuevoProducto" /></x-slot:actions>
    </x-modal>
</div>
