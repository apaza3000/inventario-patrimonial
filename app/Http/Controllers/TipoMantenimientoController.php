<?php

namespace App\Http\Controllers;

use App\Models\TipoMantenimiento;
use Illuminate\Http\JsonResponse;

class TipoMantenimientoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => TipoMantenimiento::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'descripcion']),
        ]);
    }
}
