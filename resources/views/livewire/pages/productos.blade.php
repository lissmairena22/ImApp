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

    // Buscador
    public string $search = '';

    // Control de la UI Principal
    public bool $drawerModal = false;
    public bool $isEditMode = false;

    // Control de Modales Secundarios (Categoría y Unidad)
    public bool $categoryModal = false;
    public bool $unitModal = false;
    public string $newCategoryName = '';
    public string $newUnitName = '';

    // Propiedades del Formulario Principal
    public $product_id, $name, $category_id, $unit_id, $type;
    public $sale_price, $cost_price, $stock, $min_stock;
    public $items_per_unit = 1;
    public bool $is_active = true;
    public bool $is_sellable = true;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedCategoryId()
    {
        $this->unit_id = null;
    }

    // --- NUEVAS FUNCIONES PARA GUARDAR CATEGORÍA Y UNIDAD ---
    public function saveCategory()
    {
        $this->validate([
            'newCategoryName' => 'required|string|max:255|unique:categories,name'
        ]);

        $category = Category::create(['name' => $this->newCategoryName]);

        $this->category_id = $category->id; // Seleccionar automáticamente
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

        $this->unit_id = $unit->id; // Seleccionar automáticamente
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

        // 1. Guardar el producto principal (Padre)
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

        // 2. Automatización: Si es un registro NUEVO, es un Producto y contiene más de 1 unidad base
        if (!$this->product_id && $this->type === 'Producto' && $this->items_per_unit > 1) {

            // Busca la unidad "Unidad" o la crea si no existe para asignársela al hijo
            $unidadSuela = Unit::firstOrCreate(['name' => 'Unidad']);

            Product::create([
                'name' => $this->name . ' (Suelto/Unidad)',
                'category_id' => $this->category_id,
                'unit_id' => $unidadSuela->id,
                'type' => 'Producto',
                // Calcula costos y precios proporcionales para el ítem desglosado
                'sale_price' => $this->sale_price / $this->items_per_unit,
                'cost_price' => ($this->cost_price ?: 0) / $this->items_per_unit,
                'stock' => 0, // Inicia en cero hasta que se abra un paquete
                'min_stock' => 0,
                'items_per_unit' => 1,
                'is_active' => true,
                'is_sellable' => false, // Marcado como uso interno para los servicios/impresiones
                'parent_id' => $product->id // Relación jerárquica
            ]);
        }

        $this->drawerModal = false;
        $this->success($this->isEditMode ? 'Registro actualizado' : 'Registro y unidades sueltas creados exitosamente');
    }

    // Proceso para desglosar un empaque/caja/resma en stock suelto
    public function openPackage(Product $parentProduct)
    {
        if ($parentProduct->stock < 1) {
            $this->error('No hay empaques cerrados en stock para abrir.');
            return;
        }

        // Buscar si existe un producto hijo asociado a este padre
        $childProduct = Product::where('parent_id', $parentProduct->id)->first();

        if ($childProduct) {
            // Restar 1 unidad al empaque principal
            $parentProduct->decrement('stock', 1);

            // Sumar la cantidad de unidades base contenidas al stock del hijo
            $childProduct->increment('stock', $parentProduct->items_per_unit);

            $this->success("Empaque abierto. Se agregaron {$parentProduct->items_per_unit} unidades sueltas al inventario.");
        } else {
            $this->error('Este producto no tiene configuradas unidades sueltas automáticamente.');
        }
    }

    public function toggleActive(Product $product)
    {
        $product->update(['is_active' => !$product->is_active]);
        $this->success($product->is_active ? 'Activado correctamente' : 'Desactivado correctamente');
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
        $unitsQuery = Unit::query();

        if ($this->category_id) {
            $categoria = Category::find($this->category_id);
            if ($categoria) {
                $nombreCat = mb_strtolower($categoria->name, 'UTF-8');

                if (str_contains($nombreCat, 'papel') || str_contains($nombreCat, 'cartulina')) {
                    $unitsQuery->whereIn('name', ['Resma', 'Pliego', 'Paquete', 'Unidad', 'Caja']);
                } elseif (str_contains($nombreCat, 'tarjeta') || str_contains($nombreCat, 'etiqueta') || str_contains($nombreCat, 'publicidad')) {
                    $unitsQuery->whereIn('name', ['Millar', 'Paquete', 'Caja', 'Unidad', 'Docena']);
                } elseif (str_contains($nombreCat, 'impresi') || str_contains($nombreCat, 'diseño') || str_contains($nombreCat, 'fotocopia') || str_contains($nombreCat, 'encuadernado') || str_contains($nombreCat, 'laminado') || str_contains($nombreCat, 'material')) {
                    $unitsQuery->whereIn('name', ['Unidad', 'Docena']);
                } elseif (str_contains($nombreCat, 'tinta')) {
                    $unitsQuery->whereIn('name', ['Litro', 'Unidad']);
                } else {
                    $unitsQuery->whereIn('name', ['Unidad', 'Paquete']);
                }
            }
        }

        $unidades = $unitsQuery->get();

        if ($this->unit_id && !$unidades->contains('id', $this->unit_id)) {
            $unidadExtra = Unit::find($this->unit_id);
            if ($unidadExtra) {
                $unidades->push($unidadExtra);
            }
        }

        return [
            'categories' => Category::all(),
            'units' => $unidades,
            'products' => Product::query()
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->with(['categoria', 'unidad'])
                ->orderBy('id', 'desc')
                ->paginate(10),
            'totalProducts' => Product::count(),
            'lowStock' => Product::whereColumn('stock', '<=', 'min_stock')->where('type', 'Producto')->count(),
            'headers' => [
                ['key' => 'id', 'label' => 'CÓDIGO'],
                ['key' => 'name', 'label' => 'NOMBRE'],
                ['key' => 'category.name', 'label' => 'CATEGORÍA'],
                ['key' => 'stock', 'label' => 'STOCK ACTUAL'],
                ['key' => 'status', 'label' => 'ESTADO'],
                ['key' => 'actions', 'label' => '', 'sortable' => false]
            ]
        ];
    }
}; ?>

<div>
    <x-header title="Inventario y Servicios" subtitle="Imprenta Minerva">
        <x-slot:middle class="justify-end!">
            <x-input icon="o-magnifying-glass" placeholder="Buscar..." wire:model.live.debounce.500ms="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-plus" label="Nuevo Registro" class="btn-primary" wire:click="create" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <x-stat title="TOTAL REGISTROS" value="{{ $totalProducts }}" description="Productos y servicios en sistema" icon="o-cube" />
        <x-stat title="ALERTA DE STOCK" value="{{ $lowStock }}" description="Productos por agotarse" icon="o-exclamation-triangle" color="text-error" class="bg-error/10" />
    </div>

    <x-card>
        <x-table :headers="$headers" :rows="$products" with-pagination>

            @scope('cell_id', $product)
                <strong>{{ $product->type === 'Servicio' ? 'SRV' : 'INS' }}-{{ str_pad($product->id, 3, '0', STR_PAD_LEFT) }}</strong>
            @endscope

            @scope('cell_name', $product)
                <div class="flex items-center gap-3">
                    <x-icon name="{{ $product->type === 'Servicio' ? 'o-briefcase' : 'o-document' }}" class="w-8 h-8 text-primary" />
                    <div>
                        <div class="font-bold">{{ $product->name }}</div>
                        <div class="text-xs text-gray-500 flex gap-2">
                            <span>Tipo: {{ $product->type }}</span>
                            @if(!$product->is_sellable && $product->type === 'Producto')
                                <span class="text-warning font-bold">• Solo Uso Interno</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endscope

            @scope('cell_category.name', $product)
                <x-badge :value="$product->categoria->name ?? 'N/A'" class="badge-ghost" />
            @endscope

            @scope('cell_stock', $product)
                @if($product->type === 'Producto')
                    <div>
                        <span class="font-bold {{ $product->stock <= $product->min_stock ? 'text-error' : '' }}">
                            {{ $product->stock }}
                        </span>
                        <span class="text-xs text-gray-500">{{ $product->unidad->name ?? 'Und' }}</span>
                    </div>

                    @if(($product->items_per_unit ?? 1) > 1)
                        <div class="text-xs text-info font-medium mt-1">
                            = {{ $product->stock * $product->items_per_unit }} unid. base
                        </div>
                    @endif
                @else
                    <span class="text-gray-400 italic">N/A</span>
                @endif
            @endscope

            @scope('cell_status', $product)
                @if(!$product->is_active)
                    <x-badge value="Inactivo" class="badge-neutral" icon="o-x-circle" />
                @elseif($product->type === 'Servicio')
                    <x-badge value="Activo" class="badge-info" icon="o-check-circle" />
                @elseif($product->stock <= $product->min_stock)
                    <x-badge value="Stock Crítico" class="badge-error" icon="o-exclamation-circle" />
                @else
                    <x-badge value="Óptimo" class="badge-success" icon="o-check-circle" />
                @endif
            @endscope

            @scope('cell_actions', $product)
                <div class="flex gap-2 items-center">
                    {{-- Botón dinámico: Solo aparece si el paquete contiene unidades desglosables y está activo --}}
                    @if($product->type === 'Producto' && ($product->items_per_unit ?? 1) > 1 && $product->is_active)
                        <x-button icon="o-archive-box" wire:click="openPackage({{ $product->id }})" tooltip="Abrir Empaque" spinner class="btn-sm btn-circle btn-ghost text-info" />
                    @endif

                    <x-button icon="o-pencil" wire:click="edit({{ $product->id }})" tooltip="Editar" spinner class="btn-sm btn-circle btn-ghost" />

                    @if($product->is_active)
                        <x-button icon="o-trash" wire:click="toggleActive({{ $product->id }})" wire:confirm="¿Desactivar este registro?" tooltip="Desactivar" spinner class="btn-sm btn-circle btn-ghost text-error" />
                    @else
                        <x-button icon="o-arrow-path" wire:click="toggleActive({{ $product->id }})" tooltip="Activar" spinner class="btn-sm btn-circle btn-ghost text-success" />
                    @endif
                </div>
            @endscope

        </x-table>
    </x-card>

    <x-drawer wire:model="drawerModal" title="{{ $isEditMode ? 'Editar' : 'Registrar Nuevo' }}" right separator with-close-button class="lg:w-1/3">
        <x-form wire:submit="save">
            <x-input label="Nombre del producto o servicio" wire:model="name" placeholder="Ej: Cartulina Hilo" icon="o-document-text" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <x-select label="Categoría" wire:model.live="category_id" :options="$categories" placeholder="Seleccione..." />
                    </div>
                    <x-button icon="o-plus" class="btn-primary btn-square" wire:click="$set('categoryModal', true)" tooltip="Nueva Categoría" />
                </div>

                <x-select label="Tipo" wire:model.live="type" :options="[['id'=>'Producto', 'name'=>'Producto'], ['id'=>'Servicio', 'name'=>'Servicio']]" placeholder="Seleccione..." />
            </div>

            @if($type === 'Producto')
                <div class="bg-base-200 p-3 rounded-lg">
                    <x-toggle label="Disponible para Venta Directa" wire:model="is_sellable" hint="Apágalo si es un insumo solo de uso interno de las máquinas (Ej: Tinta, Planchas)." class="toggle-primary" right />
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <x-input label="Precio Venta" wire:model="sale_price" prefix="C$" type="number" step="0.01" />
            </div>

            @if($type === 'Producto')
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-input label="Stock Inicial" wire:model="stock" icon="o-cube" type="number" />
                    <x-input label="Stock Mínimo" wire:model="min_stock" icon="o-bell-alert" type="number" />
                    <x-input label="Cant. por empaque" wire:model="items_per_unit" icon="o-arrows-pointing-out" type="number" step="0.01" hint="Ej: 500 para hojas en 1 resma." />
                </div>
            @endif

            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <x-select label="Unidad de Medida" wire:model="unit_id" :options="$units" placeholder="Seleccione unidad principal..." />
                </div>
                <x-button icon="o-plus" class="btn-primary btn-square" wire:click="$set('unitModal', true)" tooltip="Nueva Unidad" />
            </div>

            <x-slot:actions>
                <x-button label="Cancelar" @click="$wire.drawerModal = false" class="btn-ghost" />
                <x-button label="{{ $isEditMode ? 'Actualizar' : 'Guardar' }}" type="submit" icon="o-check" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-drawer>

    <!-- MODAL: Nueva Categoría -->
    <x-modal wire:model="categoryModal" title="Nueva Categoría" separator>
        <x-form wire:submit="saveCategory">
            <x-input label="Nombre de la Categoría" wire:model="newCategoryName" placeholder="Ej: Sublimación" required />
            <x-slot:actions>
                <x-button label="Cancelar" wire:click="$set('categoryModal', false)" class="btn-ghost" />
                <x-button label="Guardar" type="submit" class="btn-primary" spinner="saveCategory" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <!-- MODAL: Nueva Unidad -->
    <x-modal wire:model="unitModal" title="Nueva Unidad de Medida" separator>
        <x-form wire:submit="saveUnit">
            <x-input label="Nombre de la Unidad" wire:model="newUnitName" placeholder="Ej: Galón" required />
            <x-slot:actions>
                <x-button label="Cancelar" wire:click="$set('unitModal', false)" class="btn-ghost" />
                <x-button label="Guardar" type="submit" class="btn-primary" spinner="saveUnit" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
