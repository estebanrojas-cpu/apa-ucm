<?php

namespace Database\Seeders\Concerns;

trait CastHorasContrato
{
    /** Horas semestrales de contrato para datos de prueba SAPD. */
    public static function horasContratoDemo(?string $tipoTrabajador): int
    {
        $tipo = mb_strtolower(trim($tipoTrabajador ?? ''));

        return str_contains($tipo, 'jornada completa') ? 40 : 24;
    }
}
