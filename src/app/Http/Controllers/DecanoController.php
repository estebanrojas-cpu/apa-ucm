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
                    ->whereIn('estado', ['pendiente', 'en_carga', 'carga_cerrada'])
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
            'informe' => $informe ? [
                'observacion_general' => $informe->observacionGeneral(),
            ] : null,
        ]);
    }

    public function store(Request $request, Nomina $nomina)
    {
        $user = auth()->user();
        $this->autorizarDecanoSobreNomina($user, $nomina);

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
        $nomina->load(['academico', 'asignacionesCargo', 'periodo', 'calificacionJefatura.jefe']);
        $this->autorizarDecanoSobreNomina($user, $nomina);

        $informe = $nomina->calificacionJefatura;
        abort_if(!$informe, 404, 'El informe aún no ha sido emitido.');

        $categorias = CategoriaApa::orderBy('orden')->get();

        return view('decano.informe_jefatura', [
            'nomina'     => $nomina,
            'informe'    => $informe,
            'categorias' => $categorias,
            'decano'     => $informe->jefe ?? $user,
        ]);
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
