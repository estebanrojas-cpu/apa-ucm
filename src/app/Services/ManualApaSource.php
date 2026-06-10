<?php

namespace App\Services;

use App\Contracts\ApaDataSource;
use App\Models\CompromisoApa;
use App\Models\Nomina;

class ManualApaSource implements ApaDataSource
{
    public function getCompromisos(Nomina $nomina): ?array
    {
        $compromiso = CompromisoApa::where('nomina_id', $nomina->id)
            ->where('periodo_id', $nomina->periodo_id)
            ->whereNotNull('confirmado_en')
            ->first();

        return $compromiso?->toPesosArray();
    }
}
