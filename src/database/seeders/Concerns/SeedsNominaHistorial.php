<?php

namespace Database\Seeders\Concerns;

use App\Models\HistorialCalificacion;
use App\Models\HistorialCategoria;
use App\Models\Nomina;

trait SeedsNominaHistorial
{
    protected function seedNominaHistorial(Nomina $nomina, array $historial): void
    {
        foreach ($historial as $anio => $datos) {
            if (isset($datos['nota']) || isset($datos['concepto'])) {
                HistorialCalificacion::updateOrCreate(
                    ['nomina_id' => $nomina->id, 'anio' => $anio],
                    [
                        'nota'     => isset($datos['nota']) ? (float) $datos['nota'] : null,
                        'concepto' => $datos['concepto'] ?? null,
                    ]
                );
            }

            if (isset($datos['categoria'])) {
                HistorialCategoria::updateOrCreate(
                    ['nomina_id' => $nomina->id, 'anio' => $anio],
                    [
                        'categoria'            => $datos['categoria'],
                        'fecha_categorizacion' => $datos['fecha_categorizacion'] ?? null,
                    ]
                );
            }
        }
    }
}
