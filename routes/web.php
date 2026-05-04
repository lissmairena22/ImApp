<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

// Ruta de Login (Ya estaba bien, pero la mantenemos)
Volt::route('/login', 'auth.login')->name('login')->middleware('guest');

Route::middleware(['auth'])->group(function () {

    // Cambiamos Route::view por Volt::route en todas las páginas
    Volt::route('/', 'pages.menu')->name('dashboard');

    // Procesos de Negocio
    Volt::route('/ventas', 'pages.ventas')->name('ventas');
    Volt::route('/pedidos', 'pages.pedidos')->name('pedidos');
    Volt::route('/compras', 'pages.compras')->name('compras');
    Volt::route('/devoluciones', 'pages.devoluciones')->name('devoluciones');
    Volt::route('/credito', 'pages.credito')->name('credito');
    Volt::route('/arqueo', 'pages.arqueo')->name('arqueo');

    // Catálogos / Mantenimiento
    Volt::route('/clientes', 'pages.clientes')->name('clientes');
    Volt::route('/productos', 'pages.productos')->name('productos');
    Volt::route('/proveedores', 'pages.proveedores')->name('proveedores');
    Volt::route('/usuarios', 'pages.usuarios')->name('usuarios');

    // Otros
    Volt::route('/mantenimiento', 'pages.mantenimiento')->name('mantenimiento');
    Volt::route('/reportes', 'pages.reportes')->name('reportes');
    Volt::route('/acercade', 'pages.acercade')->name('acercade');

    Route::get('/logout', function () {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/login');
    })->name('logout');

});
