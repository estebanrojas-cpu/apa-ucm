<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Cronograma extends Model
{
    use HasUuids;

    /** @var list<string> */
    public const ETAPAS = [
        'carga_evidencias',
        'validacion_secretario',
        'informe_jefatura',
        'evaluacion_cca',
        'comunicacion_resultados',
        'apelaciones',
        'registro_ccda',
        'revision_vicerrectoria',
    ];

    /** Bloque A: inician con el período y pueden solaparse. */
    public const ETAPAS_BLOQUE_A = [
        'carga_evidencias',
        'validacion_secretario',
        'informe_jefatura',
    ];

    /** Evaluación y comunicación CCA comparten ventana operativa. */
    public const ETAPA_ESPEJO_COMUNICACION = 'evaluacion_cca';

    protected $fillable = ['periodo_id', 'etapa', 'fecha_inicio', 'fecha_fin'];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin'    => 'date',
        ];
    }

    public function estaVigente(): bool
    {
        return Carbon::today()->between($this->fecha_inicio, $this->fecha_fin);
    }

    public function haVencido(): bool
    {
        return Carbon::today()->isAfter($this->fecha_fin);
    }

    public function haTerminado(): bool
    {
        return Carbon::today()->isAfter($this->fecha_fin);
    }

    public function esFutura(): bool
    {
        return Carbon::today()->isBefore($this->fecha_inicio);
    }

    public function scopeVigentes($query)
    {
        $hoy = Carbon::today()->toDateString();

        return $query->where('fecha_inicio', '<=', $hoy)->where('fecha_fin', '>=', $hoy);
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    public static function etiqueta(string $etapa): string
    {
        return match ($etapa) {
            'carga_evidencias'        => 'Carga de Evidencias',
            'validacion_secretario'   => 'Validación Secretario',
            'informe_jefatura'        => 'Informe Jefatura',
            'evaluacion_cca'          => 'Evaluación CCA',
            'comunicacion_resultados' => 'Comunicación de Resultados',
            'apelaciones'             => 'Apelaciones',
            'registro_ccda'           => 'Registro CCDA',
            'revision_vicerrectoria'  => 'Revisión Vicerrectoría',
            default                   => $etapa,
        };
    }

    /**
     * @param  array<string, string>  $finesPorEtapa  etapa => fecha_fin (Y-m-d)
     */
    public static function calcularFechaInicio(string $etapa, string $inicioPeriodo, array $finesPorEtapa): string
    {
        if (in_array($etapa, self::ETAPAS_BLOQUE_A, true)) {
            return $inicioPeriodo;
        }

        if ($etapa === 'comunicacion_resultados') {
            return self::calcularFechaInicio('evaluacion_cca', $inicioPeriodo, $finesPorEtapa);
        }

        $etapaPrevia = match ($etapa) {
            'evaluacion_cca'         => self::finBloqueA($finesPorEtapa),
            'apelaciones'            => $finesPorEtapa['evaluacion_cca'] ?? null,
            'registro_ccda'          => $finesPorEtapa['apelaciones'] ?? null,
            'revision_vicerrectoria' => $finesPorEtapa['registro_ccda'] ?? null,
            default                  => null,
        };

        if ($etapa === 'evaluacion_cca') {
            $finBloqueA = $etapaPrevia;
            if (!$finBloqueA) {
                throw new \InvalidArgumentException('Falta fecha de cierre del Bloque A.');
            }

            return Carbon::parse($finBloqueA)->addDay()->toDateString();
        }

        if (!$etapaPrevia) {
            throw new \InvalidArgumentException("No se puede calcular inicio para etapa: {$etapa}");
        }

        return Carbon::parse($etapaPrevia)->addDay()->toDateString();
    }

    /**
     * @param  list<array{etapa: string, fecha_fin: string}>  $cronograma
     * @return list<array{etapa: string, fecha_inicio: string, fecha_fin: string}>
     */
    public static function prepararParaGuardar(string $inicioPeriodo, array $cronograma): array
    {
        $finesPorEtapa = collect($cronograma)->pluck('fecha_fin', 'etapa')->all();

        if (isset($finesPorEtapa['evaluacion_cca'])) {
            $finesPorEtapa['comunicacion_resultados'] = $finesPorEtapa['evaluacion_cca'];
        }

        $resultado = [];

        foreach (self::ETAPAS as $etapa) {
            $fechaFin = $finesPorEtapa[$etapa] ?? null;
            if (!$fechaFin) {
                continue;
            }

            $resultado[] = [
                'etapa'        => $etapa,
                'fecha_inicio' => self::calcularFechaInicio($etapa, $inicioPeriodo, $finesPorEtapa),
                'fecha_fin'    => $fechaFin,
            ];
        }

        return $resultado;
    }

    /** @param  array<string, string>  $finesPorEtapa */
    private static function finBloqueA(array $finesPorEtapa): ?string
    {
        $fines = collect(self::ETAPAS_BLOQUE_A)
            ->map(fn (string $e) => $finesPorEtapa[$e] ?? null)
            ->filter()
            ->sort()
            ->values();

        return $fines->isEmpty() ? null : $fines->last();
    }
}
