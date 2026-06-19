<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\User;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Hash;

new class extends Component {
    use WithPagination, Toast;

    public string $search = '';
    public string $filter = 'Todos';
    public bool $showPassword = false;

    public bool $drawerModal = false;
    public bool $isEditMode = false;

    public $user_id, $name, $username, $password, $role, $status = 'Activo';

    public function setFilter(string $filterName)
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

    public function edit(User $user)
    {
        $this->user_id = $user->id;
        $this->name = $user->name;
        $this->username = $user->username;
        $this->role = $user->role;
        $this->status = $user->status;
        $this->password = '';

        $this->isEditMode = true;
        $this->drawerModal = true;
    }

    public function toggleStatus(User $user)
    {
        $user->status = ($user->status === 'Activo') ? 'Inactivo' : 'Activo';
        $user->save();
        $this->success("Usuario {$user->username} ahora está " . strtolower($user->status));
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username,' . $this->user_id,
            'role' => 'required|in:Administrador,Cajero,Diseñador,Produccion',
            'status' => 'required|in:Activo,Inactivo',
        ];

        if (!$this->isEditMode || !empty($this->password)) {
            $rules['password'] = 'required|min:6';
        }

        $this->validate($rules);

        $data = [
            'name' => $this->name,
            'username' => $this->username,
            'role' => $this->role,
            'status' => $this->status,
        ];

        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        User::updateOrCreate(['id' => $this->user_id], $data);

        $this->drawerModal = false;
        $this->success($this->isEditMode ? 'Usuario actualizado correctamente' : 'Usuario creado con éxito');
    }

    public function resetForm()
    {
        $this->reset(['user_id', 'name', 'username', 'password', 'role']);
        $this->status = 'Activo';
        $this->resetErrorBag();
    }

    public function with(): array
    {
        $users = User::query()
            ->when($this->filter !== 'Todos', fn($q) => $q->where('role', $this->filter))
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('id', 'desc')
            ->paginate(10);

        return [
            'users' => $users,
            'stats' => [
                'total' => User::count(),
                'admin' => User::where('role', 'Administrador')->count(),
                'cajeros' => User::where('role', 'Cajero')->count(),
                'operativos' => User::whereIn('role', ['Diseñador', 'Produccion'])->count(),
            ],
            'headers' => [
                ['key' => 'id', 'label' => 'ID'],
                ['key' => 'username', 'label' => 'USUARIO'],
                ['key' => 'role', 'label' => 'ROL'],
                ['key' => 'status', 'label' => 'ESTADO'],
                ['key' => 'actions', 'label' => '', 'sortable' => false]
            ]
        ];
    }
}; ?>

<div class="p-6 bg-gray-50/50 min-h-screen">
    <x-header title="Usuarios del Sistema" subtitle="Control de acceso y roles" separator class="mb-6">
        <x-slot:actions>
            <x-button icon="o-user-plus" label="Nuevo Usuario" class="btn-primary shadow-sm hover:scale-105 transition-transform" wire:click="create" />
        </x-slot:actions>
    </x-header>

    {{-- Mini-Dashboard Estadístico --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <x-stat title="Total" value="{{ $stats['total'] }}" icon="o-users" class="bg-white border-l-4 border-gray-400 shadow-sm" />
        <x-stat title="Admins" value="{{ $stats['admin'] }}" icon="o-shield-check" class="bg-white border-l-4 border-error text-error shadow-sm" />
        <x-stat title="Cajeros" value="{{ $stats['cajeros'] }}" icon="o-banknotes" class="bg-white border-l-4 border-success text-success shadow-sm" />
        <x-stat title="Operativos" value="{{ $stats['operativos'] }}" icon="o-cog" class="bg-white border-l-4 border-info text-info shadow-sm" />
    </div>

    <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-white p-4 rounded-t-2xl shadow-sm border-b border-gray-100">
        <div class="flex gap-2 w-full md:w-auto overflow-x-auto pb-2 md:pb-0">
            <x-button label="Todos" wire:click="setFilter('Todos')" class="btn-sm {{ $filter === 'Todos' ? 'btn-neutral' : 'btn-ghost border border-gray-200' }}" />
            <x-button label="Administradores" wire:click="setFilter('Administrador')" class="btn-sm {{ $filter === 'Administrador' ? 'btn-error text-white' : 'btn-ghost border border-gray-200' }}" />
            <x-button label="Cajeros" wire:click="setFilter('Cajero')" class="btn-sm {{ $filter === 'Cajero' ? 'btn-success text-white' : 'btn-ghost border border-gray-200' }}" />
        </div>
        <div class="w-full md:w-80">
            <x-input icon="o-magnifying-glass" placeholder="Buscar por nombre..." wire:model.live.debounce.500ms="search" clearable class="input-sm bg-gray-50" />
        </div>
    </div>

    <x-card class="rounded-t-none shadow-sm border-t-0 bg-white">
        <x-table :headers="$headers" :rows="$users" with-pagination class="table-sm">
            @scope('cell_id', $user)
                <span class="text-[10px] font-bold text-gray-400">#{{ str_pad($user->id, 3, '0', STR_PAD_LEFT) }}</span>
            @endscope

            @scope('cell_username', $user)
                <div class="flex items-center gap-3">
                    <x-avatar placeholder="{{ strtoupper(substr($user->name, 0, 1)) }}" class="!w-9 !h-9 bg-indigo-50 text-indigo-700 font-black shadow-sm" />
                    <div>
                        <div class="font-bold text-gray-800">{{ $user->username }}</div>
                        <div class="text-xs text-gray-500">{{ $user->name }}</div>
                    </div>
                </div>
            @endscope

            @scope('cell_role', $user)
                @php
                    $roleClasses = match($user->role) {
                        'Administrador' => 'badge-error text-white',
                        'Cajero' => 'badge-success text-white',
                        'Diseñador' => 'badge-primary text-white',
                        'Produccion' => 'badge-info text-white',
                        default => 'badge-ghost'
                    };
                @endphp
                <x-badge :value="$user->role" class="{{ $roleClasses }} badge-sm font-bold shadow-sm" />
            @endscope

            @scope('cell_status', $user)
                <x-badge value="{{ $user->status }}" class="{{ $user->status == 'Activo' ? 'badge-success' : 'badge-error' }} text-white badge-sm font-bold shadow-sm" />
            @endscope

           @scope('cell_actions', $user)
                <div class="flex items-center gap-1">
                    <x-button
                        icon="o-pencil-square"
                        wire:click="edit({{ $user->id }})"
                        class="btn-sm btn-circle btn-ghost text-gray-400 hover:text-primary"
                        tooltip="Editar"
                    />

                    <x-button
                        icon="{{ $user->status === 'Activo' ? 'o-no-symbol' : 'o-arrow-path' }}"
                        wire:click="toggleStatus({{ $user->id }})"
                        class="btn-sm btn-circle btn-ghost {{ $user->status === 'Activo' ? 'text-error' : 'text-success' }}"
                        tooltip="{{ $user->status === 'Activo' ? 'Desactivar' : 'Activar' }}"
                        wire:confirm="¿Estás seguro de cambiar el estado de este usuario?"
                        spinner
                    />
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-drawer wire:model="drawerModal" title="{{ $isEditMode ? 'Editar Usuario' : 'Nuevo Usuario' }}" right separator with-close-button class="lg:w-1/3 backdrop-blur-sm">
        <x-form wire:submit="save">
            <div class="space-y-6">
                {{-- Datos Personales --}}
                <div class="bg-gray-50 p-5 rounded-2xl border border-gray-100 space-y-4">
                    <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                        <x-icon name="o-user" class="w-4 h-4" /> Datos Personales
                    </h4>
                    <x-input label="Nombre Completo" wire:model="name" icon="o-user" class="bg-white" required />
                    <x-input label="Nombre de Usuario (Login)" wire:model="username" icon="o-at-symbol" class="bg-white" required />
                </div>

                <div class="bg-indigo-50/50 p-5 rounded-2xl border border-indigo-50 space-y-4">
                    <h4 class="text-[10px] font-black text-indigo-400 uppercase tracking-widest flex items-center gap-2">
                        <x-icon name="o-lock-closed" class="w-4 h-4" /> Seguridad y Permisos
                    </h4>

                    <x-input
                        label="Contraseña"
                        wire:model="password"
                        type="{{ $showPassword ? 'text' : 'password' }}"
                        icon="o-key"
                        placeholder="{{ $isEditMode ? 'Dejar en blanco para no cambiar' : 'Mínimo 6 caracteres' }}"
                        class="bg-white"
                    >
                        <x-slot:append>
                            <x-button
                                icon="{{ $showPassword ? 'o-eye-slash' : 'o-eye' }}"
                                class="btn-ghost btn-sm"
                                @click="$wire.showPassword = !$wire.showPassword"
                                type="button"
                            />
                        </x-slot:append>
                </x-input>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-select label="Rol *" wire:model="role" icon="o-shield-check" class="bg-white"
                                  :options="[['id'=>'Administrador', 'name'=>'Administrador'], ['id'=>'Cajero', 'name'=>'Cajero'], ['id'=>'Diseñador', 'name'=>'Diseñador'], ['id'=>'Produccion', 'name'=>'Producción']]" />

                        <x-select label="Estado *" wire:model="status" icon="o-swatch" class="bg-white"
                                  :options="[['id'=>'Activo', 'name'=>'Activo'], ['id'=>'Inactivo', 'name'=>'Inactivo']]" />
                    </div>
                </div>
            </div>

            <x-slot:actions>
                <x-button label="Cancelar" @click="$wire.drawerModal = false" class="btn-ghost" />
                <x-button label="Guardar Usuario" type="submit" icon="o-check-circle" class="btn-primary shadow-sm font-bold" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-drawer>
</div>
