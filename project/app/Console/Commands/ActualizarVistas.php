<?php

/*
 * Define un comando Laravel Artisan que actualiza
 * 3 vistas materializadas de PostreSQL usadas para el
 * dashboard.
 *
 * Uso:
 *
 * php artisan observatorio:actualizar-vistas
 *
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ActualizarVistas extends Command
{
    protected $signature = 'observatorio:actualizar-vistas';

    protected $description = 'Actualiza las vistas materializadas del dashboard, la distribucion territorial y la concentracion de daños.';

    public function handle(): int
    {
        foreach (
            [
                'vm_dashboard_resumen',
                'vm_distribucion_territorial',
                'vm_concentracion_danos'
            ]
        ) as $vista){
        DB::statement("REFRESH MATERIALIZED VIEW {$vista}");
        $this->info("Actualizada: {$vista}");
        }

        return self::SUCCESS;
    }
}
