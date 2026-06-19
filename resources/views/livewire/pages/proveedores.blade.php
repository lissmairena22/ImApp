<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Provider;
use Mary\Traits\Toast;
use Livewire\Attributes\Computed;

new class extends Component {
    use Toast;
    use WithPagination;

    public string $search = '';
    public string $filter = 'Todos';

    public bool $drawerModal = false;
    public bool $isEditMode = false;

    public ?int $provider_id = null;
    public string $nombre = '';
    public string $ruc = '';
    public string $direccion = '';
    public string $telefono = '';
    public bool $activo = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setFilter(string $filterName): void
    {
        $this->filter = $filterName;
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->isEditMode = false;
        $this->drawerModal = true;
    }

    public function edit(int $id): void
    {
        $p = Provider::findOrFail($id);

        $this->provider_id = $p->id;
        $this->nombre = $p->company_name;
        $this->ruc = $p->ruc ?? '';
        $this->direccion = $p->address ?? '';
        $this->telefono = $p->phone ?? '';
        $this->activo = $p->is_active;

        $this->isEditMode = true;
        $this->drawerModal = true;
    }

    public function save(): void
    {
        if ($this->isEditMode) {
            $this->validate([
                'direccion' => ['required', 'string'],
                'telefono'  => ['required', 'regex:/^[0-9\s\+\-]+$/'],
            ], [
                'direccion.required' => 'La dirección es obligatoria.',
                'telefono.required'  => 'El teléfono es obligatorio.',
                'telefono.regex'     => 'El teléfono solo debe contener números.',
            ]);

            Provider::findOrFail($this->provider_id)->update([
                'address'   => $this->direccion,
                'phone'     => $this->telefono,
                'is_active' => $this->activo,
            ]);

            $mensaje = 'Proveedor actualizado correctamente.';
        } else {
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
            ]);

            Provider::create([
                'company_name' => $this->nombre,
                'ruc'          => strtoupper($this->ruc),
                'address'      => $this->direccion,
                'phone'        => $this->telefono,
                'is_active'    => $this->activo,
            ]);

            $mensaje = 'Proveedor registrado exitosamente.';
        }

        $this->drawerModal = false;
        $this->success($mensaje, position: 'toast-top toast-center');
    }

    public function toggleActive(Provider $provider): void
    {
        $provider->update(['is_active' => !$provider->is_active]);
        $this->success($provider->is_active ? 'Proveedor habilitado.' : 'Proveedor bloqueado.');
    }

    public function resetForm(): void
    {
        $this->reset(['provider_id', 'nombre', 'ruc', 'direccion', 'telefono']);
        $this->activo = true;
    }

    #[Computed]
    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => 'CÓDIGO'],
            ['key' => 'company_name', 'label' => 'PROVEEDOR / EMPRESA'],
            ['key' => 'ruc', 'label' => 'RUC'],
            ['key' => 'contact', 'label' => 'CONTACTO'],
            ['key' => 'is_active', 'label' => 'ESTADO'],
            ['key' => 'acciones', 'label' => '', 'sortable' => false],
        ];
    }

    public function with(): array
    {
        $stats = [
            'total' => Provider::count(),
            'active' => Provider::where('is_active', true)->count(),
            'new' => Provider::whereMonth('created_at', now()->month)
                             ->whereYear('created_at', now()->year)
                             ->count(),
        ];

        $proveedores = Provider::query()
            ->when($this->filter === 'Activos', fn($q) => $q->where('is_active', true))
            ->when($this->filter === 'Inactivos', fn($q) => $q->where('is_active', false))
            ->when($this->search, function ($q) {
                $b = $this->search;
                $q->where(function($query) use ($b) {
                    $query->where('company_name', 'like', "%{$b}%")
                          ->orWhere('ruc', 'like', "%{$b}%")
                          ->orWhere('phone', 'like', "%{$b}%");
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(10);

        return [
            'proveedores' => $proveedores,
            'stats' => $stats
        ];
    }
};
?>

<div class="p-6 bg-gray-50/50 min-h-screen">

    <x-header title="Gestión de Proveedores" subtitle="Directorio de abastecimiento de Imprenta Minerva" separator class="mb-6">
        <x-slot:actions>
            <x-button label="Nuevo Proveedor" icon="o-plus" class="btn-primary shadow-sm hover:scale-105 transition-transform" wire:click="create" />
        </x-slot:actions>
    </x-header>

    {{-- Mini-Dashboard Estadístico --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-stat title="Total Registrados"
                value="{{ $stats['total'] }}"
                icon="o-building-storefront"
                class="bg-white border-l-4 border-indigo-500 shadow-sm hover:shadow-md transition-shadow" />

        <x-stat title="Proveedores Activos"
                value="{{ $stats['active'] }}"
                icon="o-check-badge"
                class="bg-white border-l-4 border-success shadow-sm hover:shadow-md transition-shadow" />

        <x-stat title="Nuevos este Mes"
                value="+{{ $stats['new'] }}"
                icon="o-arrow-trending-up"
                class="bg-white border-l-4 border-purple-500 shadow-sm hover:shadow-md transition-shadow" />
    </div>

    {{-- Barra de Búsqueda y Filtros Rápidos --}}
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-white p-4 rounded-t-2xl shadow-sm border-b border-gray-100">
        <div class="flex gap-2 w-full md:w-auto overflow-x-auto pb-2 md:pb-0">
            <x-button label="Todos" wire:click="setFilter('Todos')" class="btn-sm {{ $filter === 'Todos' ? 'btn-neutral' : 'btn-ghost border border-gray-200' }}" />
            <x-button label="Activos" wire:click="setFilter('Activos')" class="btn-sm {{ $filter === 'Activos' ? 'btn-success text-white' : 'btn-ghost border border-gray-200' }}" />
            <x-button label="Inactivos" wire:click="setFilter('Inactivos')" class="btn-sm {{ $filter === 'Inactivos' ? 'btn-error text-white' : 'btn-ghost border border-gray-200' }}" />
        </div>

        <div class="w-full md:w-96 relative">
            <x-input icon="o-magnifying-glass"
                     placeholder="Buscar por nombre, RUC o teléfono..."
                     wire:model.live.debounce.500ms="search"
                     clearable
                     class="input-sm w-full bg-gray-50" />
        </div>
    </div>

    <x-card class="rounded-t-none shadow-sm border-t-0 bg-white">
        <x-table :headers="$this->headers" :rows="$proveedores" with-pagination class="table-sm">

            @scope('cell_id', $row)
                <span class="font-black text-gray-400">PROV-{{ str_pad($row->id, 3, '0', STR_PAD_LEFT) }}</span>
            @endscope

            @scope('cell_company_name', $row)
                <div class="flex items-center gap-3">
                    <x-avatar placeholder="{{ strtoupper(substr($row->company_name, 0, 1)) }}" class="!w-10 !h-10 {{ $row->is_active ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-400' }} font-black shadow-sm" />
                    <div>
                        <div class="font-bold text-gray-800 {{ !$row->is_active ? 'opacity-50' : '' }}">{{ $row->company_name }}</div>
                        <div class="text-[11px] text-gray-400 flex items-center gap-1 mt-0.5">
                            <x-icon name="o-map-pin" class="w-3 h-3" />
                            <span class="truncate max-w-[200px]">{{ $row->address }}</span>
                        </div>
                    </div>
                </div>
            @endscope

            @scope('cell_ruc', $row)
                <span class="font-mono font-bold text-sm text-gray-600 {{ !$row->is_active ? 'opacity-50' : '' }}">{{ $row->ruc }}</span>
            @endscope

            @scope('cell_contact', $row)
                <div class="font-medium text-gray-700 flex items-center gap-2 {{ !$row->is_active ? 'opacity-50' : '' }}">
                    <div class="bg-gray-100 p-1.5 rounded-md text-gray-500"><x-icon name="o-phone" class="w-3 h-3" /></div>
                    {{ $row->phone }}
                </div>
            @endscope

            @scope('cell_is_active', $row)
                @if($row->is_active)
                    <x-badge value="Activo" class="badge-success text-white badge-sm font-bold shadow-sm" icon="o-check-circle" />
                @else
                    <x-badge value="Inactivo" class="badge-neutral text-white badge-sm font-bold shadow-sm" icon="o-x-circle" />
                @endif
            @endscope

            @scope('cell_acciones', $row)
                <div class="flex justify-end gap-1">
                    <div class="tooltip tooltip-left" data-tip="Editar Contacto">
                        <x-button icon="o-pencil-square" wire:click="edit({{ $row->id }})" class="btn-sm btn-circle btn-ghost text-indigo-500 hover:bg-indigo-50" />
                    </div>

                    <div class="tooltip tooltip-left" data-tip="{{ $row->is_active ? 'Bloquear Proveedor' : 'Reactivar Proveedor' }}">
                        <x-button icon="{{ $row->is_active ? 'o-no-symbol' : 'o-arrow-path' }}"
                                  wire:click="toggleActive({{ $row->id }})"
                                  wire:confirm="¿Seguro que deseas {{ $row->is_active ? 'bloquear' : 'activar' }} a este proveedor?"
                                  class="btn-sm btn-circle btn-ghost {{ $row->is_active ? 'text-error hover:bg-error/10' : 'text-success hover:bg-success/10' }}" />
                    </div>
                </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-10">
                    <x-icon name="o-building-storefront" class="w-16 h-16 text-gray-300 mx-auto mb-4" />
                    <p class="text-gray-500 font-bold">No se encontraron proveedores.</p>
                    <p class="text-sm text-gray-400">Intenta cambiar los filtros o los términos de búsqueda.</p>
                </div>
            </x-slot:empty>

        </x-table>
    </x-card>

    {{-- Formulario Único (Drawer) Inteligente --}}
    <x-drawer wire:model="drawerModal" title="{{ $isEditMode ? 'Actualizar Proveedor' : 'Registro de Proveedor' }}" right separator with-close-button class="lg:w-1/3 backdrop-blur-sm">

        @if($isEditMode)
            <div class="bg-warning/10 border border-warning/20 p-3 rounded-xl mb-4 flex items-start gap-3">
                <x-icon name="o-shield-check" class="w-5 h-5 text-warning mt-0.5" />
                <p class="text-xs text-warning-content font-medium">Por políticas de seguridad, el <strong>Nombre</strong> y el <strong>RUC</strong> no pueden ser modificados una vez registrados. Solo puedes actualizar la información de contacto.</p>
            </div>
        @endif

        <x-form wire:submit="save">
            <div class="space-y-6">

                {{-- Sección: Datos de Empresa --}}
                <div class="bg-gray-50 p-5 rounded-2xl border border-gray-100 space-y-4">
                    <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                        <x-icon name="o-building-office" class="w-4 h-4" /> Datos de Empresa
                    </h4>

                    <x-input label="Nombre / Razón Social *"
                             wire:model="nombre"
                             icon="o-building-office-2"
                             class="{{ $isEditMode ? 'bg-gray-100 text-gray-500' : 'bg-white font-bold' }}"
                             readonly="{{ $isEditMode }}"
                             required />

                    <x-input label="RUC *"
                             wire:model="ruc"
                             icon="o-identification"
                             class="{{ $isEditMode ? 'bg-gray-100 text-gray-500 font-mono' : 'bg-white font-mono uppercase' }}"
                             placeholder="Ej: J0000000000000"
                             readonly="{{ $isEditMode }}"
                             required />
                </div>

                {{-- Sección: Contacto --}}
                <div class="bg-indigo-50/50 p-5 rounded-2xl border border-indigo-50 space-y-4">
                    <h4 class="text-[10px] font-black text-indigo-400 uppercase tracking-widest flex items-center gap-2">
                        <x-icon name="o-phone" class="w-4 h-4" /> Información de Contacto
                    </h4>

                    {{-- MÁSCARA DE TELÉFONO DE 8 DÍGITOS --}}
                    <x-input label="Teléfono (NIC) *"
                             wire:model="telefono"
                             icon="o-device-phone-mobile"
                             class="bg-white font-bold"
                             x-mask="9999-9999"
                             placeholder="0000-0000"
                             required />

                    <x-textarea label="Dirección Física *"
                                wire:model="direccion"
                                placeholder="Ubicación exacta..."
                                rows="3"
                                icon="o-map-pin"
                                class="bg-white"
                                required />
                </div>

                {{-- Estado --}}
                <div class="flex items-center justify-between bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                    <div>
                        <p class="font-bold text-gray-700 text-sm">Estado Operativo</p>
                        <p class="text-xs text-gray-400">Permite registrar compras con ellos.</p>
                    </div>
                    <x-toggle wire:model="activo" class="toggle-success" right />
                </div>
            </div>

            <x-slot:actions>
                <x-button label="Cancelar" @click="$wire.drawerModal = false" class="btn-ghost text-gray-500" />
                <x-button label="{{ $isEditMode ? 'Guardar Cambios' : 'Registrar Proveedor' }}" type="submit" icon="o-check-circle" class="btn-primary shadow-sm font-bold" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-drawer>

</div>
