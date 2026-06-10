<?php

namespace App\Services;

use App\Models\CompromisoApa;

class CalificacionCadService
{
    /** @var array<string, string> slug APA → campo evaluación */
    public const CAMPOS = [
        'docencia'      => 'puntaje_docencia',
        'investigacion' => 'puntaje_investigacion',
        'vinculacion'   => 'puntaje_vinculacion',
        'gestion'       => 'puntaje_gestion',
        'formacion'     => 'puntaje_formacion',
    ];

    /** slug CategoriaApa → clave reglamento */
    public const SLUG_A_REGLAMENTO = [
        'docencia'           => 'docencia',
        'investigacion'      => 'investigacion',
        'vinculacion'        => 'vinculacion',
        'gestion'            => 'gestion',
        'formacion_continua' => 'formacion',
    ];

    public static function pesosParaCategoria(?string $categoria): array
    {
        $categoria = $categoria ?: 'adjunto';

        return config("reglamento_apa.{$categoria}", config('reglamento_apa.adjunto'));
    }

    /**
     * % APA desde compromiso confirmado; fallback al reglamento por categoría.
     *
     * @return array<string, float|int>
     */
    public static function pesosDesdeCompromiso(?CompromisoApa $compromiso, ?string $categoria): array
    {
        if ($compromiso && $compromiso->estaConfirmado()) {
            return $compromiso->toPesosArray();
        }

        return self::pesosParaCategoria($categoria);
    }

    /** @deprecated Use pesosDesdeCompromiso */
    public static function pesosDesdeNomina(?object $nomina, ?string $categoria): array
    {
        return self::pesosParaCategoria($categoria);
    }

    /**
     * nota_final = min(Σ(%T_i × N_i) / 100, 5.0)
     *
     * @param  array<string, float|int|string>  $notas  slug => nota 1.0–5.0
     * @param  array<string, int|float>  $pesos  slug => %T
     */
    public static function calcularNotaFinal(array $notas, array $pesos): float
    {
        $suma = 0.0;

        foreach (self::CAMPOS as $slug => $campo) {
            $nota  = (float) ($notas[$slug] ?? $notas[$campo] ?? 0);
            $peso  = (float) ($pesos[$slug] ?? 0);
            $suma += ($peso * $nota) / 100;
        }

        return round(min($suma, 5.0), 2);
    }

    public static function calcularDesdeEvaluacion(object $evaluacion, ?string $categoriaAcademica, ?\App\Models\CompromisoApa $compromiso = null): float
    {
        if (!empty($evaluacion->sin_calificacion)) {
            return 0.0;
        }

        $notas = [];
        foreach (self::CAMPOS as $slug => $campo) {
            $notas[$slug] = (float) $evaluacion->{$campo};
        }

        return self::calcularNotaFinal(
            $notas,
            self::pesosDesdeCompromiso($compromiso, $categoriaAcademica)
        );
    }

    public static function vigenteHasta(?string $categoria): \Carbon\Carbon
    {
        return $categoria === 'auxiliar'
            ? now()->addYear()
            : now()->addYears(2);
    }

    public static function conceptoDesdeNota(float $nota): string
    {
        return match (true) {
            $nota >= 4.5 => 'excelente',
            $nota >= 4.0 => 'muy_bueno',
            $nota >= 3.5 => 'bueno',
            $nota >= 2.7 => 'regular',
            default      => 'deficiente',
        };
    }

    public static function labelConcepto(string $concepto): string
    {
        return match ($concepto) {
            'excelente'  => 'Excelente',
            'muy_bueno'  => 'Muy Bueno',
            'bueno'      => 'Bueno',
            'regular'    => 'Regular',
            'aceptable'  => 'Aceptable',
            'deficiente' => 'Deficiente',
            default      => $concepto,
        };
    }

    public static function labelCategoria(?string $cat): string
    {
        return match ($cat) {
            'titular'  => 'Titular',
            'adjunto'  => 'Adjunto',
            'auxiliar' => 'Auxiliar',
            default    => '—',
        };
    }

    public static function labelLinea(?string $linea): string
    {
        return match ($linea) {
            'docente'       => 'Docente',
            'investigador'  => 'Investigador',
            'mixta'         => 'Mixta',
            default         => '—',
        };
    }
}
