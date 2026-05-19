<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Nomina extends Model
{
    use HasUuids;

    protected $fillable = [
        'periodo_id', 'user_id', 'estado',
        'con_licencia', 'observacion_licencia', 'observacion_secretario',
    ];

    protected function casts(): array
    {
        return ['con_licencia' => 'boolean'];
    }

    // ── Helpers ──────────────────────────────────────────────────────────
    public function puedeCargarEvidencias(): bool
    {
        return in_array($this->estado, ['pendiente', 'en_carga']);
    }

    public function estaEnEvaluacion(): bool
    {
        return $this->estado === 'en_evaluacion';
    }

    // ── Relaciones ───────────────────────────────────────────────────────
    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    public function academico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(Evidencia::class);
    }

    public function evidenciasNormales(): HasMany
    {
        return $this->hasMany(Evidencia::class)->where('es_apelacion', false);
    }

    public function evidenciasApelacion(): HasMany
    {
        return $this->hasMany(Evidencia::class)->where('es_apelacion', true);
    }

    public function evaluaciones(): HasMany
    {
        return $this->hasMany(Evaluacion::class);
    }

    public function calificacionFinal(): HasOne
    {
        return $this->hasOne(CalificacionFinal::class)->latestOfMany();
    }

    public function calificacionJefatura(): HasOne
    {
        return $this->hasOne(CalificacionJefatura::class);
    }

    public function apelacion(): HasOne
    {
        return $this->hasOne(Apelacion::class)->latestOfMany();
    }

    // ── Scopes ───────────────────────────────────────────────────────────
    public function scopeDeEstado($query, string $estado)
    {
        return $query->where('estado', $estado);
    }
}