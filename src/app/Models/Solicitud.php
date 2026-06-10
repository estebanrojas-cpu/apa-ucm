<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Solicitud extends Model
{
    use HasUuids;

    protected $table = 'solicitudes';

    protected $fillable = [
        'nomina_id', 'tipo', 'fecha_inicio', 'fecha_fin',
        'motivo', 'documento_adjunto', 'estado',
        'creado_por', 'iniciada_por', 'aprobada_por',
        'fecha_aprobacion', 'motivo_rechazo',
        'fecha_reincorporacion', 'reincorporado_por',
        'motivo_reincorporacion', 'nuevo_plazo_evidencias',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio'           => 'date',
            'fecha_fin'              => 'date',
            'fecha_aprobacion'       => 'datetime',
            'fecha_reincorporacion'  => 'datetime',
            'nuevo_plazo_evidencias' => 'date',
        ];
    }

    public function nomina(): BelongsTo
    {
        return $this->belongsTo(Nomina::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function iniciadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iniciada_por');
    }

    public function aprobadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobada_por');
    }

    public function reincorporadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reincorporado_por');
    }

    public function estaActiva(): bool
    {
        return $this->estado === 'activa';
    }

    public function esLicenciaMedica(): bool
    {
        return $this->tipo === 'licencia_medica';
    }

    public function labelTipo(): string
    {
        return match ($this->tipo) {
            'licencia_medica'  => 'Licencia médica',
            'extension_plazo'  => 'Extensión de plazo',
            default            => $this->tipo,
        };
    }

    public function labelEstado(): string
    {
        return match ($this->estado) {
            'activa'  => 'Activa',
            'cerrada' => 'Cerrada',
            default   => $this->estado,
        };
    }

    public function scopeActivas($query)
    {
        return $query->where('estado', 'activa');
    }

}
