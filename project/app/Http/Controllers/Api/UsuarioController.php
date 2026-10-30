<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\UsuarioRol;
use App\Services\Auditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    /** Perfil del usuario autenticado: sus roles vigentes y los permisos que de ellos se derivan. */
    public function perfil(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $roles = $usuario->roles()->where('roles.activo', true)->get();
        $permisos = Permiso::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $roles->pluck('id')))
            ->orderBy('codigo')
            ->pluck('codigo');

        return response()->json([
            'usuario' => $usuario,
            'roles' => $roles->pluck('codigo')->values(),
            'permisos' => $permisos,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $consulta = Usuario::query()
            ->with(['roles:id,codigo,nombre', 'tipoDocumento:id,codigo,nombre'])
            ->orderBy('apellidos')
            ->orderBy('nombres');

        if ($request->filled('buscar')) {
            $texto = '%' . addcslashes((string) $request->input('buscar'), '%_\\') . '%';
            $consulta->where(fn ($q) => $q
                ->where('nombres', 'ilike', $texto)
                ->orWhere('apellidos', 'ilike', $texto)
                ->orWhere('correo', 'ilike', $texto));
        }
        if ($request->filled('rol')) {
            $consulta->whereHas('roles', fn ($q) => $q->where('roles.codigo', $request->input('rol')));
        }
        if ($request->filled('activo')) {
            $consulta->where('activo', $request->boolean('activo'));
        }

        $porPagina = min(max($request->integer('por_pagina', 25), 1), 100);

        return response()->json($consulta->paginate($porPagina));
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(
            Usuario::with(['roles:id,codigo,nombre', 'tipoDocumento:id,codigo,nombre'])->findOrFail($id)
        );
    }

    /**
     * Invita a una persona por correo mediante Supabase Auth. Al crearse la cuenta,
     * el disparador de la base genera su perfil con el rol recolector; aquí se
     * completan sus datos y se le asignan los roles indicados. La persona define
     * su contraseña desde el enlace de la invitación.
     */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:150', 'unique:usuarios,correo'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'entidad' => ['nullable', 'string', 'max:150'],
            'matricula_profesional' => ['nullable', 'string', 'max:30'],
            'tipo_documento_id' => ['nullable', 'integer', 'exists:tipos_documento,id', 'required_with:numero_documento'],
            'numero_documento' => [
                'nullable', 'string', 'max:20',
                Rule::unique('usuarios', 'numero_documento')->where('tipo_documento_id', $request->input('tipo_documento_id')),
            ],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,codigo'],
        ]);

        $clave = config('supabase.service_role_key');

        $respuesta = Http::withHeaders(['apikey' => $clave])
            ->withToken($clave)
            ->timeout(10)
            ->post(rtrim((string) config('supabase.url'), '/') . '/auth/v1/invite', [
                'email' => $datos['correo'],
                'data' => ['nombres' => $datos['nombres'], 'apellidos' => $datos['apellidos']],
            ]);

        if ($respuesta->failed()) {
            throw ValidationException::withMessages([
                'correo' => 'No se pudo enviar la invitación: '
                    . ($respuesta->json('msg') ?? $respuesta->json('message') ?? 'error del servicio de autenticación.'),
            ]);
        }

        // El disparador de la base ya creó el perfil con el id de la cuenta.
        $usuario = Usuario::findOrFail($respuesta->json('id'));
        $usuario->update(Arr::only($datos, [
            'telefono', 'entidad', 'matricula_profesional', 'tipo_documento_id', 'numero_documento',
        ]));
        $this->sincronizarRoles($usuario, $datos['roles'], $request->user()->getKey());

        Auditor::registrar($request, 'invitar', 'usuarios', $usuario->id, null, [
            'correo' => $usuario->correo, 'roles' => $datos['roles'],
        ]);

        return response()->json($usuario->load('roles:id,codigo,nombre'), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $usuario = Usuario::findOrFail($id);

        $datos = $request->validate([
            'nombres' => ['sometimes', 'string', 'max:100'],
            'apellidos' => ['sometimes', 'string', 'max:100'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:20'],
            'entidad' => ['sometimes', 'nullable', 'string', 'max:150'],
            'matricula_profesional' => ['sometimes', 'nullable', 'string', 'max:30'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        abort_if(
            $usuario->id === $request->user()->getKey() && ($datos['activo'] ?? true) === false,
            422,
            'No puede desactivar su propia cuenta.'
        );

        $antes = $usuario->toArray();
        $usuario->update($datos);

        Auditor::registrar($request, 'actualizar', 'usuarios', $usuario->id, $antes, $datos);

        return response()->json($usuario->refresh()->load('roles:id,codigo,nombre'));
    }

    /** Da de baja al usuario: queda inactivo y con borrado lógico, conservando su historial. */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $usuario = Usuario::findOrFail($id);

        abort_if($usuario->id === $request->user()->getKey(), 422, 'No puede dar de baja su propia cuenta.');

        $usuario->update(['activo' => false]);
        $usuario->delete();

        Auditor::registrar($request, 'dar_de_baja', 'usuarios', $usuario->id, ['activo' => true], ['activo' => false]);

        return response()->json(null, 204);
    }

    /** Reemplaza los roles vigentes del usuario por los códigos indicados. */
    public function actualizarRoles(Request $request, string $id): JsonResponse
    {
        $usuario = Usuario::findOrFail($id);

        $datos = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,codigo'],
        ]);

        $antes = $usuario->roles()->pluck('roles.codigo')->all();
        $this->sincronizarRoles($usuario, $datos['roles'], $request->user()->getKey());

        Auditor::registrar($request, 'sincronizar_roles', 'usuarios', $usuario->id, ['roles' => $antes], ['roles' => array_values($datos['roles'])]);

        return response()->json($usuario->load('roles:id,codigo,nombre'));
    }

    /**
     * Revoca los roles que ya no corresponden y asigna los nuevos. Un rol revocado
     * antes se reactiva sobre la misma fila, conservando la restricción de unicidad.
     */
    private function sincronizarRoles(Usuario $usuario, array $codigos, string $asignadoPor): void
    {
        $roles = Rol::whereIn('codigo', $codigos)->pluck('id');

        DB::transaction(function () use ($usuario, $roles, $asignadoPor) {
            UsuarioRol::where('usuario_id', $usuario->id)
                ->whereNull('revocado_en')
                ->whereNotIn('rol_id', $roles)
                ->update(['revocado_en' => now()]);

            foreach ($roles as $rolId) {
                $asignacion = UsuarioRol::firstOrNew(['usuario_id' => $usuario->id, 'rol_id' => $rolId]);

                if (! $asignacion->exists || $asignacion->revocado_en !== null) {
                    $asignacion->fill([
                        'asignado_por_usuario_id' => $asignadoPor,
                        'asignado_en' => now(),
                        'revocado_en' => null,
                    ])->save();
                }
            }
        });
    }
}
