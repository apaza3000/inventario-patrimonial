<?php

use App\Http\Controllers\AmbienteController;
use App\Http\Controllers\BienController;
use App\Http\Controllers\CondicionBienController;
use App\Http\Controllers\EspecialidadController;
use App\Http\Controllers\EstadoBienController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\TipoAmbienteController;
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
