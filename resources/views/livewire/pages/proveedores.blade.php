<?php

use Livewire\Volt\Component;

new class extends Component
{
    
    public bool $modalAbrir = false;

   
    public string $nombre    = '';
    public string $ruc       = '';
    public string $direccion = '';
    public string $telefono  = '';

    
    public string $busqueda = '';

    
    public function proveedores(): array
    {
        return [];
    }

    
    public function headers(): array
    {
        return [
            ['key' => 'nombre',    'label' => 'Nombre'],
            ['key' => 'ruc',       'label' => 'Código RUC'],
            ['key' => 'direccion', 'label' => 'Dirección'],
            ['key' => 'telefono',  'label' => 'Teléfono'],
            ['key' => 'activo',    'label' => 'Estado'],
            ['key' => 'acciones',  'label' => 'Acciones', 'sortable' => false],
        ];
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
            wire:model.live="busqueda"
            placeholder="Buscar por nombre, RUC o teléfono..."
            icon="o-magnifying-glass"
            clearable
        />
    </div>

   
    <x-card shadow>
        <x-table :headers="$this->headers()" :rows="$this->proveedores()" striped>

            @scope('cell_activo', $row)
                @if($row['activo'])
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
        </div>

        <x-slot:actions>
            <x-button label="Cancelar" wire:click="$set('modalAbrir', false)" />
            <x-button label="Registrar" icon="o-check" class="btn-primary" />
        </x-slot:actions>

    </x-modal>

</div>