<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Client;
use Mary\Traits\Toast;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;
    use Toast;

    public string $search = '';
    public string $filter = 'Todos';

    public bool $drawerModal = false;
    public bool $isEditMode = false;

    public $client_id, $name, $dni, $phone, $email, $address;
    public bool $is_active = true;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function setFilter($filterName)
    {
        $this->filter = $filterName;
        $this->resetPage();
    }

    public function create()
    {
        $this->resetForm();
        $this->isEditMode = false;
        $this->drawerModal = true;
    }

    public function edit(Client $client)
    {
        $this->client_id = $client->id;
        $this->name = $client->name;
        $this->dni = $client->dni;
        $this->phone = $client->phone;
        $this->email = $client->email;
        $this->address = $client->address;
        $this->is_active = $client->is_active;

        $this->isEditMode = true;
        $this->drawerModal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'dni' => ['required', 'string', 'max:20', Rule::unique('clients', 'dni')->ignore($this->client_id)],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('clients', 'email')->ignore($this->client_id)],
            'address' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'El nombre del cliente o empresa es obligatorio.',
            'name.min' => 'El nombre debe tener al menos 3 caracteres.',
            'dni.required' => 'El DNI o Cédula/ es obligatorio.',
            'dni.unique' => 'Este número de identificación ya está registrado en otro cliente.',
            'phone.required' => 'El número de teléfono es obligatorio para contacto.',
            'email.email' => 'Por favor ingrese un formato de correo electrónico válido.',
            'email.unique' => 'Este correo electrónico ya está en uso por otro cliente.',
        ]);

        Client::updateOrCreate(
            ['id' => $this->client_id],
            [
                'name' => $this->name,
                'dni' => $this->dni,
                'phone' => $this->phone,
                'email' => $this->email,
                'address' => $this->address,
                'is_active' => $this->is_active,
            ]
        );

        $this->drawerModal = false;
        $this->success($this->isEditMode ? 'Cliente actualizado exitosamente.' : 'Cliente registrado exitosamente.');
    }

    public function toggleActive(Client $client)
    {
        $client->update(['is_active' => !$client->is_active]);
        $this->success($client->is_active ? 'Cliente activado y listo para operar.' : 'Cliente desactivado (bloqueado).');
    }

    public function resetForm()
    {
        $this->reset(['client_id', 'name', 'dni', 'phone', 'email', 'address']);
        $this->is_active = true;
    }

    public function with(): array
    {
        $totalClients = Client::count();
        $activeClients = Client::where('is_active', true)->count();
        $newThisMonth = Client::whereMonth('created_at', now()->month)
                              ->whereYear('created_at', now()->year)
                              ->count();

        $clients = Client::query()
            ->select(['id', 'name', 'dni', 'phone', 'email', 'address', 'is_active', 'created_at'])
            ->when($this->filter === 'Activos', fn($q) => $q->where('is_active', true))
            ->when($this->filter === 'Inactivos', fn($q) => $q->where('is_active', false))
            ->when($this->search, function($q) {
                $q->where(function($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                          ->orWhere('dni', 'like', "%{$this->search}%")
                          ->orWhere('email', 'like', "%{$this->search}%")
                          ->orWhere('phone', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(10);

        return [
            'clients' => $clients,
            'stats' => [
                'total' => $totalClients,
                'active' => $activeClients,
                'new' => $newThisMonth,
            ],
            'headers' => [
                ['key' => 'id', 'label' => 'ID'],
                ['key' => 'name', 'label' => 'CLIENTE / EMPRESA'],
                ['key' => 'dni', 'label' => 'DNI / CEDULA'],
                ['key' => 'phone', 'label' => 'CONTACTO'],
                ['key' => 'status', 'label' => 'ESTADO'],
                ['key' => 'actions', 'label' => '', 'sortable' => false]
            ]
        ];
    }
}; ?>

<div class="p-6">
    <x-header title="Gestión de Clientes" subtitle="Directorio de Imprenta Minerva">
        <x-slot:actions>
            <x-button icon="o-plus" label="Nuevo Cliente" class="btn-primary shadow-sm hover:scale-105 transition-transform" wire:click="create" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-stat title="Total Registrados"
                value="{{ $stats['total'] }}"
                icon="o-users"
                class="bg-white border-l-4 border-indigo-500 shadow-sm hover:shadow-md transition-shadow" />

        <x-stat title="Clientes Activos"
                value="{{ $stats['active'] }}"
                icon="o-check-badge"
                class="bg-white border-l-4 border-success shadow-sm hover:shadow-md transition-shadow" />

        <x-stat title="Nuevos este Mes"
                value="+{{ $stats['new'] }}"
                icon="o-arrow-trending-up"
                class="bg-white border-l-4 border-purple-500 shadow-sm hover:shadow-md transition-shadow" />
    </div>

    <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-white p-4 rounded-t-2xl shadow-sm border-b border-gray-100">
        <div class="flex gap-2 w-full md:w-auto overflow-x-auto pb-2 md:pb-0">
            <x-button label="Todos" wire:click="setFilter('Todos')" class="btn-sm {{ $filter === 'Todos' ? 'btn-neutral' : 'btn-ghost border border-gray-200' }}" />
            <x-button label="Activos" wire:click="setFilter('Activos')" class="btn-sm {{ $filter === 'Activos' ? 'btn-success text-white' : 'btn-ghost border border-gray-200' }}" />
            <x-button label="Inactivos" wire:click="setFilter('Inactivos')" class="btn-sm {{ $filter === 'Inactivos' ? 'btn-error text-white' : 'btn-ghost border border-gray-200' }}" />
        </div>

        <div class="w-full md:w-96 relative">
            <x-input icon="o-magnifying-glass"
                     placeholder="Buscar por nombre, DNI, correo..."
                     wire:model.live.debounce.500ms="search"
                     clearable
                     class="input-sm w-full bg-gray-50" />
        </div>
    </div>

    <x-card class="rounded-t-none shadow-sm border-t-0 bg-white">
        <x-table :headers="$headers" :rows="$clients" with-pagination class="table-sm">

            @scope('cell_id', $client)
                <span class="font-black text-gray-400">CLI-{{ str_pad($client->id, 4, '0', STR_PAD_LEFT) }}</span>
            @endscope

            @scope('cell_name', $client)
                <div class="flex items-center gap-3">
                    <x-avatar placeholder="{{ strtoupper(substr($client->name, 0, 1)) }}" class="!w-10 !h-10 {{ $client->is_active ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-400' }} font-black shadow-sm" />
                    <div>
                        <div class="font-bold text-gray-800 {{ !$client->is_active ? 'opacity-50' : '' }}">{{ $client->name }}</div>
                        <div class="text-[11px] text-gray-400 flex items-center gap-1 mt-0.5">
                            <x-icon name="o-calendar" class="w-3 h-3" />
                            Alta: {{ $client->created_at->format('d/m/Y') }}
                        </div>
                    </div>
                </div>
            @endscope

            @scope('cell_dni', $client)
                <span class="font-mono text-sm text-gray-600 {{ !$client->is_active ? 'opacity-50' : '' }}">{{ $client->dni }}</span>
            @endscope

            @scope('cell_phone', $client)
                <div>
                    <div class="font-medium text-gray-700 flex items-center gap-1 {{ !$client->is_active ? 'opacity-50' : '' }}">
                        <x-icon name="o-phone" class="w-3 h-3 text-gray-400" />
                        {{ $client->phone }}
                    </div>
                    @if($client->email)
                        <div class="text-[11px] text-gray-400 flex items-center gap-1 mt-0.5 {{ !$client->is_active ? 'opacity-50' : '' }}">
                            <x-icon name="o-envelope" class="w-3 h-3" />
                            {{ $client->email }}
                        </div>
                    @endif
                </div>
            @endscope

            @scope('cell_status', $client)
                @if($client->is_active)
                    <x-badge value="Activo" class="badge-success text-white badge-sm font-bold shadow-sm" icon="o-check-circle" />
                @else
                    <x-badge value="Inactivo" class="badge-error text-white badge-sm font-bold shadow-sm" icon="o-x-circle" />
                @endif
            @endscope

            @scope('cell_actions', $client)
                <div class="flex justify-end gap-1">
                    <div class="tooltip tooltip-left" data-tip="Editar Cliente">
                        <x-button icon="o-pencil-square" wire:click="edit({{ $client->id }})" class="btn-sm btn-circle btn-ghost text-indigo-500 hover:bg-indigo-50" />
                    </div>

                    <div class="tooltip tooltip-left" data-tip="{{ $client->is_active ? 'Bloquear Cliente' : 'Reactivar Cliente' }}">
                        <x-button icon="{{ $client->is_active ? 'o-no-symbol' : 'o-arrow-path' }}"
                                  wire:click="toggleActive({{ $client->id }})"
                                  wire:confirm="¿Seguro que deseas {{ $client->is_active ? 'desactivar' : 'activar' }} a este cliente?"
                                  class="btn-sm btn-circle btn-ghost {{ $client->is_active ? 'text-error hover:bg-error/10' : 'text-success hover:bg-success/10' }}" />
                    </div>
                </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-10">
                    <x-icon name="o-users" class="w-16 h-16 text-gray-300 mx-auto mb-4" />
                    <p class="text-gray-500 font-bold">No se encontraron clientes.</p>
                    <p class="text-sm text-gray-400">Intenta cambiar los filtros o los términos de búsqueda.</p>
                </div>
            </x-slot:empty>
        </x-table>
    </x-card>

    <x-drawer wire:model="drawerModal" title="{{ $isEditMode ? 'Editar Perfil de Cliente' : 'Registro de Nuevo Cliente' }}" right separator with-close-button class="lg:w-1/3 backdrop-blur-sm">
        <x-form wire:submit="save">

            <div class="space-y-6">


                <div class="bg-gray-50 p-5 rounded-2xl border border-gray-100 space-y-4">
                    <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                        <x-icon name="o-identification" class="w-4 h-4" /> Datos de Identificación
                    </h4>
                    <x-input label="Nombre Completo / Razón Social *" wire:model="name" icon="o-user" class="bg-white" required />

                    <x-input label="DNI o Cédula  *"
                             wire:model="dni"
                             icon="o-credit-card"
                             class="bg-white font-mono uppercase"
                             x-mask="999-999999-9999a"
                             placeholder="000-000000-0000A"
                             required />
                </div>

                <div class="bg-indigo-50/50 p-5 rounded-2xl border border-indigo-50 space-y-4">
                    <h4 class="text-[10px] font-black text-indigo-400 uppercase tracking-widest flex items-center gap-2">
                        <x-icon name="o-device-phone-mobile" class="w-4 h-4" /> Información de Contacto
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-input label="Teléfono *"
                                 wire:model="phone"
                                 icon="o-phone"
                                 class="bg-white"
                                 x-mask="9999-9999"
                                 placeholder="0000-0000"
                                 required />

                        <x-input label="Email" wire:model="email" icon="o-envelope" class="bg-white" placeholder="ejemplo@correo.com" />
                    </div>
                    <x-textarea label="Dirección Física" wire:model="address" placeholder="Ubicación exacta del cliente..." rows="2" icon="o-map-pin" class="bg-white" />
                </div>

                {{-- Estado --}}
                @if($isEditMode)
                    <div class="flex items-center justify-between bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                        <div>
                            <p class="font-bold text-gray-700 text-sm">Estado de la cuenta</p>
                            <p class="text-xs text-gray-400">Determina si puede realizar pedidos.</p>
                        </div>
                        <x-toggle wire:model="is_active" class="toggle-success" />
                    </div>
                @endif
            </div>

            <x-slot:actions>
                <x-button label="Cancelar" @click="$wire.drawerModal = false" class="btn-ghost text-gray-500" />
                <x-button label="{{ $isEditMode ? 'Actualizar Cliente' : 'Guardar Cliente' }}" type="submit" icon="o-check-circle" class="btn-primary shadow-sm font-bold" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-drawer>
</div>
