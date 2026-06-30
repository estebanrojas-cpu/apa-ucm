<?php

namespace App\Http\Controllers;

use App\Enums\CargoFacultad;
use App\Models\AsignacionCargo;
use App\Models\ComisionCca;
use App\Models\Facultad;
use App\Models\Nomina;
use App\Models\Periodo;
use App\Services\CargoPeriodoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AsignacionFacultadController extends Controller
{
    public function __construct(private CargoPeriodoService $cargos) {}

    public function index(Periodo $periodo): Response
    {
        $facultades = Facultad::orderBy('nombre')->get()->map(function (Facultad $f) use ($periodo) {
            $asignaciones = $this->cargos->asignacionesFacultad($periodo->id, $f->id);
            $comision     = ComisionCca::where('periodo_id', $periodo->id)
                ->where('facultad_id', $f->id)
                ->first();

            return [
                'id'            => $f->id,
                'nombre'        => $f->nombre,
                'codigo'        => $f->codigo,
                'nominas_count' => Nomina::where('periodo_id', $periodo->id)->where('facultad_id', $f->id)->count(),
                'completo'      => $asignaciones->count() >= 5,
                'comision'      => $comision ? [
                    'estado'        => $comision->estado,
                    'confirmada_en' => $comision->confirmada_en?->format('d/m/Y H:i'),
                ] : null,
            ];
        });

        return Inertia::render('AsignacionFacultad/Index', [
            'periodo'    => $periodo->only(['id', 'anio', 'nombre', 'estado']),
            'facultades' => $facultades,
        ]);
    }

    public function edit(Periodo $periodo, Facultad $facultad): Response
    {
        $asignaciones = AsignacionCargo::where('periodo_id', $periodo->id)
            ->where('facultad_id', $facultad->id)
            ->get()
            ->keyBy('slot');

        $mapCandidato = fn (Nomina $n) => [
            'id'    => $n->id,
            'name'  => $n->nombre ?? $n->academico?->name,
            'rut'   => $n->rut ?? $n->academico?->rut,
            'email' => $n->academico?->email,
        ];

        $candidatos = Nomina::with('academico')
            ->where('periodo_id', $periodo->id)
            ->where('facultad_id', $facultad->id)
            ->orderBy('nombre')
            ->get()
            ->map($mapCandidato)
            ->values();

        $candidatosExternos = Nomina::with('academico')
            ->where('periodo_id', $periodo->id)
            ->where('facultad_id', '!=', $facultad->id)
            ->orderBy('nombre')
            ->get()
            ->map($mapCandidato)
            ->values();

        $slots = [
            'secretario', 'decano', 'director_escuela', 'director_departamento',
            'miembro_cca_1', 'miembro_cca_2', 'miembro_cca_sindicato',
        ];

        $valores = [];
        foreach ($slots as $slot) {
            $valores[$slot] = $asignaciones->get($slot)?->nomina_id;
        }

        $comision = ComisionCca::paraPeriodoFacultad($periodo->id, $facultad->id);

        return Inertia::render('AsignacionFacultad/Edit', [
            'periodo'  => $periodo->only(['id', 'anio', 'nombre']),
            'facultad' => $facultad->only(['id', 'nombre', 'codigo']),
            'cargos'   => $valores,
            'slots'    => collect($slots)->map(fn ($s) => [
                'key'   => $s,
                'label' => match ($s) {
                    'secretario'             => 'Secretario/a de Facultad',
                    'decano'                 => 'Decano/a',
                    'director_escuela'       => 'Director/a de Escuela',
                    'director_departamento'  => 'Director/a de Departamento',
                    'miembro_cca_1'          => 'Miembro CCA 1',
                    'miembro_cca_2'          => 'Miembro CCA 2',
                    'miembro_cca_sindicato'  => 'Miembro CCA (Sindicato)',
                    default                  => $s,
                },
                'requiere_cca' => str_starts_with($s, 'miembro_cca'),
                'es_externo'   => in_array($s, ['miembro_cca_1', 'miembro_cca_2'], true),
            ])->values(),
            'candidatos'          => $candidatos,
            'candidatos_externos' => $candidatosExternos,
            'comision_estado'  => $comision->estado,
            'comision_bloqueada' => $comision->estaConfirmada(),
        ]);
    }

    public function update(Request $request, Periodo $periodo, Facultad $facultad)
    {
        $comision = ComisionCca::paraPeriodoFacultad($periodo->id, $facultad->id);
        if ($comision->estaConfirmada()) {
            return back()->with('error', 'La comisión ya está confirmada. No se pueden modificar los cargos.');
        }

        $data = $request->validate([
            'secretario'             => ['nullable', 'uuid', 'exists:nominas,id'],
            'decano'                 => ['nullable', 'uuid', 'exists:nominas,id'],
            'director_escuela'       => ['nullable', 'uuid', 'exists:nominas,id'],
            'director_departamento'  => ['nullable', 'uuid', 'exists:nominas,id'],
            'miembro_cca_1'          => ['nullable', 'uuid', 'exists:nominas,id'],
            'miembro_cca_2'          => ['nullable', 'uuid', 'exists:nominas,id'],
            'miembro_cca_sindicato'  => ['nullable', 'uuid', 'exists:nominas,id'],
        ]);

        // Validar slots internos (misma facultad).
        $slotsInternos = ['secretario', 'decano', 'director_escuela', 'director_departamento', 'miembro_cca_sindicato'];
        $idsInternos = array_unique(array_filter(array_map(fn ($s) => $data[$s] ?? null, $slotsInternos)));
        if (!empty($idsInternos)) {
            $validosInternos = Nomina::where('periodo_id', $periodo->id)
                ->where('facultad_id', $facultad->id)
                ->whereIn('id', $idsInternos)
                ->pluck('id')
                ->all();
            if (count($validosInternos) !== count($idsInternos)) {
                return back()->with('error', 'Los cargos de secretario, decano, director y sindicato deben pertenecer a la nómina de esta facultad.');
            }
        }

        // Validar slots externos CCA 1 y 2 (otra facultad del mismo período).
        $slotsExternos = ['miembro_cca_1', 'miembro_cca_2'];
        $idsExternos = array_unique(array_filter(array_map(fn ($s) => $data[$s] ?? null, $slotsExternos)));
        if (!empty($idsExternos)) {
            $validosExternos = Nomina::where('periodo_id', $periodo->id)
                ->where('facultad_id', '!=', $facultad->id)
                ->whereIn('id', $idsExternos)
                ->pluck('id')
                ->all();
            if (count($validosExternos) !== count($idsExternos)) {
                return back()->with('error', 'Los miembros CCA 1 y 2 deben pertenecer a la nómina de otra facultad.');
            }
        }

        if (empty($data['miembro_cca_1']) || empty($data['miembro_cca_2']) || empty($data['miembro_cca_sindicato'])) {
            return back()->with('error', 'Debe designar los 3 miembros CCA: Miembro 1, Miembro 2 y Miembro Sindicato.');
        }

        $this->cargos->guardarAsignacionesFacultad(
            $periodo->id,
            $facultad->id,
            $data,
            $request->user()->id
        );

        return redirect()
            ->route('analista.periodos.cargos.index', $periodo)
            ->with('success', "Cargos de {$facultad->nombre} guardados. Revise la comisión CCA y confírmela.");
    }

    public function confirmarComision(Request $request, Periodo $periodo, Facultad $facultad)
    {
        $comision = ComisionCca::withCount('integrantes')
            ->where('periodo_id', $periodo->id)
            ->where('facultad_id', $facultad->id)
            ->firstOrFail();

        if ($comision->integrantes_count < 3) {
            return back()->with('error', 'Debe haber 3 miembros CCA designados (Miembro 1, Miembro 2 y Miembro Sindicato) antes de confirmar.');
        }

        $comision->update([
            'estado'        => 'confirmada',
            'designado_por' => $request->user()->id,
            'confirmada_en' => now(),
        ]);

        return back()->with('success', "Comisión de {$facultad->nombre} confirmada.");
    }

    public function buscarCandidatos(Periodo $periodo, Facultad $facultad, Request $request)
    {
        $q    = trim($request->query('q', ''));
        $slot = $request->query('slot', '');

        $slotsExternos = ['miembro_cca_1', 'miembro_cca_2'];
        $query = Nomina::with('academico')
            ->where('periodo_id', $periodo->id);

        if (in_array($slot, $slotsExternos, true)) {
            // CCA 1 y 2 son externos: solo personas de OTRAS facultades.
            $query->where('facultad_id', '!=', $facultad->id);
        } else {
            // Todos los demás cargos son internos a la facultad.
            $query->where('facultad_id', $facultad->id);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('nombre', 'ilike', "%{$q}%")
                    ->orWhere('rut', 'ilike', "%{$q}%")
                    ->orWhereHas('academico', fn ($u) =>
                        $u->where('name', 'ilike', "%{$q}%")->orWhere('rut', 'ilike', "%{$q}%")
                    );
            });
        }

        return response()->json(
            $query->orderBy('nombre')->limit(20)->get()->map(fn (Nomina $n) => [
                'id'    => $n->id,
                'name'  => $n->nombre ?? $n->academico?->name,
                'rut'   => $n->rut ?? $n->academico?->rut,
                'label' => ($n->nombre ?? $n->academico?->name) . ' — ' . ($n->rut ?? ''),
            ])
        );
    }
}
