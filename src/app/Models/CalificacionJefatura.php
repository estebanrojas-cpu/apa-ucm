<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalificacionJefatura extends Model
{
    use HasUuids;

    protected $table = 'calificaciones_jefatura';

    protected $fillable = ['nomina_id', 'jefe_id', 'puntaje', 'comentario'];

    public function observaciones(): array
    {
        if (!$this->comentario) {
            return [];
        }
        $decoded = json_decode($this->comentario, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function observacionPorCategoria(string $slug): string
    {
        return $this->observaciones()[$slug] ?? '';
    }

    public function observacionGeneral(): string
    {
        return $this->observaciones()['observacion_general'] ?? '';
    }

    public function esInforme(): bool
    {
        return $this->puntaje === 0;
    }

    public function calificacionLabel(): string
    {
        if ($this->esInforme()) {
            return 'Informe';
        }

        return match(true) {
            $this->puntaje >= 80 => 'Muy Bueno',
            $this->puntaje >= 60 => 'Bueno',
            $this->puntaje >= 40 => 'Aceptable',
            default              => 'Deficiente',
        };
    }

    public function nomina(): BelongsTo
    {
        return $this->belongsTo(Nomina::class);
    }

    public function jefe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_id');
    }
}