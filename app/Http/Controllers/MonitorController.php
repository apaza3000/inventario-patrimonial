<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MonitorController extends Controller
{
    public function index(): JsonResponse
    {
        $monitores = Monitor::with('equipo')->orderBy('bien_id')->paginate(15);
        $monitores->getCollection()->transform(fn (Monitor $monitor) => $this->apiData($monitor));

        return response()->json($monitores);
    }

    public function show(int $bien_id): JsonResponse
    {
        $monitor = Monitor::with('equipo')->find($bien_id);

        if ($monitor === null) {
            return $this->notFound();
        }

        return response()->json(['data' => $this->apiData($monitor)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $monitor = Monitor::create($data);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                return $this->duplicateBien();
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Se creó el monitor correctamente.',
            'data' => $this->apiData($monitor->load('equipo')),
        ], 201);
    }

    public function update(Request $request, int $bien_id): JsonResponse
    {
        $monitor = Monitor::find($bien_id);

        if ($monitor === null) {
            return $this->notFound();
        }

        $data = $this->validatedData($request, $bien_id);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $monitor->update($data);

        return response()->json([
            'message' => 'Se actualizó el monitor correctamente.',
            'data' => $this->apiData($monitor->refresh()->load('equipo')),
        ]);
    }

    public function destroy(int $bien_id): JsonResponse
    {
        $monitor = Monitor::find($bien_id);

        if ($monitor === null) {
            return $this->notFound();
        }

        $monitor->delete();

        return response()->json(['message' => 'Se eliminó el monitor correctamente.']);
    }

    private function validatedData(Request $request, ?int $bien_id = null): array|JsonResponse
    {
        if (array_key_exists('tamanio_pantalla', $request->all())) {
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => ['tamanio_pantalla' => ['Use tamano_pantalla en la API.']],
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'bien_id' => $bien_id === null
                ? ['required', 'integer', 'exists:equipos,bien_id', Rule::unique('monitores', 'bien_id')]
                : ['sometimes', 'required', 'integer', Rule::in([$bien_id])],
            'tamano_pantalla' => ['sometimes', 'nullable', 'string', 'max:50'],
            'detalle_pantalla' => ['sometimes', 'nullable', 'string', 'max:150'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if (array_key_exists('tamano_pantalla', $data)) {
            $data['tamanio_pantalla'] = $data['tamano_pantalla'];
            unset($data['tamano_pantalla']);
        }

        return $data;
    }

    private function apiData(Monitor $monitor): array
    {
        $data = $monitor->toArray();
        $data['tamano_pantalla'] = $data['tamanio_pantalla'];
        unset($data['tamanio_pantalla']);

        return $data;
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Monitor no encontrado.'], 404);
    }

    private function duplicateBien(): JsonResponse
    {
        return response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => ['bien_id' => ['El equipo ya tiene un monitor registrado.']],
        ], 422);
    }
}
