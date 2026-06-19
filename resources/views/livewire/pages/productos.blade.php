<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;
use Mary\Traits\Toast;

new class extends Component {
    use WithPagination;
    use Toast;

    public string $search = '';
    public string $filter = 'Todos';

    public bool $drawerModal = false;
    public bool $isEditMode = false;

    public bool $categoryModal = false;
    public bool $unitModal = false;
    public string $newCategoryName = '';
    public string $newUnitName = '';

    public $product_id, $name, $category_id, $unit_id, $type;
    public $sale_price, $cost_price, $stock, $min_stock;
    public $items_per_unit = 1;
    public bool $is_active = true;
    public bool $is_sellable = true;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function setFilter($filterName)
    {
        $this->filter = $filterName;
        $this->resetPage();
    }

    public function updatedCategoryId()
    {
        $this->unit_id = null;
    }

    public function saveCategory()
    {
        $this->validate([
            'newCategoryName' => 'required|string|max:255|unique:categories,name'
        ]);

        $category = Category::create(['name' => $this->newCategoryName]);

        $this->category_id = $category->id;
        $this->categoryModal = false;
        $this->newCategoryName = '';
        $this->success('Categoría creada exitosamente');
    }

    public function saveUnit()
    {
        $this->validate([
            'newUnitName' => 'required|string|max:255|unique:units,name'
        ]);

        $unit = Unit::create(['name' => $this->newUnitName]);

        $this->unit_id = $unit->id;
        $this->unitModal = false;
        $this->newUnitName = '';
        $this->success('Unidad de medida creada exitosamente');
    }
    // --------------------------------------------------------

    public function create()
    {
        $this->resetForm();
        $this->isEditMode = false;
        $this->drawerModal = true;
    }

    public function edit(Product $product)
    {
        $this->product_id = $product->id;
        $this->name = $product->name;
        $this->category_id = $product->category_id;
        $this->unit_id = $product->unit_id;
        $this->type = $product->type;
        $this->sale_price = $product->sale_price;
        $this->cost_price = $product->cost_price;
        $this->stock = $product->stock;
        $this->min_stock = $product->min_stock;
        $this->items_per_unit = $product->items_per_unit ?? 1;
        $this->is_active = $product->is_active;
        $this->is_sellable = $product->is_sellable ?? true;

        $this->isEditMode = true;
        $this->drawerModal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\sáéíóúÁÉÍÓÚñÑ\-\/]+$/'],
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'sale_price' => 'required|numeric|min:0',
            'type' => 'required|in:Producto,Servicio',
            'stock' => $this->type === 'Producto' ? 'required|numeric|min:0' : 'nullable',
            'min_stock' => $this->type === 'Producto' ? 'required|numeric|min:0' : 'nullable',
            'items_per_unit' => $this->type === 'Producto' ? 'required|numeric|min:0.01' : 'nullable',
            'is_sellable' => 'boolean',
        ]);

        $product = Product::updateOrCreate(
            ['id' => $this->product_id],
            [
                'name' => $this->name,
                'category_id' => $this->category_id,
                'unit_id' => $this->unit_id,
                'type' => $this->type,
                'sale_price' => $this->sale_price ?: 0,
                'cost_price' => $this->cost_price ?: 0,
                'stock' => $this->type === 'Producto' ? ($this->stock ?: 0) : 0,
                'min_stock' => $this->type === 'Producto' ? ($this->min_stock ?: 0) : 0,
                'items_per_unit' => $this->type === 'Producto' ? ($this->items_per_unit ?: 1) : 1,
                'is_active' => $this->is_active,
                'is_sellable' => $this->type === 'Producto' ? $this->is_sellable : true,
            ]
        );

        if (!$this->product_id && $this->type === 'Producto' && $this->items_per_unit > 1) {
            $looseUnit = Unit::firstOrCreate(['name' => 'Unidad']);

            Product::create([
                'name' => $this->name . ' (Suelto/Unidad)',
                'category_id' => $this->category_id,
                'unit_id' => $looseUnit->id,
                'type' => 'Producto',
                'sale_price' => $this->sale_price / $this->items_per_unit,
                'cost_price' => ($this->cost_price ?: 0) / $this->items_per_unit,
                'stock' => 0,
                'min_stock' => 0,
                'items_per_unit' => 1,
                'is_active' => true,
                'is_sellable' => false,
                'parent_id' => $product->id
            ]);
        }

        $this->drawerModal = false;
        $this->success($this->isEditMode ? 'Registro actualizado exitosamente.' : 'Registro creado exitosamente.');
    }

    public function openPackage(Product $parentProduct)
    {
        if ($parentProduct->stock < 1) {
            $this->error('No hay empaques cerrados en stock para abrir.');
            return;
        }

        $childProduct = Product::where('parent_id', $parentProduct->id)->first();

        if ($childProduct) {
            $parentProduct->decrement('stock', 1);
            $childProduct->increment('stock', $parentProduct->items_per_unit);

            $this->success("Empaque abierto. Se agregaron {$parentProduct->items_per_unit} unidades sueltas al inventario.");
        } else {
            $this->error('Este producto no tiene configuradas unidades sueltas automáticamente.');
        }
    }

    public function toggleActive(Product $product)
    {
        $product->update(['is_active' => !$product->is_active]);
        $this->success($product->is_active ? 'Item activado correctamente.' : 'Item desactivado correctamente.');
    }

    public function resetForm()
    {
        $this->reset(['product_id', 'name', 'category_id', 'unit_id', 'type', 'sale_price', 'cost_price', 'stock', 'min_stock']);
        $this->items_per_unit = 1;
        $this->is_active = true;
        $this->is_sellable = true;
    }

    public function with(): array
    {
        $unitsQuery = Unit::query()->select(['id', 'name']);

        if ($this->category_id) {
            $category = Category::find($this->category_id);
            if ($category) {
                $categoryName = mb_strtolower($category->name, 'UTF-8');

                if (str_contains($categoryName, 'papel') || str_contains($categoryName, 'cartulina')) {
                    $unitsQuery->whereIn('name', ['Resma', 'Pliego', 'Paquete', 'Unidad', 'Caja']);
                } elseif (str_contains($categoryName, 'tarjeta') || str_contains($categoryName, 'etiqueta') || str_contains($categoryName, 'publicidad')) {
                    $unitsQuery->whereIn('name', ['Millar', 'Paquete', 'Caja', 'Unidad', 'Docena']);
                } elseif (str_contains($categoryName, 'impresi') || str_contains($categoryName, 'diseño') || str_contains($categoryName, 'fotocopia') || str_contains($categoryName, 'encuadernado') || str_contains($categoryName, 'laminado') || str_contains($categoryName, 'material')) {
                    $unitsQuery->whereIn('name', ['Unidad', 'Docena']);
                } elseif (str_contains($categoryName, 'tinta')) {
                    $unitsQuery->whereIn('name', ['Litro', 'Unidad']);
                } else {
                    $unitsQuery->whereIn('name', ['Unidad', 'Paquete']);
                }
            }
        }

        $units = $unitsQuery->get();

        if ($this->unit_id && !$units->contains('id', $this->unit_id)) {
            $extraUnit = Unit::find($this->unit_id);
            if ($extraUnit) {
                $units->push($extraUnit);
            }
        }

        $stats = [
            'total' => Product::count(),
            'products' => Product::where('type', 'Producto')->count(),
            'services' => Product::where('type', 'Servicio')->count(),
            'lowStock' => Product::where('type', 'Producto')->whereColumn('stock', '<=', 'min_stock')->count(),
        ];

        $products = Product::query()
            ->select(['id', 'name', 'category_id', 'unit_id', 'type', 'sale_price', 'cost_price', 'stock', 'min_stock', 'items_per_unit', 'is_active', 'is_sellable'])
            ->with(['category:id,name', 'unit:id,name'])
            ->when($this->filter === 'Productos', fn($q) => $q->where('type', 'Producto'))
            ->when($this->filter === 'Servicios', fn($q) => $q->where('type', 'Servicio'))
            ->when($this->filter === 'Bajo Stock', fn($q) => $q->where('type', 'Producto')->whereColumn('stock', '<=', 'min_stock'))
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('id', 'desc')
            ->paginate(10);

        return [
            'categories' => Category::query()->select(['id', 'name'])->orderBy('name')->get(),
            'units' => $units,
            'products' => $products,
            'stats' => $stats,
            'headers' => [
                ['key' => 'id', 'label' => 'CÓDIGO'],
                ['key' => 'name', 'label' => 'ÍTEM'],
                ['key' => 'category.name', 'label' => 'CATEGORÍA'],
                ['key' => 'prices', 'label' => 'PRECIOS', 'sortable' => false],
                ['key' => 'stock', 'label' => 'INVENTARIO'],
                ['key' => 'status', 'label' => 'ESTADO'],
                ['key' => 'actions', 'label' => '', 'sortable' => false]
            ]
        ];
    }
}; ?>

<div class="p-6">
    <x-header title="Inventario y Servicios" subtitle="Catálogo de Imprenta Minerva">
        <x-slot:actions>
            <x-button icon="o-plus" label="Nuevo Registro" class="btn-primary shadow-sm hover:scale-105 transition-transform" wire:click="create" />
        </x-slot:actions>
    </x-header>

    {{-- Mini-Dashboard Estadístico --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <x-stat title="Total Registros"
                value="{{ $stats['total'] }}"
                icon="o-archive-box"
                class="bg-white border-l-4 border-gray-400 shadow-sm hover:shadow-md transition-shadow" />

        <x-stat title="Productos Físicos"
                value="{{ $stats['products'] }}"
                icon="o-cube"
                class="bg-white border-l-4 border-indigo-500 shadow-sm hover:shadow-md transition-shadow" />

        <x-stat title="Servicios de Taller"
                value="{{ $stats['services'] }}"
                icon="o-briefcase"
                class="bg-white border-l-4 border-info shadow-sm hover:shadow-md transition-shadow" />

        <x-stat title="Alerta de Stock"
                value="{{ $stats['lowStock'] }}"
                icon="o-exclamation-triangle"
                class="bg-error/10 border-l-4 border-error text-error shadow-sm hover:shadow-md transition-shadow" />
    </div>

    {{-- Barra de Búsqueda y Filtros Rápidos --}}
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-white p-4 rounded-t-2xl shadow-sm border-b border-gray-100">
        <div class="flex gap-2 w-full md:w-auto overflow-x-auto pb-2 md:pb-0">
            <x-button label="Todos" wire:click="setFilter('Todos')" class="btn-sm {{ $filter === 'Todos' ? 'btn-neutral' : 'btn-ghost border border-gray-200' }}" />
            <x-button label="Solo Productos" wire:click="setFilter('Productos')" class="btn-sm {{ $filter === 'Productos' ? 'bg-indigo-500 text-white hover:bg-indigo-600' : 'btn-ghost border border-gray-200' }}" />
            <x-button label="Solo Servicios" wire:click="setFilter('Servicios')" class="btn-sm {{ $filter === 'Servicios' ? 'btn-info text-white' : 'btn-ghost border border-gray-200' }}" />
            <x-button label="Bajo Stock" wire:click="setFilter('Bajo Stock')" icon="o-fire" class="btn-sm {{ $filter === 'Bajo Stock' ? 'btn-error text-white' : 'btn-ghost border border-gray-200 text-error' }}" />
        </div>

        <div class="w-full md:w-96 relative">
            <x-input icon="o-magnifying-glass"
                     placeholder="Buscar por nombre o código..."
                     wire:model.live.debounce.500ms="search"
                     clearable
                     class="input-sm w-full bg-gray-50" />
        </div>
    </div>

    <x-card class="rounded-t-none shadow-sm border-t-0 bg-white">
        <x-table :headers="$headers" :rows="$products" with-pagination class="table-sm">

            @scope('cell_id', $product)
                <span class="font-black text-gray-400">{{ $product->type === 'Servicio' ? 'SRV' : 'INS' }}-{{ str_pad($product->id, 4, '0', STR_PAD_LEFT) }}</span>
            @endscope

            @scope('cell_name', $product)
                <div class="flex items-center gap-3">
                    <x-avatar placeholder="{{ $product->type === 'Servicio' ? 'S' : 'P' }}" class="!w-10 !h-10 {{ $product->type === 'Servicio' ? 'bg-info/10 text-info' : 'bg-indigo-100 text-indigo-700' }} font-black shadow-sm" />
                    <div>
                        <div class="font-bold text-gray-800 {{ !$product->is_active ? 'opacity-50' : '' }}">{{ $product->name }}</div>
                        <div class="text-[11px] text-gray-400 flex items-center gap-2 mt-0.5">
                            <span class="uppercase tracking-wider font-bold">{{ $product->type }}</span>
                            @if(!$product->is_sellable && $product->type === 'Producto')
                                <span class="text-warning font-bold bg-warning/10 px-1.5 rounded-sm">• Solo Uso Interno</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endscope

            @scope('cell_category.name', $product)
                <span class="text-xs font-medium text-gray-600 bg-gray-50 px-2 py-1 rounded-md border border-gray-100">
                    {{ $product->category->name ?? 'N/A' }}
                </span>
            @endscope

            {{-- NUEVA COLUMNA: PRECIOS Y COSTOS --}}
            @scope('cell_prices', $product)
                <div class="flex flex-col {{ !$product->is_active ? 'opacity-50' : '' }}">
                    <span class="font-black text-primary text-sm">C$ {{ number_format($product->sale_price, 2) }}</span>
                    @if($product->cost_price > 0)
                        <span class="text-[10px] text-gray-400 font-medium">Costo: C$ {{ number_format($product->cost_price, 2) }}</span>
                    @endif
                </div>
            @endscope

            @scope('cell_stock', $product)
                @if($product->type === 'Producto')
                    <div class="{{ !$product->is_active ? 'opacity-50' : '' }}">
                        <span class="font-black text-sm {{ $product->stock <= $product->min_stock ? 'text-error' : 'text-gray-700' }}">
                            {{ number_format($product->stock, 2) }}
                        </span>
                        <span class="text-[11px] text-gray-500 uppercase tracking-wider font-bold">{{ $product->unit->name ?? 'Und' }}</span>
                    </div>

                    @if(($product->items_per_unit ?? 1) > 1)
                        <div class="text-[10px] text-indigo-500 font-bold mt-0.5 bg-indigo-50 inline-block px-1.5 rounded-sm">
                            = {{ number_format($product->stock * $product->items_per_unit, 2) }} unid. base
                        </div>
                    @endif
                @else
                    <span class="text-gray-300 text-xs italic">No aplica</span>
                @endif
            @endscope

            @scope('cell_status', $product)
                @if(!$product->is_active)
                    <x-badge value="Inactivo" class="badge-neutral badge-sm font-bold shadow-sm" icon="o-x-circle" />
                @elseif($product->type === 'Servicio')
                    <x-badge value="Activo" class="badge-info text-white badge-sm font-bold shadow-sm" icon="o-check-circle" />
                @elseif($product->stock <= $product->min_stock)
                    <x-badge value="Stock Crítico" class="badge-error text-white badge-sm font-bold shadow-sm animate-pulse" icon="o-exclamation-circle" />
                @else
                    <x-badge value="Óptimo" class="badge-success text-white badge-sm font-bold shadow-sm" icon="o-check-circle" />
                @endif
            @endscope

            @scope('cell_actions', $product)
                <div class="flex justify-end gap-1">
                    @if($product->type === 'Producto' && ($product->items_per_unit ?? 1) > 1 && $product->is_active)
                        <div class="tooltip tooltip-left" data-tip="Abrir Empaque Cerrado">
                            <x-button icon="o-archive-box-arrow-down" wire:click="openPackage({{ $product->id }})" wire:confirm="¿Desea destapar 1 empaque para agregarlo a sueltos?" spinner class="btn-sm btn-circle btn-ghost text-indigo-500 hover:bg-indigo-50" />
                        </div>
                    @endif

                    <div class="tooltip tooltip-left" data-tip="Editar Ítem">
                        <x-button icon="o-pencil-square" wire:click="edit({{ $product->id }})" spinner class="btn-sm btn-circle btn-ghost text-gray-500 hover:bg-gray-100" />
                    </div>

                    <div class="tooltip tooltip-left" data-tip="{{ $product->is_active ? 'Desactivar' : 'Activar' }}">
                        <x-button icon="{{ $product->is_active ? 'o-no-symbol' : 'o-arrow-path' }}"
                                  wire:click="toggleActive({{ $product->id }})"
                                  wire:confirm="¿Seguro que deseas {{ $product->is_active ? 'desactivar' : 'activar' }} este ítem?"
                                  spinner
                                  class="btn-sm btn-circle btn-ghost {{ $product->is_active ? 'text-error hover:bg-error/10' : 'text-success hover:bg-success/10' }}" />
                    </div>
                </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-10">
                    <x-icon name="o-cube-transparent" class="w-16 h-16 text-gray-300 mx-auto mb-4" />
                    <p class="text-gray-500 font-bold">No se encontraron registros.</p>
                    <p class="text-sm text-gray-400">Intenta cambiar los filtros superiores o la búsqueda.</p>
                </div>
            </x-slot:empty>

        </x-table>
    </x-card>

    {{-- Formulario (Drawer) Dinámico y Seccionado --}}
    <x-drawer wire:model="drawerModal" title="{{ $isEditMode ? 'Editar Ítem' : 'Registrar Nuevo Ítem' }}" right separator with-close-button class="lg:w-1/3 backdrop-blur-sm">
        <x-form wire:submit="save">

            <div class="space-y-6 pb-4">

                {{-- SECCIÓN 1: DATOS GENERALES --}}
                <div class="bg-gray-50 p-5 rounded-2xl border border-gray-100 space-y-4">
                    <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                        <x-icon name="o-document-text" class="w-4 h-4" /> Datos Generales
                    </h4>

                    <x-input label="Nombre / Descripción *" wire:model="name" placeholder="Ej: Cartulina Hilo" icon="o-pencil" class="bg-white font-bold text-gray-800" required />

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-select label="Tipo *" wire:model.live="type" :options="[['id'=>'Producto', 'name'=>'Producto Físico'], ['id'=>'Servicio', 'name'=>'Servicio / Taller']]" class="bg-white" required />

                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <x-select label="Categoría *" wire:model.live="category_id" :options="$categories" placeholder="Seleccione..." class="bg-white" required />
                            </div>
                            <x-button icon="o-plus" class="btn-primary btn-square btn-sm mb-1" wire:click="$set('categoryModal', true)" tooltip="Nueva Categoría" />
                        </div>
                    </div>

                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <x-select label="Unidad de Medida *" wire:model="unit_id" :options="$units" placeholder="Seleccione unidad..." class="bg-white" required />
                        </div>
                        <x-button icon="o-plus" class="btn-primary btn-square btn-sm mb-1" wire:click="$set('unitModal', true)" tooltip="Nueva Unidad" />
                    </div>
                </div>

                {{-- SECCIÓN 2: INFORMACIÓN FINANCIERA --}}
                <div class="bg-indigo-50/50 p-5 rounded-2xl border border-indigo-50 space-y-4">
                    <h4 class="text-[10px] font-black text-indigo-400 uppercase tracking-widest flex items-center gap-2">
                        <x-icon name="o-currency-dollar" class="w-4 h-4" /> Información Financiera
                    </h4>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input label="Precio Venta *" wire:model="sale_price" prefix="C$" type="number" step="0.01" class="bg-white font-black text-indigo-700" required />
                        <x-input label="Costo (Opcional)" wire:model="cost_price" prefix="C$" type="number" step="0.01" class="bg-white" hint="Para calcular ganancias." />
                    </div>
                </div>

                {{-- SECCIÓN 3: CONTROL DE INVENTARIO (Solo si es Producto) --}}
                @if($type === 'Producto')
                    <div class="bg-orange-50/30 p-5 rounded-2xl border border-orange-100 space-y-4 animate-fade-in">
                        <h4 class="text-[10px] font-black text-orange-500 uppercase tracking-widest flex items-center gap-2">
                            <x-icon name="o-archive-box" class="w-4 h-4" /> Control de Inventario
                        </h4>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-input label="Stock Actual *" wire:model="stock" icon="o-cube" type="number" step="0.01" class="bg-white font-bold" required />
                            <x-input label="Alerta Mínima *" wire:model="min_stock" icon="o-bell-alert" type="number" step="0.01" class="bg-white" required />
                        </div>

                        <x-input label="Cant. por empaque cerrado" wire:model="items_per_unit" icon="o-arrows-pointing-out" type="number" step="0.01" class="bg-white" hint="Ej: Pon 500 si vendes resmas pero consumes hojas sueltas." />

                        <div class="bg-white p-3 rounded-xl border border-gray-200 mt-2">
                            <x-toggle label="Disponible para Venta Directa" wire:model="is_sellable" hint="Desactiva si es solo insumo interno (Ej: Tintas)." class="toggle-success" right />
                        </div>
                    </div>
                @endif

            </div>

            <x-slot:actions>
                <x-button label="Cancelar" @click="$wire.drawerModal = false" class="btn-ghost text-gray-500" />
                <x-button label="{{ $isEditMode ? 'Actualizar Registro' : 'Guardar Registro' }}" type="submit" icon="o-check-circle" class="btn-primary shadow-sm font-bold" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-drawer>

    <x-modal wire:model="categoryModal" title="Nueva Categoría" separator class="backdrop-blur-sm">
        <x-form wire:submit="saveCategory">
            <x-input label="Nombre de la Categoría" wire:model="newCategoryName" placeholder="Ej: Sublimación" required />
            <x-slot:actions>
                <x-button label="Cancelar" wire:click="$set('categoryModal', false)" class="btn-ghost" />
                <x-button label="Guardar" type="submit" class="btn-primary" spinner="saveCategory" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-modal wire:model="unitModal" title="Nueva Unidad de Medida" separator class="backdrop-blur-sm">
        <x-form wire:submit="saveUnit">
            <x-input label="Nombre de la Unidad" wire:model="newUnitName" placeholder="Ej: Galón" required />
            <x-slot:actions>
                <x-button label="Cancelar" wire:click="$set('unitModal', false)" class="btn-ghost" />
                <x-button label="Guardar" type="submit" class="btn-primary" spinner="saveUnit" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
