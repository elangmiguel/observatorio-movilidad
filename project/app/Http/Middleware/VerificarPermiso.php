<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que el usuario autenticado tenga al menos uno de los permisos indicados.
 * Uso en rutas: ->middleware('permiso:reportes.aprobar') o 'permiso:a,b'.
 */
class VerificarPermiso
{
    public function handle(Request $request, Closure $next, string ...$permisos): Response
    {
        $usuario = $request->user();

        if (! $usuario || ! $usuario->activo) {
            abort(401, 'No autenticado.');
        }

        foreach ($permisos as $permiso) {
            if ($usuario->tienePermiso($permiso)) {
                return $next($request);
            }
        }

        abort(403, 'No tiene permiso para realizar esta acción.');
    }
}
