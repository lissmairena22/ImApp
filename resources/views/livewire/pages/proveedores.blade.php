<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Provider;
use Mary\Traits\Toast;
use Livewire\Attributes\Computed;

new class extends Component {
    use Toast;
    use WithPagination;


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

    #[Computed]
    public function headers(): array
    {
        return [
            ['key' => 'company_name', 'label' => 'Nombre'],
            ['key' => 'ruc',          'label' => 'Código RUC'],
            ['key' => 'address',      'label' => 'Dirección'],
            ['key' => 'phone',        'label' => 'Teléfono'],
            ['key' => 'is_active',    'label' => 'Estado'],
            ['key' => 'acciones',     'label' => 'Acciones', 'sortable' => false],
        ];
    }

    #[Computed]
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
            ->Paginate(10);
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


    <x-header title="Proveedores" separator>
        <x-slot:actions>
            <x-button
                label="Agregar Proveedor"
                icon="o-plus"
                class="btn-primary"
                wire:click="$set('modalAbrir', true)"
            />
        </x-slot:actions>
    </x-header>


    <div class="mb-4 max-w-md">
        <x-input
            wire:model.live.debounce.500ms="busqueda"
            placeholder="Buscar por nombre, RUC o teléfono..."
            icon="o-magnifying-glass"
            clearable
        />
    </div>


    <x-card shadow>
      <x-table :headers="$this->headers" :rows="$this->proveedores" striped with-pagination>

            @scope('cell_is_active', $row)
                @if($row->is_active)
                    <x-badge value="Activo"   class="badge-success badge-soft" />
                @else
                    <x-badge value="Inactivo" class="badge-error badge-soft" />
                @endif
            @endscope

            @scope('cell_acciones', $row)
                <x-button
                    icon="o-pencil-square"
                    class="btn-sm btn-warning btn-soft"
                    tooltip="Editar"
                    wire:click="abrirEditar({{ $row->id }})"
                />
            @endscope

        </x-table>
    </x-card>


    <x-modal wire:model="modalAbrir" title="Registrar Proveedor" subtitle="Complete los datos del proveedor" separator>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-input
                label="Nombre"
                wire:model="nombre"
                icon="o-building-office"
            />
            <x-input
                label="RUC"
                wire:model="ruc"
                icon="o-identification"
            />
            <x-input
                label="Dirección"
                wire:model="direccion"
                icon="o-map-pin"
                class="md:col-span-2"
            />
            <x-input
                label="Teléfono"
                wire:model="telefono"
                icon="o-phone"
            />
            <div class="flex items-center gap-3 mt-2">
                <x-toggle
                    label="Activo"
                    wire:model="activo"
                />
            </div>
        </div>

        <x-slot:actions>
            <x-button
                label="Cancelar"
                wire:click="cancelarRegistro"
            />
            <x-button
                label="Registrar"
                icon="o-check"
                class="btn-primary"
                wire:click="registrar"
            />
        </x-slot:actions>

    </x-modal>


    <x-modal wire:model="modalEditar" title="Editar Proveedor" subtitle="Solo puede modificar dirección, teléfono y estado" separator>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-input
                label="Dirección"
                wire:model="editDireccion"
                icon="o-map-pin"
                class="md:col-span-2"
            />
            <x-input
                label="Teléfono"
                wire:model="editTelefono"
                icon="o-phone"
            />
            <div class="flex items-center gap-3 mt-6">
                <x-toggle
                    label="Activo"
                    wire:model="editActivo"
                />
            </div>
        </div>

        <x-slot:actions>
            <x-button label="Cancelar" wire:click="$set('modalEditar', false)" />
            <x-button
                label="Guardar Cambios"
                icon="o-check"
                class="btn-primary"
                wire:click="guardarEdicion"
            />
        </x-slot:actions>

    </x-modal>

</div>
