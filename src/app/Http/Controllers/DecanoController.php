<?php

namespace App\Http\Controllers;

use App\Enums\CargoFacultad;
use App\Models\AsignacionCargo;
use App\Models\CategoriaApa;
use App\Models\CalificacionJefatura;
use App\Models\Cronograma;
use App\Models\Nomina;
use App\Models\Periodo;
use App\Services\CargoPeriodoService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DecanoController extends Controller
{
    public function __construct(private CargoPeriodoService $cargos) {}

    public function index(): Response
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        $directivos = collect();
        $habilitada = false;

        if ($periodo && $user->facultad_id && $this->esDecanoActivo($user, $periodo)) {
            $etapa = Cronograma::where('periodo_id', $periodo->id)
                ->where('etapa', 'informe_jefatura')
                ->first();

            $habilitada = $etapa && !$etapa->esFutura();

            if ($habilitada) {
                $directivoIds = AsignacionCargo::where('periodo_id', $periodo->id)
                    ->where('facultad_id', $user->facultad_id)
                    ->whereIn('cargo', array_map(
                        fn (CargoFacultad $c) => $c->value,
                        CargoFacultad::directivosParaDecano()
                    ))
                    ->pluck('nomina_id');

                $directivos = Nomina::with(['academico', 'calificacionJefatura', 'asignacionesCargo'])
                    ->where('periodo_id', $periodo->id)
                    ->whereIn('id', $directivoIds)
                    ->whereIn('estado', ['evaluado', 'apelado', 'cerrado', 'en_evaluacion', 'carga_cerrada'])
                    ->get()
                    ->map(fn (Nomina $n) => [
                        'id'            => $n->id,
                        'estado'        => $n->estado,
                        'cargo'         => $n->asignacionesCargo->first()?->label(),
                        'academico'     => [
                            'name' => $n->academico->name,
                            'rut'  => $n->academico->rut,
                        ],
                        'tiene_informe' => $n->calificacionJefatura !== null,
                        'puntaje'       => $n->calificacionJefatura?->puntaje,
                        'calificacion'  => $n->calificacionJefatura?->calificacionLabel(),
                    ]);
            }
        }

        return Inertia::render('Decano/Directivos', [
            'periodo'    => $periodo?->only(['id', 'anio', 'nombre']),
            'directivos' => $directivos->values(),
            'habilitada' => $habilitada,
        ]);
    }

    public function show(Nomina $nomina): Response
    {
        $user = auth()->user();
        $nomina->load(['academico', 'asignacionesCargo', 'periodo']);
        $this->autorizarDecanoSobreNomina($user, $nomina);

        $categorias    = CategoriaApa::orderBy('orden')->get();
        $informe       = $nomina->calificacionJefatura;
        $observaciones = $informe?->observaciones() ?? [];

        return Inertia::render('Decano/Informe', [
            'nomina' => [
                'id'        => $nomina->id,
                'estado'    => $nomina->estado,
                'cargo'     => $nomina->asignacionesCargo->first()?->label(),
                'academico' => [
                    'name'  => $nomina->academico->name,
                    'rut'   => $nomina->academico->rut,
                    'email' => $nomina->academico->email,
                ],
            ],
            'categorias' => $categorias->map(fn ($c) => [
                'id'     => $c->id,
                'nombre' => $c->nombre,
                'slug'   => $c->slug,
            ]),
            'informe' => $informe ? [
                'puntaje'             => $informe->puntaje,
                'observacion_general' => $informe->observacionGeneral(),
                'observaciones'       => $observaciones,
            ] : null,
        ]);
    }

    public function store(Request $request, Nomina $nomina)
    {
        $user = auth()->user();
        $this->autorizarDecanoSobreNomina($user, $nomina);

        $categorias = CategoriaApa::orderBy('orden')->pluck('slug')->toArray();
        $rules      = [
            'puntaje'             => ['required', 'integer', 'min:0', 'max:100'],
            'observacion_general' => ['nullable', 'string', 'max:2000'],
        ];
        foreach ($categorias as $slug) {
            $rules["obs_{$slug}"] = ['nullable', 'string', 'max:1000'];
        }

        $data = $request->validate($rules);

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

        return back()->with('success', 'Informe de jefatura guardado.');
    }

    private function esDecanoActivo($user, Periodo $periodo): bool
    {
        return $this->cargos->tieneCargo($user, CargoFacultad::Decano, $periodo);
    }

    private function autorizarDecanoSobreNomina($user, Nomina $nomina): void
    {
        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        if (!$this->esDecanoActivo($user, $nomina->periodo)) {
            abort(403, 'No está designado como decano/a en el período activo.');
        }

        if (!$nomina->esDirectivoFacultad()) {
            abort(403, 'Solo puede informar sobre cargos directivos de la facultad.');
        }
    }
}
