<?php

namespace App\Services;

use App\Models\CompromisoApa;
use App\Models\Evaluacion;
use Illuminate\Support\Collection;

class CalificacionCadService
{
    /** @var list<string> */
    public const AREAS_HORAS_PRINCIPALES = ['docencia', 'investigacion', 'extension', 'administracion'];

    /** clave reglamento → columna hrs_* */
    public const REG_A_AREA_HRS = [
        'docencia'      => 'docencia',
        'investigacion' => 'investigacion',
        'vinculacion'   => 'extension',
        'gestion'       => 'administracion',
    ];
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
     * % tiempo asignado a partir de horas sumadas S1+S2 por área (sin otras).
     *
     * @param  array<string, float>  $sumHoras  claves: docencia, investigacion, extension, administracion
     * @return array<string, float>  claves: docencia, investigacion, vinculacion, gestion, formacion
     */
    public static function pesosDesdeHorasSumadas(array $sumHoras, ?string $categoria = null): array
    {
        $total = 0.0;
        foreach (self::AREAS_HORAS_PRINCIPALES as $area) {
            $total += (float) ($sumHoras[$area] ?? 0);
        }

        if ($total <= 0) {
            return self::pesosParaCategoria($categoria);
        }

        $mapa    = ['docencia' => 'docencia', 'investigacion' => 'investigacion', 'extension' => 'vinculacion', 'administracion' => 'gestion'];
        $pesos   = ['formacion' => 0.0];
        $suma    = 0.0;
        $lastKey = null;

        foreach ($mapa as $area => $key) {
            $hrs = (float) ($sumHoras[$area] ?? 0);
            if ($hrs > 0) {
                $pct         = round($hrs / $total * 100, 2);
                $pesos[$key] = $pct;
                $suma       += $pct;
                $lastKey     = $key;
            } else {
                $pesos[$key] = 0.0;
            }
        }

        if ($lastKey !== null) {
            $pesos[$lastKey] = round(100 - ($suma - $pesos[$lastKey]), 2);
        }

        return $pesos;
    }

    /**
     * @param  Collection<int, Evaluacion>  $evaluaciones
     * @return array{S1: array<string, float>, S2: array<string, float>}|null
     */
    public static function horasRealesPromedioPorSemestre(Collection $evaluaciones): ?array
    {
        $valid = $evaluaciones->filter(fn (Evaluacion $e) => $e->tieneHorasRealesCompletas());
        if ($valid->isEmpty()) {
            return null;
        }

        $out = ['S1' => [], 'S2' => []];
        foreach (Evaluacion::SEMESTRES as $sem) {
            foreach (self::AREAS_HORAS_PRINCIPALES as $area) {
                $col = Evaluacion::columnaHorasReal($area, $sem);
                $out[$sem][$area] = round((float) $valid->avg($col), 2);
            }
        }

        return $out;
    }

    /** @return array<string, float> */
    public static function sumarHorasAnualesDesdeSemestres(array $horasPorSemestre): array
    {
        $sum = array_fill_keys(self::AREAS_HORAS_PRINCIPALES, 0.0);
        foreach (Evaluacion::SEMESTRES as $sem) {
            foreach (self::AREAS_HORAS_PRINCIPALES as $area) {
                $sum[$area] += (float) ($horasPorSemestre[$sem][$area] ?? 0);
            }
        }

        return $sum;
    }

    /**
     * nota_final = min(Σ(%T_i × N_i) / 100 + extra, 5.0)
     *
     * @param  array<string, float|int|string>  $notas  slug => nota 1.0–5.0
     * @param  array<string, int|float>  $pesos  slug => %T
     * @param  float  $extra  bonus por otras actividades (0, 0.1, 0.2, 0.3)
     */
    public static function calcularNotaFinal(array $notas, array $pesos, float $extra = 0.0): float
    {
        $suma = 0.0;

        foreach (self::CAMPOS as $slug => $campo) {
            $nota  = (float) ($notas[$slug] ?? $notas[$campo] ?? 0);
            $peso  = (float) ($pesos[$slug] ?? 0);
            $suma += ($peso * $nota) / 100;
        }

        return round(min($suma + $extra, 5.0), 2);
    }

    public static function calcularDesdeEvaluacion(object $evaluacion, ?string $categoriaAcademica, array|\App\Models\CompromisoApa|null $compromiso = null): float
    {
        if (!empty($evaluacion->sin_calificacion)) {
            return 0.0;
        }

        $notas = [];
        foreach (self::CAMPOS as $slug => $campo) {
            $notas[$slug] = (float) $evaluacion->{$campo};
        }

        $extra = (float) ($evaluacion->extra_otras_actividades ?? 0.0);

        if (is_array($compromiso)) {
            $pesos = $compromiso;
        } elseif ($evaluacion instanceof Evaluacion) {
            $pesos = $evaluacion->pesosParaCalificacion($categoriaAcademica);
        } else {
            $pesos = self::pesosDesdeCompromiso($compromiso, $categoriaAcademica);
        }

        return self::calcularNotaFinal($notas, $pesos, $extra);
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

    /** Apelaciones Regular/Deficiente → CCDA (2° nivel); demás conceptos → CCA. */
    public static function destinoApelacionParaConcepto(?string $concepto): string
    {
        return in_array($concepto, ['regular', 'deficiente'], true) ? 'ccda' : 'cca';
    }

    public static function labelDestinoApelacion(string $destino): string
    {
        return $destino === 'ccda' ? 'CCDA (2° nivel)' : 'CCA';
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
