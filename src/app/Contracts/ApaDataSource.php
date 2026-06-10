<?php

namespace App\Contracts;

use App\Models\Nomina;

interface ApaDataSource
{
    /**
     * @return array<string, float>|null  Claves reglamento: docencia, investigacion, vinculacion, gestion, formacion
     */
    public function getCompromisos(Nomina $nomina): ?array;
}
