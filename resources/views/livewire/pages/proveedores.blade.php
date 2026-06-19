<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Provider;
use Mary\Traits\Toast;

new class extends Component {
    use WithPagination;
    use Toast;

    public bool $modalAbrir  = false;
    public bool $modalEditar = false;

    public string $nombre    = '';
    public string $ruc       = '';
    public string $direccion = '';
    public string $telefono  = '';
    public bool   $activo    = true;

    public ?int   $editId        = null;
    public string $editDireccion = '';
    public string $editTelefono  = '';
    public bool   $editActivo    = true;

    public string $busqueda = '';

    public function headers(): array
    {
        return [
            ['key' => 'company_name', 'label' => 'NOMBRE DEL PROVEEDOR'],
            ['key' => 'ruc',          'label' => 'RUC'],
            ['key' => 'address',      'label' => 'DIRECCIÓN'],
            ['key' => 'phone',        'label' => 'TELÉFONO'],
            ['key' => 'is_active',    'label' => 'ESTADO'],
            ['key' => 'acciones',     'label' => '', 'sortable' => false],
        ];
    }

    public function proveedores()
    {
        return Provider::query()
            ->when($this->busqueda, function ($q) {
                $b = $this->busqueda;
                $q->where('company_name', 'like', "%{$b}%")
                  ->orWhere('ruc',   'like', "%{$b}%")
                  ->orWhere('phone', 'like', "%{$b}%");
            })
            ->orderBy('company_name')
            ->paginate(10);
    }

    public function cancelarRegistro(): void
    {
        $this->reset(['nombre', 'ruc', 'direccion', 'telefono', 'activo']);
        $this->activo     = true;
        $this->modalAbrir = false;
    }

    public function registrar(): void
    {
        $this->validate([
            'nombre'    => ['required', 'regex:/^[^\d]+$/'],
            'ruc'       => ['required', 'alpha_num', 'unique:providers,ruc'],
            'direccion' => ['required', 'string'],
            'telefono'  => ['required', 'regex:/^[0-9\s\+\-]+$/'],
        ], [
            'nombre.required'    => 'El nombre es obligatorio.',
            'nombre.regex'       => 'El nombre no debe contener números.',
            'ruc.required'       => 'El RUC es obligatorio.',
            'ruc.alpha_num'      => 'El RUC solo puede contener letras y números.',
            'ruc.unique'         => 'Este RUC ya está registrado.',
            'direccion.required' => 'La dirección es obligatoria.',
            'telefono.required'  => 'El teléfono es obligatorio.',
            'telefono.regex'     => 'El teléfono solo debe contener números.',
        ]);

        Provider::create([
            'company_name' => $this->nombre,
            'ruc'          => $this->ruc,
            'address'      => $this->direccion,
            'phone'        => $this->telefono,
            'is_active'    => $this->activo,
        ]);

        $this->reset(['nombre', 'ruc', 'direccion', 'telefono']);
        $this->activo     = true;
        $this->modalAbrir = false;
        $this->success('Proveedor registrado correctamente.', position: 'toast-bottom toast-end');
    }

    public function abrirEditar(int $id): void
    {
        $p = Provider::findOrFail($id);
        $this->editId        = $p->id;
        $this->editDireccion = $p->address   ?? '';
        $this->editTelefono  = $p->phone     ?? '';
        $this->editActivo    = $p->is_active;
        $this->modalEditar   = true;
    }

    public function guardarEdicion(): void
    {
        $this->validate([
            'editDireccion' => ['required', 'string'],
            'editTelefono'  => ['required', 'regex:/^[0-9\s\+\-]+$/'],
        ], [
            'editDireccion.required' => 'La dirección es obligatoria.',
            'editTelefono.required'  => 'El teléfono es obligatorio.',
            'editTelefono.regex'     => 'El teléfono solo debe contener números.',
        ]);

        Provider::findOrFail($this->editId)->update([
            'address'   => $this->editDireccion,
            'phone'     => $this->editTelefono,
            'is_active' => $this->editActivo,
        ]);

        $this->reset(['editId', 'editDireccion', 'editTelefono', 'editActivo']);
        $this->editActivo  = true;
        $this->modalEditar = false;
        $this->success('Proveedor actualizado correctamente.', position: 'toast-bottom toast-end');
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }
};
?>

<div class="p-6">

    {{-- CABECERA --}}
    <x-header title="Directorio de Proveedores" subtitle="Gestión y administración de contactos comerciales" separator class="mb-6">
        <x-slot:actions>
            <x-button
                label="Nuevo Proveedor"
                icon="o-plus"
                class="btn-primary shadow-sm"
                wire:click="$set('modalAbrir', true)"
            />
        </x-slot:actions>
    </x-header>

    {{-- TABLA Y BUSCADOR INTEGRADO --}}
    <x-card shadow class="border border-base-200 p-0 overflow-hidden">

        <x-slot:title>
            <div class="flex items-center w-full py-2">
                <div class="w-full max-w-md">
                    <x-input
                        wire:model.live="busqueda"
                        placeholder="Buscar por nombre, RUC o teléfono..."
                        icon="o-magnifying-glass"
                        clearable
                        class="input-md bg-base-100"
                    />
                </div>
            </div>
        </x-slot:title>

        {{-- AQUÍ ESTÁ LA MAGIA: El atributo "with-pagination" es el que dibuja los números --}}
        <x-table :headers="$this->headers()" :rows="$this->proveedores()" with-pagination striped class="bg-base-100 mt-2 text-sm">

            {{-- Formato de Celdas Mejorado Visualmente --}}
            @scope('cell_company_name', $row)
                <div class="flex items-center gap-3">
                    <x-icon name="o-building-office-2" class="w-9 h-9 text-primary/70 bg-primary/10 p-2 rounded-xl" />
                    <span class="font-bold text-base">{{ $row->company_name }}</span>
                </div>
            @endscope

            @scope('cell_ruc', $row)
                <span class="font-mono text-gray-500 bg-base-200 px-2 py-1 rounded-md text-xs font-semibold">{{ $row->ruc }}</span>
            @endscope

            @scope('cell_address', $row)
                <span class="text-gray-600 truncate max-w-xs block" title="{{ $row->address }}">{{ $row->address }}</span>
            @endscope

            @scope('cell_phone', $row)
                <span class="text-gray-600 font-medium">{{ $row->phone }}</span>
            @endscope

            @scope('cell_is_active', $row)
                @if($row->is_active)
                    <x-badge value="Activo" class="badge-success badge-soft font-bold rounded-full px-3" icon="o-check-circle" />
                @else
                    <x-badge value="Inactivo" class="badge-error badge-soft font-bold rounded-full px-3" icon="o-x-circle" />
                @endif
            @endscope

            @scope('cell_acciones', $row)
                <div class="flex justify-end gap-1">
                    <x-button
                        icon="o-pencil-square"
                        class="btn-sm btn-ghost text-warning hover:bg-warning/20 rounded-full"
                        tooltip="Editar"
                        wire:click="abrirEditar({{ $row->id }})"
                    />
                </div>
            @endscope

            {{-- Estado Vacío --}}
            <x-slot:empty>
                <div class="text-center py-10 text-gray-400">
                    <x-icon name="o-building-office-2" class="w-16 h-16 mx-auto mb-4 opacity-50" />
                    <p class="text-lg">No se encontraron proveedores con los criterios de búsqueda.</p>
                </div>
            </x-slot:empty>

        </x-table>
    </x-card>

    {{-- ============================== --}}
    {{-- MODALES --}}
    {{-- ============================== --}}

    <x-modal wire:model="modalAbrir" title="Registrar Proveedor" subtitle="Complete los datos del nuevo contacto" separator>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 py-4">
            <x-input
                label="Nombre de Empresa"
                wire:model="nombre"
                icon="o-building-office"
                class="input-md"
            />
            <x-input
                label="RUC"
                wire:model="ruc"
                icon="o-identification"
                class="input-md"
            />
            <div class="md:col-span-2">
                <x-input
                    label="Dirección Completa"
                    wire:model="direccion"
                    icon="o-map-pin"
                    class="input-md"
                />
            </div>
            <x-input
                label="Teléfono"
                wire:model="telefono"
                icon="o-phone"
                class="input-md"
            />
            <div class="flex items-center pt-6">
                <x-toggle
                    label="Proveedor Activo"
                    wire:model="activo"
                    class="toggle-primary"
                    right
                />
            </div>
        </div>

        <x-slot:actions>
            <x-button label="Cancelar" wire:click="cancelarRegistro" class="btn-ghost" />
            <x-button label="Registrar Proveedor" icon="o-check" class="btn-primary" wire:click="registrar" />
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="modalEditar" title="Editar Proveedor" subtitle="Modificación de datos de contacto y estado" separator>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 py-4">
            <div class="md:col-span-2">
                <x-input
                    label="Dirección Completa"
                    wire:model="editDireccion"
                    icon="o-map-pin"
                    class="input-md"
                />
            </div>
            <x-input
                label="Teléfono"
                wire:model="editTelefono"
                icon="o-phone"
                class="input-md"
            />
            <div class="flex items-center pt-6">
                <x-toggle
                    label="Proveedor Activo"
                    wire:model="editActivo"
                    class="toggle-primary"
                    right
                />
            </div>
        </div>

        <x-slot:actions>
            <x-button label="Cancelar" wire:click="$set('modalEditar', false)" class="btn-ghost" />
            <x-button label="Guardar Cambios" icon="o-check" class="btn-primary" wire:click="guardarEdicion" />
        </x-slot:actions>
    </x-modal>

</div>
