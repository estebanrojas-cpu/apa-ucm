<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluacion extends Model
{
    use HasUuids;

    protected $table = 'evaluaciones';

    /** @var list<string> */
    public const AREAS_HORAS = ['docencia', 'investigacion', 'extension', 'administracion', 'otras'];

    /** @var list<string> */
    public const SEMESTRES = ['S1', 'S2'];

    protected $fillable = [
        'nomina_id', 'evaluador_id',
        'puntaje_docencia', 'puntaje_investigacion',
        'puntaje_vinculacion', 'puntaje_gestion', 'puntaje_formacion',
        'extra_otras_actividades',
        'hrs_real_docencia_s1', 'hrs_real_investigacion_s1', 'hrs_real_extension_s1',
        'hrs_real_administracion_s1', 'hrs_real_otras_s1',
        'hrs_real_docencia_s2', 'hrs_real_investigacion_s2', 'hrs_real_extension_s2',
        'hrs_real_administracion_s2', 'hrs_real_otras_s2',
        'comentario', 'es_apelacion',
        'vigente_hasta', 'sin_calificacion', 'motivo_sc',
    ];

    protected function casts(): array
    {
        return [
            'es_apelacion'             => 'boolean',
            'sin_calificacion'         => 'boolean',
            'vigente_hasta'            => 'date',
            'puntaje_docencia'         => 'decimal:1',
            'puntaje_investigacion'    => 'decimal:1',
            'puntaje_vinculacion'      => 'decimal:1',
            'puntaje_gestion'          => 'decimal:1',
            'puntaje_formacion'        => 'decimal:1',
            'extra_otras_actividades'  => 'decimal:1',
            'hrs_real_docencia_s1'       => 'decimal:2',
            'hrs_real_investigacion_s1'  => 'decimal:2',
            'hrs_real_extension_s1'      => 'decimal:2',
            'hrs_real_administracion_s1' => 'decimal:2',
            'hrs_real_otras_s1'          => 'decimal:2',
            'hrs_real_docencia_s2'       => 'decimal:2',
            'hrs_real_investigacion_s2'  => 'decimal:2',
            'hrs_real_extension_s2'      => 'decimal:2',
            'hrs_real_administracion_s2' => 'decimal:2',
            'hrs_real_otras_s2'          => 'decimal:2',
        ];
    }

    public static function columnaHorasReal(string $area, string $semestre): string
    {
        return 'hrs_real_'.$area.'_'.strtolower($semestre);
    }

    /** @return array<string, array<string, float|null>> */
    public function horasRealesArray(): array
    {
        $out = [];

        foreach (self::SEMESTRES as $sem) {
            $suffix = strtolower($sem);
            foreach (self::AREAS_HORAS as $area) {
                $col = "hrs_real_{$area}_{$suffix}";
                $out[$sem]["hrs_{$area}"] = $this->{$col} !== null ? (float) $this->{$col} : null;
            }
        }

        return $out;
    }

    /** @param  array<string, array<string, mixed>>|null  $horasReales */
    public static function horasRealesDesdeRequest(?array $horasReales): array
    {
        $attrs = [];

        foreach (self::SEMESTRES as $sem) {
            foreach (self::AREAS_HORAS as $area) {
                $key = self::columnaHorasReal($area, $sem);
                $raw = $horasReales[$sem]["hrs_{$area}"] ?? null;
                $attrs[$key] = $raw === null || $raw === ''
                    ? null
                    : round((float) $raw, 2);
            }
        }

        return $attrs;
    }

    public function tieneHorasRealesCompletas(): bool
    {
        foreach (self::SEMESTRES as $sem) {
            foreach (['docencia', 'investigacion', 'extension', 'administracion'] as $area) {
                if ($this->{self::columnaHorasReal($area, $sem)} === null) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return array<string, float>|null */
    public function pesosDesdeHorasReales(?string $categoria = null): ?array
    {
        if (!$this->tieneHorasRealesCompletas()) {
            return null;
        }

        $sumHoras = [];
        foreach (\App\Services\CalificacionCadService::AREAS_HORAS_PRINCIPALES as $area) {
            $sumHoras[$area] = 0.0;
            foreach (self::SEMESTRES as $sem) {
                $sumHoras[$area] += (float) $this->{self::columnaHorasReal($area, $sem)};
            }
        }

        return \App\Services\CalificacionCadService::pesosDesdeHorasSumadas($sumHoras, $categoria);
    }

    /** @return array<string, float> */
    public function pesosParaCalificacion(?string $categoria = null): array
    {
        if ($pesos = $this->pesosDesdeHorasReales($categoria)) {
            return $pesos;
        }

        $this->loadMissing('nomina');

        return $this->nomina->pesosApa($categoria ?? $this->nomina->categoriaEfectiva());
    }

    public function notaFinalCad(?string $categoriaAcademica, array|\App\Models\CompromisoApa|null $compromiso = null): float
    {
        if (is_array($compromiso)) {
            $pesos = $compromiso;
        } else {
            $this->loadMissing('nomina');
            $pesos = $this->pesosParaCalificacion($categoriaAcademica);
        }

        return \App\Services\CalificacionCadService::calcularDesdeEvaluacion(
            $this,
            $categoriaAcademica,
            $pesos
        );
    }

    /** @deprecated Use notaFinalCad() — kept for backward compat in views */
    public function puntajeTotal(): int
    {
        return (int) round(
            $this->puntaje_docencia + $this->puntaje_investigacion
            + $this->puntaje_vinculacion + $this->puntaje_gestion + $this->puntaje_formacion
        );
    }

    public function nomina(): BelongsTo
    {
        return $this->belongsTo(Nomina::class);
    }

    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluador_id');
    }

    public function comentariosVicerrectora(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ComentarioVicerrectora::class);
    }
}