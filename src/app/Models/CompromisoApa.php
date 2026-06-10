<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompromisoApa extends Model
{
    use HasUuids;

    protected $table = 'compromisos_apa';

    protected $fillable = [
        'nomina_id', 'periodo_id',
        'pct_docencia', 'pct_investigacion', 'pct_extension',
        'pct_administracion', 'pct_otras',
        'fuente', 'confirmado_en', 'modificado_por', 'modificado_en',
    ];

    protected function casts(): array
    {
        return [
            'pct_docencia'       => 'decimal:2',
            'pct_investigacion'  => 'decimal:2',
            'pct_extension'      => 'decimal:2',
            'pct_administracion' => 'decimal:2',
            'pct_otras'          => 'decimal:2',
            'confirmado_en'      => 'datetime',
            'modificado_en'      => 'datetime',
        ];
    }

    public function estaConfirmado(): bool
    {
        return $this->confirmado_en !== null;
    }

    public function sumaPorcentajes(): float
    {
        return (float) $this->pct_docencia
            + (float) $this->pct_investigacion
            + (float) $this->pct_extension
            + (float) $this->pct_administracion
            + (float) $this->pct_otras;
    }

    /** @return array<string, float> */
    public function toPesosArray(): array
    {
        return [
            'docencia'      => (float) $this->pct_docencia,
            'investigacion' => (float) $this->pct_investigacion,
            'vinculacion'   => (float) $this->pct_extension,
            'gestion'       => (float) $this->pct_administracion,
            'formacion'     => (float) $this->pct_otras,
        ];
    }

    public function nomina(): BelongsTo
    {
        return $this->belongsTo(Nomina::class);
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    public function modificadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modificado_por');
    }
}
