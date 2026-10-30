<?php

namespace App\Http\Controllers\Api;

use App\Models\Permiso;
use App\Models\Rol;
use App\Services\Auditor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolController extends CatalogoController
{
    protected const MODELO = Rol::class;
    protected const PERMISO = 'roles.gestionar';

    protected function reglas(?Model $registro = null): array
    {
        return $this->reglasBase('roles', $registro);
    }

    protected function alMostrar(Model $registro): Model
    {
        return $registro->load('permisos:id,codigo,nombre,modulo');
    }

    /** Reemplaza el conjunto de permisos del rol por los códigos indicados. */
    public function sincronizarPermisos(Request $request, string $id): JsonResponse
    {
        $rol = Rol::findOrFail($id);

        $datos = $request->validate([
            'permisos' => ['present', 'array'],
            'permisos.*' => ['string', 'exists:permisos,codigo'],
        ]);

        $antes = $rol->permisos()->pluck('permisos.codigo')->all();
        $rol->permisos()->sync(Permiso::whereIn('codigo', $datos['permisos'])->pluck('id'));

        Auditor::registrar(
            $request, 'sincronizar_permisos', 'roles', $rol->id,
            ['permisos' => $antes], ['permisos' => array_values($datos['permisos'])]
        );

        return response()->json($this->alMostrar($rol));
    }
}
