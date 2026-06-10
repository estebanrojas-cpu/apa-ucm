<?php

namespace App\Http\Controllers;

use App\Models\CalificacionFinal;
use App\Models\CategoriaApa;
use App\Models\Cronograma;
use App\Models\Evaluacion;
use App\Models\Evidencia;
use App\Models\Nomina;
use App\Models\Notificacion;
use App\Models\Periodo;
use App\Models\PlazoFacultad;
use App\Services\CalificacionCadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvaluacionController extends Controller
{
    public function index(): Response
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        $expedientes        = collect();
        $evaluacionHabilitada = false;
        $fechaAperturaEval  = null;

        if ($periodo && $user->facultad_id) {
            $etapaCarga = Cronograma::where('periodo_id', $periodo->id)
                ->where('etapa', 'carga_evidencias')
                ->first();

            $plazo = PlazoFacultad::where('periodo_id', $periodo->id)
                ->where('facultad_id', $user->facultad_id)
                ->first();

            $recepcionCerrada     = $plazo && $plazo->estaCerradoFormalmente();
            $evaluacionHabilitada = $recepcionCerrada || ($etapaCarga && $etapaCarga->haTerminado());
            $fechaAperturaEval    = $etapaCarga?->fecha_fin->format('d/m/Y');

            if ($evaluacionHabilitada) {
                $expedientes = Nomina::with(['academico.facultad', 'evaluaciones', 'calificacionFinal'])
                    ->where('periodo_id', $periodo->id)
                    ->whereHas('academico', fn ($q) => $q->where('facultad_id', $user->facultad_id))
                    ->whereIn('estado', ['carga_cerrada', 'en_evaluacion', 'evaluado'])
                    ->orderBy('created_at')
                    ->get()
                    ->map(function (Nomina $n) use ($user) {
                        $cf = $n->calificacionFinal;
                        $yoEvaluado = $n->evaluaciones->contains('evaluador_id', $user->id);

                        return [
                            'id'                => $n->id,
                            'estado'            => $n->estado,
                            'estado_label'      => match ($n->estado) {
                                'carga_cerrada' => 'Por evaluar',
                                'en_evaluacion' => 'En evaluación',
                                'evaluado'      => 'Evaluado',
                                default         => $n->estado,
                            },
                            'con_licencia'      => $n->con_licencia,
                            'academico'         => [
                                'name' => $n->academico->name,
                                'rut'  => $n->academico->rut,
                            ],
                            'facultad'          => $n->academico->facultad?->nombre,
                            'categoria'         => CalificacionCadService::labelCategoria(
                                $n->categoriaEfectiva()
                            ),
                            'yo_evaluado'       => $yoEvaluado,
                            'n_evaluaciones'    => $n->evaluaciones->count(),
                            'nota_final'        => $cf?->nota_final,
                            'concepto_final'    => $cf
                                ? CalificacionCadService::labelConcepto($cf->calificacion)
                                : null,
                        ];
                    });
            }
        }

        return Inertia::render('CCA/Expedientes', [
            'periodo'               => $periodo?->only(['id', 'anio', 'nombre']),
            'expedientes'           => $expedientes->values(),
            'evaluacionHabilitada'  => $evaluacionHabilitada,
            'fechaAperturaEval'     => $fechaAperturaEval,
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

        $etapaCarga = Cronograma::where('periodo_id', $nomina->periodo_id)
            ->where('etapa', 'carga_evidencias')
            ->first();

        $plazo = PlazoFacultad::where('periodo_id', $nomina->periodo_id)
            ->where('facultad_id', $user->facultad_id)
            ->first();

        $recepcionCerrada = $plazo && $plazo->estaCerradoFormalmente();

        if (!$recepcionCerrada && $etapaCarga && !$etapaCarga->haTerminado()) {
            return redirect()->route('cca.expedientes')
                ->with('error', 'La evaluación se habilita cuando cierre el período de entrega de evidencias ('.$etapaCarga->fecha_fin->format('d/m/Y').').');
        }

        $nomina->load(['academico.facultad', 'academico.departamento']);

        $apelacion   = $nomina->apelacion;
        $esApelacion = $apelacion && $apelacion->estado === 'resuelta'
                       && !$nomina->calificacionFinal()->where('es_apelacion', true)->exists();

        $categorias = CategoriaApa::orderBy('orden')->get();
        $evidencias = $esApelacion
            ? $nomina->evidenciasApelacion()->with('categoria')->get()
            : $nomina->evidenciasNormales()->with('categoria')->get();

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
            ->where('es_apelacion', $esApelacion)
            ->first();

        $academico = $nomina->academico;
        // Categoría: primero del campo SAPD de la nómina, luego del perfil del usuario
        $categoria = $nomina->categoriaEfectiva();
        $pesos     = CalificacionCadService::pesosParaCategoria($categoria);

        $categoriasConPeso = $categorias->map(fn ($c) => [
            'id'     => $c->id,
            'nombre' => $c->nombre,
            'slug'   => $c->slug,
            'peso'   => $pesos[CalificacionCadService::SLUG_A_REGLAMENTO[$c->slug] ?? $c->slug] ?? 0,
        ]);

        $todasEvaluaciones = Evaluacion::with('evaluador')
            ->where('nomina_id', $nomina->id)
            ->where('es_apelacion', $esApelacion)
            ->get()
            ->map(fn ($e) => [
                'evaluador'     => $e->evaluador->name,
                'nota_final'    => $e->notaFinalCad($categoria),
            ]);

        $calificacionFinal = $esApelacion
            ? $nomina->calificacionFinal()->where('es_apelacion', true)->first()
            : $nomina->calificacionFinal()->where('es_apelacion', false)->first();

        return Inertia::render('CCA/EvaluarExpediente', [
            'nomina' => [
                'id'           => $nomina->id,
                'estado'       => $nomina->estado,
                'con_licencia' => $nomina->con_licencia,
                'academico'    => [
                    'name'                  => $academico->name,
                    'rut'                   => $academico->rut,
                    'email'                 => $academico->email,
                    'facultad'              => $academico->facultad?->nombre,
                    'departamento'          => $academico->departamento?->nombre,
                    'categoria_academica'   => CalificacionCadService::labelCategoria($categoria),
                    'categoria_key'         => $categoria,
                    'linea_desarrollo'      => CalificacionCadService::labelLinea($academico->linea_desarrollo),
                    'fecha_jerarquizacion'  => $academico->fecha_jerarquizacion?->format('d/m/Y'),
                    'horas_contrato'        => $nomina->horas_contrato ?? $academico->horas_contrato_isem,
                    'horas_contrato_isem'   => $academico->horas_contrato_isem,
                    'horas_contrato_iisem'  => $academico->horas_contrato_iisem,
                    'nota_anterior'         => $nomina->notaAnterior(),
                    'concepto_anterior'     => $nomina->conceptoAnterior(),
                ],
            ],
            'categorias'             => $categoriasConPeso,
            'pesosReglamento'        => $pesos,
            'evidenciasPorCategoria' => $evidenciasPorCategoria,
            'miEvaluacion'           => $miEvaluacion ? [
                'puntaje_docencia'      => (float) $miEvaluacion->puntaje_docencia,
                'puntaje_investigacion' => (float) $miEvaluacion->puntaje_investigacion,
                'puntaje_vinculacion'   => (float) $miEvaluacion->puntaje_vinculacion,
                'puntaje_gestion'       => (float) $miEvaluacion->puntaje_gestion,
                'puntaje_formacion'     => (float) $miEvaluacion->puntaje_formacion,
                'comentario'            => $miEvaluacion->comentario,
                'nota_final'            => $miEvaluacion->notaFinalCad($categoria),
                'fecha'                 => $miEvaluacion->updated_at->format('d/m/Y H:i'),
                'evaluador'             => $user->name,
            ] : null,
            'todasEvaluaciones'      => $todasEvaluaciones,
            'calificacionFinal'      => $calificacionFinal ? [
                'nota_final'    => (float) ($calificacionFinal->nota_final ?? 0),
                'calificacion'  => $calificacionFinal->calificacion,
                'concepto_label'=> CalificacionCadService::labelConcepto($calificacionFinal->calificacion),
                'fecha'         => $calificacionFinal->fecha->format('d/m/Y'),
                'observacion'   => $calificacionFinal->observacion,
            ] : null,
            'esApelacion'            => $esApelacion,
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

        $apelacion   = $nomina->apelacion;
        $esApelacion = $apelacion && $apelacion->estado === 'resuelta'
                       && !$nomina->calificacionFinal()->where('es_apelacion', true)->exists();

        if (!$esApelacion && $nomina->calificacionFinal()->where('es_apelacion', false)->exists()) {
            return back()->with('error', 'Ya existe una calificación final. No se puede modificar la evaluación.');
        }

        $data = $request->validate([
            'puntaje_docencia'      => ['required', 'numeric', 'min:1', 'max:5'],
            'puntaje_investigacion' => ['required', 'numeric', 'min:1', 'max:5'],
            'puntaje_vinculacion'   => ['required', 'numeric', 'min:1', 'max:5'],
            'puntaje_gestion'       => ['required', 'numeric', 'min:1', 'max:5'],
            'puntaje_formacion'     => ['required', 'numeric', 'min:1', 'max:5'],
            'comentario'            => ['nullable', 'string', 'max:2000'],
        ]);

        Evaluacion::updateOrCreate(
            ['nomina_id' => $nomina->id, 'evaluador_id' => $user->id, 'es_apelacion' => $esApelacion],
            $data
        );

        $evaluacion = Evaluacion::where('nomina_id', $nomina->id)
            ->where('evaluador_id', $user->id)
            ->where('es_apelacion', $esApelacion)
            ->first();

        $categoria = $nomina->categoriaEfectiva();
        $notaFinal = $evaluacion->notaFinalCad($categoria);
        $concepto  = CalificacionCadService::labelConcepto(
            CalificacionCadService::conceptoDesdeNota($notaFinal)
        );

        if ($nomina->estado === 'carga_cerrada') {
            $nomina->update(['estado' => 'en_evaluacion']);
        }

        Notificacion::create([
            'user_id' => $nomina->user_id,
            'tipo'    => 'evaluacion_cca',
            'titulo'  => 'Evaluación CCA registrada',
            'mensaje' => "La CCA ha registrado una evaluación de su expediente. "
                . "Nota calculada: {$notaFinal}/5.0 ({$concepto}).",
            'leida'   => false,
            'url'     => route('academico.dashboard'),
        ]);

        return back()->with('success', 'Evaluación registrada correctamente.');
    }

    public function imprimirCalificacion(Nomina $nomina): View
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        $calificacion = $nomina->calificacionFinal;
        if (!$calificacion) {
            abort(404, 'Este expediente no tiene calificación final.');
        }

        $esApelacion = $calificacion->es_apelacion;
        $nomina->load(['academico.facultad', 'academico.departamento']);
        $academico = $nomina->academico;
        $categoria = $nomina->categoriaEfectiva();
        $pesos     = CalificacionCadService::pesosParaCategoria($categoria);
        $categorias = CategoriaApa::orderBy('orden')->get();

        $evaluaciones = Evaluacion::with('evaluador')
            ->where('nomina_id', $nomina->id)
            ->where('es_apelacion', $esApelacion)
            ->get();

        $horasBase = $nomina->horas_contrato ?? $academico->horas_contrato_isem ?? 0;

        $areas = $categorias->map(function ($cat) use ($pesos, $evaluaciones, $academico, $horasBase) {
            $slugReg     = CalificacionCadService::SLUG_A_REGLAMENTO[$cat->slug] ?? $cat->slug;
            $campo       = CalificacionCadService::CAMPOS[$slugReg] ?? null;
            $peso        = (float) ($pesos[$slugReg] ?? 0);
            $nota        = ($campo && $evaluaciones->count() > 0)
                ? round((float) $evaluaciones->avg($campo), 2)
                : 0.0;
            $concepto    = $nota > 0 ? CalificacionCadService::conceptoDesdeNota($nota) : null;
            $ponderacion = round(($peso * $nota) / 100, 2);
            $horasIsem   = $peso > 0 ? round(($peso * $horasBase) / 100, 1) : 0;
            $horasIisem  = $peso > 0 ? round(($peso * ($academico->horas_contrato_iisem ?? $horasBase)) / 100, 1) : 0;

            return [
                'nombre'      => $cat->nombre,
                'peso'        => $peso,
                'nota'        => $nota > 0 ? number_format($nota, 2) : '—',
                'concepto'    => $concepto ? CalificacionCadService::labelConcepto($concepto) : '—',
                'ponderacion' => $nota > 0 ? number_format($ponderacion, 2) : '—',
                'horas_isem'  => $horasIsem > 0 ? number_format($horasIsem, 2) : '—',
                'horas_iisem' => $horasIisem > 0 ? number_format($horasIisem, 2) : '—',
            ];
        });

        $periodo = $nomina->periodo;

        return view('cca.calificacion', compact(
            'nomina', 'calificacion', 'evaluaciones', 'periodo',
            'academico', 'categoria', 'areas'
        ));
    }

    public function finalize(Request $request, Nomina $nomina)
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        $apelacion   = $nomina->apelacion;
        $esApelacion = $apelacion && $apelacion->estado === 'resuelta'
                       && !$nomina->calificacionFinal()->where('es_apelacion', true)->exists();

        if (!$esApelacion && $nomina->calificacionFinal()->where('es_apelacion', false)->exists()) {
            return back()->with('error', 'Este expediente ya tiene una calificación final registrada.');
        }

        $evaluaciones = Evaluacion::where('nomina_id', $nomina->id)
            ->where('es_apelacion', $esApelacion)
            ->get();

        if ($evaluaciones->isEmpty()) {
            return back()->with('error', 'No hay evaluaciones registradas. Debe haber al menos una evaluación para finalizar.');
        }

        $data = $request->validate([
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $categoria = $nomina->categoriaEfectiva();

        $notasCad = $evaluaciones->map(
            fn ($e) => CalificacionCadService::calcularDesdeEvaluacion($e, $categoria)
        );

        $notaFinal  = round($notasCad->avg(), 2);
        $concepto   = CalificacionCadService::conceptoDesdeNota($notaFinal);
        $puntajeLegacy = (int) round($notaFinal * 20);

        CalificacionFinal::create([
            'nomina_id'       => $nomina->id,
            'puntaje_total'   => $puntajeLegacy,
            'nota_final'      => $notaFinal,
            'calificacion'    => $concepto,
            'determinada_por' => $user->id,
            'fecha'           => now()->toDateString(),
            'observacion'     => $data['observacion'] ?? null,
            'es_apelacion'    => $esApelacion,
        ]);

        $nomina->update(['estado' => 'evaluado']);

        $labelCalif = CalificacionCadService::labelConcepto($concepto);

        Notificacion::create([
            'user_id' => $nomina->user_id,
            'tipo'    => 'calificacion_final',
            'titulo'  => 'Calificación final registrada',
            'mensaje' => "La CCA ha registrado su calificación final: {$labelCalif} (nota {$notaFinal}/5.0)."
                       . ($esApelacion ? ' (Proceso de apelación)' : ''),
        ]);

        return back()->with('success', 'Calificación final registrada correctamente.');
    }
}
