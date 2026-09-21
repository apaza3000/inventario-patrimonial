<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RolController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Rol::orderBy('id')->paginate(15));
    }

    public function show(int $id): JsonResponse
    {
        $rol = Rol::find($id);

        if ($rol === null) {
            return $this->notFound();
        }

        return response()->json(['data' => $rol]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $rol = Rol::create($data);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                return $this->duplicateNombre();
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Se creó el rol correctamente.',
            'data' => $rol,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $rol = Rol::find($id);

        if ($rol === null) {
            return $this->notFound();
        }

        $data = $this->validatedData($request, $id);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $rol->update($data);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                return $this->duplicateNombre();
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Se actualizó el rol correctamente.',
            'data' => $rol->refresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $rol = Rol::find($id);

        if ($rol === null) {
            return $this->notFound();
        }

        if ($rol->usuarios()->exists()) {
            return $this->inUse();
        }

        try {
            $rol->delete();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1451) {
                return $this->inUse();
            }

            throw $exception;
        }

        return response()->json(['message' => 'Se eliminó el rol correctamente.']);
    }

    private function validatedData(Request $request, ?int $id = null): array|JsonResponse
    {
        $required = $id === null ? ['required'] : ['sometimes', 'required'];

        $validator = Validator::make($request->all(), [
            'nombre' => [...$required, 'string', 'max:50', Rule::unique('roles', 'nombre')->ignore($id)],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:150'],
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
        return response()->json(['message' => 'Rol no encontrado.'], 404);
    }

    private function duplicateNombre(): JsonResponse
    {
        return response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => ['nombre' => ['El nombre del rol ya está registrado.']],
        ], 422);
    }

    private function inUse(): JsonResponse
    {
        return response()->json(['message' => 'No se puede eliminar el rol porque tiene usuarios asociados.'], 409);
    }
}
