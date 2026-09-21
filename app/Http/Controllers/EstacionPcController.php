<?php

namespace App\Http\Controllers;

use App\Models\EstacionPc;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EstacionPcController extends Controller
{
    private const RELATIONS = ['componentes.bien'];

    public function index(): JsonResponse
    {
        return response()->json(
            EstacionPc::with(self::RELATIONS)->orderBy('id')->paginate(15)
        );
    }

    public function show(int $id): JsonResponse
    {
        $estacion = EstacionPc::with(self::RELATIONS)->find($id);

        if ($estacion === null) {
            return $this->notFound();
        }

        return response()->json(['data' => $estacion]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $estacion = EstacionPc::create($data);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                return $this->duplicateCodigo();
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Se creó la estación correctamente.',
            'data' => $estacion->refresh()->load(self::RELATIONS),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $estacion = EstacionPc::find($id);

        if ($estacion === null) {
            return $this->notFound();
        }

        $data = $this->validatedData($request, $id);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $estacion->update($data);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                return $this->duplicateCodigo();
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Se actualizó la estación correctamente.',
            'data' => $estacion->refresh()->load(self::RELATIONS),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $estacion = EstacionPc::find($id);

        if ($estacion === null) {
            return $this->notFound();
        }

        if ($estacion->componentes()->exists()) {
            return $this->inUse();
        }

        try {
            $estacion->delete();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1451) {
                return $this->inUse();
            }

            throw $exception;
        }

        return response()->json(['message' => 'Se eliminó la estación correctamente.']);
    }

    private function validatedData(Request $request, ?int $id = null): array|JsonResponse
    {
        $required = $id === null ? ['required'] : ['sometimes', 'required'];

        $validator = Validator::make($request->all(), [
            'codigo' => [...$required, 'string', 'max:20', Rule::unique('estaciones_pc', 'codigo')->ignore($id)],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:100'],
            'activo' => ['sometimes', 'required', 'boolean'],
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
        return response()->json(['message' => 'Estación no encontrada.'], 404);
    }

    private function duplicateCodigo(): JsonResponse
    {
        return response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => ['codigo' => ['El código ya está registrado.']],
        ], 422);
    }

    private function inUse(): JsonResponse
    {
        return response()->json([
            'message' => 'No se puede eliminar la estación porque tiene componentes registrados en su historial.',
        ], 409);
    }
}
