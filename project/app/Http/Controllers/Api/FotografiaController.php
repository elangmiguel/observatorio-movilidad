<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fotografia;
use App\Models\Reporte;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FotografiaController extends Controller
{
    /** Fotografías de un reporte, con su metadato EXIF y una URL temporal de consulta. */
    public function porReporte(Request $request, string $reporte): JsonResponse
    {
        $modelo = Reporte::findOrFail($reporte);
        $this->autorizar($request->user(), $modelo);

        $fotografias = Fotografia::where('reporte_id', $modelo->id)
            ->orderByDesc('es_principal')
            ->orderBy('subida_en')
            ->get()
            ->map(fn (Fotografia $f) => $this->conUrl($f));

        return response()->json($fotografias);
    }

    public function show(Request $request, string $fotografia): JsonResponse
    {
        $modelo = Fotografia::with('reporte')->findOrFail($fotografia);
        $this->autorizar($request->user(), $modelo->reporte);

        return response()->json($this->conUrl($modelo));
    }

    private function conUrl(Fotografia $fotografia): Fotografia
    {
        $fotografia->setAttribute(
            'url',
            Storage::disk('observatorio')->temporaryUrl($fotografia->url_almacenamiento, now()->addMinutes(15))
        );

        return $fotografia->makeHidden(['url_almacenamiento', 'hash_sha256']);
    }

    /** Ven las fotografías quienes tienen acceso al mapa con imágenes y el recolector que las tomó. */
    private function autorizar(Usuario $usuario, Reporte $reporte): void
    {
        if ($usuario->tienePermiso('mapa.ver_fotografias')) {
            return;
        }

        abort_unless(
            $usuario->tienePermiso('reportes.ver_propios') && $reporte->usuario_id === $usuario->id,
            403,
            'No tiene permiso para ver esta fotografía.'
        );
    }
}
