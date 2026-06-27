<?php

namespace App\Models;

use App\Enums\CargoFacultad;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionCargo extends Model
{
    use HasUuids;

    protected $table = 'asignaciones_cargo';

    protected $fillable = [
        'periodo_id', 'facultad_id', 'nomina_id', 'slot', 'cargo', 'asignado_por',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    public function facultad(): BelongsTo
    {
        return $this->belongsTo(Facultad::class);
    }

    public function nomina(): BelongsTo
    {
        return $this->belongsTo(Nomina::class);
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }

    public function cargoEnum(): CargoFacultad
    {
        return CargoFacultad::from($this->cargo);
    }

    public function label(): string
    {
        return $this->cargoEnum()->label();
    }
}
