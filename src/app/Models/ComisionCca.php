<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComisionCca extends Model
{
    use HasUuids;

    protected $table = 'comisiones_cca';

    protected $fillable = [
        'periodo_id',
        'facultad_id',
        'estado',
        'designado_por',
        'confirmada_en',
    ];

    protected function casts(): array
    {
        return [
            'confirmada_en' => 'datetime',
        ];
    }

    public function estaConfirmada(): bool
    {
        return $this->estado === 'confirmada';
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    public function facultad(): BelongsTo
    {
        return $this->belongsTo(Facultad::class);
    }

    public function designadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designado_por');
    }

    public function integrantes(): HasMany
    {
        return $this->hasMany(ComisionIntegrante::class);
    }

    public static function paraPeriodoFacultad(string $periodoId, string $facultadId): self
    {
        return static::firstOrCreate(
            ['periodo_id' => $periodoId, 'facultad_id' => $facultadId],
            ['estado' => 'borrador']
        );
    }
}
