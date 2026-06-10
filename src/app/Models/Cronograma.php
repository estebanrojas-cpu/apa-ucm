<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cronograma extends Model
{
    use HasUuids;

    public const ETAPAS = [
        'carga_evidencias',
        'validacion_secretario',
        'evaluacion_cca',
        'consejo_facultad',
        'apelaciones',
        'revision_vicerrectoria',
        'cierre',
    ];

    public const ETIQUETAS = [
        'carga_evidencias'       => 'Carga de Evidencias',
        'validacion_secretario'  => 'Validación Secretario',
        'evaluacion_cca'         => 'Evaluación CCA',
        'consejo_facultad'       => 'Consejo de Facultad',
        'apelaciones'            => 'Apelaciones',
        'revision_vicerrectoria' => 'Revisión Vicerrectoría',
        'cierre'                 => 'Cierre',
    ];

    /** Etapas que inician en paralelo con el período */
    public const ETAPAS_PARALELAS = [
        'carga_evidencias',
        'validacion_secretario',
    ];

    protected $fillable = [
        'periodo_id', 'etapa', 'fecha_inicio', 'fecha_fin',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin'    => 'date',
        ];
    }

    public function estaVigente(): bool
    {
        $hoy = now()->toDateString();

        return $this->fecha_inicio->toDateString() <= $hoy
            && $this->fecha_fin->toDateString() >= $hoy;
    }

    public function haTerminado(): bool
    {
        return $this->fecha_fin->toDateString() < now()->toDateString();
    }

    public function esFutura(): bool
    {
        return $this->fecha_inicio->isFuture();
    }

    /**
     * @param  array<string, string>  $finesPorEtapa
     */
    public static function calcularFechaInicio(string $etapa, string $periodoInicio, array $finesPorEtapa): string
    {
        return match ($etapa) {
            'carga_evidencias', 'validacion_secretario' => $periodoInicio,
            'evaluacion_cca'         => $finesPorEtapa['carga_evidencias'],
            'consejo_facultad'       => $finesPorEtapa['evaluacion_cca'],
            'apelaciones'            => $finesPorEtapa['consejo_facultad'],
            'revision_vicerrectoria' => $finesPorEtapa['apelaciones'],
            'cierre'                 => $finesPorEtapa['revision_vicerrectoria'],
            default                  => throw new \InvalidArgumentException("Etapa desconocida: {$etapa}"),
        };
    }

    /**
     * @param  array<int, array{etapa: string, fecha_fin: string}>  $entradas
     * @return array<int, array{etapa: string, fecha_inicio: string, fecha_fin: string}>
     */
    public static function prepararParaGuardar(string $periodoInicio, array $entradas): array
    {
        $finesPorEtapa = collect($entradas)->pluck('fecha_fin', 'etapa')->all();

        return collect($entradas)->map(fn (array $entry) => [
            'etapa'        => $entry['etapa'],
            'fecha_inicio' => self::calcularFechaInicio($entry['etapa'], $periodoInicio, $finesPorEtapa),
            'fecha_fin'    => $entry['fecha_fin'],
        ])->all();
    }

    public static function etiqueta(string $etapa): string
    {
        return self::ETIQUETAS[$etapa] ?? $etapa;
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    public function scopeVigentes($query)
    {
        $hoy = now()->toDateString();

        return $query->where('fecha_inicio', '<=', $hoy)
            ->where('fecha_fin', '>=', $hoy);
    }

    public function scopeDeEtapa($query, string $etapa)
    {
        return $query->where('etapa', $etapa);
    }
}
