<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Http\Request;

/**
 * Registra en la tabla auditoria las acciones relevantes de los usuarios.
 */
class Auditor
{
    public static function registrar(
        Request $request,
        string $accion,
        string $entidad,
        string|int|null $entidadId = null,
        ?array $antes = null,
        ?array $despues = null,
    ): void {
        Auditoria::create([
            'usuario_id' => $request->user()?->getKey(),
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId !== null ? (string) $entidadId : null,
            'datos_anteriores' => $antes,
            'datos_nuevos' => $despues,
            'direccion_ip' => $request->ip(),
        ]);
    }
}
