<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NodoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\TransaccionController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'formulario'])->name('login');
Route::post('/login', [AuthController::class, 'iniciar'])->middleware('throttle:10,1');

Route::middleware('supabase.auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'cerrar'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    // Sucursales y cajeros
    Route::get('/nodos', [NodoController::class, 'index'])->name('nodos.index');
    Route::get('/nodos/crear', [NodoController::class, 'create'])->name('nodos.create');
    Route::post('/nodos', [NodoController::class, 'store'])->name('nodos.store');
    Route::get('/nodos/{id}/editar', [NodoController::class, 'edit'])->whereUuid('id')->name('nodos.edit');
    Route::put('/nodos/{id}', [NodoController::class, 'update'])->whereUuid('id')->name('nodos.update');
    Route::post('/nodos/{id}/api-key', [NodoController::class, 'regenerarApiKey'])->whereUuid('id')->name('nodos.api-key');
    Route::post('/nodos/{id}/estado', [NodoController::class, 'cambiarEstado'])->whereUuid('id')->name('nodos.estado');

    // Historial global
    Route::get('/transacciones', [TransaccionController::class, 'index'])->name('transacciones.index');
    Route::get('/transacciones/csv', [TransaccionController::class, 'csv'])->name('transacciones.csv');

    // Reportes
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/reportes/csv', [ReporteController::class, 'csv'])->name('reportes.csv');
});
