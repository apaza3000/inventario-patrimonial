<?php

namespace App\Http\Controllers;

use App\Models\Mantenimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MantenimientoController extends Controller
{
    private const RELATIONS = ['bien', 'tipoMantenimiento', 'tecnico'];

    public function index(): JsonResponse
    {
        return response()->json(
            Mantenimiento::with(self::RELATIONS)->orderBy('id')->paginate(15)
        );
    }

    public function show(int $id): JsonResponse
    {
        $mantenimiento = Mantenimiento::with(self::RELATIONS)->find($id);

        if ($mantenimiento === null) {
            return $this->notFound();
        }

        return response()->json(['data' => $mantenimiento]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $mantenimiento = Mantenimiento::create($data);

        return response()->json([
            'message' => 'Se creó el mantenimiento correctamente.',
            'data' => $mantenimiento->load(self::RELATIONS),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $mantenimiento = Mantenimiento::find($id);

        if ($mantenimiento === null) {
            return $this->notFound();
        }

        $data = $this->validatedData($request, true);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $mantenimiento->update($data);

        return response()->json([
            'message' => 'Se actualizó el mantenimiento correctamente.',
            'data' => $mantenimiento->refresh()->load(self::RELATIONS),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $mantenimiento = Mantenimiento::find($id);

        if ($mantenimiento === null) {
            return $this->notFound();
        }

        $mantenimiento->delete();

        return response()->json(['message' => 'Se eliminó el mantenimiento correctamente.']);
    }

    private function validatedData(Request $request, bool $updating = false): array|JsonResponse
    {
        $required = $updating ? 'sometimes' : 'required';

        $validator = Validator::make($request->all(), [
            'bien_id' => [$required, 'required', 'integer', 'between:1,2147483647', 'exists:bienes,id'],
            'tipo_mantenimiento_id' => [$required, 'required', 'integer', 'between:1,2147483647', 'exists:tipos_mantenimiento,id'],
            'tecnico_id' => ['sometimes', 'nullable', 'integer', 'between:1,2147483647', 'exists:usuarios,id'],
            'fecha_mantenimiento' => [$required, 'required', 'date_format:Y-m-d'],
            'descripcion' => [$required, 'required', 'string', 'max:255'],
            'diagnostico' => ['sometimes', 'nullable', 'string', 'max:255'],
            'trabajo_realizado' => ['sometimes', 'nullable', 'string', 'max:255'],
            'observaciones' => ['sometimes', 'nullable', 'string', 'max:255'],
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
        return response()->json(['message' => 'Mantenimiento no encontrado.'], 404);
    }
}
