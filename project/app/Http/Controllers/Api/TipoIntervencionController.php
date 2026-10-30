<?php

namespace App\Http\Controllers\Api;

use App\Models\TipoIntervencion;
use Illuminate\Database\Eloquent\Model;

class TipoIntervencionController extends CatalogoController
{
    protected const MODELO = TipoIntervencion::class;

    protected function reglas(?Model $registro = null): array
    {
        return $this->reglasBase('tipos_intervencion', $registro);
    }
}
