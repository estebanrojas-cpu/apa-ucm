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
        return !$this->esSoloDaConocer()
            && in_array($this->estado, ['pendiente', 'en_carga']);
    }

    /**
     * Ciclo evaluativo APA en semestres: auxiliar cada 2, adjunto/titular cada 4.
     */
    public function cicloEvaluacionSemestres(): int
    {
        return match (strtolower($this->categoriaEfectiva())) {
            'auxiliar', 'instructor' => 2,
            default                  => 4,
        };
    }

    /**
     * ¿Corresponde evaluación formal CCA este período?
     * Mientras la nota anterior siga vigente, solo se registra la declaración APA.
     */
    public function participaEvaluacionFormal(): bool
    {
        if ($this->esSoloDaConocer()) {
            return false;
        }

        return !$this->notaVigente();
    }

    public function horasContratoSemestre(string $semestre): float
    {
        return app(\App\Services\ApaContratoService::class)->horasSemestre($this, $semestre);
    }

    public function horasContratoEvaluacion(): float
    {
        return $this->horasContratoSemestre('S1') * $this->cicloEvaluacionSemestres();
    }

    /** Declaración APA semestral (todos los evaluables, incluso en años de solo registro). */
    public function puedeDeclararApa(): bool
    {
        if ($this->esSoloDaConocer()) {
            return false;
        }
        if (!in_array($this->estado, ['pendiente', 'en_carga'])) {
            return false;
        }

        return $this->plazoApaVigente();
    }

    public function cargaEvidenciasHabilitada(): bool
    {
        if ($this->esSoloDaConocer()) {
            return false;
        }
        if (!$this->participaEvaluacionFormal()) {
            return false;
        }
        if (!$this->puedeCargarEvidencias()) {
            return false;
        }

        return $this->plazoApaVigente();
    }

    /** Explica por qué la carga de evidencias está bloqueada (null = habilitada o sin nómina). */
    public function motivoBloqueoCargaEvidencias(): ?string
    {
        if ($this->esSoloDaConocer()) {
            return ($this->labelExclusionEvaluacion() ?? 'Se da a conocer') . '. No participa del proceso evaluativo.';
        }

        if (!$this->participaEvaluacionFormal()) {
            $semestres = $this->cicloEvaluacionSemestres();
            $vence     = $this->fechaVencimientoNota()?->format('d/m/Y');

            return "Su calificación anterior sigue vigente"
                . ($vence ? " (hasta {$vence})" : '')
                . ". Este período solo registra la declaración APA por semestre; "
                . "podrá cargar evidencias cuando corresponda evaluación formal (ciclo de {$semestres} semestres).";
        }

        if (!$this->puedeCargarEvidencias()) {
            return match ($this->estado) {
                'carga_cerrada', 'en_evaluacion', 'evaluado', 'cerrado'
                    => 'El expediente ya fue cerrado para recepción de evidencias.',
                default => 'El expediente no está habilitado para carga en este estado.',
            };
        }

        if (!$this->plazoApaVigente()) {
            return 'El plazo de carga de evidencias de su facultad no está vigente.';
        }

        return null;
    }

    /** Plazo de facultad / cronograma vigente para declaración APA. */
    protected function plazoApaVigente(): bool
    {
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
        return !$this->esSoloDaConocer() && $this->estado === 'en_evaluacion';
    }

    /**
     * Cargos / roles excluidos de la evaluación APA formal (solo se da a conocer).
     * El secretario sí se evalúa: es académico con rol administrativo adicional.
     */
    public function esSoloDaConocer(): bool
    {
        if ($this->esDecano() || $this->esDirectorDepartamento()) {
            return true;
        }

        $user = $this->academico;
        if (!$user) {
            return false;
        }

        return $user->hasAnyAssignedRole(['analista_ccda', 'vicerrectora']);
    }

    /** Decano/a de facultad. */
    public function esDecano(): bool
    {
        $posicion = mb_strtolower($this->nombre_posicion ?? '');

        return str_contains($posicion, 'decana') || str_contains($posicion, 'decano');
    }

    /** Director/a de departamento académico. */
    public function esDirectorDepartamento(): bool
    {
        $posicion = mb_strtolower($this->nombre_posicion ?? '');

        if (!str_contains($posicion, 'director') && !str_contains($posicion, 'directora')) {
            return false;
        }

        return str_contains($posicion, 'departamento') || str_contains($posicion, 'depto');
    }

    public function labelExclusionEvaluacion(): ?string
    {
        if ($this->esDecano()) {
            return 'Se da a conocer (decano/a)';
        }
        if ($this->esDirectorDepartamento()) {
            return 'Se da a conocer (director/a de departamento)';
        }
        if ($this->academico?->hasAnyAssignedRole(['analista_ccda', 'vicerrectora'])) {
            return 'Se da a conocer (cargo institucional)';
        }

        return null;
    }

    /** @return list<string> */
    public function inferirRolesAcceso(): array
    {
        return app(\App\Services\NominaAccesoService::class)->inferirRoles($this);
    }

    public function tieneCuentaSistema(): bool
    {
        return $this->user_id !== null;
    }

    public function scopeEvaluables($query)
    {
        return $query
            ->where(function ($q) {
                $q->whereNull('nombre_posicion')
                    ->orWhere(function ($q2) {
                        $q2->whereRaw('LOWER(nombre_posicion) NOT LIKE ?', ['%decana%'])
                            ->whereRaw('LOWER(nombre_posicion) NOT LIKE ?', ['%decano%'])
                            ->whereRaw('LOWER(nombre_posicion) NOT LIKE ?', ['%director%departamento%'])
                            ->whereRaw('LOWER(nombre_posicion) NOT LIKE ?', ['%director%depto%'])
                            ->whereRaw('LOWER(nombre_posicion) NOT LIKE ?', ['%directora%departamento%'])
                            ->whereRaw('LOWER(nombre_posicion) NOT LIKE ?', ['%directora%depto%']);
                    });
            })
            ->where(function ($q) {
                $q->whereNull('user_id')
                    ->orWhereHas('academico', function ($uq) {
                        $uq->whereNotIn('role', ['analista_ccda', 'vicerrectora'])
                            ->whereDoesntHave('userRoles', fn ($rq) =>
                                $rq->whereIn('role', ['analista_ccda', 'vicerrectora'])
                            );
                    });
            });
    }

    /** Expedientes que entran al flujo CCA en este período (excluye solo registro APA). */
    public function scopeEvaluacionFormal($query)
    {
        return $query->evaluables();
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
        $h = $this->ultimaCalificacionHistorial();

        return $h ? (float) $h->nota : ($this->academico?->nota_anterior ? (float) $this->academico->nota_anterior : null);
    }

    public function conceptoAnterior(): ?string
    {
        $h = $this->ultimaCalificacionHistorial();

        return $h?->concepto ?? $this->academico?->concepto_anterior;
    }

    public function ultimaCalificacionHistorial(): ?HistorialCalificacion
    {
        return $this->historialCalificaciones()
            ->whereNotNull('nota')
            ->orderByDesc('anio')
            ->first();
    }

    /**
     * Referencia para calcular vigencia: última calificación registrada
     * (fin de ese año) o, si no hay historial, fecha de categorización.
     */
    protected function referenciaVigenciaNota(): ?\Carbon\Carbon
    {
        $ultima = $this->ultimaCalificacionHistorial();

        if ($ultima) {
            return \Carbon\Carbon::create((int) $ultima->anio, 12, 31);
        }

        return $this->fecha_categorizacion;
    }

    protected function mesesVigenciaNota(): int
    {
        return match (strtolower($this->categoriaEfectiva())) {
            'auxiliar' => 12,
            default    => 24,
        };
    }

    /**
     * ¿La nota sigue vigente para evaluarse en el período?
     * - Auxiliar: 1 año   — Adjunto/Titular: 2 años
     * - Se calcula desde la última calificación del historial, no solo desde categorización.
     * - Decano/a: no aplica (no participa del proceso evaluativo).
     */
    public function notaVigente(): bool
    {
        if ($this->esSoloDaConocer()) {
            return false;
        }

        $ref = $this->referenciaVigenciaNota();
        if (!$ref) {
            return false;
        }

        return now()->lte($ref->copy()->addMonths($this->mesesVigenciaNota()));
    }

    public function fechaVencimientoNota(): ?\Carbon\Carbon
    {
        if ($this->esSoloDaConocer()) {
            return null;
        }

        $ref = $this->referenciaVigenciaNota();
        if (!$ref) {
            return null;
        }

        return $ref->copy()->addMonths($this->mesesVigenciaNota());
    }

    public function tieneCompromisoApaConfirmado(): bool
    {
        // Verificar si tiene ambos semestres confirmados
        $tieneS1 = $this->compromisos()->where('semestre', 'S1')->whereNotNull('confirmado_en')->exists();
        $tieneS2 = $this->compromisos()->where('semestre', 'S2')->whereNotNull('confirmado_en')->exists();
        
        return $tieneS1 && $tieneS2;
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

    public function comisionIntegrante(): HasOne
    {
        return $this->hasOne(ComisionIntegrante::class);
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

    /** Todos los compromisos APA (uno por semestre) */
    public function compromisos(): HasMany
    {
        return $this->hasMany(CompromisoApa::class)->orderBy('semestre');
    }

    /** @deprecated Usar pesosApa() para evaluación; compromisoApa solo para display */
    public function compromisoApa(): HasOne
    {
        return $this->hasOne(CompromisoApa::class)->whereNotNull('confirmado_en')->orderBy('semestre');
    }

    /**
     * Pesos APA ponderados por horas reales sumadas de todos los semestres confirmados.
     * Si no hay compromisos confirmados, cae al reglamento por categoría.
     *
     * @return array<string, float>  claves: docencia, investigacion, vinculacion, gestion, formacion
     */
    public function pesosApa(?string $categoria = null): array
    {
        $confirmados = $this->compromisos->filter(fn ($c) => $c->estaConfirmado());

        if ($confirmados->isEmpty()) {
            return \App\Services\CalificacionCadService::pesosParaCategoria($categoria);
        }

        $areas    = ['docencia', 'investigacion', 'extension', 'administracion'];
        $sumHoras = [];
        foreach ($areas as $area) {
            $sumHoras[$area] = (float) $confirmados->sum("hrs_{$area}");
        }

        $total = array_sum($sumHoras);
        if ($total <= 0) {
            return \App\Services\CalificacionCadService::pesosParaCategoria($categoria);
        }

        $mapa   = ['docencia' => 'docencia', 'investigacion' => 'investigacion', 'extension' => 'vinculacion', 'administracion' => 'gestion'];
        $pesos  = ['formacion' => 0.0];
        $suma   = 0.0;
        $lastKey = null;

        foreach ($mapa as $area => $key) {
            if ($sumHoras[$area] > 0) {
                $pct          = round($sumHoras[$area] / $total * 100, 2);
                $pesos[$key]  = $pct;
                $suma        += $pct;
                $lastKey      = $key;
            } else {
                $pesos[$key] = 0.0;
            }
        }

        if ($lastKey !== null) {
            $pesos[$lastKey] = round(100 - ($suma - $pesos[$lastKey]), 2);
        }

        return $pesos;
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

    public function apelaciones(): HasMany
    {
        return $this->hasMany(Apelacion::class);
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
        if ($this->esSoloDaConocer()) {
            return $this->labelExclusionEvaluacion() ?? 'Se da a conocer';
        }

        if (!$this->participaEvaluacionFormal()) {
            return 'Solo registro APA';
        }

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
