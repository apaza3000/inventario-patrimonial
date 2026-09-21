<?php

namespace App\Http\Controllers;

use App\Models\Bien;
use App\Models\EstacionComponente;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EstacionComponenteController extends Controller
{
    private const RELATIONS = ['estacion', 'bien'];

    public function index(): JsonResponse
    {
        return response()->json(
            EstacionComponente::with(self::RELATIONS)->orderBy('id')->paginate(15)
        );
    }

    public function show(int $id): JsonResponse
    {
        $componente = EstacionComponente::with(self::RELATIONS)->find($id);

        if ($componente === null) {
            return $this->notFound();
        }

        return response()->json(['data' => $componente]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        if ($this->fechaRetiroAnterior($data['fecha_asignacion'] ?? null, $data['fecha_retiro'] ?? null)) {
            return $this->invalidDates();
        }

        $data['activo'] = $data['activo'] ?? true;
        $stateError = $this->invalidState((bool) $data['activo'], $data['fecha_retiro'] ?? null);

        if ($stateError !== null) {
            return $stateError;
        }

        try {
            return DB::transaction(function () use ($data) {
                if (Bien::whereKey($data['bien_id'])->lockForUpdate()->first() === null) {
                    return $this->invalidBien();
                }

                if ((bool) $data['activo'] && EstacionComponente::where('bien_id', $data['bien_id'])
                    ->where('activo', true)->exists()) {
                    return $this->alreadyAssigned();
                }

                $componente = EstacionComponente::create($data);

                return response()->json([
                    'message' => 'Se asignó el componente correctamente.',
                    'data' => $componente->load(self::RELATIONS),
                ], 201);
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1452) {
                return $this->invalidReference();
            }

            throw $exception;
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $bienId = EstacionComponente::whereKey($id)->value('bien_id');

        if ($bienId === null) {
            return $this->notFound();
        }

        if (array_key_exists('estacion_id', $request->all()) || array_key_exists('bien_id', $request->all())) {
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => ['identidad' => ['La estación y el bien de una asignación no se pueden cambiar.']],
            ], 422);
        }

        $data = $this->validatedData($request, true);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        return DB::transaction(function () use ($id, $bienId, $data) {
            Bien::whereKey($bienId)->lockForUpdate()->first();
            $componente = EstacionComponente::whereKey($id)->lockForUpdate()->first();

            if ($componente === null) {
                return $this->notFound();
            }

            $fechaAsignacion = array_key_exists('fecha_asignacion', $data)
                ? $data['fecha_asignacion']
                : $componente->fecha_asignacion?->format('Y-m-d');
            $fechaRetiro = array_key_exists('fecha_retiro', $data)
                ? $data['fecha_retiro']
                : $componente->fecha_retiro?->format('Y-m-d');

            if ($this->fechaRetiroAnterior($fechaAsignacion, $fechaRetiro)) {
                return $this->invalidDates();
            }

            $activo = array_key_exists('activo', $data) ? (bool) $data['activo'] : $componente->activo;

            if ((! $componente->activo || $componente->fecha_retiro !== null) && $activo) {
                return $this->cannotReactivate();
            }

            $stateError = $this->invalidState($activo, $fechaRetiro);

            if ($stateError !== null) {
                return $stateError;
            }

            if ($activo && EstacionComponente::where('bien_id', $bienId)
                ->where('id', '!=', $id)->where('activo', true)->exists()) {
                return $this->alreadyAssigned();
            }

            $componente->update($data);

            return response()->json([
                'message' => 'Se actualizó la asignación correctamente.',
                'data' => $componente->refresh()->load(self::RELATIONS),
            ]);
        });
    }

    private function validatedData(Request $request, bool $updating = false): array|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'estacion_id' => $updating ? ['prohibited'] : ['required', 'integer', 'between:1,2147483647', 'exists:estaciones_pc,id'],
            'bien_id' => $updating ? ['prohibited'] : ['required', 'integer', 'between:1,2147483647', 'exists:bienes,id'],
            'fecha_asignacion' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'fecha_retiro' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
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

    private function fechaRetiroAnterior(?string $asignacion, ?string $retiro): bool
    {
        return $asignacion !== null && $retiro !== null && strcmp($retiro, $asignacion) < 0;
    }

    private function invalidState(bool $activo, ?string $fechaRetiro): ?JsonResponse
    {
        if ($activo && $fechaRetiro !== null) {
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => ['fecha_retiro' => ['Una asignación activa no puede tener fecha de retiro.']],
            ], 422);
        }

        if (! $activo && $fechaRetiro === null) {
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => ['fecha_retiro' => ['Una asignación retirada debe tener fecha de retiro.']],
            ], 422);
        }

        return null;
    }

    private function cannotReactivate(): JsonResponse
    {
        return response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => ['activo' => ['Una asignación retirada no puede reactivarse; registre una nueva asignación.']],
        ], 422);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Asignación de componente no encontrada.'], 404);
    }

    private function invalidBien(): JsonResponse
    {
        return response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => ['bien_id' => ['El bien no existe.']],
        ], 422);
    }

    private function invalidReference(): JsonResponse
    {
        return response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => ['relaciones' => ['La estación o el bien ya no existe.']],
        ], 422);
    }

    private function invalidDates(): JsonResponse
    {
        return response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => ['fecha_retiro' => ['La fecha de retiro no puede ser anterior a la asignación.']],
        ], 422);
    }

    private function alreadyAssigned(): JsonResponse
    {
        return response()->json([
            'message' => 'El bien ya tiene una asignación activa en una estación.',
        ], 409);
    }
}
