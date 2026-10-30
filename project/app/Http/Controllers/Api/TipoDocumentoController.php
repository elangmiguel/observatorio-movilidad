<?php

namespace App\Http\Controllers\Api;

use App\Models\TipoDocumento;
use Illuminate\Database\Eloquent\Model;

class TipoDocumentoController extends CatalogoController
{
    protected const MODELO = TipoDocumento::class;

    protected function reglas(?Model $registro = null): array
    {
        return $this->reglasBase('tipos_documento', $registro);
    }
}
