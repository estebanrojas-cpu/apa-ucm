<?php

namespace App\Services;

use App\Models\ConfiguracionApa;
use App\Models\Nomina;
use App\Models\User;

class ApaContratoService
{
    /**
     * Horas de contrato efectivas para un semestre APA (S1 o S2).
     *
     * Prioridad: perfil del usuario → horas SAPD en nómina → base configurable (40 h).
     */
    public function horasSemestre(Nomina $nomina, string $semestre = 'S1', ?User $user = null): float
    {
        $user ??= $nomina->academico;
        $campo = $semestre === 'S1' ? 'horas_contrato_isem' : 'horas_contrato_iisem';

        if ($user && (int) $user->{$campo} > 0) {
            return (float) $user->{$campo};
        }

        if ($nomina->horas_contrato > 0) {
            $horas = (int) $nomina->horas_contrato;

            // Valores SAPD altos se interpretan como horas anuales (ej. 80 → 40/semestre).
            return $horas > 80 ? round($horas / 2, 1) : (float) $horas;
        }

        return (float) ConfiguracionApa::get('horas_semestre_base', 40);
    }

    /** @return array{categoria_academica: ?string, horas_contrato_isem: int, horas_contrato_iisem: int} */
    public function perfilDesdeNomina(Nomina $nomina): array
    {
        return [
            'categoria_academica'  => $this->mapearCategoriaUsuario($nomina->categoria),
            'horas_contrato_isem'  => (int) round($this->horasSemestre($nomina, 'S1')),
            'horas_contrato_iisem' => (int) round($this->horasSemestre($nomina, 'S2')),
        ];
    }

    public function mapearCategoriaUsuario(?string $categoria): ?string
    {
        return match (strtolower(trim($categoria ?? ''))) {
            'titular', 'jerarquizado' => 'titular',
            'adjunto'                 => 'adjunto',
            'auxiliar', 'instructor'  => 'auxiliar',
            default                   => null,
        };
    }
}
