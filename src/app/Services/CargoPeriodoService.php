<?php

namespace App\Services;

use App\Enums\CargoFacultad;
use App\Models\AsignacionCargo;
use App\Models\ComisionCca;
use App\Models\HistorialCargo;
use App\Models\Nomina;
use App\Models\Periodo;
use App\Models\User;
use Illuminate\Support\Collection;

class CargoPeriodoService
{
    public function periodoActivo(): ?Periodo
    {
        return Periodo::where('estado', 'activo')->latest()->first();
    }

    /** @return Collection<int, AsignacionCargo> */
    public function asignacionesFacultad(string $periodoId, string $facultadId): Collection
    {
        return AsignacionCargo::with('nomina.academico')
            ->where('periodo_id', $periodoId)
            ->where('facultad_id', $facultadId)
            ->get();
    }

    /** @return Collection<int, AsignacionCargo> */
    public function asignacionesUsuario(User $user, ?Periodo $periodo = null): Collection
    {
        $periodo ??= $this->periodoActivo();
        if (!$periodo) {
            return collect();
        }

        $nominaIds = Nomina::where('periodo_id', $periodo->id)
            ->where('user_id', $user->id)
            ->pluck('id');

        if ($nominaIds->isEmpty()) {
            return collect();
        }

        return AsignacionCargo::where('periodo_id', $periodo->id)
            ->whereIn('nomina_id', $nominaIds)
            ->get();
    }

    public function tieneCargo(User $user, CargoFacultad $cargo, ?Periodo $periodo = null): bool
    {
        return $this->asignacionesUsuario($user, $periodo)
            ->contains(fn (AsignacionCargo $a) => $a->cargo === $cargo->value);
    }

    public function esDecanoPeriodo(Nomina $nomina): bool
    {
        return AsignacionCargo::where('periodo_id', $nomina->periodo_id)
            ->where('nomina_id', $nomina->id)
            ->where('cargo', CargoFacultad::Decano->value)
            ->exists();
    }

    public function esDirectivoPeriodo(Nomina $nomina): bool
    {
        return AsignacionCargo::where('periodo_id', $nomina->periodo_id)
            ->where('nomina_id', $nomina->id)
            ->whereIn('cargo', array_map(
                fn (CargoFacultad $c) => $c->value,
                CargoFacultad::directivosParaDecano()
            ))
            ->exists();
    }

    /**
     * @param  array<string, string|null>  $cargos  clave cargo => nomina_id
     */
    public function guardarAsignacionesFacultad(
        string $periodoId,
        string $facultadId,
        array $cargos,
        string $asignadoPorId
    ): void {
        $slots = [
            CargoFacultad::Secretario->value,
            CargoFacultad::Decano->value,
            CargoFacultad::DirectorEscuela->value,
            CargoFacultad::MiembroCca->value,
            CargoFacultad::MiembroCcaSindicato->value,
        ];

        $mapa = [
            'secretario'           => CargoFacultad::Secretario->value,
            'decano'               => CargoFacultad::Decano->value,
            'director_escuela'     => CargoFacultad::DirectorEscuela->value,
            'miembro_cca_1'        => CargoFacultad::MiembroCca->value,
            'miembro_cca_2'        => CargoFacultad::MiembroCca->value,
            'miembro_cca_sindicato'=> CargoFacultad::MiembroCcaSindicato->value,
        ];

        AsignacionCargo::where('periodo_id', $periodoId)
            ->where('facultad_id', $facultadId)
            ->delete();

        $ccaNominaIds = [];

        foreach ($mapa as $slot => $cargoValue) {
            $nominaId = $cargos[$slot] ?? null;
            if (!$nominaId) {
                continue;
            }

            AsignacionCargo::create([
                'periodo_id'   => $periodoId,
                'facultad_id'  => $facultadId,
                'nomina_id'    => $nominaId,
                'slot'         => $slot,
                'cargo'        => $cargoValue,
                'asignado_por' => $asignadoPorId,
            ]);

            if (in_array($cargoValue, [
                CargoFacultad::MiembroCca->value,
                CargoFacultad::MiembroCcaSindicato->value,
            ], true)) {
                $ccaNominaIds[] = $nominaId;
            }

            $this->registrarHistorialCargo($nominaId, $periodoId, $facultadId, $cargoValue);
        }

        $this->sincronizarComisionCca($periodoId, $facultadId, $ccaNominaIds, $asignadoPorId);
    }

    /** @param list<string> $nominaIds */
    private function sincronizarComisionCca(
        string $periodoId,
        string $facultadId,
        array $nominaIds,
        string $designadoPorId
    ): void {
        $comision = ComisionCca::paraPeriodoFacultad($periodoId, $facultadId);

        if ($comision->estaConfirmada()) {
            return;
        }

        $comision->integrantes()->delete();

        foreach (array_unique($nominaIds) as $nominaId) {
            $comision->integrantes()->create(['nomina_id' => $nominaId]);
        }

        $comision->update(['designado_por' => $designadoPorId]);
    }

    private function registrarHistorialCargo(
        string $nominaId,
        string $periodoId,
        string $facultadId,
        string $cargo
    ): void {
        $nomina = Nomina::find($nominaId);
        if (!$nomina?->user_id) {
            return;
        }

        HistorialCargo::firstOrCreate(
            [
                'user_id'    => $nomina->user_id,
                'periodo_id' => $periodoId,
                'cargo'      => $cargo,
            ],
            [
                'facultad_id' => $facultadId,
                'created_at'  => now(),
            ]
        );
    }

    /** @return list<string> roles navegables para sesión */
    public function rolesSesionDesdeCargos(User $user, ?Periodo $periodo = null): array
    {
        $roles = [];

        foreach ($this->asignacionesUsuario($user, $periodo) as $asignacion) {
            $rol = CargoFacultad::from($asignacion->cargo)->rolSesion();
            if ($rol) {
                $roles[] = $rol;
            }
        }

        return array_values(array_unique($roles));
    }

    /** @return list<array{value: string, label: string}> */
    public function badgesCargosSinVista(User $user, ?Periodo $periodo = null): array
    {
        $badges = [];

        foreach ($this->asignacionesUsuario($user, $periodo) as $asignacion) {
            $enum = CargoFacultad::from($asignacion->cargo);
            if ($enum->rolSesion() === null) {
                $badges[] = ['value' => $enum->value, 'label' => $enum->label()];
            }
        }

        return $badges;
    }
}
