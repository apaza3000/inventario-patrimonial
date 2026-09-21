<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InventarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->queryFor($request)
                ->orderBy('bien_id')
                ->orderBy('anio')
                ->paginate(15)
        );
    }

    public function show(Request $request, int $bien_id, int $anio): JsonResponse
    {
        $inventario = $this->queryFor($request)
            ->where('bien_id', $bien_id)
            ->where('anio', $anio)
            ->first();

        if ($inventario === null) {
            return $this->notFound();
        }

        return response()->json(['data' => $inventario]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $created = DB::transaction(function () use ($data) {
                if (DB::table('inventarios')
                    ->where('bien_id', $data['bien_id'])
                    ->where('anio', $data['anio'])
                    ->exists()) {
                    return false;
                }

                return DB::table('inventarios')->insert($data);
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                return $this->duplicateCombination();
            }

            if (($exception->errorInfo[1] ?? null) === 1452) {
                return $this->invalidBien();
            }

            throw $exception;
        }

        if (! $created) {
            return $this->duplicateCombination();
        }

        return response()->json([
            'message' => 'Se creó el inventario correctamente.',
            'data' => $this->findInventario($data['bien_id'], $data['anio']),
        ], 201);
    }

    public function update(Request $request, int $bien_id, int $anio): JsonResponse
    {
        $data = $this->validatedData($request, true);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        return DB::transaction(function () use ($bien_id, $anio, $data) {
            $query = DB::table('inventarios')
                ->where('bien_id', $bien_id)
                ->where('anio', $anio);

            if ($query->lockForUpdate()->first() === null) {
                return $this->notFound();
            }

            DB::table('inventarios')
                ->where('bien_id', $bien_id)
                ->where('anio', $anio)
                ->update(['inventario' => $data['inventario']]);

            return response()->json([
                'message' => 'Se actualizó el inventario correctamente.',
                'data' => $this->findInventario($bien_id, $anio),
            ]);
        });
    }

    public function destroy(int $bien_id, int $anio): JsonResponse
    {
        $deleted = DB::table('inventarios')
            ->where('bien_id', $bien_id)
            ->where('anio', $anio)
            ->delete();

        if ($deleted === 0) {
            return $this->notFound();
        }

        return response()->json(['message' => 'Se eliminó el inventario correctamente.']);
    }

    private function findInventario(int $bien_id, int $anio): ?Inventario
    {
        return Inventario::with('bien')
            ->where('bien_id', $bien_id)
            ->where('anio', $anio)
            ->first();
    }

    private function queryFor(Request $request): Builder
    {
        $query = Inventario::with('bien');

        if ($request->user('web')?->rol?->nombre === 'asistente') {
            $query->whereHas('bien.ambiente.tipoAmbiente', function (Builder $query) {
                $query->where('nombre', 'LABORATORIO');
            });
        }

        return $query;
    }

    private function validatedData(Request $request, bool $updating = false): array|JsonResponse
    {
        if ($updating) {
            $identityFields = array_intersect_key($request->all(), array_flip(['bien_id', 'anio']));

            if ($identityFields !== []) {
                $errors = [];

                foreach (array_keys($identityFields) as $field) {
                    $errors[$field] = ['Este campo forma parte de la clave primaria y no se puede cambiar.'];
                }

                return response()->json([
                    'message' => 'Los datos enviados no son válidos.',
                    'errors' => $errors,
                ], 422);
            }
        }

        $rules = $updating
            ? ['inventario' => ['required', 'string', 'max:20']]
            : [
                'bien_id' => ['required', 'integer', 'between:1,2147483647', 'exists:bienes,id'],
                'anio' => ['required', 'integer', 'between:-2147483648,2147483647'],
                'inventario' => ['required', 'string', 'max:20'],
            ];

        $validator = Validator::make($request->all(), $rules);

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
        return response()->json(['message' => 'Inventario no encontrado.'], 404);
    }

    private function duplicateCombination(): JsonResponse
    {
        return response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => ['anio' => ['Ya existe un inventario para este bien y año.']],
        ], 422);
    }

    private function invalidBien(): JsonResponse
    {
        return response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => ['bien_id' => ['El bien no existe.']],
        ], 422);
    }
}
