<?php

use Illuminate\Support\Facades\Route;

// RUTAS PRINCIPALES
Route::livewire('/', 'pages::menu');
Route::livewire('/login', 'pages::login');
Route::livewire('/ventas', 'pages::ventas');
Route::livewire('/devoluciones', 'pages::devoluciones');
Route::livewire('/credito', 'pages::credito');
Route::livewire('/arqueo', 'pages::arqueo');
Route::livewire('/compras', 'pages::compras');
Route::livewire('/clientes', 'pages::clientes');
Route::livewire('/productos', 'pages::productos');
Route::livewire('/proveedores', 'pages::proveedores');
Route::livewire('/usuarios', 'pages::usuarios');
Route::livewire('/mantenimiento', 'pages::mantenimiento');
Route::livewire('/Reportes', 'pages::Reportes');
Route::livewire('/acercade', 'pages::acercade');
