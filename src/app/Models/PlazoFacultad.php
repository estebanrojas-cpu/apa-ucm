<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlazoFacultad extends Model
{
    use HasUuids;

    protected $table    = 'plazos_facultad';
    protected $fillable = ['periodo_id', 'facultad_id', 'fecha_limite', 'creado_por'];

    protected function casts(): array
    {
        return ['fecha_limite' => 'date'];
    }

    public function estaVigente(): bool
    {
        return now()->toDateString() <= $this->fecha_limite->toDateString();
    }

    public function periodo(): BelongsTo   { return $this->belongsTo(Periodo::class); }
    public function facultad(): BelongsTo  { return $this->belongsTo(Facultad::class); }
    public function creadoPor(): BelongsTo { return $this->belongsTo(User::class, 'creado_por'); }
}
