<?php

namespace App\Http\Controllers;

use App\Models\Bien;
use App\Models\Equipo;
use App\Models\Mueble;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MuebleController extends Controller
{
    private const RELATIONS = ['bien', 'tipoMueble'];

    public function index(): JsonResponse
    {
        return response()->json(
            Mueble::with(self::RELATIONS)->orderBy('bien_id')->paginate(15)
        );
    }

    public function show(int $bien_id): JsonResponse
    {
        $mueble = Mueble::with(self::RELATIONS)->find($bien_id);

        if ($mueble === null) {
            return $this->notFound();
        }

        return response()->json(['data' => $mueble]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $result = DB::transaction(function () use ($data) {
                $bien_id = $data['bien_id'];

                if (Bien::query()->whereKey($bien_id)->lockForUpdate()->first() === null) {
                    return response()->json([
                        'message' => 'Los datos enviados no son válidos.',
                        'errors' => ['bien_id' => ['El bien no existe.']],
                    ], 422);
                }

                if (Equipo::query()->whereKey($bien_id)->exists()) {
                    return response()->json([
                        'message' => 'No se puede registrar el mueble porque el bien ya está clasificado como equipo.',
                    ], 409);
                }

                if (Mueble::query()->whereKey($bien_id)->exists()) {
                    return $this->duplicateBien();
                }

                return Mueble::create($data);
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                return $this->duplicateBien();
            }

            throw $exception;
        }

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json([
            'message' => 'Se creó el mueble correctamente.',
            'data' => $result->load(self::RELATIONS),
        ], 201);
    }

    public function update(Request $request, int $bien_id): JsonResponse
    {
        $mueble = Mueble::find($bien_id);

        if ($mueble === null) {
            return $this->notFound();
        }

        $data = $this->validatedData($request, $bien_id);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $mueble->update($data);

        return response()->json([
            'message' => 'Se actualizó el mueble correctamente.',
            'data' => $mueble->refresh()->load(self::RELATIONS),
        ]);
    }

    public function destroy(int $bien_id): JsonResponse
    {
        $mueble = Mueble::find($bien_id);

        if ($mueble === null) {
            return $this->notFound();
        }

        try {
            $mueble->delete();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1451) {
                return response()->json([
                    'message' => 'No se puede eliminar el mueble porque tiene registros relacionados.',
                ], 409);
            }

            throw $exception;
        }

        return response()->json(['message' => 'Se eliminó el mueble correctamente.']);
    }

    private function validatedData(Request $request, ?int $bien_id = null): array|JsonResponse
    {
        $creating = $bien_id === null;

        $validator = Validator::make($request->all(), [
            'bien_id' => $creating
                ? ['required', 'integer', 'exists:bienes,id', Rule::unique('muebles', 'bien_id')]
                : ['sometimes', 'required', 'integer', Rule::in([$bien_id])],
            'tipo_mueble_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:tipos_mueble,id'],
            'material' => ['sometimes', 'nullable', 'string', 'max:150'],
            'color' => ['sometimes', 'nullable', 'string', 'max:80'],
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
        return response()->json(['message' => 'Mueble no encontrado.'], 404);
    }

    private function duplicateBien(): JsonResponse
    {
        return response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => ['bien_id' => ['El bien ya tiene un mueble registrado.']],
        ], 422);
    }
}
