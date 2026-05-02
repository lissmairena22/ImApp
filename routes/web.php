<?php

use Illuminate\Support\Facades\Route;

// PÁGINA PRINCIPAL
Route::view('/', 'pages.menu');

Route::view('/login', 'pages.login');

// MÓDULO DE VENTAS
Route::view('/ventas', 'pages.ventas');
Route::view('/pedidos', 'pages.pedidos');

// MÓDULOS COMERCIALES
Route::view('/compras', 'pages.compras');
Route::view('/devoluciones', 'pages.devoluciones');
Route::view('/credito', 'pages.credito');
Route::view('/arqueo', 'pages.arqueo');

// CATÁLOGOS
Route::view('/clientes', 'pages.clientes');
Route::view('/productos', 'pages.productos');
Route::view('/proveedores', 'pages.proveedores');
Route::view('/usuarios', 'pages.usuarios');

// SISTEMA
Route::view('/mantenimiento', 'pages.mantenimiento');
Route::view('/reportes', 'pages.reportes');
Route::view('/acercade', 'pages.acercade');
