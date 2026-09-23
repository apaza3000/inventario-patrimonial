<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    private const RELATIONS = ['rol', 'especialidad'];

    private const ESPECIALIDADES_ACADEMICAS = [1, 2, 3];

    public function index(): JsonResponse
    {
        return response()->json(Usuario::with(self::RELATIONS)->orderBy('id')->paginate(15));
    }

    public function show(int $id): JsonResponse
    {
        $usuario = Usuario::with(self::RELATIONS)->find($id);

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
            'data' => $usuario->refresh()->load(self::RELATIONS),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $usuario = Usuario::find($id);

        if ($usuario === null) {
            return $this->notFound();
        }

        $data = $this->validatedData($request, $usuario);

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
            'data' => $usuario->refresh()->load(self::RELATIONS),
        ]);
    }

    private function validatedData(Request $request, ?Usuario $usuario = null): array|JsonResponse
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

        $required = $usuario === null ? ['required'] : ['sometimes', 'required'];

        $validator = Validator::make($request->all(), [
            'nombres' => [...$required, 'string', 'max:100'],
            'apellidos' => [...$required, 'string', 'max:100'],
            'correo' => [...$required, 'email', 'max:120', Rule::unique('usuarios', 'correo')->ignore($usuario?->id)],
            'password' => [...$required, 'string', 'min:8', 'max:72'],
            'rol_id' => [...$required, 'integer', 'between:1,2147483647', 'exists:roles,id'],
            'especialidad_id' => ['sometimes', 'nullable', 'integer', 'between:1,2147483647', 'exists:especialidades,id'],
            'activo' => ['sometimes', 'required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $rolId = $data['rol_id'] ?? $usuario?->rol_id;
        $rol = Rol::query()->find($rolId);

        if ($rol?->nombre === 'coordinador') {
            $especialidadId = array_key_exists('especialidad_id', $data)
                ? $data['especialidad_id']
                : $usuario?->especialidad_id;

            if ($especialidadId === null) {
                return response()->json([
                    'message' => 'Los datos enviados no son válidos.',
                    'errors' => [
                        'especialidad_id' => ['La especialidad es obligatoria para los usuarios coordinadores.'],
                    ],
                ], 422);
            }

            if (! in_array($especialidadId, self::ESPECIALIDADES_ACADEMICAS, true)) {
                return response()->json([
                    'message' => 'Los datos enviados no son válidos.',
                    'errors' => [
                        'especialidad_id' => ['La especialidad seleccionada no corresponde a una carrera académica.'],
                    ],
                ], 422);
            }
        } else {
            $data['especialidad_id'] = null;
        }

        return $data;
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
