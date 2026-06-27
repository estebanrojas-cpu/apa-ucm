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

        $candidatos = Nomina::with('academico')
            ->where('periodo_id', $periodo->id)
            ->where('facultad_id', $facultad->id)
            ->orderBy('nombre')
            ->get()
            ->map(fn (Nomina $n) => [
                'id'    => $n->id,
                'name'  => $n->nombre ?? $n->academico?->name,
                'rut'   => $n->rut ?? $n->academico?->rut,
                'email' => $n->academico?->email,
            ])
            ->values();

        $slots = [
            'secretario', 'decano', 'director_escuela',
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
                    'secretario'            => 'Secretario/a de Facultad',
                    'decano'                => 'Decano/a',
                    'director_escuela'      => 'Director/a de Escuela',
                    'miembro_cca_1'         => 'Miembro CCA 1',
                    'miembro_cca_2'         => 'Miembro CCA 2',
                    'miembro_cca_sindicato' => 'Miembro CCA (Sindicato)',
                    default                 => $s,
                },
                'requiere_cca' => str_starts_with($s, 'miembro_cca'),
            ])->values(),
            'candidatos'       => $candidatos,
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
            'secretario'            => ['nullable', 'uuid', 'exists:nominas,id'],
            'decano'                => ['nullable', 'uuid', 'exists:nominas,id'],
            'director_escuela'      => ['nullable', 'uuid', 'exists:nominas,id'],
            'miembro_cca_1'         => ['nullable', 'uuid', 'exists:nominas,id'],
            'miembro_cca_2'         => ['nullable', 'uuid', 'exists:nominas,id'],
            'miembro_cca_sindicato' => ['nullable', 'uuid', 'exists:nominas,id'],
        ]);

        $ids = array_filter(array_values($data));
        $validos = Nomina::where('periodo_id', $periodo->id)
            ->where('facultad_id', $facultad->id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        if (count($validos) !== count($ids)) {
            return back()->with('error', 'Todos los designados deben pertenecer a la nómina de esta facultad.');
        }

        $ccaIds = array_filter([
            $data['miembro_cca_1'] ?? null,
            $data['miembro_cca_2'] ?? null,
            $data['miembro_cca_sindicato'] ?? null,
        ]);

        if (count($ccaIds) < 2) {
            return back()->with('error', 'Debe designar al menos 2 miembros CCA (incluye sindicato si aplica).');
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

        if ($comision->integrantes_count < 2) {
            return back()->with('error', 'Designe al menos 2 miembros CCA en los cargos de facultad.');
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
        $q = trim($request->query('q', ''));

        $query = Nomina::with('academico')
            ->where('periodo_id', $periodo->id)
            ->where('facultad_id', $facultad->id);

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
