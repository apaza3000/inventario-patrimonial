<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user('web')?->activo) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'El usuario está inactivo.'], 403);
        }

        return $next($request);
    }
}
