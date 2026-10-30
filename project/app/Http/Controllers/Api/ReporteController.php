<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FiltraReportes;
use App\Http\Controllers\Controller;
use App\Models\EstadoReporte;
use App\Models\Reporte;
use App\Models\Usuario;
use App\Services\Auditor;
use App\Services\RecepcionReporteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    use FiltraReportes;

    /** Columnas de la bandeja (se excluyen la geometría y la ruta del archivo). */
    private const COLUMNAS_BANDEJA = [
        'id', 'codigo_local', 'usuario_id', 'campana_id', 'fecha_captura', 'recibido_en',
        'latitud', 'longitud', 'precision_gps_m', 'via_sector', 'punto_referencia',
        'comuna_id', 'comuna', 'barrio_id', 'barrio',
        'estado_reporte_id', 'estado_reporte', 'estado_ia', 'reporte_principal_id',
        'fotografia_id', 'clasificacion_ia_id', 'confianza_ia', 'confianza_severidad_ia',
        'validacion_id', 'decision_validacion', 'tipo_deterioro_id', 'severidad_id', 'severidad',
        'prioridad_id', 'prioridad', 'puntaje', 'intervencion_id', 'estado_atencion',
    ];

    /** Recibe un reporte de la aplicación móvil (multipart con la fotografía). Es idempotente. */
    public function recibir(Request $request, RecepcionReporteService $servicio): JsonResponse
    {
        $datos = $request->validate([
            'codigo_instalacion' => ['required', 'string', 'max:64'],
            'plataforma' => ['nullable', 'string', 'max:20'],
            'modelo' => ['nullable', 'string', 'max:80'],
            'version_app' => ['nullable', 'string', 'max:20'],
            'codigo_local' => ['required', 'string', 'max:64'],
            'campana_id' => ['nullable', 'uuid', 'exists:campanas,id'],
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            'precision_gps_m' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'fuente_ubicacion' => ['required', 'in:gps,exif,manual,red'],
            'fecha_captura' => ['required', 'date'],
            'via_sector' => ['nullable', 'string', 'max:150'],
            'barrio_reportado' => ['nullable', 'string', 'max:100'],
            'punto_referencia' => ['nullable', 'string', 'max:200'],
            'tipo_deterioro_id' => ['nullable', 'integer', 'exists:tipos_deterioro,id'],
            'tipo_superficie_id' => ['nullable', 'integer', 'exists:tipos_superficie,id'],
            'descripcion_ciudadano' => ['nullable', 'string'],
            'foto' => ['required', 'file', 'image', 'max:15360'],
        ]);

        ['reporte' => $reporte, 'duplicado' => $duplicado] =
            $servicio->recibir($request->user(), $datos, $request->file('foto'));

        return response()->json([
            'id' => $reporte->id,
            'duplicado' => $duplicado,
            'estado_reporte_id' => $reporte->estado_reporte_id,
            'comuna_id' => $reporte->comuna_id,
            'barrio_id' => $reporte->barrio_id,
        ], $duplicado ? 200 : 201);
    }

    /** Bandeja de reportes recibidos, con filtros por comuna, barrio, fecha, severidad y estado. */
    public function index(Request $request): JsonResponse
    {
        $consulta = DB::table('v_reportes_consolidados')->select(self::COLUMNAS_BANDEJA);
        $this->filtrarReportes($consulta, $request);

        return response()->json(
            $consulta->orderByDesc('recibido_en')->paginate($this->porPagina($request))
        );
    }

    /** Reportes levantados por el usuario autenticado. */
    public function misReportes(Request $request): JsonResponse
    {
        $reportes = Reporte::query()
            ->where('usuario_id', $request->user()->getKey())
            ->with(['estadoReporte:id,codigo,nombre', 'tipoDeterioro:id,codigo,nombre', 'barrio:id,nombre'])
            ->latest('recibido_en')
            ->paginate($this->porPagina($request));

        return response()->json($reportes);
    }

    public function show(Request $request, string $reporte): JsonResponse
    {
        $modelo = Reporte::with([
            'usuario:id,nombres,apellidos',
            'comuna:id,nombre',
            'barrio:id,nombre',
            'tipoDeterioro:id,codigo,nombre',
            'tipoSuperficie:id,codigo,nombre',
            'estadoReporte:id,codigo,nombre',
            'fotografiaPrincipal',
            'ultimaClasificacionIa.tipoDeterioro:id,codigo,nombre',
            'ultimaClasificacionIa.severidad:id,codigo,nombre,nivel',
            'validacionVigente.deterioros.tipoDeterioro:id,codigo,nombre',
            'validacionVigente.severidad:id,codigo,nombre,nivel',
            'priorizacionVigente.prioridad:id,codigo,nombre,nivel',
            'intervenciones.estadoIntervencion:id,codigo,nombre',
        ])->findOrFail($reporte);

        $this->autorizarLectura($request->user(), $modelo);

        // La ruta del archivo no se expone: la URL temporal se pide en /fotografias/{id}.
        $modelo->fotografiaPrincipal?->makeHidden('url_almacenamiento');

        return response()->json($modelo);
    }

    /** Un reporte recibido pasa a "en revisión" cuando un responsable técnico lo abre. */
    public function abrirRevision(Request $request, string $reporte): JsonResponse
    {
        $modelo = Reporte::with('estadoReporte')->findOrFail($reporte);

        if ($modelo->estadoReporte->codigo === 'recibido') {
            $modelo->update([
                'estado_reporte_id' => EstadoReporte::where('codigo', 'en_revision')->value('id'),
            ]);
            Auditor::registrar($request, 'abrir_revision', 'reportes', $modelo->id, ['estado' => 'recibido'], ['estado' => 'en_revision']);
        }

        return response()->json($modelo->refresh()->load('estadoReporte:id,codigo,nombre'));
    }

    /** Marca el reporte como duplicado de otro, para no contar dos veces el mismo daño. */
    public function marcarDuplicado(Request $request, string $reporte): JsonResponse
    {
        $modelo = Reporte::with('estadoReporte')->findOrFail($reporte);

        $datos = $request->validate([
            'reporte_principal_id' => ['required', 'uuid', 'exists:reportes,id'],
        ]);

        abort_if($datos['reporte_principal_id'] === $modelo->id, 422, 'Un reporte no puede ser duplicado de sí mismo.');
        abort_if($modelo->estadoReporte->codigo === 'aprobado', 422, 'Un reporte aprobado no puede marcarse como duplicado.');

        $antes = ['estado' => $modelo->estadoReporte->codigo, 'reporte_principal_id' => $modelo->reporte_principal_id];

        $modelo->update([
            'estado_reporte_id' => EstadoReporte::where('codigo', 'duplicado')->value('id'),
            'reporte_principal_id' => $datos['reporte_principal_id'],
        ]);

        Auditor::registrar($request, 'marcar_duplicado', 'reportes', $modelo->id, $antes, [
            'estado' => 'duplicado',
            'reporte_principal_id' => $datos['reporte_principal_id'],
        ]);

        return response()->json($modelo->refresh()->load('estadoReporte:id,codigo,nombre'));
    }

    /** Quien tiene permiso total ve cualquier reporte; el recolector solo los suyos. */
    private function autorizarLectura(Usuario $usuario, Reporte $reporte): void
    {
        if ($usuario->tienePermiso('reportes.ver_todos')) {
            return;
        }

        abort_unless(
            $usuario->tienePermiso('reportes.ver_propios') && $reporte->usuario_id === $usuario->id,
            403,
            'No tiene permiso para ver este reporte.'
        );
    }
}
