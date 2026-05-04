<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : 'Imprenta América' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-200/50">

    <x-nav sticky class="lg:hidden bg-base-100 border-b border-base-300">
        <x-slot:brand>
            <div class="flex items-center gap-2 italic font-black text-primary">
                <x-icon name="o-printer" class="w-6 h-6" />
                <span>Imprenta <span class="text-base-content">América</span></span>
            </div>
        </x-slot:brand>
        <x-slot:actions>
            <label for="main-drawer" class="lg:hidden me-3">
                <x-icon name="o-bars-3" class="cursor-pointer" />
            </label>
        </x-slot:actions>
    </x-nav>

    <x-main>
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 border-r border-base-300 shadow-xl">

            <div class="px-6 pt-6 pb-4">
                <div class="flex items-center gap-3 italic font-black text-2xl text-primary">
                    <x-icon name="o-printer" class="w-10 h-10" />
                    <span class="hidden-when-collapsed">América</span>
                </div>
                <p class="text-[10px] uppercase tracking-widest font-bold text-gray-400 mt-1 hidden-when-collapsed">Sistema de Gestión</p>
            </div>

            <x-menu activate-by-route class="px-2">

                @if($user = auth()->user())
                    <x-menu-separator />
                    <x-list-item :item="$user" value="username" sub-value="role" no-separator no-hover class="mb-4 bg-primary/5 rounded-xl border border-primary/10">
                        <x-slot:avatar>
                            <div class="bg-primary text-primary-content rounded-lg w-10 h-10 flex items-center justify-center font-bold">
                                {{ strtoupper(substr($user->username, 0, 1)) }}
                            </div>
                        </x-slot:avatar>
                        <x-slot:actions>
                            <x-button icon="o-power" class="btn-circle btn-ghost btn-xs text-error" tooltip="Cerrar Sesión" link="/logout" no-wire-navigate />
                        </x-slot:actions>
                    </x-list-item>
                @endif

                <x-menu-item title="Dashboard" icon="o-home" link="/" />

                <x-menu-sub title="Producción" icon="o-briefcase">
                    <x-menu-item title="Órdenes de Trabajo" icon="o-clipboard-document-list" link="/ordenes" />
                    <x-menu-item title="Seguimiento" icon="o-arrow-path" link="/produccion" />
                </x-menu-sub>

                <x-menu-sub title="Comercial" icon="o-banknotes">
                    <x-menu-item title="Nueva Venta" icon="o-shopping-cart" link="/ventas" />
                    <x-menu-item title="Devoluciones" icon="o-arrow-path-rounded-square" link="/devoluciones" />
                    <x-menu-item title="Clientes" icon="o-user-group" link="/clientes" />
                    <x-menu-item title="Reporte de Ventas" icon="o-document-chart-bar" link="/reportes-ventas" />
                </x-menu-sub>

                <x-menu-sub title="Inventario" icon="o-archive-box">
                    <x-menu-item title="Productos/Materiales" icon="o-square-3-stack-3d" link="/productos" />
                    <x-menu-item title="Compras/Insumos" icon="o-truck" link="/compras" />
                </x-menu-sub>

                <x-menu-separator />
                <x-menu-sub title="Configuración" icon="o-cog-8-tooth">
                    <x-menu-item title="Usuarios" icon="o-users" link="/usuarios" />
                    <x-menu-item title="Cajas y Turnos" icon="o-calculator" link="/cajas" />
                </x-menu-sub>

            </x-menu>
        </x-slot:sidebar>

        <x-slot:content class="bg-base-200/50">
            <div class="max-w-7xl mx-auto">
                {{ $slot }}
            </div>
        </x-slot:content>
    </x-main>

    {{-- Notificaciones --}}
    <x-toast />
</body>
</html>
