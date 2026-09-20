<?php

namespace App\Http\Controllers;

use App\Models\Ambiente;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AmbienteController extends Controller
{
    private const RELATIONS = ['sede', 'tipoAmbiente', 'especialidad'];

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Ambiente::with(self::RELATIONS)->orderBy('id')->get(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $ambiente = Ambiente::with(self::RELATIONS)->find($id);

        if ($ambiente === null) {
            return $this->notFound();
        }

        return response()->json(['data' => $ambiente]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $ambiente = Ambiente::create($data);

        return response()->json([
            'message' => 'Se creó el ambiente correctamente.',
            'data' => $ambiente->load(self::RELATIONS),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ambiente = Ambiente::find($id);

        if ($ambiente === null) {
            return $this->notFound();
        }

        $data = $this->validatedData($request, true);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $ambiente->update($data);

        return response()->json([
            'message' => 'Se actualizó el ambiente correctamente.',
            'data' => $ambiente->refresh()->load(self::RELATIONS),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $ambiente = Ambiente::find($id);

        if ($ambiente === null) {
            return $this->notFound();
        }

        if ($ambiente->bienes()->exists()) {
            return $this->inUse('bienes');
        }

        if (DB::table('movimientos')
            ->where('ambiente_origen_id', $id)
            ->orWhere('ambiente_destino_id', $id)
            ->exists()) {
            return $this->inUse('movimientos');
        }

        try {
            $ambiente->delete();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1451) {
                return $this->inUse('bienes o movimientos');
            }

            throw $exception;
        }

        return response()->json(['message' => 'Se eliminó el ambiente correctamente.']);
    }

    private function validatedData(Request $request, bool $updating = false): array|JsonResponse
    {
        $required = $updating ? 'sometimes' : 'required';
        $measure = ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'];

        $validator = Validator::make($request->all(), [
            'nombre' => [$required, 'string', 'max:100'],
            'sede_id' => [$required, 'integer', 'exists:sedes,id'],
            'tipo_ambiente_id' => ['sometimes', 'nullable', 'integer', 'exists:tipos_ambiente,id'],
            'especialidad_id' => ['sometimes', 'nullable', 'integer', 'exists:especialidades,id'],
            'area' => $measure,
            'ancho' => $measure,
            'largo' => $measure,
            'activo' => ['sometimes', 'boolean'],
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
        return response()->json(['message' => 'El ambiente no existe.'], 404);
    }

    private function inUse(string $relation): JsonResponse
    {
        return response()->json([
            'message' => "No se puede eliminar el ambiente porque está en uso por $relation.",
        ], 409);
    }
}
