<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CriterioGravedad;
use App\Models\Reporte;
use App\Models\Severidad;
use App\Models\Validacion;
use App\Models\ValidacionDeterioro;
use App\Services\Auditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ValidacionController extends Controller
{
    /** Permiso adicional que exige cada decisión, además de reportes.validar. */
    private const PERMISO_DECISION = [
        Validacion::DECISION_APROBADO => 'reportes.aprobar',
        Validacion::DECISION_CORREGIDO => 'reportes.corregir',
        Validacion::DECISION_RECHAZADO => 'reportes.rechazar',
    ];

    /** Historial de validaciones de un reporte, de la más reciente a la más antigua. */
    public function index(string $reporte): JsonResponse
    {
        Reporte::findOrFail($reporte);

        $validaciones = Validacion::where('reporte_id', $reporte)
            ->with([
                'usuario:id,nombres,apellidos',
                'tipoDeterioro:id,codigo,nombre',
                'severidad:id,codigo,nombre,nivel',
                'tipoSuperficie:id,codigo,nombre',
                'deterioros.tipoDeterioro:id,codigo,nombre',
                'deterioros.severidad:id,codigo,nombre,nivel',
            ])
            ->orderByDesc('validado_en')
            ->get();

        return response()->json($validaciones);
    }

    /**
     * Registra la validación técnica de un reporte: la decisión, los deterioros
     * observados con sus medidas y el diagnóstico global. Al guardarla, la base
     * deja vigente esta validación y actualiza el estado del reporte.
     */
    public function store(Request $request, string $reporte): JsonResponse
    {
        $modelo = Reporte::with('estadoReporte')->findOrFail($reporte);

        $datos = $request->validate([
            'decision' => ['required', Rule::in(array_keys(self::PERMISO_DECISION))],
            'clasificacion_ia_id' => [
                'nullable', 'uuid',
                Rule::exists('clasificaciones_ia', 'id')->where('reporte_id', $modelo->id),
            ],
            'tipo_superficie_id' => ['nullable', 'integer', 'exists:tipos_superficie,id'],
            'motivo_rechazo' => ['required_if:decision,' . Validacion::DECISION_RECHAZADO, 'nullable', 'string'],
            'observacion' => ['nullable', 'string'],
            'deterioros' => ['required_unless:decision,' . Validacion::DECISION_RECHAZADO, 'array'],
            'deterioros.*.tipo_deterioro_id' => ['required', 'integer', 'exists:tipos_deterioro,id', 'distinct'],
            'deterioros.*.severidad_id' => ['nullable', 'integer', 'exists:severidades,id'],
            'deterioros.*.longitud_m' => ['nullable', 'numeric', 'min:0'],
            'deterioros.*.ancho_mm' => ['nullable', 'numeric', 'min:0'],
            'deterioros.*.profundidad_mm' => ['nullable', 'numeric', 'min:0'],
            'deterioros.*.area_m2' => ['nullable', 'numeric', 'min:0'],
            'deterioros.*.cantidad' => ['nullable', 'integer', 'min:0'],
            'deterioros.*.diametro_max_mm' => ['nullable', 'numeric', 'min:0'],
            'deterioros.*.longitud_afectada_pct' => ['nullable', 'numeric', 'between:0,100'],
            'deterioros.*.observacion' => ['nullable', 'string'],
        ]);

        $usuario = $request->user();

        abort_unless(
            $usuario->tienePermiso(self::PERMISO_DECISION[$datos['decision']]),
            403,
            'No tiene permiso para registrar esta decisión.'
        );
        abort_if($modelo->estadoReporte->codigo === 'duplicado', 422, 'Un reporte duplicado no se valida.');

        $rechazado = $datos['decision'] === Validacion::DECISION_RECHAZADO;
        $filas = $rechazado ? [] : $this->completarGravedad($datos['deterioros'] ?? []);
        $principal = $rechazado ? null : $this->deterioroPrincipal($filas);
        $estadoAnterior = $modelo->estadoReporte->codigo;

        $validacion = DB::transaction(function () use ($datos, $modelo, $usuario, $filas, $principal) {
            $validacion = Validacion::create([
                'reporte_id' => $modelo->id,
                'usuario_id' => $usuario->id,
                'clasificacion_ia_id' => $datos['clasificacion_ia_id'] ?? null,
                'decision' => $datos['decision'],
                'tipo_deterioro_id' => $principal['tipo_deterioro_id'] ?? null,
                'severidad_id' => $principal['severidad_id'] ?? null,
                'tipo_superficie_id' => $datos['tipo_superficie_id'] ?? null,
                'motivo_rechazo' => $datos['motivo_rechazo'] ?? null,
                'observacion' => $datos['observacion'] ?? null,
            ]);

            foreach ($filas as $fila) {
                $validacion->deterioros()->create($fila);
            }

            return $validacion;
        });

        $modelo->refresh()->load('estadoReporte:id,codigo,nombre');

        Auditor::registrar(
            $request, 'validar', 'reportes', $modelo->id,
            ['estado' => $estadoAnterior],
            ['estado' => $modelo->estadoReporte->codigo, 'decision' => $datos['decision'], 'validacion_id' => $validacion->id]
        );

        return response()->json([
            'validacion' => $validacion->load(['deterioros.tipoDeterioro:id,codigo,nombre', 'deterioros.severidad:id,codigo,nombre,nivel']),
            'estado_reporte' => $modelo->estadoReporte,
        ], 201);
    }

    /**
     * Propone la gravedad de un deterioro a partir de sus medidas y devuelve los
     * criterios del INVÍAS, para que la pantalla de validación los muestre.
     */
    public function sugerirGravedad(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo_deterioro_id' => ['required', 'integer', 'exists:tipos_deterioro,id'],
            'profundidad_mm' => ['nullable', 'numeric', 'min:0'],
            'longitud_afectada_pct' => ['nullable', 'numeric', 'between:0,100'],
            'cantidad' => ['nullable', 'integer', 'min:0'],
            'diametro_max_mm' => ['nullable', 'numeric', 'min:0'],
            'area_m2' => ['nullable', 'numeric', 'min:0'],
        ]);

        $severidadId = (new ValidacionDeterioro($datos))->severidadSugerida();

        return response()->json([
            'severidad_id' => $severidadId,
            'severidad' => $severidadId ? Severidad::find($severidadId, ['id', 'codigo', 'nombre', 'nivel']) : null,
            'criterios' => CriterioGravedad::where('tipo_deterioro_id', $datos['tipo_deterioro_id'])
                ->orderBy('severidad_id')
                ->orderBy('grupo_condicion')
                ->get(['severidad_id', 'grupo_condicion', 'parametro', 'valor_minimo', 'valor_maximo',
                    'minimo_inclusivo', 'maximo_inclusivo', 'descripcion']),
        ]);
    }

    /**
     * Asigna la gravedad sugerida por los criterios a los deterioros que no la traen.
     * Si el deterioro solo tiene criterios descriptivos, el validador debe indicarla.
     */
    private function completarGravedad(array $deterioros): array
    {
        if ($deterioros === []) {
            throw ValidationException::withMessages([
                'deterioros' => 'Registre al menos un deterioro.',
            ]);
        }

        $filas = [];

        foreach (array_values($deterioros) as $i => $deterioro) {
            $deterioro['severidad_id'] ??= (new ValidacionDeterioro($deterioro))->severidadSugerida();

            if ($deterioro['severidad_id'] === null) {
                throw ValidationException::withMessages([
                    "deterioros.$i.severidad_id" => 'Indique la gravedad: los criterios de este deterioro son descriptivos.',
                ]);
            }

            $filas[] = $deterioro;
        }

        return $filas;
    }

    /** El deterioro principal es el de mayor gravedad; ese nivel es la gravedad del reporte. */
    private function deterioroPrincipal(array $filas): array
    {
        $niveles = Severidad::pluck('nivel', 'id');

        return collect($filas)
            ->sortByDesc(fn (array $fila) => $niveles[$fila['severidad_id']])
            ->first();
    }
}
