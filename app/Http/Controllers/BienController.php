<?php

namespace App\Http\Controllers;

use App\Models\Bien;
use Illuminate\Http\JsonResponse;

class BienController extends Controller
{
    public function index(): JsonResponse
    {
        $bienes = Bien::with(['ambiente', 'estado', 'condicion'])
            ->orderBy('id')
            ->paginate(15);

        return response()->json($bienes);
    }

    public function show(int $id): JsonResponse
    {
        $bien = Bien::with(['ambiente', 'estado', 'condicion'])->find($id);

        if ($bien === null) {
            return response()->json(['message' => 'Bien no encontrado.'], 404);
        }

        return response()->json(['data' => $bien]);
    }
}
