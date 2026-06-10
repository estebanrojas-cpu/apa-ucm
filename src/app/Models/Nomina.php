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
        'periodo_id', 'user_id', 'facultad_id', 'estado',
        'con_licencia', 'observacion_licencia', 'plazo_licencia', 'documento_licencia',
        'observacion_secretario',
        // Campos SAPD UCM
        'numero_personal', 'rut', 'nombre', 'adscripcion_academica',
        'unidad_superior', 'unidad', 'nombre_posicion', 'tipo_trabajador',
        'fecha_inicio_contrato', 'horas_contrato', 'categoria', 'fecha_categorizacion',
        'datos_adicionales',
    ];

    protected function casts(): array
    {
        return [
            'con_licencia'          => 'boolean',
            'plazo_licencia'        => 'date',
            'fecha_inicio_contrato' => 'date',
            'fecha_categorizacion'  => 'date',
            'datos_adicionales'     => 'array',
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function puedeCargarEvidencias(): bool
    {
        return in_array($this->estado, ['pendiente', 'en_carga']);
    }

    public function cargaEvidenciasHabilitada(): bool
    {
        if (!$this->puedeCargarEvidencias()) {
            return false;
        }

        $facultadId = $this->facultad_id ?? $this->academico?->facultad_id;
        if (!$facultadId) {
            return false;
        }

        $plazo = PlazoFacultad::where('periodo_id', $this->periodo_id)
            ->where('facultad_id', $facultadId)
            ->first();

        if ($plazo?->estaCerradoFormalmente()) {
            return false;
        }

        $plazoIndividualVigente = $this->plazo_licencia
            && $this->plazo_licencia->toDateString() >= now()->toDateString();

        if ($this->con_licencia && !$plazoIndividualVigente) {
            return false;
        }

        if ($plazoIndividualVigente) {
            return true;
        }

        $etapaCarga = Cronograma::where('periodo_id', $this->periodo_id)
            ->where('etapa', 'carga_evidencias')
            ->first();

        if ($etapaCarga?->haTerminado()) {
            return false;
        }

        return $plazo === null || $plazo->estaVigente();
    }

    public function estaEnEvaluacion(): bool
    {
        return $this->estado === 'en_evaluacion';
    }

    /**
     * Categoría efectiva: primero del campo SAPD de la nómina,
     * luego del perfil del usuario (compatibilidad legada).
     */
    public function categoriaEfectiva(): string
    {
        return $this->categoria
            ?? $this->academico?->categoria_academica
            ?? 'adjunto';
    }

    /**
     * Nota vigente más reciente desde historial_calificaciones.
     * Cae al campo legado en el usuario si no hay historial.
     */
    public function notaAnterior(): ?float
    {
        $h = $this->historialCalificaciones()
            ->whereNotNull('nota')
            ->orderByDesc('anio')
            ->first();

        return $h ? (float) $h->nota : ($this->academico?->nota_anterior ? (float) $this->academico->nota_anterior : null);
    }

    public function conceptoAnterior(): ?string
    {
        $h = $this->historialCalificaciones()
            ->whereNotNull('nota')
            ->orderByDesc('anio')
            ->first();

        return $h?->concepto ?? $this->academico?->concepto_anterior;
    }

    /**
     * ¿La nota sigue vigente según la categoría y fecha de categorización?
     * - Auxiliar: 1 año   — Adjunto/Titular: 2 años
     */
    public function notaVigente(): bool
    {
        if (!$this->fecha_categorizacion || !$this->categoria) {
            return false;
        }
        $meses = match (strtolower($this->categoria)) {
            'auxiliar' => 12,
            default    => 24,
        };

        return now()->diffInMonths($this->fecha_categorizacion, false) > -$meses;
    }

    public function fechaVencimientoNota(): ?\Carbon\Carbon
    {
        if (!$this->fecha_categorizacion || !$this->categoria) {
            return null;
        }
        $meses = match (strtolower($this->categoria)) {
            'auxiliar' => 12,
            default    => 24,
        };

        return $this->fecha_categorizacion->copy()->addMonths($meses);
    }

    public function tieneCompromisoApaConfirmado(): bool
    {
        return $this->compromisoApa && $this->compromisoApa->estaConfirmado();
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

    public function facultad(): BelongsTo
    {
        return $this->belongsTo(Facultad::class);
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

    public function historialCalificaciones(): HasMany
    {
        return $this->hasMany(HistorialCalificacion::class)->orderByDesc('anio');
    }

    public function historialCategorias(): HasMany
    {
        return $this->hasMany(HistorialCategoria::class)->orderByDesc('anio');
    }

    public function compromisoApa(): HasOne
    {
        return $this->hasOne(CompromisoApa::class);
    }

    public function calificacionFinal(): HasOne
    {
        return $this->hasOne(CalificacionFinal::class)
            ->whereRaw(
                '"calificaciones_finales"."id" = (
                    SELECT cf.id
                    FROM calificaciones_finales cf
                    WHERE cf.nomina_id = "calificaciones_finales"."nomina_id"
                    ORDER BY cf.created_at DESC
                    LIMIT 1
                )'
            );
    }

    public function calificacionJefatura(): HasOne
    {
        return $this->hasOne(CalificacionJefatura::class);
    }

    public function apelacion(): HasOne
    {
        return $this->hasOne(Apelacion::class)
            ->whereRaw(
                '"apelaciones"."id" = (
                    SELECT a.id
                    FROM apelaciones a
                    WHERE a.nomina_id = "apelaciones"."nomina_id"
                    ORDER BY a.created_at DESC
                    LIMIT 1
                )'
            );
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class);
    }

    public function solicitudActiva(string $tipo = 'licencia_medica'): ?Solicitud
    {
        return $this->solicitudes()
            ->where('tipo', $tipo)
            ->where('estado', 'activa')
            ->latest()
            ->first();
    }

    public function tieneLicenciaMedicaActiva(): bool
    {
        return $this->solicitudActiva('licencia_medica') !== null;
    }

    public function estadoReporte(): string
    {
        $licencia = $this->solicitudActiva('licencia_medica');
        if ($licencia) {
            $hasta = $licencia->fecha_fin?->format('d/m/Y') ?? 'indefinido';

            return "Pendiente hasta {$hasta}";
        }

        $sinCalif = $this->evaluaciones
            ->where('es_apelacion', false)
            ->first(fn ($e) => $e->sin_calificacion ?? false);

        if ($sinCalif) {
            $motivo = $sinCalif->motivo_sc ? " — {$sinCalif->motivo_sc}" : '';

            return "S/C{$motivo}";
        }

        if (in_array($this->estado, ['evaluado', 'cerrado'])) {
            return 'Evaluado';
        }

        return 'Sin evaluar';
    }

    public function tieneSolicitudLicenciaPendiente(): bool
    {
        return $this->solicitudes()
            ->where('tipo', 'licencia_medica')
            ->where('estado', 'pendiente_aprobacion')
            ->exists();
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeDeEstado($query, string $estado)
    {
        return $query->where('estado', $estado);
    }
}
