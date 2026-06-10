<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialCalificacion extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'historial_calificaciones';

    protected $fillable = [
        'nomina_id', 'anio', 'nota', 'concepto',
        'observacion', 'resumen', 'proceso', 'informe_path',
    ];

    protected function casts(): array
    {
        return [
            'nota'       => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function nomina(): BelongsTo
    {
        return $this->belongsTo(Nomina::class);
    }
}
