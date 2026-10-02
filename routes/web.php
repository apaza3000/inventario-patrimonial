<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::redirect('/', '/home');

Route::view('/login', 'auth.login')->middleware('guest')->name('login');

Route::get('/home', DashboardController::class)
    ->middleware(['auth:web', 'usuario.activo'])
    ->name('dashboard');

Route::view('/inventario/bienes', 'inventario.bienes.index')
    ->middleware(['auth:web', 'usuario.activo', 'rol:superadmin'])
    ->name('inventario.bienes');

Route::view('/inventario/estaciones', 'inventario.estaciones.index')
    ->middleware(['auth:web', 'usuario.activo', 'rol:superadmin'])
    ->name('inventario.estaciones');

Route::view('/inventario/toma-inventario', 'inventario.toma-inventario.index')
    ->middleware(['auth:web', 'usuario.activo', 'rol:asistente'])
    ->name('inventario.toma-inventario');

Route::view('/movimientos', 'movimientos.index')
    ->middleware(['auth:web', 'usuario.activo', 'rol:asistente'])
    ->name('movimientos.index');

Route::view('/mantenimientos', 'mantenimientos.index')
    ->middleware(['auth:web', 'usuario.activo', 'rol:coordinador'])
    ->name('mantenimientos.index');
