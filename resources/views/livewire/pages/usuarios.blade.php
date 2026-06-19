<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\User;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Hash;

new class extends Component {
    use WithPagination;
    use Toast;

    public string $search = '';
    public bool $drawerModal = false;
    public bool $isEditMode = false;

    // Propiedades del Formulario
    public $user_id, $name, $username, $email, $password, $role, $status = 'Activo';

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
        $this->email = $user->email;
        $this->role = $user->role;
        $this->status = $user->status;
        $this->password = ''; // Vacío por seguridad

        $this->isEditMode = true;
        $this->drawerModal = true;
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username,' . $this->user_id,
            'email' => 'nullable|email|unique:users,email,' . $this->user_id,
            'role' => 'required|in:Administrador,Cajero,Diseñador,Produccion',
            'status' => 'required|in:Activo,Inactivo',
        ];

        // Password solo requerido si es nuevo o si se escribió algo
        if (!$this->isEditMode || !empty($this->password)) {
            $rules['password'] = 'required|min:6';
        }

        $this->validate($rules);

        $data = [
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
        ];

        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        User::updateOrCreate(['id' => $this->user_id], $data);

        $this->drawerModal = false;
        $this->success($this->isEditMode ? 'Usuario actualizado' : 'Usuario creado');
    }

    public function resetForm()
    {
        $this->reset(['user_id', 'name', 'username', 'email', 'password', 'role']);
        $this->status = 'Activo';
    }

    public function with(): array
    {
        return [
            'users' => User::query()
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->paginate(10),
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

<div>
    <x-header title="Usuarios del Sistema" subtitle="Control de acceso">
        <x-slot:middle class="justify-end!">
            <x-input icon="o-magnifying-glass" placeholder="Buscar usuario..." wire:model.live.debounce.500ms="search" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-user-plus" label="Nuevo Usuario" class="btn-primary" wire:click="create" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :headers="$headers" :rows="$users" with-pagination>
            @scope('cell_id', $user)
                <span class="text-xs text-gray-400">#{{ $user->id }}</span>
            @endscope

            @scope('cell_username', $user)
                <div class="flex items-center gap-3">
                    <div class="bg-secondary/10 p-2 rounded-full">
                        <x-icon name="o-user" class="w-5 h-5 text-secondary" />
                    </div>
                    <div>
                        <div class="font-bold">{{ $user->username }}</div>
                        <div class="text-xs text-gray-500">{{ $user->name }}</div>
                    </div>
                </div>
            @endscope

            @scope('cell_role', $user)
                <x-badge :value="$user->role" class="badge-outline badge-primary" />
            @endscope

            @scope('cell_status', $user)
                <x-badge value="{{ $user->status }}" class="{{ $user->status == 'Activo' ? 'badge-success' : 'badge-error' }}" />
            @endscope

            @scope('cell_actions', $user)
                <x-button icon="o-pencil-square" wire:click="edit({{ $user->id }})" class="btn-sm btn-ghost text-info" />
            @endscope
        </x-table>
    </x-card>

    <x-drawer wire:model="drawerModal" title="Configuración de Usuario" right separator with-close-button class="lg:w-1/3">
        <x-form wire:submit="save">
            <x-input label="Nombre Completo" wire:model="name" icon="o-user" />
            <x-input label="Nombre de Usuario (Login)" wire:model="username" icon="o-at-symbol" />
            <x-input label="Correo electrónico" wire:model="email" icon="o-envelope" placeholder="usuario@correo.com" />

            <x-input label="Contraseña" wire:model="password" type="password" icon="o-key"
                     placeholder="{{ $isEditMode ? 'Dejar en blanco para no cambiar' : 'Mínimo 6 caracteres' }}" />

            <div class="grid grid-cols-2 gap-4">
                <x-select label="Rol" wire:model="role" icon="o-shield-check"
                          :options="[
                              ['id'=>'Administrador', 'name'=>'Administrador'],
                              ['id'=>'Cajero', 'name'=>'Cajero'],
                              ['id'=>'Diseñador', 'name'=>'Diseñador'],
                              ['id'=>'Produccion', 'name'=>'Producción']
                          ]" />

                <x-select label="Estado" wire:model="status" icon="o-swatch"
                          :options="[['id'=>'Activo', 'name'=>'Activo'], ['id'=>'Inactivo', 'name'=>'Inactivo']]" />
            </div>

            <x-slot:actions>
                <x-button label="Cancelar" @click="$wire.drawerModal = false" class="btn-ghost" />
                <x-button label="Guardar" type="submit" icon="o-check" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-drawer>
</div>
