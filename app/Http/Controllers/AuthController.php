<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        if (! $request->hasSession()) {
            return response()->json(['message' => 'Se requiere una sesión del frontend autorizada.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'correo' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! Auth::guard('web')->attempt([
            'correo' => $validator->validated()['correo'],
            'password' => $validator->validated()['password'],
            'activo' => true,
        ])) {
            return response()->json(['message' => 'Las credenciales son incorrectas o el usuario está inactivo.'], 401);
        }

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Sesión iniciada correctamente.',
            'data' => $request->user('web')->load(['rol', 'especialidad']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user('web')->load(['rol', 'especialidad'])]);
    }
}
