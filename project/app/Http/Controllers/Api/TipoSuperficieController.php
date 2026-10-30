<?php

namespace App\Http\Controllers\Api;

use App\Models\TipoSuperficie;
use Illuminate\Database\Eloquent\Model;

class TipoSuperficieController extends CatalogoController
{
    protected const MODELO = TipoSuperficie::class;

    protected function reglas(?Model $registro = null): array
    {
        return $this->reglasBase('tipos_superficie', $registro);
    }
}
