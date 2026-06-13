<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Client;
use Mary\Traits\Toast;
use Illuminate\Validation\Rule;

new class extends Component {
    use WithPagination;
    use Toast;

    public string $search = '';
    public bool $drawerModal = false;
    public bool $isEditMode = false;

    public $client_id, $name, $dni, $phone, $email, $address;
    public bool $is_active = true;

    public function updatedSearch()
    {
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
            'dni.required' => 'El DNI o Cédula/RUC es obligatorio.',
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
        $this->success($this->isEditMode ? 'Cliente actualizado exitosamente' : 'Cliente registrado exitosamente');
    }

    public function toggleActive(Client $client)
    {
        $client->update(['is_active' => !$client->is_active]);
        $this->success($client->is_active ? 'Cliente activado' : 'Cliente desactivado');
    }

    public function resetForm()
    {
        $this->reset(['client_id', 'name', 'dni', 'phone', 'email', 'address']);
        $this->is_active = true;
    }

    public function with(): array
    {
        return [
            'clients' => Client::query()
            ->select(['id', 'name', 'dni', 'phone', 'email', 'address', 'is_active'])
            ->when($this->search, function($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('dni', 'like', "%{$this->search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10),
            'totalClients' => Client::count(),
            'activeClients' => Client::where('is_active', true)->count(),
            'headers' => [
                ['key' => 'id', 'label' => 'ID'],
                ['key' => 'name', 'label' => 'CLIENTE / EMPRESA'],
                ['key' => 'dni', 'label' => 'DNI/RUC'],
                ['key' => 'phone', 'label' => 'TELÉFONO'],
                ['key' => 'status', 'label' => 'ESTADO'],
                ['key' => 'actions', 'label' => '', 'sortable' => false]
            ]
        ];
    }
}; ?>

<div>
    <x-header title="Gestión de Clientes" subtitle="Directorio de Imprenta América">
        <x-slot:middle class="justify-end!">
            <x-input icon="o-magnifying-glass" placeholder="Buscar por nombre o DNI..." wire:model.live.debounce.500ms="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-plus" label="Nuevo Cliente" class="btn-primary" wire:click="create" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <x-stat title="TOTAL CLIENTES" value="{{ $totalClients }}" icon="o-users" />
        <x-stat title="CLIENTES ACTIVOS" value="{{ $activeClients }}" icon="o-check-badge" color="text-success" class="bg-success/10" />
    </div>

    <x-card>
        <x-table :headers="$headers" :rows="$clients" with-pagination>
            @scope('cell_id', $client)
                <strong>CLI-{{ str_pad($client->id, 3, '0', STR_PAD_LEFT) }}</strong>
            @endscope

            @scope('cell_name', $client)
                <div class="flex items-center gap-3">
                    <x-avatar placeholder="{{ strtoupper(substr($client->name, 0, 1)) }}" class="!w-9 !h-9 bg-primary/10 text-primary font-bold" />
                    <div>
                        <div class="font-bold">{{ $client->name }}</div>
                        <div class="text-xs text-gray-500">{{ $client->email ?? 'Sin correo' }}</div>
                    </div>
                </div>
            @endscope

            @scope('cell_status', $client)
                <x-badge value="{{ $client->is_active ? 'Activo' : 'Inactivo' }}"
                         class="{{ $client->is_active ? 'badge-success' : 'badge-neutral' }}"
                         icon="{{ $client->is_active ? 'o-check-circle' : 'o-x-circle' }}" />
            @endscope

            @scope('cell_actions', $client)
                <div class="flex gap-2">
                    <x-button icon="o-pencil" wire:click="edit({{ $client->id }})" spinner class="btn-sm btn-circle btn-ghost" />
                    <x-button icon="{{ $client->is_active ? 'o-trash' : 'o-arrow-path' }}"
                              wire:click="toggleActive({{ $client->id }})"
                              class="btn-sm btn-circle btn-ghost {{ $client->is_active ? 'text-error' : 'text-success' }}" />
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-drawer wire:model="drawerModal" title="{{ $isEditMode ? 'Editar Cliente' : 'Nuevo Cliente' }}" right separator with-close-button class="lg:w-1/3">
        <x-form wire:submit="save">
            <x-input label="Nombre Completo / Razón Social *" wire:model="name" icon="o-user" required />
            <x-input label="DNI o Cédula/RUC *" wire:model="dni" icon="o-identification" required />

            <div class="grid grid-cols-2 gap-4">
                <x-input label="Teléfono *" wire:model="phone" icon="o-phone" required />
                <x-input label="Email" wire:model="email" icon="o-envelope" />
            </div>

            <x-textarea label="Dirección" wire:model="address" placeholder="Ubicación del cliente..." rows="3" />

            <x-slot:actions>
                <x-button label="Cancelar" @click="$wire.drawerModal = false" class="btn-ghost" />
                <x-button label="Guardar Cliente" type="submit" icon="o-check" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-drawer>
</div>
