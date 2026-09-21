<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Usuario::with('rol')->orderBy('id')->paginate(15));
    }

    public function show(int $id): JsonResponse
    {
        $usuario = Usuario::with('rol')->find($id);

        if ($usuario === null) {
            return $this->notFound();
        }

        return response()->json(['data' => $usuario]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        $data['password_hash'] = Hash::make($data['password']);
        $data['fecha_registro'] = now();
        unset($data['password']);

        try {
            $usuario = Usuario::create($data);
        } catch (QueryException $exception) {
            return $this->writeError($exception);
        }

        return response()->json([
            'message' => 'Se creó el usuario correctamente.',
            'data' => $usuario->refresh()->load('rol'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $usuario = Usuario::find($id);

        if ($usuario === null) {
            return $this->notFound();
        }

        $data = $this->validatedData($request, $id);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        if (array_key_exists('password', $data)) {
            $data['password_hash'] = Hash::make($data['password']);
            unset($data['password']);
        }

        try {
            $usuario->update($data);
        } catch (QueryException $exception) {
            return $this->writeError($exception);
        }

        return response()->json([
            'message' => 'Se actualizó el usuario correctamente.',
            'data' => $usuario->refresh()->load('rol'),
        ]);
    }

    private function validatedData(Request $request, ?int $id = null): array|JsonResponse
    {
        $serverFields = array_intersect_key($request->all(), array_flip(['password_hash', 'fecha_registro']));

        if ($serverFields !== []) {
            $errors = [];

            foreach (array_keys($serverFields) as $field) {
                $errors[$field] = ['Este campo lo administra el servidor y no puede enviarse directamente.'];
            }

            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $errors,
            ], 422);
        }

        $required = $id === null ? ['required'] : ['sometimes', 'required'];

        $validator = Validator::make($request->all(), [
            'nombres' => [...$required, 'string', 'max:100'],
            'apellidos' => [...$required, 'string', 'max:100'],
            'correo' => [...$required, 'email', 'max:120', Rule::unique('usuarios', 'correo')->ignore($id)],
            'password' => [...$required, 'string', 'min:8', 'max:72'],
            'rol_id' => [...$required, 'integer', 'between:1,2147483647', 'exists:roles,id'],
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

    private function writeError(QueryException $exception): JsonResponse
    {
        if (($exception->errorInfo[1] ?? null) === 1062) {
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => ['correo' => ['El correo ya está registrado.']],
            ], 422);
        }

        if (($exception->errorInfo[1] ?? null) === 1452) {
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => ['rol_id' => ['El rol no existe.']],
            ], 422);
        }

        throw $exception;
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Usuario no encontrado.'], 404);
    }
}
