<?php

namespace App\Http\Controllers;

use App\Models\Ambiente;
use App\Models\Bien;
use App\Models\Movimiento;
use App\Models\Usuario;
use App\Services\MovimientoPdfService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MovimientoController extends Controller
{
    private const RELATIONS = ['bien', 'ambienteOrigen', 'ambienteDestino', 'ordenadoPor', 'ejecutadoPor'];

    public function __construct(private readonly MovimientoPdfService $pdfService)
    {
    }

    public function opciones(): JsonResponse
    {
        return response()->json([
            'data' => [
                'bienes' => Bien::query()
                    ->orderBy('id')
                    ->get(['id', 'cbi', 'descripcion', 'ambiente_id']),
                'ambientes' => Ambiente::query()
                    ->where('activo', true)
                    ->orderBy('id')
                    ->get(['id', 'nombre', 'sede_id']),
                'responsables' => Usuario::query()
                    ->where('activo', true)
                    ->orderBy('apellidos')
                    ->orderBy('nombres')
                    ->get(['id', 'nombres', 'apellidos']),
            ],
        ]);
    }

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
            'ordenado_por' => [
                'required',
                'integer',
                'between:1,2147483647',
                Rule::exists('usuarios', 'id')->where(fn ($query) => $query->where('activo', true)),
            ],
            'ejecutado_por' => [
                'required',
                'integer',
                'between:1,2147483647',
                Rule::exists('usuarios', 'id')->where(fn ($query) => $query->where('activo', true)),
            ],
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
        $generatedPath = null;

        try {
            $result = DB::transaction(function () use ($data, &$generatedPath) {
                $bien = Bien::whereKey($data['bien_id'])->lockForUpdate()->first();

                if ($bien === null) {
                    return response()->json([
                        'message' => 'Los datos enviados no son válidos.',
                        'errors' => ['bien_id' => ['El bien no existe.']],
                    ], 422);
                }

                if (Movimiento::where('bien_id', $bien->id)
                    ->where('estado', Movimiento::ESTADO_PENDIENTE_FIRMA)
                    ->exists()) {
                    return response()->json([
                        'message' => 'El bien ya tiene un movimiento pendiente de firma.',
                    ], 409);
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
                $data['estado'] = Movimiento::ESTADO_PENDIENTE_FIRMA;
                $movimiento = Movimiento::create($data);
                $movimiento->load(self::RELATIONS);

                $generatedPath = "movimientos/{$movimiento->id}/generado.pdf";

                if (! Storage::disk('local')->put(
                    $generatedPath,
                    $this->pdfService->render($movimiento)
                )) {
                    throw new RuntimeException('No se pudo guardar el PDF generado.');
                }

                $movimiento->update(['pdf_generado_ruta' => $generatedPath]);

                return $movimiento->refresh()->load(self::RELATIONS);
            });
        } catch (QueryException $exception) {
            if ($generatedPath !== null) {
                Storage::disk('local')->delete($generatedPath);
            }

            if (($exception->errorInfo[1] ?? null) === 1452) {
                return response()->json([
                    'message' => 'Los datos enviados no son válidos.',
                    'errors' => ['relaciones' => ['Una de las referencias enviadas ya no existe.']],
                ], 422);
            }

            throw $exception;
        } catch (Throwable $exception) {
            if ($generatedPath !== null) {
                Storage::disk('local')->delete($generatedPath);
            }

            throw $exception;
        }

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json([
            'message' => 'Se registró el movimiento pendiente de firma correctamente.',
            'data' => $result,
        ], 201);
    }

    public function downloadGenerated(int $id): StreamedResponse|JsonResponse
    {
        $movimiento = Movimiento::find($id);

        if ($movimiento === null) {
            return response()->json(['message' => 'Movimiento no encontrado.'], 404);
        }

        if ($movimiento->pdf_generado_ruta === null
            || ! Storage::disk('local')->exists($movimiento->pdf_generado_ruta)) {
            return response()->json(['message' => 'PDF generado no encontrado.'], 404);
        }

        return Storage::disk('local')->download(
            $movimiento->pdf_generado_ruta,
            "movimiento-{$movimiento->id}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }

    public function uploadSigned(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $signedPath = null;

        try {
            return DB::transaction(function () use ($request, $id, &$signedPath) {
                $movimiento = Movimiento::whereKey($id)->lockForUpdate()->first();

                if ($movimiento === null) {
                    return response()->json(['message' => 'Movimiento no encontrado.'], 404);
                }

                if ($movimiento->estado !== Movimiento::ESTADO_PENDIENTE_FIRMA) {
                    return response()->json([
                        'message' => 'El movimiento ya fue finalizado y su PDF firmado no puede reemplazarse.',
                    ], 409);
                }

                $bien = Bien::whereKey($movimiento->bien_id)->lockForUpdate()->first();

                if ($bien === null) {
                    return response()->json(['message' => 'El bien del movimiento ya no existe.'], 409);
                }

                $origen = $movimiento->ambiente_origen_id === null
                    ? null
                    : (int) $movimiento->ambiente_origen_id;
                $actual = $bien->ambiente_id === null ? null : (int) $bien->ambiente_id;

                if ($origen !== $actual) {
                    return response()->json([
                        'message' => 'El ambiente de origen ya no coincide con la ubicación actual del bien.',
                    ], 409);
                }

                $signedPath = $request->file('archivo')->storeAs(
                    "movimientos/{$movimiento->id}",
                    'firmado-'.Str::uuid().'.pdf',
                    'local'
                );

                if (! is_string($signedPath)) {
                    throw new RuntimeException('No se pudo guardar el PDF firmado.');
                }

                $bien->update(['ambiente_id' => $movimiento->ambiente_destino_id]);
                $movimiento->update([
                    'estado' => Movimiento::ESTADO_FINALIZADO,
                    'pdf_firmado_ruta' => $signedPath,
                    'fecha_firma' => now(),
                ]);

                return response()->json([
                    'message' => 'El movimiento se finalizó correctamente.',
                    'data' => $movimiento->refresh()->load(self::RELATIONS),
                ]);
            });
        } catch (Throwable $exception) {
            if ($signedPath !== null) {
                Storage::disk('local')->delete($signedPath);
            }

            throw $exception;
        }
    }

    public function downloadSigned(int $id): StreamedResponse|JsonResponse
    {
        $movimiento = Movimiento::find($id);

        if ($movimiento === null) {
            return response()->json(['message' => 'Movimiento no encontrado.'], 404);
        }

        if ($movimiento->estado !== Movimiento::ESTADO_FINALIZADO
            || $movimiento->pdf_firmado_ruta === null
            || ! Storage::disk('local')->exists($movimiento->pdf_firmado_ruta)) {
            return response()->json(['message' => 'PDF firmado no encontrado.'], 404);
        }

        return Storage::disk('local')->download(
            $movimiento->pdf_firmado_ruta,
            "movimiento-{$movimiento->id}-firmado.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }
}
