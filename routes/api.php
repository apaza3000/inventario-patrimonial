<?php

use App\Http\Controllers\AmbienteController;
use App\Http\Controllers\BienController;
use App\Http\Controllers\CondicionBienController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\EquipoComputoController;
use App\Http\Controllers\EstacionComponenteController;
use App\Http\Controllers\EstacionPcController;
use App\Http\Controllers\EspecialidadController;
use App\Http\Controllers\EstadoBienController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\MantenimientoController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\MuebleController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\TipoAmbienteController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/bienes', [BienController::class, 'index']);
Route::get('/bienes/{id}', [BienController::class, 'show'])->where('id', '[0-9]{1,10}');
Route::post('/bienes', [BienController::class, 'store']);
Route::match(['put', 'patch'], '/bienes/{id}', [BienController::class, 'update'])->where('id', '[0-9]{1,10}');
Route::delete('/bienes/{id}', [BienController::class, 'destroy'])->where('id', '[0-9]{1,10}');

Route::apiResource('sedes', SedeController::class)->parameter('sedes', 'id')->whereNumber('id');
Route::apiResource('tipos-ambiente', TipoAmbienteController::class)->parameter('tipos-ambiente', 'id')->whereNumber('id');
Route::apiResource('especialidades', EspecialidadController::class)->parameter('especialidades', 'id')->whereNumber('id');
Route::apiResource('estados-bien', EstadoBienController::class)->parameter('estados-bien', 'id')->whereNumber('id');
Route::apiResource('condiciones-bien', CondicionBienController::class)->parameter('condiciones-bien', 'id')->whereNumber('id');
Route::apiResource('ambientes', AmbienteController::class)->parameter('ambientes', 'id')->whereNumber('id');
Route::apiResource('muebles', MuebleController::class)->parameter('muebles', 'bien_id')->where(['bien_id' => '[0-9]{1,10}']);
Route::apiResource('equipos', EquipoController::class)->parameter('equipos', 'bien_id')->where(['bien_id' => '[0-9]{1,10}']);
Route::apiResource('equipos-computo', EquipoComputoController::class)->parameter('equipos-computo', 'bien_id')->where(['bien_id' => '[0-9]{1,10}']);
Route::apiResource('monitores', MonitorController::class)->parameter('monitores', 'bien_id')->where(['bien_id' => '[0-9]{1,10}']);

Route::get('/inventarios', [InventarioController::class, 'index']);
Route::get('/inventarios/{bien_id}/{anio}', [InventarioController::class, 'show'])
    ->where(['bien_id' => '[0-9]{1,10}', 'anio' => '-?[0-9]{1,10}']);
Route::post('/inventarios', [InventarioController::class, 'store']);
Route::match(['put', 'patch'], '/inventarios/{bien_id}/{anio}', [InventarioController::class, 'update'])
    ->where(['bien_id' => '[0-9]{1,10}', 'anio' => '-?[0-9]{1,10}']);
Route::delete('/inventarios/{bien_id}/{anio}', [InventarioController::class, 'destroy'])
    ->where(['bien_id' => '[0-9]{1,10}', 'anio' => '-?[0-9]{1,10}']);

Route::apiResource('mantenimientos', MantenimientoController::class)
    ->parameter('mantenimientos', 'id')
    ->where(['id' => '[0-9]{1,10}']);

Route::get('/movimientos', [MovimientoController::class, 'index']);
Route::get('/movimientos/{id}', [MovimientoController::class, 'show'])->where('id', '[0-9]{1,10}');
Route::post('/movimientos', [MovimientoController::class, 'store']);

Route::apiResource('estaciones-pc', EstacionPcController::class)
    ->parameter('estaciones-pc', 'id')
    ->where(['id' => '[0-9]{1,10}']);
Route::apiResource('estacion-componentes', EstacionComponenteController::class)
    ->except('destroy')
    ->parameter('estacion-componentes', 'id')
    ->where(['id' => '[0-9]{1,10}']);

Route::apiResource('roles', RolController::class)
    ->parameter('roles', 'id')
    ->where(['id' => '[0-9]{1,10}']);
Route::apiResource('usuarios', UsuarioController::class)
    ->except('destroy')
    ->parameter('usuarios', 'id')
    ->where(['id' => '[0-9]{1,10}']);
