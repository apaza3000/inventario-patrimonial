<?php

namespace App\Http\Controllers;

use App\Models\Bien;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BienController extends Controller
{
    private const RELATIONS = ['ambiente', 'estado', 'condicion'];

    private const DEPENDENCIES = [
        'inventarios' => 'inventarios',
        'muebles' => 'muebles',
        'equipos' => 'equipos',
        'mantenimientos' => 'mantenimientos',
        'movimientos' => 'movimientos',
        'estacion_componentes' => 'componentes de estación',
    ];

    public function index(): JsonResponse
    {
        $bienes = Bien::with(self::RELATIONS)
            ->orderBy('id')
            ->paginate(15);

        return response()->json($bienes);
    }

    public function show(int $id): JsonResponse
    {
        $bien = Bien::with(self::RELATIONS)->find($id);

        if ($bien === null) {
            return $this->notFound();
        }

        return response()->json(['data' => $bien]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $bien = Bien::create($data);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                return $this->duplicateCbi();
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Se creó el bien correctamente.',
            'data' => $bien->load(self::RELATIONS),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $bien = Bien::find($id);

        if ($bien === null) {
            return $this->notFound();
        }

        $data = $this->validatedData($request, $id);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $bien->update($data);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                return $this->duplicateCbi();
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Se actualizó el bien correctamente.',
            'data' => $bien->refresh()->load(self::RELATIONS),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $bien = Bien::find($id);

        if ($bien === null) {
            return $this->notFound();
        }

        foreach (self::DEPENDENCIES as $table => $label) {
            if (DB::table($table)->where('bien_id', $id)->exists()) {
                return $this->inUse($label);
            }
        }

        try {
            $bien->delete();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1451) {
                return $this->inUse('otros registros');
            }

            throw $exception;
        }

        return response()->json(['message' => 'Se eliminó el bien correctamente.']);
    }

    private function validatedData(Request $request, ?int $id = null): array|JsonResponse
    {
        $required = $id === null ? 'required' : 'sometimes';

        $validator = Validator::make($request->all(), [
            'cbi' => ['sometimes', 'nullable', 'string', 'max:25', Rule::unique('bienes', 'cbi')->ignore($id)],
            'descripcion' => [$required, 'string', 'max:150'],
            'estado_id' => ['sometimes', 'nullable', 'integer', 'exists:estados_bien,id'],
            'condicion_id' => ['sometimes', 'nullable', 'integer', 'exists:condiciones_bien,id'],
            'ambiente_id' => ['sometimes', 'nullable', 'integer', 'exists:ambientes,id'],
            'observaciones' => ['sometimes', 'nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
            'fecha_registro' => ['sometimes', 'nullable', 'date'],
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
        return response()->json(['message' => 'Bien no encontrado.'], 404);
    }

    private function duplicateCbi(): JsonResponse
    {
        return response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => ['cbi' => ['El CBI ya está registrado.']],
        ], 422);
    }

    private function inUse(string $relation): JsonResponse
    {
        return response()->json([
            'message' => "No se puede eliminar el bien porque está en uso por $relation.",
        ], 409);
    }
}
