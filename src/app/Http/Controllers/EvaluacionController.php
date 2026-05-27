<?php

namespace App\Http\Controllers;

use App\Models\CalificacionFinal;
use App\Models\CategoriaApa;
use App\Models\Evaluacion;
use App\Models\Evidencia;
use App\Models\Nomina;
use App\Models\Periodo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvaluacionController extends Controller
{
    public function index(): Response
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        $expedientes = collect();

        if ($periodo && $user->facultad_id) {
            $expedientes = Nomina::with(['academico', 'evaluaciones', 'calificacionFinal'])
                ->where('periodo_id', $periodo->id)
                ->whereHas('academico', fn ($q) => $q->where('facultad_id', $user->facultad_id))
                ->whereIn('estado', ['carga_cerrada', 'en_evaluacion', 'evaluado'])
                ->orderBy('created_at')
                ->get()
                ->map(fn ($n) => [
                    'id'              => $n->id,
                    'estado'          => $n->estado,
                    'con_licencia'    => $n->con_licencia,
                    'academico'       => [
                        'name' => $n->academico->name,
                        'rut'  => $n->academico->rut,
                    ],
                    'yo_evalué'       => $n->evaluaciones->contains('evaluador_id', $user->id),
                    'n_evaluaciones'  => $n->evaluaciones->count(),
                    'calificacion'    => $n->calificacionFinal?->calificacion,
                    'puntaje_total'   => $n->calificacionFinal?->puntaje_total,
                ]);
        }

        return Inertia::render('CCA/Expedientes', [
            'periodo'     => $periodo?->only(['id', 'anio', 'nombre']),
            'expedientes' => $expedientes->values(),
        ]);
    }

    public function show(Nomina $nomina): Response
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        if (!in_array($nomina->estado, ['carga_cerrada', 'en_evaluacion', 'evaluado'])) {
            abort(403);
        }

        $categorias = CategoriaApa::orderBy('orden')->get();
        $evidencias = $nomina->evidenciasNormales()->with('categoria')->get();

        $evidenciasPorCategoria = [];
        foreach ($evidencias as $ev) {
            $evidenciasPorCategoria[$ev->categoria_id][] = [
                'id'             => $ev->id,
                'nombre_archivo' => $ev->nombre_archivo,
                'tamano'         => $ev->tamanoFormateado(),
                'descripcion'    => $ev->descripcion,
                'created_at'     => $ev->created_at->format('d/m/Y H:i'),
                'url_descarga'   => route('cca.evidencias.download', [$nomina->id, $ev->id]),
            ];
        }

        $miEvaluacion = Evaluacion::where('nomina_id', $nomina->id)
            ->where('evaluador_id', $user->id)
            ->where('es_apelacion', false)
            ->first();

        $todasEvaluaciones = Evaluacion::with('evaluador')
            ->where('nomina_id', $nomina->id)
            ->where('es_apelacion', false)
            ->get()
            ->map(fn ($e) => [
                'evaluador' => $e->evaluador->name,
                'puntaje_total' => $e->puntajeTotal(),
            ]);

        $calificacionFinal = $nomina->calificacionFinal;

        return Inertia::render('CCA/EvaluarExpediente', [
            'nomina' => [
                'id'           => $nomina->id,
                'estado'       => $nomina->estado,
                'con_licencia' => $nomina->con_licencia,
                'academico'    => [
                    'name'  => $nomina->academico->name,
                    'rut'   => $nomina->academico->rut,
                    'email' => $nomina->academico->email,
                ],
            ],
            'categorias'             => $categorias->map(fn ($c) => [
                'id'     => $c->id,
                'nombre' => $c->nombre,
                'slug'   => $c->slug,
            ]),
            'evidenciasPorCategoria' => $evidenciasPorCategoria,
            'miEvaluacion'           => $miEvaluacion ? [
                'puntaje_docencia'       => $miEvaluacion->puntaje_docencia,
                'puntaje_investigacion'  => $miEvaluacion->puntaje_investigacion,
                'puntaje_vinculacion'    => $miEvaluacion->puntaje_vinculacion,
                'puntaje_gestion'        => $miEvaluacion->puntaje_gestion,
                'puntaje_formacion'      => $miEvaluacion->puntaje_formacion,
                'comentario'             => $miEvaluacion->comentario,
                'puntaje_total'          => $miEvaluacion->puntajeTotal(),
            ] : null,
            'todasEvaluaciones'      => $todasEvaluaciones,
            'calificacionFinal'      => $calificacionFinal ? [
                'puntaje_total' => $calificacionFinal->puntaje_total,
                'calificacion'  => $calificacionFinal->calificacion,
                'fecha'         => $calificacionFinal->fecha->format('d/m/Y'),
                'observacion'   => $calificacionFinal->observacion,
            ] : null,
        ]);
    }

    public function downloadEvidencia(Nomina $nomina, Evidencia $evidencia): StreamedResponse
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        if ($evidencia->nomina_id !== $nomina->id) {
            abort(404);
        }

        if (!Storage::disk('public')->exists($evidencia->ruta)) {
            abort(404);
        }

        return Storage::disk('public')->download($evidencia->ruta, $evidencia->nombre_archivo);
    }

    public function store(Request $request, Nomina $nomina)
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        if ($nomina->calificacionFinal()->where('es_apelacion', false)->exists()) {
            return back()->with('error', 'Ya existe una calificación final. No se puede modificar la evaluación.');
        }

        $data = $request->validate([
            'puntaje_docencia'      => ['required', 'integer', 'min:0', 'max:20'],
            'puntaje_investigacion' => ['required', 'integer', 'min:0', 'max:20'],
            'puntaje_vinculacion'   => ['required', 'integer', 'min:0', 'max:20'],
            'puntaje_gestion'       => ['required', 'integer', 'min:0', 'max:20'],
            'puntaje_formacion'     => ['required', 'integer', 'min:0', 'max:20'],
            'comentario'            => ['nullable', 'string', 'max:1000'],
        ]);

        Evaluacion::updateOrCreate(
            ['nomina_id' => $nomina->id, 'evaluador_id' => $user->id, 'es_apelacion' => false],
            $data
        );

        if ($nomina->estado === 'carga_cerrada') {
            $nomina->update(['estado' => 'en_evaluacion']);
        }

        return back()->with('success', 'Evaluación registrada correctamente.');
    }

    public function finalize(Request $request, Nomina $nomina)
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        if ($nomina->calificacionFinal()->where('es_apelacion', false)->exists()) {
            return back()->with('error', 'Este expediente ya tiene una calificación final registrada.');
        }

        $evaluaciones = Evaluacion::where('nomina_id', $nomina->id)
            ->where('es_apelacion', false)
            ->get();

        if ($evaluaciones->isEmpty()) {
            return back()->with('error', 'No hay evaluaciones registradas. Debe haber al menos una evaluación para finalizar.');
        }

        $data = $request->validate([
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $n = $evaluaciones->count();
        $puntajeTotal = (int) round(
            ($evaluaciones->sum('puntaje_docencia')
             + $evaluaciones->sum('puntaje_investigacion')
             + $evaluaciones->sum('puntaje_vinculacion')
             + $evaluaciones->sum('puntaje_gestion')
             + $evaluaciones->sum('puntaje_formacion')) / $n
        );

        $calificacion = match(true) {
            $puntajeTotal >= 80 => 'muy_bueno',
            $puntajeTotal >= 60 => 'bueno',
            $puntajeTotal >= 40 => 'aceptable',
            default             => 'deficiente',
        };

        CalificacionFinal::create([
            'nomina_id'      => $nomina->id,
            'puntaje_total'  => $puntajeTotal,
            'calificacion'   => $calificacion,
            'determinada_por'=> $user->id,
            'fecha'          => now()->toDateString(),
            'observacion'    => $data['observacion'] ?? null,
            'es_apelacion'   => false,
        ]);

        $nomina->update(['estado' => 'evaluado']);

        return back()->with('success', 'Calificación final registrada correctamente.');
    }
}
