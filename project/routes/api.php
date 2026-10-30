<?php

use App\Http\Controllers\Api\AuditoriaController;
use App\Http\Controllers\Api\BarrioController;
use App\Http\Controllers\Api\CampanaController;
use App\Http\Controllers\Api\ComunaController;
use App\Http\Controllers\Api\CriterioGravedadController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EstadoIntervencionController;
use App\Http\Controllers\Api\EstadoReporteController;
use App\Http\Controllers\Api\EvidenciaController;
use App\Http\Controllers\Api\FotografiaController;
use App\Http\Controllers\Api\IntervencionController;
use App\Http\Controllers\Api\MapaController;
use App\Http\Controllers\Api\PermisoController;
use App\Http\Controllers\Api\PrioridadController;
use App\Http\Controllers\Api\PriorizacionController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\SeveridadController;
use App\Http\Controllers\Api\SincronizacionController;
use App\Http\Controllers\Api\TipoDeterioroController;
use App\Http\Controllers\Api\TipoDocumentoController;
use App\Http\Controllers\Api\TipoIntervencionController;
use App\Http\Controllers\Api\TipoSuperficieController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\ValidacionController;
use Illuminate\Support\Facades\Route;

/*
 * API
 *
 * 'Auth' usa el guard por defecto.
 *
 * Cada ruta exige el permiso indicado con el middleware 'permiso:modulo.accion'
 *
 */

Route::middleware('auth:supabase')->group(function (){

    Route::get('me', [UsuarioController::class, 'perfil']);

    // Vista 1. Dashboard del observatorio
    Route::middleware('permiso:dashboard.ver')->prefix('dashboard')->group(function (){
        Route::get('resumen',                   [DashboardController::class, 'resumen']);
        Route::get('distribucion',              [DashboardController::class, 'distribucion']);
    });

    // Vista 2. Mapa de daños viales
    Route::middleware('permiso:mapa.ver')->prefix('mapa')->group(function (){
        Route::get('reportes',                  [MapaController::class, 'reportes']);
        Route::get('concentracion',             [MapaController::class, 'concentracion']);
    });

    Route::get('fotografias/{fotografia}', [FotografiaController::class, 'show'])
        ->whereUuid('fotografia');

    // Vista 3. Gestion de reportes
    Route::prefix('reportes')->group(function(){

        Route::post('recibir',                  [ReporteController::class, 'recibir'])
            ->middleware('permiso:reportes.crear');

        Route::get('mis-reportes',              [ReporteController::class, 'mis-reportes'])
            ->middleware('permiso:reportes.ver_propios');

        Route::get('/',                         [ReporteController::class, 'index'])
            ->middleware('permiso:reportes.ver_todos');

        Route::get('{reporte}',                 [ReporteController::class, 'show'])
            ->whereUuid('reporte');

        Route::get('{reporte}/fotografias',     [FotografiaController::class, 'porReporte'])
            ->whereUuid('reporte');

        Route::post('{reporte}/revision',       [ReporteController::class, 'abrirRevision'])
            ->middleware('permiso:reportes.validar')
            ->whereUuid('reporte');

        Route::post('{reporte}/duplicado',      [ReporteController::class, 'marcarDuplicado'])
            ->middleware('permiso:reportes.rechazar')
            ->whereUuid('reporte');

        Route::get('{reporte}/validaciones',    [ValidacionController::class, 'index'])
            ->middleware('permiso:reportes.ver_todos')
            ->whereUuid('reporte');

        Route::post('reporte/validaciones',     [ValidacionController::class, 'store'])
            ->middleware('permiso:reportes.validar')
            ->whereUuid('reporte');

        Route::get('{reporte}/priorizaciones',  [PriorizacionController::class, 'historial'])
            ->middleware('permiso:priorizacion.ver')
            ->whereUuid('reporte');

        Route::post('{reporte}/priorizacion',   [PriorizacionController::class,  'store'])
            ->middleware('permiso:priorizacion.asignar')
            ->whereUuid('reporte');
    });

    Route::post('deterioros/sugerir-gravedad',  [ValidacionController::class, 'sugerirGravedad'])
        ->middleware('permiso:reportes.validar');

    // Vista 4. Priorizacion e intervencion
    Route::get('priorizaciones',            [PriorizacionController::class, 'index'])
        ->middleware('permiso:priorizacion.ver');

    Route::prefix('intervenciones')->group(function(){

        Route::get('/',                         [IntervencionController::class, 'show'])
            ->middleware('permiso:intervenciones.ver');

        Route::post('/',                        [IntervencionController::class, 'store'])
            ->middleware('permiso:intervenciones.programar');

        Route::get('{intervencion}',            [IntervencionController::class, 'show'])
            ->middleware('permiso:intervenciones.ver')
            ->whereUuid('intervencion');

        Route::match(
            ['put', 'patch'], '{intervencion}', [IntervencionController::class, 'update'])
            ->middleware('permiso:intervenciones.programar')
            ->whereUuid('intervencion');

        Route::post('{intervencion}/estado',    [IntervencionController::class, 'cambiarEstado'])
            ->middleware('permiso:intervenciones.cambiar_estado')
            ->whereUuid('intervencion');

        Route::post('{intervencion}/reportes',  [IntervencionController::class, 'agregarReporte'])
            ->middleware('permiso:intervenciones.programar')
            ->whereUuid('intervencion');

        Route::delete(
            '{intervencion}/reportes/{reporte}',[IntervencionController::class, 'quitarReporte'])
            ->middleware('permiso:intervenciones.programar')
            ->whereUuid(['intervencion', 'reporte']);

        Route::get('{intervencion}/evidencieas',[IntervencionController::class, 'index'])
            ->middleware('permiso:intervenciones.ver')
            ->whereUuid('intervencion');

        Route::post('{intervencion}/evidencias',[IntervencionController::class, 'store'])
            ->middleware('permiso:intervenciones.cargar_evidencia')
            ->whereUuid('intervencion');
    });

    // Recepcion y auditoria
    Route::get('sincronizaciones',          [SincronizacionController::class, 'index'])
        ->middleware('permiso:reportes.ver_todos');

    Route::get('auditoria',                 [AuditoriaController::class, 'index'])
        ->middleware('permiso:auditoria.ver');

    // Administracion de usuarios
    Route::middleware('permiso:usuarios.gestionar')->group(function () {

        Route::apiResource('usuarios',      UsuarioController::class)->parameters(['usuarios' => 'id']);
        Route::put('usuarios/{id}/roles',   [UsuarioController::class, 'actualizarRoles']);
    });

    $catalogos = [
        'tipos-documento'       => TipoDocumentoController::class,
        'tipos-deterioro'       => TipoDeterioroController::class,
        'tipos-superficie'      => TipoSuperficieController::class,
        'severidades'           => SeveridadController::class,
        'prioridades'           => PrioridadController::class,
        'tipos-intervencion'    => TipoIntervencionController::class,
        'estados-reporte'       => EstadoReporteController::class,
        'estados-intervencion'  => EstadoIntervencionController::class,
        'criterios-gravedad'    => CriterioGravedadController::class,
        'comunas'               => ComunaController::class,
        'barrios'               => BarrioController::class,
        'campanas'              => CampanaController::class,
        'roles'                 => RolController::class,
        'permisos'              => PermisoController::class,
    ];

    foreach ($catalogos as $ruta => $controlador){
        Route::apiResource($ruta, $controlador)
            ->parameters([$ruta = 'id'])
            ->only($controlador::ACCIONES);
    }

    Route::put('roles/{id}/permisos',       [RolController::class, 'sincronizarPermisos']);
});
