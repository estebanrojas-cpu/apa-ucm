<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'rut',
        'telefono',
        'facultad_id',
        'departamento_id',
        'role',
        'activo',
        'categoria_academica',
        'linea_desarrollo',
        'fecha_jerarquizacion',
        'horas_contrato_isem',
        'horas_contrato_iisem',
        'nota_anterior',
        'concepto_anterior',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'activo'            => 'boolean',
        ];
    }

    // ── Roles ────────────────────────────────────────────────────────────

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function activeRole(): ?string
    {
        $sessionRole = session('active_role');

        if ($sessionRole) {
            $assigned = $this->assignedRoles();
            if (in_array($sessionRole, $assigned, true)) {
                return $sessionRole;
            }
        }

        return $this->userRoles()->value('role') ?? $this->role;
    }

    /** @return list<string> */
    public function assignedRoles(): array
    {
        $roles = $this->relationLoaded('userRoles')
            ? $this->userRoles->pluck('role')->all()
            : $this->userRoles()->pluck('role')->all();

        if ($roles !== []) {
            return $roles;
        }

        return $this->role ? [$this->role] : [];
    }

    /** Roles asignados + miembro_cca si integra comisión confirmada del período activo. */
    public function rolesParaSesion(): array
    {
        $roles = $this->assignedRoles();

        if ($this->puedeActuarComoCca() && !in_array('miembro_cca', $roles, true)) {
            $roles[] = 'miembro_cca';
        }

        return array_values(array_unique($roles));
    }

    public function puedeActuarComoCca(?Periodo $periodo = null): bool
    {
        if (!$this->facultad_id) {
            return false;
        }

        $periodo ??= Periodo::where('estado', 'activo')->latest()->first();

        if (!$periodo) {
            return false;
        }

        return ComisionIntegrante::whereHas('nomina', fn ($q) => $q->where('user_id', $this->id))
            ->whereHas('comision', fn ($q) => $q
                ->where('periodo_id', $periodo->id)
                ->where('facultad_id', $this->facultad_id)
                ->where('estado', 'confirmada'))
            ->exists();
    }

    public static function integrantesComisionPeriodo(string $periodoId, string $facultadId)
    {
        $userIds = Nomina::whereIn('id', ComisionIntegrante::query()
            ->whereHas('comision', fn ($q) => $q
                ->where('periodo_id', $periodoId)
                ->where('facultad_id', $facultadId)
                ->where('estado', 'confirmada'))
            ->pluck('nomina_id'))
            ->whereNotNull('user_id')
            ->pluck('user_id');

        return static::whereIn('id', $userIds)->orderBy('name')->get();
    }

    public function syncUserRoles(array $roles): void
    {
        $roles = array_values(array_unique($roles));

        $this->userRoles()->delete();

        foreach ($roles as $role) {
            $this->userRoles()->create(['role' => $role]);
        }

        if ($roles !== []) {
            $this->update(['role' => $roles[0]]);
        }
    }

    public function hasRole(string|array $roles): bool
    {
        return in_array($this->activeRole(), (array) $roles, true);
    }

    public function hasAnyAssignedRole(string|array $roles): bool
    {
        $assigned = $this->assignedRoles();

        foreach ((array) $roles as $role) {
            if (in_array($role, $assigned, true)) {
                return true;
            }
        }

        return false;
    }

    public static function findByAssignedRole(string $role): ?self
    {
        return static::whereHas('userRoles', fn ($q) => $q->where('role', $role))->first()
            ?? static::where('role', $role)->first();
    }

    public function nominaActiva(): ?Nomina
    {
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        if (!$periodo) {
            return null;
        }

        return $this->nominas()->where('periodo_id', $periodo->id)->first();
    }

    public function tieneLicenciaMedicaActiva(): bool
    {
        return $this->nominaActiva()?->tieneLicenciaMedicaActiva() ?? false;
    }

    public function getBloqueadoPorLicenciaAttribute(): bool
    {
        return $this->tieneLicenciaMedicaActiva();
    }

    public function isAnalistaCCDA(): bool     { return $this->activeRole() === 'analista_ccda'; }
    public function isSecretario(): bool       { return $this->activeRole() === 'secretario'; }
    public function isMiembroCCA(): bool       { return $this->activeRole() === 'miembro_cca'; }
    public function isJefeAcademico(): bool    { return $this->activeRole() === 'jefe_academico'; }
    public function isAcademico(): bool        { return $this->activeRole() === 'academico'; }

    // ── Relaciones ───────────────────────────────────────────────────────
    public function facultad(): BelongsTo
    {
        return $this->belongsTo(Facultad::class);
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    /** Períodos que este usuario (analista CCDA) creó */
    public function periodosCreados(): HasMany
    {
        return $this->hasMany(Periodo::class, 'creado_por');
    }

    /** Nóminas en que este usuario figura como evaluado */
    public function nominas(): HasMany
    {
        return $this->hasMany(Nomina::class);
    }

    /** Evaluaciones que este usuario realizó como evaluador CCA */
    public function evaluaciones(): HasMany
    {
        return $this->hasMany(Evaluacion::class, 'evaluador_id');
    }

    /** Calificaciones de jefatura emitidas por este usuario */
    public function calificacionesJefatura(): HasMany
    {
        return $this->hasMany(CalificacionJefatura::class, 'jefe_id');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeDeRol($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeDeFacultad($query, string $facultadId)
    {
        return $query->where('facultad_id', $facultadId);
    }
}