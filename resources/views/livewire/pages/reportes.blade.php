<?php

use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public array $operationalReports = [
        [
            'title' => 'Reporte de Compra',
            'description' => 'Consulta y genera el resumen de compras e insumos registrados.',
            'icon' => 'o-truck',
            'color' => 'border-primary',
            'type' => 'compra',
        ],
        [
            'title' => 'Reporte de Venta',
            'description' => 'Genera el consolidado de ventas realizadas en el sistema.',
            'icon' => 'o-shopping-cart',
            'color' => 'border-success',
            'type' => 'venta',
        ],
        [
            'title' => 'Reporte de Pedidos',
            'description' => 'Revisa las ordenes de trabajo y pedidos de produccion.',
            'icon' => 'o-clipboard-document-list',
            'color' => 'border-secondary',
            'type' => 'pedidos',
        ],
        [
            'title' => 'Reporte de Devoluciones',
            'description' => 'Genera el historial de devoluciones realizadas por clientes.',
            'icon' => 'o-arrow-path-rounded-square',
            'color' => 'border-warning',
            'type' => 'devoluciones',
        ],
        [
            'title' => 'Reporte de Arqueo de Caja',
            'description' => 'Obtiene el resumen de caja, movimientos y cierre de turno.',
            'icon' => 'o-calculator',
            'color' => 'border-error',
            'type' => 'arqueo de caja',
        ],
    ];

    public array $registryReports = [
        [
            'title' => 'Lista de Productos',
            'description' => 'Catalogo de productos, materiales e inventario disponible.',
            'icon' => 'o-square-3-stack-3d',
            'route' => 'productos',
        ],
        [
            'title' => 'Lista de Clientes',
            'description' => 'Registro completo de clientes activos en la imprenta.',
            'icon' => 'o-user-group',
            'route' => 'clientes',
        ],
        [
            'title' => 'Lista de Usuarios',
            'description' => 'Usuarios del sistema, roles y datos de acceso.',
            'icon' => 'o-users',
            'route' => 'usuarios',
        ],
        [
            'title' => 'Lista de Proveedores',
            'description' => 'Directorio de proveedores e informacion comercial.',
            'icon' => 'o-building-office-2',
            'route' => 'proveedores',
        ],
    ];

    public function generate(string $type): void
    {
        $this->info('Preparando reporte de ' . ucfirst($type) . '.', position: 'toast-top toast-center');
    }
};
?>

<div>
    <x-header title="Reportes del Sistema" subtitle="Generacion de reportes operativos y registros maestros" separator>
        <x-slot:actions>
            <x-button label="Panel de Control" icon="o-home" link="/" class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2">
            <x-card title="Reportes Operativos" subtitle="Selecciona el modulo que deseas generar" icon="o-document-chart-bar" shadow class="bg-base-100">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach($operationalReports as $report)
                        <div class="border-l-4 {{ $report['color'] }} bg-base-200/40 rounded-lg p-4 flex flex-col min-h-52">
                            <div class="flex items-start gap-3">
                                <div class="w-11 h-11 rounded-lg bg-base-100 border border-base-300 flex items-center justify-center shrink-0">
                                    <x-icon :name="$report['icon']" class="w-6 h-6 text-primary" />
                                </div>

                                <div>
                                    <h3 class="font-bold text-base-content leading-tight">{{ $report['title'] }}</h3>
                                    <p class="text-sm text-gray-500 mt-1">{{ $report['description'] }}</p>
                                </div>
                            </div>

                            <div class="mt-auto pt-5">
                                <x-button
                                    label="Generar Reporte"
                                    icon="o-document-arrow-down"
                                    wire:click="generate('{{ $report['type'] }}')"
                                    spinner="generate"
                                    class="btn-primary w-full"
                                />
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>

        <div>
            <x-card title="Centro de Reportes" subtitle="Accesos rapidos del sistema" icon="o-folder-open" shadow class="bg-base-100 border-t-4 border-primary h-full">
                <div class="space-y-4">
                    <div class="stats shadow-sm bg-base-200/60 w-full">
                        <div class="stat">
                            <div class="stat-title text-xs uppercase font-bold text-gray-500">Reportes operativos</div>
                            <div class="stat-value text-3xl text-primary">{{ count($operationalReports) }}</div>
                        </div>
                    </div>

                    <div class="stats shadow-sm bg-base-200/60 w-full">
                        <div class="stat">
                            <div class="stat-title text-xs uppercase font-bold text-gray-500">Registros disponibles</div>
                            <div class="stat-value text-3xl text-secondary">{{ count($registryReports) }}</div>
                        </div>
                    </div>

                    <x-alert
                        title="Listo para generar"
                        description="Usa los botones de cada tarjeta para preparar el reporte correspondiente."
                        icon="o-information-circle"
                        class="alert-info"
                    />
                </div>
            </x-card>
        </div>
    </div>

    <x-card title="Reportes de Registros" subtitle="Listados maestros para consulta y control" icon="o-list-bullet" shadow class="bg-base-100">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach($registryReports as $report)
                <div class="bg-base-200/40 border border-base-300 rounded-lg p-4 flex flex-col min-h-48">
                    <div class="flex items-start gap-3">
                        <div class="w-11 h-11 rounded-lg bg-base-100 border border-base-300 flex items-center justify-center shrink-0">
                            <x-icon :name="$report['icon']" class="w-6 h-6 text-secondary" />
                        </div>

                        <div>
                            <h3 class="font-bold text-base-content leading-tight">{{ $report['title'] }}</h3>
                            <p class="text-sm text-gray-500 mt-1">{{ $report['description'] }}</p>
                        </div>
                    </div>

                    <div class="mt-auto pt-5">
                        <x-button
                            label="Generar Reporte"
                            icon="o-document-arrow-down"
                            wire:click="generate('{{ $report['route'] }}')"
                            spinner="generate"
                            class="btn-outline btn-secondary w-full"
                        />
                    </div>
                </div>
            @endforeach
        </div>
    </x-card>
</div>
