<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluacion extends Model
{
    use HasUuids;

    protected $table = 'evaluaciones';

    protected $fillable = [
        'nomina_id', 'evaluador_id',
        'puntaje_docencia', 'puntaje_investigacion',
        'puntaje_vinculacion', 'puntaje_gestion', 'puntaje_formacion',
        'comentario', 'es_apelacion',
        'vigente_hasta', 'sin_calificacion', 'motivo_sc',
    ];

    protected function casts(): array
    {
        return [
            'es_apelacion'          => 'boolean',
            'sin_calificacion'      => 'boolean',
            'vigente_hasta'         => 'date',
            'puntaje_docencia'      => 'decimal:1',
            'puntaje_investigacion' => 'decimal:1',
            'puntaje_vinculacion'   => 'decimal:1',
            'puntaje_gestion'       => 'decimal:1',
            'puntaje_formacion'     => 'decimal:1',
        ];
    }

    public function notaFinalCad(?string $categoriaAcademica, ?CompromisoApa $compromiso = null): float
    {
        if (!$compromiso && $this->relationLoaded('nomina')) {
            $compromiso = $this->nomina->compromisoApa;
        }

        return \App\Services\CalificacionCadService::calcularDesdeEvaluacion(
            $this,
            $categoriaAcademica,
            $compromiso
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