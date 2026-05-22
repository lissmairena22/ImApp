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

    // Control de la UI de Recetas
    public bool $recipeDrawer = false;
    public ?Product $currentService = null;
    public $material_id_to_add;
    public $material_quantity = 1;

    // Propiedades del Formulario Principal
    public $product_id, $name, $category_id, $unit_id, $type;
    public $sale_price, $cost_price, $stock, $min_stock;
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
        $this->is_active = $product->is_active;
        $this->is_sellable = $product->is_sellable ?? true;

        $this->isEditMode = true;
        $this->drawerModal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\sáéíóúÁÉÍÓÚñÑ\-\/]+$/'], // Permitimos números y guiones para los materiales
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'sale_price' => 'required|numeric|min:0',
            'type' => 'required|in:Producto,Servicio',
            'stock' => 'required|numeric|min:0',
            'min_stock' => 'required|numeric|min:0',
            'is_sellable' => 'boolean',
        ]);

        Product::updateOrCreate(
            ['id' => $this->product_id],
            [
                'name' => $this->name,
                'category_id' => $this->category_id,
                'unit_id' => $this->unit_id,
                'type' => $this->type,
                'sale_price' => $this->sale_price ?: 0,
                'cost_price' => $this->cost_price ?: 0,
                'stock' => $this->stock,
                'min_stock' => $this->min_stock,
                'is_active' => $this->is_active,
                'is_sellable' => $this->is_sellable,
            ]
        );

        $this->drawerModal = false;
        $this->success($this->isEditMode ? 'Producto actualizado' : 'Producto registrado');
    }

    public function toggleActive(Product $product)
    {
        $product->update(['is_active' => !$product->is_active]);
        $this->success($product->is_active ? 'Producto activado' : 'Producto desactivado');
    }

    public function resetForm()
    {
        $this->reset(['product_id', 'name', 'category_id', 'unit_id', 'type', 'sale_price', 'cost_price', 'stock', 'min_stock']);
        $this->is_active = true;
        $this->is_sellable = true;
    }

    // --- NUEVAS FUNCIONES PARA LA RECETA (BOM) ---

    // Abrir el panel de receta
    public function openRecipe(Product $product)
    {
        $this->currentService = $product;
        $this->reset(['material_id_to_add', 'material_quantity']);
        $this->recipeDrawer = true;
    }

    // Agregar material al servicio
    public function addMaterial()
    {
        $this->validate([
            'material_id_to_add' => 'required|exists:products,id',
            'material_quantity' => 'required|numeric|min:0.01'
        ]);

        // Evitar duplicados en la receta
        if ($this->currentService->materiales()->where('material_id', $this->material_id_to_add)->exists()) {
            $this->error('Este material ya está en la receta.');
            return;
        }

        // Guardar en la tabla intermedia service_materials
        $this->currentService->materiales()->attach($this->material_id_to_add, [
            'quantity' => $this->material_quantity
        ]);

        $this->success('Material agregado a la receta.');
        $this->reset(['material_id_to_add']);
        $this->material_quantity = 1;
    }

    // Quitar material del servicio
    public function removeMaterial($materialId)
    {
        $this->currentService->materiales()->detach($materialId);
        $this->success('Material removido de la receta.');
    }

    // --- FIN FUNCIONES RECETA ---

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

        // Consultas para la Receta
        $availableMaterials = Product::where('type', 'Producto')->where('is_active', true)->orderBy('name')->get();
        $recipeMaterials = $this->currentService ? $this->currentService->materiales : collect();

        return [
            'categories' => Category::all(),
            'units' => $unitsQuery->get(),
            'availableMaterials' => $availableMaterials,
            'recipeMaterials' => $recipeMaterials,
            'products' => Product::query()
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->with(['categoria', 'unidad'])
                ->orderBy('id', 'desc')
                ->paginate(10),
            'totalProducts' => Product::count(),
            'lowStock' => Product::whereColumn('stock', '<=', 'min_stock')->where('type', 'Producto')->count(),
            'headers' => [
                ['key' => 'id', 'label' => 'CÓDIGO'],
                ['key' => 'name', 'label' => 'INSUMO / MATERIAL'],
                ['key' => 'category.name', 'label' => 'CATEGORÍA'],
                ['key' => 'stock', 'label' => 'STOCK ACTUAL'],
                ['key' => 'status', 'label' => 'ESTADO'],
                ['key' => 'actions', 'label' => '', 'sortable' => false]
            ]
        ];
    }
}; ?>

<div>
    <x-header title="Inventario de productos" subtitle="Imprenta Minerva">
        <x-slot:middle class="justify-end!">
            <x-input icon="o-magnifying-glass" placeholder="Buscar material (ej. papel)..." wire:model.live.debounce.500ms="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-plus" label="Nuevo Producto" class="btn-primary" wire:click="create" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <x-stat title="TOTAL PRODUCTOS" value="{{ $totalProducts }}" description="Registrados en sistema" icon="o-cube" />
        <x-stat title="ALERTA DE STOCK" value="{{ $lowStock }}" description="Materiales por agotarse" icon="o-exclamation-triangle" color="text-error" class="bg-error/10" />
        <x-stat title="ÓRDENES ACTIVAS" value="12" description="Consumiendo inventario" icon="o-clipboard-document-check" color="text-success" class="bg-success/10" />
    </div>

    <x-card>
        <x-table :headers="$headers" :rows="$products" with-pagination>

            @scope('cell_id', $product)
                <strong>INS-{{ str_pad($product->id, 3, '0', STR_PAD_LEFT) }}</strong>
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
                <span class="font-bold {{ $product->stock <= $product->min_stock ? 'text-error' : '' }}">
                    {{ $product->stock }}
                </span>
                <span class="text-xs text-gray-500">{{ $product->unidad->name ?? 'Und' }}</span>
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
                <div class="flex gap-2">

                    @if($product->type === 'Servicio')
                        <x-button icon="o-beaker" wire:click="openRecipe({{ $product->id }})" tooltip="Configurar Receta" spinner class="btn-sm btn-circle btn-ghost text-info" />
                    @endif

                    <x-button icon="o-pencil" wire:click="edit({{ $product->id }})" tooltip="Editar" spinner class="btn-sm btn-circle btn-ghost" />

                    @if($product->is_active)
                        <x-button icon="o-trash" wire:click="toggleActive({{ $product->id }})" wire:confirm="¿Desactivar este producto?" tooltip="Desactivar" spinner class="btn-sm btn-circle btn-ghost text-error" />
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

            <div class="grid grid-cols-2 gap-4">
                <x-select label="Categoría" wire:model.live="category_id" :options="$categories" placeholder="Seleccione..." />
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

            <div class="grid grid-cols-2 gap-4">
                <x-input label="Stock Inicial" wire:model="stock" icon="o-cube" type="number" />
                <x-input label="Stock Mínimo" wire:model="min_stock" icon="o-bell-alert" type="number" />
            </div>

            <x-select label="Unidad de Medida" wire:model="unit_id" :options="$units" placeholder="Seleccione unidad..." />

            <x-slot:actions>
                <x-button label="Cancelar" @click="$wire.drawerModal = false" class="btn-ghost" />
                <x-button label="{{ $isEditMode ? 'Actualizar' : 'Guardar' }}" type="submit" icon="o-check" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-drawer>

    <x-drawer wire:model="recipeDrawer" title="Materiales para: {{ $currentService?->name }}" right separator with-close-button class="lg:w-1/3">

        <div class="text-sm text-gray-500 mb-4">
            Agrega los insumos que se consumen automáticamente cada vez que se vende este servicio.
        </div>

        <x-form wire:submit="addMaterial">
            <x-select label="Seleccionar Material" wire:model="material_id_to_add" :options="$availableMaterials" placeholder="Buscar insumo..." searchable />

            <x-input label="Cantidad a descontar por servicio" wire:model="material_quantity" type="number" step="0.01" hint="Ej: 0.5 (medio pliego), 2 (dos unidades)." />

            <x-button label="Agregar a la Receta" type="submit" icon="o-plus" class="btn-info btn-sm text-white w-full" spinner="addMaterial" />
        </x-form>

       <hr class="my-6 border-base-300" />

        <div class="font-bold mb-3 text-lg">Materiales Actuales</div>

        <div class="space-y-2">
            @if($currentService && $recipeMaterials->count() > 0)
                @foreach($recipeMaterials as $mat)
                    <div class="flex justify-between items-center p-3 bg-base-200 rounded-lg shadow-sm">
                        <div>
                            <div class="font-bold text-sm">{{ $mat->name }}</div>
                            <div class="text-xs text-primary font-bold">
                                Consume: {{ $mat->pivot->quantity }} {{ $mat->unidad->name ?? 'Und' }}
                            </div>
                        </div>
                        <x-button icon="o-trash" wire:click="removeMaterial({{ $mat->id }})" wire:confirm="¿Quitar material?" class="btn-sm btn-circle btn-ghost text-error" spinner />
                    </div>
                @endforeach
            @else
                <div class="text-sm text-gray-400 text-center p-4 border border-dashed rounded-lg">
                    Aún no has agregado materiales a esta receta.
                </div>
            @endif
        </div>
    </x-drawer>

</div>
