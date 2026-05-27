<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cronograma extends Model
{
    use HasUuids;

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

    // ── Helpers ──────────────────────────────────────────────────────────
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

    // ── Relaciones ───────────────────────────────────────────────────────
    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────
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
