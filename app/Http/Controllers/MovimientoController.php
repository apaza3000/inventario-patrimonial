<?php

namespace App\Http\Controllers;

use App\Models\Bien;
use App\Models\Movimiento;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MovimientoController extends Controller
{
    private const RELATIONS = ['bien', 'ambienteOrigen', 'ambienteDestino', 'ordenadoPor', 'ejecutadoPor'];

    public function index(): JsonResponse
    {
        return response()->json(
            Movimiento::with(self::RELATIONS)->orderBy('id')->paginate(15)
        );
    }

    public function show(int $id): JsonResponse
    {
        $movimiento = Movimiento::with(self::RELATIONS)->find($id);

        if ($movimiento === null) {
            return response()->json(['message' => 'Movimiento no encontrado.'], 404);
        }

        return response()->json(['data' => $movimiento]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bien_id' => ['required', 'integer', 'between:1,2147483647', 'exists:bienes,id'],
            'ambiente_origen_id' => ['sometimes', 'nullable', 'integer', 'between:1,2147483647', 'exists:ambientes,id'],
            'ambiente_destino_id' => ['required', 'integer', 'between:1,2147483647', 'exists:ambientes,id'],
            'ordenado_por' => ['sometimes', 'nullable', 'integer', 'between:1,2147483647', 'exists:usuarios,id'],
            'ejecutado_por' => ['sometimes', 'nullable', 'integer', 'between:1,2147483647', 'exists:usuarios,id'],
            'fecha_movimiento' => ['required', 'date_format:Y-m-d H:i:s'],
            'motivo' => ['required', 'string', 'max:255'],
            'observaciones' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        try {
            return DB::transaction(function () use ($data) {
                $bien = Bien::whereKey($data['bien_id'])->lockForUpdate()->first();

                if ($bien === null) {
                    return response()->json([
                        'message' => 'Los datos enviados no son válidos.',
                        'errors' => ['bien_id' => ['El bien no existe.']],
                    ], 422);
                }

                $origen = isset($data['ambiente_origen_id']) ? (int) $data['ambiente_origen_id'] : null;
                $actual = $bien->ambiente_id === null ? null : (int) $bien->ambiente_id;

                if ($origen !== $actual) {
                    return response()->json([
                        'message' => 'El ambiente de origen no coincide con la ubicación actual del bien.',
                    ], 409);
                }

                if ($origen === (int) $data['ambiente_destino_id']) {
                    return response()->json([
                        'message' => 'Los datos enviados no son válidos.',
                        'errors' => ['ambiente_destino_id' => ['El destino debe ser diferente del origen.']],
                    ], 422);
                }

                $ultimo = Movimiento::where('bien_id', $bien->id)
                    ->orderByDesc('fecha_movimiento')
                    ->orderByDesc('id')
                    ->first(['fecha_movimiento']);

                if ($ultimo !== null && strcmp(
                    $data['fecha_movimiento'],
                    $ultimo->fecha_movimiento->format('Y-m-d H:i:s')
                ) < 0) {
                    return response()->json([
                        'message' => 'Los datos enviados no son válidos.',
                        'errors' => ['fecha_movimiento' => ['La fecha no puede ser anterior al último movimiento del bien.']],
                    ], 422);
                }

                $data['ambiente_origen_id'] = $origen;
                $movimiento = Movimiento::create($data);
                $bien->update(['ambiente_id' => $data['ambiente_destino_id']]);

                return response()->json([
                    'message' => 'Se registró el movimiento correctamente.',
                    'data' => $movimiento->load(self::RELATIONS),
                ], 201);
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1452) {
                return response()->json([
                    'message' => 'Los datos enviados no son válidos.',
                    'errors' => ['relaciones' => ['Una de las referencias enviadas ya no existe.']],
                ], 422);
            }

            throw $exception;
        }
    }
}
