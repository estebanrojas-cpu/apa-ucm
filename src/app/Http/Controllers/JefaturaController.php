<?php

namespace App\Http\Controllers;

use App\Models\CalificacionJefatura;
use App\Models\AsignacionCargo;
use App\Models\CategoriaApa;
use App\Models\Cronograma;
use App\Models\Nomina;
use App\Models\Periodo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JefaturaController extends Controller
{
    public function index(): Response
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        $academicos          = collect();
        $etapaHabilitada     = true;
        $fechaInicioJefatura = null;

        if ($periodo && $user->facultad_id) {
            $esDirector = $user->hasAnyAssignedRole(['director_departamento', 'jefe_academico']);

            $etapa = Cronograma::where('periodo_id', $periodo->id)
                ->where('etapa', 'informe_jefatura')
                ->first();

            if ($etapa) {
                $etapaHabilitada     = !$etapa->esFutura();
                $fechaInicioJefatura = $etapa->fecha_inicio->format('d/m/Y');
            }

            if ($etapaHabilitada && $esDirector) {
                $query = Nomina::with(['academico.departamento', 'calificacionJefatura', 'asignacionesCargo'])
                    ->where('periodo_id', $periodo->id)
                    ->evaluables()
                    ->whereHas('academico', function ($q) use ($user) {
                        $q->where('facultad_id', $user->facultad_id);
                        if ($user->departamento_id) {
                            $q->where('departamento_id', $user->departamento_id);
                        }
                    })
                    ->whereIn('estado', ['pendiente', 'en_carga', 'carga_cerrada']);

                $academicos = $query->get()
                    ->reject(fn (Nomina $n) => $n->esDirectivoFacultad())
                    ->map(fn ($n) => [
                    'id'            => $n->id,
                    'estado'        => $n->estado,
                    'academico'     => [
                        'name'        => $n->academico->name,
                        'rut'         => $n->academico->rut,
                        'departamento'=> $n->academico->departamento?->nombre,
                    ],
                    'tiene_informe' => $n->calificacionJefatura !== null,
                ])->values();
            }
        }

        return Inertia::render('Jefatura/Academicos', [
            'periodo'             => $periodo?->only(['id', 'anio', 'nombre']),
            'academicos'          => $academicos->values(),
            'etapaHabilitada'     => $etapaHabilitada,
            'fechaInicioJefatura' => $fechaInicioJefatura,
        ]);
    }

    public function show(Nomina $nomina): Response
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        $categorias    = CategoriaApa::orderBy('orden')->get();
        $informe       = $nomina->calificacionJefatura;
        $observaciones = $informe?->observaciones() ?? [];

        return Inertia::render('Jefatura/Informe', [
            'nomina' => [
                'id'        => $nomina->id,
                'estado'    => $nomina->estado,
                'academico' => [
                    'name'         => $nomina->academico->name,
                    'rut'          => $nomina->academico->rut,
                    'email'        => $nomina->academico->email,
                    'departamento' => $nomina->academico->departamento?->nombre,
                ],
            ],
            'informe' => $informe ? [
                'observacion_general' => $informe->observacionGeneral(),
            ] : null,
        ]);
    }

    public function store(Request $request, Nomina $nomina)
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        $data = $request->validate([
            'observacion' => ['nullable', 'string', 'max:3000'],
        ]);

        CalificacionJefatura::updateOrCreate(
            ['nomina_id' => $nomina->id, 'jefe_id' => $user->id],
            [
                'puntaje'    => 0,
                'comentario' => json_encode(
                    ['observacion_general' => $data['observacion'] ?? ''],
                    JSON_UNESCAPED_UNICODE
                ),
            ]
        );

        return back()->with('success', 'Informe de jefatura emitido correctamente.');
    }

    public function imprimir(Nomina $nomina)
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        $informe = $nomina->calificacionJefatura;
        if (!$informe) {
            abort(404);
        }

        $categorias    = CategoriaApa::orderBy('orden')->get();
        $observaciones = $informe->observaciones();
        $periodo       = $nomina->periodo;

        $jefe = auth()->user();

        // Obtener firmantes configurados para la facultad/periodo de la nómina.
        $facultadId = $nomina->academico?->facultad_id ?? $nomina->facultad_id;
        $periodoId  = $periodo->id;

        $slots = [
            'miembro_cca_1',
            'miembro_cca_2',
            'secretario',
            'decano',
            'miembro_cca_sindicato',
        ];

        $firmantes = [];
        foreach ($slots as $slot) {
            $asig = AsignacionCargo::where('periodo_id', $periodoId)
                ->where('facultad_id', $facultadId)
                ->where('slot', $slot)
                ->first();

            $nombre = null;
            if ($asig && $asig->nomina && $asig->nomina->academico) {
                $nombre = $asig->nomina->academico->name;
            }

            $firmantes[$slot] = $nombre;
        }

        return view('jefatura.imprimir', compact(
            'nomina', 'informe', 'categorias', 'observaciones', 'periodo', 'jefe', 'firmantes'
        ));
    }
}
