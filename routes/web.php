<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/login', 'auth.login')->name('login')->middleware('guest');
Volt::route('/restablecer-contrasena', 'auth.restablecercont')->name('restablecercont')->middleware('guest');
Volt::route('/restablecer-contrasena/{token}', 'auth.resetpassword')->name('restablecercont.reset')->middleware('guest');
Route::middleware(['auth'])->group(function () {
    Volt::route('/', 'pages.menu')->name('dashboard');

    Route::middleware(['cash.open'])->group(function () {
        Volt::route('/ventas', 'pages.ventas')->name('ventas');
        Volt::route('/pedidos', 'pages.pedidos')->name('pedidos');
        Volt::route('/compras', 'pages.compras')->name('compras');
        Volt::route('/salidas-inventario', 'pages.salidas-inventario')->name('salidas-inventario');
        Volt::route('/devoluciones', 'pages.devoluciones')->name('devoluciones');
        Volt::route('/credito', 'pages.credito')->name('credito');
        Volt::route('/arqueo', 'pages.arqueo')->name('arqueo');
        Volt::route('/egresos', 'pages.egresos')->name('egresos');

        Volt::route('/clientes', 'pages.clientes')->name('clientes');
        Volt::route('/productos', 'pages.productos')->name('productos');
        Volt::route('/proveedores', 'pages.proveedores')->name('proveedores');
        Volt::route('/usuarios', 'pages.usuarios')->name('usuarios');

        Volt::route('/mantenimiento', 'pages.mantenimiento')->name('mantenimiento');
        Volt::route('/reportes', 'pages.reportes')->name('reportes');
        Volt::route('/acercade', 'pages.acercade')->name('acercade');
    });

    Route::get('/logout', function () {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/login');
    })->name('logout');
});
