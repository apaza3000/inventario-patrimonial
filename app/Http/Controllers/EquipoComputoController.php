<?php

namespace App\Http\Controllers;

use App\Models\EquipoComputo;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EquipoComputoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            EquipoComputo::with('equipo')->orderBy('bien_id')->paginate(15)
        );
    }

    public function show(int $bien_id): JsonResponse
    {
        $equipoComputo = EquipoComputo::with('equipo')->find($bien_id);

        if ($equipoComputo === null) {
            return $this->notFound();
        }

        return response()->json(['data' => $equipoComputo]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $equipoComputo = EquipoComputo::create($data);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                return $this->duplicateBien();
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Se creó el equipo de cómputo correctamente.',
            'data' => $equipoComputo->load('equipo'),
        ], 201);
    }

    public function update(Request $request, int $bien_id): JsonResponse
    {
        $equipoComputo = EquipoComputo::find($bien_id);

        if ($equipoComputo === null) {
            return $this->notFound();
        }

        $data = $this->validatedData($request, $bien_id);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $equipoComputo->update($data);

        return response()->json([
            'message' => 'Se actualizó el equipo de cómputo correctamente.',
            'data' => $equipoComputo->refresh()->load('equipo'),
        ]);
    }

    public function destroy(int $bien_id): JsonResponse
    {
        $equipoComputo = EquipoComputo::find($bien_id);

        if ($equipoComputo === null) {
            return $this->notFound();
        }

        $equipoComputo->delete();

        return response()->json(['message' => 'Se eliminó el equipo de cómputo correctamente.']);
    }

    private function validatedData(Request $request, ?int $bien_id = null): array|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bien_id' => $bien_id === null
                ? ['required', 'integer', 'exists:equipos,bien_id', Rule::unique('equipos_computo', 'bien_id')]
                : ['sometimes', 'required', 'integer', Rule::in([$bien_id])],
            'procesador' => ['sometimes', 'nullable', 'string', 'max:100'],
            'ram' => ['sometimes', 'nullable', 'string', 'max:100'],
            'almacenamiento' => ['sometimes', 'nullable', 'string', 'max:150'],
            'detalle_adicional' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        return $validator->validated();
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Equipo de cómputo no encontrado.'], 404);
    }

    private function duplicateBien(): JsonResponse
    {
        return response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => ['bien_id' => ['El equipo ya tiene un registro de cómputo.']],
        ], 422);
    }
}
