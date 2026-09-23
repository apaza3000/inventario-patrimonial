<?php

namespace App\Http\Controllers;

use App\Models\Bien;
use App\Models\Equipo;
use App\Models\Mueble;
use App\Services\AlcanceDatosService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EquipoController extends Controller
{
    private const RELATIONS = ['bien.ambiente.especialidad', 'tipoEquipo', 'marca', 'equipoComputo', 'monitor'];

    public function __construct(private readonly AlcanceDatosService $alcance)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->alcance->equipos($request->user('web'))
                ->with(self::RELATIONS)
                ->orderBy('bien_id')
                ->paginate(15)
        );
    }

    public function show(Request $request, int $bien_id): JsonResponse
    {
        $equipo = $this->alcance->equipos($request->user('web'))
            ->with(self::RELATIONS)
            ->find($bien_id);

        if ($equipo === null) {
            return $this->notFound();
        }

        return response()->json(['data' => $equipo]);
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

                if (Mueble::query()->whereKey($bien_id)->exists()) {
                    return response()->json([
                        'message' => 'No se puede registrar el equipo porque el bien ya está clasificado como mueble.',
                    ], 409);
                }

                if (Equipo::query()->whereKey($bien_id)->exists()) {
                    return $this->duplicateBien();
                }

                return Equipo::create($data);
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
            'message' => 'Se creó el equipo correctamente.',
            'data' => $result->load(self::RELATIONS),
        ], 201);
    }

    public function update(Request $request, int $bien_id): JsonResponse
    {
        $equipo = Equipo::find($bien_id);

        if ($equipo === null) {
            return $this->notFound();
        }

        $data = $this->validatedData($request, $bien_id);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $equipo->update($data);

        return response()->json([
            'message' => 'Se actualizó el equipo correctamente.',
            'data' => $equipo->refresh()->load(self::RELATIONS),
        ]);
    }

    public function destroy(int $bien_id): JsonResponse
    {
        $equipo = Equipo::find($bien_id);

        if ($equipo === null) {
            return $this->notFound();
        }

        if ($equipo->equipoComputo()->exists()) {
            return $this->inUse('equipos de cómputo');
        }

        if ($equipo->monitor()->exists()) {
            return $this->inUse('monitores');
        }

        try {
            $equipo->delete();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1451) {
                return $this->inUse('registros relacionados');
            }

            throw $exception;
        }

        return response()->json(['message' => 'Se eliminó el equipo correctamente.']);
    }

    private function validatedData(Request $request, ?int $bien_id = null): array|JsonResponse
    {
        $creating = $bien_id === null;

        $validator = Validator::make($request->all(), [
            'bien_id' => $creating
                ? ['required', 'integer', 'exists:bienes,id', Rule::unique('equipos', 'bien_id')]
                : ['sometimes', 'required', 'integer', Rule::in([$bien_id])],
            'tipo_equipo_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:tipos_equipo,id'],
            'marca_id' => ['sometimes', 'nullable', 'integer', 'exists:marcas,id'],
            'modelo' => ['sometimes', 'nullable', 'string', 'max:100'],
            'serie' => ['sometimes', 'nullable', 'string', 'max:100'],
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
        return response()->json(['message' => 'Equipo no encontrado.'], 404);
    }

    private function duplicateBien(): JsonResponse
    {
        return response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => ['bien_id' => ['El bien ya tiene un equipo registrado.']],
        ], 422);
    }

    private function inUse(string $relation): JsonResponse
    {
        return response()->json([
            'message' => "No se puede eliminar el equipo porque está en uso por $relation.",
        ], 409);
    }
}
