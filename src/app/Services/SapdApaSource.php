<?php

namespace App\Services;

use App\Contracts\ApaDataSource;
use App\Models\Nomina;

/**
 * Integración futura con SAPD. Por ahora no está conectado.
 */
class SapdApaSource implements ApaDataSource
{
    public function getCompromisos(Nomina $nomina): ?array
    {
        return null;
    }
}
