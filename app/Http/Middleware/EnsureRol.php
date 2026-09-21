<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $nombreRol = $request->user('web')?->rol?->nombre;

        if ($nombreRol !== 'superadmin' && ! in_array($nombreRol, $roles, true)) {
            return new JsonResponse(['message' => 'No tiene permiso para realizar esta acción.'], 403);
        }

        return $next($request);
    }
}
