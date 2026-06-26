<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Periodo extends Model
{
    use HasUuids;

    protected $fillable = [
        'anio', 'nombre', 'estado',
        'fecha_inicio', 'fecha_cierre', 'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio'  => 'date',
            'fecha_cierre'  => 'date',
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────
    public function estaActivo(): bool   { return $this->estado === 'activo'; }
    public function estaCerrado(): bool  { return $this->estado === 'cerrado'; }

    public function semestrePorNumero(int $numero): ?SemestreAcademico
    {
        if ($this->relationLoaded('semestres')) {
            return $this->semestres->firstWhere('numero', $numero);
        }

        return $this->semestres()->where('numero', $numero)->first();
    }

    /** Ambos semestres con fecha de cierre — requisito para declarar compromiso APA. */
    public function tieneSemestresApaConfigurados(): bool
    {
        return $this->semestrePorNumero(1)?->fecha_cierre !== null
            && $this->semestrePorNumero(2)?->fecha_cierre !== null;
    }

    // ── Scopes ───────────────────────────────────────────────────────────
    public function scopeActivo($query)
    {
        return $query->where('estado', 'activo')->latest();
    }

    // ── Relaciones ───────────────────────────────────────────────────────
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function cronogramas(): HasMany
    {
        return $this->hasMany(Cronograma::class);
    }

    public function nominas(): HasMany
    {
        return $this->hasMany(Nomina::class);
    }

    public function actas(): HasMany
    {
        return $this->hasMany(Acta::class);
    }

    public function semestres(): HasMany
    {
        return $this->hasMany(SemestreAcademico::class);
    }

    public function comisionesCca(): HasMany
    {
        return $this->hasMany(ComisionCca::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────
    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }
}