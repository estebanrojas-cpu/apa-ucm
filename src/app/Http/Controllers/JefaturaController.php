<?php

namespace App\Http\Controllers;

use App\Models\CalificacionJefatura;
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
            $etapa = Cronograma::where('periodo_id', $periodo->id)
                ->where('etapa', 'consejo_facultad')
                ->first();

            if ($etapa) {
                $etapaHabilitada     = !$etapa->esFutura();
                $fechaInicioJefatura = $etapa->fecha_inicio->format('d/m/Y');
            }

            if ($etapaHabilitada) {
                $query = Nomina::with(['academico.departamento', 'calificacionJefatura'])
                    ->where('periodo_id', $periodo->id)
                    ->whereHas('academico', function ($q) use ($user) {
                        $q->where('facultad_id', $user->facultad_id);
                        if ($user->departamento_id) {
                            $q->where('departamento_id', $user->departamento_id);
                        }
                    })
                    ->whereIn('estado', ['evaluado', 'apelado', 'cerrado']);

                $academicos = $query->get()->map(fn ($n) => [
                    'id'            => $n->id,
                    'estado'        => $n->estado,
                    'academico'     => [
                        'name'        => $n->academico->name,
                        'rut'         => $n->academico->rut,
                        'departamento'=> $n->academico->departamento?->nombre,
                    ],
                    'tiene_informe' => $n->calificacionJefatura !== null,
                    'puntaje'       => $n->calificacionJefatura?->puntaje,
                    'calificacion'  => $n->calificacionJefatura?->calificacionLabel(),
                ]);
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
            'categorias' => $categorias->map(fn ($c) => [
                'id'     => $c->id,
                'nombre' => $c->nombre,
                'slug'   => $c->slug,
            ]),
            'informe' => $informe ? [
                'puntaje'              => $informe->puntaje,
                'observacion_general'  => $informe->observacionGeneral(),
                'observaciones'        => $observaciones,
            ] : null,
        ]);
    }

    public function store(Request $request, Nomina $nomina)
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        $categorias = CategoriaApa::orderBy('orden')->pluck('slug')->toArray();

        $rules = [
            'puntaje'             => ['required', 'integer', 'min:0', 'max:100'],
            'observacion_general' => ['nullable', 'string', 'max:2000'],
        ];
        foreach ($categorias as $slug) {
            $rules["obs_{$slug}"] = ['nullable', 'string', 'max:1000'];
        }

        $data = $request->validate($rules, [
            'puntaje.required' => 'El puntaje es obligatorio.',
            'puntaje.min'      => 'El puntaje mínimo es 0.',
            'puntaje.max'      => 'El puntaje máximo es 100.',
        ]);

        $observaciones = ['observacion_general' => $data['observacion_general'] ?? ''];
        foreach ($categorias as $slug) {
            $observaciones[$slug] = $data["obs_{$slug}"] ?? '';
        }

        CalificacionJefatura::updateOrCreate(
            ['nomina_id' => $nomina->id, 'jefe_id' => $user->id],
            [
                'puntaje'    => $data['puntaje'],
                'comentario' => json_encode($observaciones, JSON_UNESCAPED_UNICODE),
            ]
        );

        return back()->with('success', 'Informe de jefatura guardado correctamente.');
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

        return view('jefatura.imprimir', compact(
            'nomina', 'informe', 'categorias', 'observaciones', 'periodo'
        ));
    }
}
