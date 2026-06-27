<?php

namespace App\Http\Controllers;

use App\Models\ComisionCca;
use App\Models\CalificacionFinal;
use App\Models\CategoriaApa;
use App\Models\CompromisoApa;
use App\Models\Cronograma;
use App\Models\Evaluacion;
use App\Models\Evidencia;
use App\Models\Nomina;
use App\Models\Notificacion;
use App\Models\Periodo;
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

        if ($periodo && $user->facultad_id && $user->puedeActuarComoCca($periodo)) {
            $etapaCarga = Cronograma::where('periodo_id', $periodo->id)
                ->where('etapa', 'validacion_secretario')
                ->first();

            $comisionConfirmada = ComisionCca::where('periodo_id', $periodo->id)
                ->where('facultad_id', $user->facultad_id)
                ->where('estado', 'confirmada')
                ->exists();

            $evaluacionHabilitada = $comisionConfirmada
                && $etapaCarga
                && $etapaCarga->haTerminado();
            $fechaAperturaEval    = $etapaCarga?->fecha_fin->format('d/m/Y');

            if ($evaluacionHabilitada) {
                $expedientes = Nomina::with([
                    'academico.facultad', 'evaluaciones', 'calificacionFinal',
                    'evidenciasNormales', 'evidenciasApelacion', 'compromisos', 'apelacion',
                ])
                    ->where('periodo_id', $periodo->id)
                    ->where('facultad_id', $user->facultad_id)
                    ->listosEvaluacionCca()
                    ->where('user_id', '!=', $user->id)
                    ->orderBy('created_at')
                    ->get()
                    ->map(function (Nomina $n) use ($user) {
                        $cf = $n->calificacionFinal;
                        $enApelacion = $n->requiereReevaluacionApelacionCca();
                        $yoEvaluado = $n->evaluaciones
                            ->where('evaluador_id', $user->id)
                            ->where('es_apelacion', $enApelacion)
                            ->isNotEmpty();

                        return [
                            'id'                => $n->id,
                            'estado'            => $n->estado,
                            'en_apelacion'      => $enApelacion,
                            'estado_label'      => $enApelacion
                                ? 'Re-evaluación apelación'
                                : match ($n->estado) {
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
                            'concepto_final'    => $cf && !$enApelacion
                                ? CalificacionCadService::labelConcepto($cf->calificacion)
                                : ($enApelacion && $cf
                                    ? CalificacionCadService::labelConcepto($cf->calificacion).' (original)'
                                    : null),
                        ];
                    });
            }
        }

        return Inertia::render('CCA/Expedientes', [
            'periodo'               => $periodo?->only(['id', 'anio', 'nombre']),
            'expedientes'           => $expedientes->values(),
            'evaluacionHabilitada'  => $evaluacionHabilitada,
            'fechaAperturaEval'     => $fechaAperturaEval,
            'comisionConfirmada'    => $periodo && $user->facultad_id
                ? ComisionCca::where('periodo_id', $periodo->id)
                    ->where('facultad_id', $user->facultad_id)
                    ->where('estado', 'confirmada')
                    ->exists()
                : false,
            'esIntegranteComision'  => $user->puedeActuarComoCca($periodo),
        ]);
    }

    public function show(Nomina $nomina): Response
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        if ($nomina->esSoloDaConocer()) {
            abort(403, $nomina->labelExclusionEvaluacion() ?? 'Este académico no participa del proceso evaluativo.');
        }

        if (!$nomina->participaEvaluacionFormal()) {
            abort(403, 'Este académico solo registra declaración APA este período; no corresponde evaluación formal.');
        }

        $this->autorizarEvaluacionCca($nomina, $user);

        if (!in_array($nomina->estado, ['carga_cerrada', 'en_evaluacion', 'evaluado'])) {
            abort(403);
        }

        $etapaCarga = Cronograma::where('periodo_id', $nomina->periodo_id)
            ->where('etapa', 'validacion_secretario')
            ->first();

        if ($etapaCarga && !$etapaCarga->haTerminado()) {
            return redirect()->route('cca.expedientes')
                ->with('error', 'La evaluación se habilita cuando cierre la validación del secretario ('.$etapaCarga->fecha_fin->format('d/m/Y').').');
        }

        $nomina->load(['academico.facultad', 'academico.departamento', 'compromisos', 'evidenciasNormales', 'evidenciasApelacion']);

        $apelacion   = $nomina->apelacion;
        $esApelacion = $nomina->requiereReevaluacionApelacionCca();

        $categorias = CategoriaApa::orderBy('orden')->get();

        $conteoEvidencias = [];
        $conteoEvidenciasApelacion = [];
        foreach ($nomina->evidenciasNormales as $ev) {
            $conteoEvidencias[$ev->categoria_id] = ($conteoEvidencias[$ev->categoria_id] ?? 0) + 1;
        }
        foreach ($nomina->evidenciasApelacion as $ev) {
            $conteoEvidenciasApelacion[$ev->categoria_id] = ($conteoEvidenciasApelacion[$ev->categoria_id] ?? 0) + 1;
        }

        $miEvaluacion = Evaluacion::where('nomina_id', $nomina->id)
            ->where('evaluador_id', $user->id)
            ->where('es_apelacion', $esApelacion)
            ->first();

        $academico  = $nomina->academico;
        $categoria     = $nomina->categoriaEfectiva();
        $pesosDeclarados = $nomina->pesosApa($categoria);
        $pesosCalificacion = $miEvaluacion?->pesosParaCalificacion($categoria) ?? $pesosDeclarados;
        $sinCompromiso = $nomina->compromisos->filter(fn ($c) => $c->estaConfirmado())->isEmpty();

        $categoriasConPeso = $categorias->map(fn ($c) => [
            'id'     => $c->id,
            'nombre' => $c->nombre,
            'slug'   => $c->slug,
            'peso'   => $pesosDeclarados[CalificacionCadService::SLUG_A_REGLAMENTO[$c->slug] ?? $c->slug] ?? 0,
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

        $calificacionOriginal = $esApelacion
            ? $nomina->calificacionFinal()->where('es_apelacion', false)->first()
            : null;

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
                    'categoria_academica'   => CalificacionCadService::labelCategoria($nomina->categoriaEfectiva()),
                    'categoria_key'         => $categoria,
                    'linea_desarrollo'      => CalificacionCadService::labelLinea($academico->linea_desarrollo),
                    'fecha_jerarquizacion'  => $academico->fecha_jerarquizacion?->format('d/m/Y'),
                    'horas_contrato_isem'   => $academico->horas_contrato_isem,
                    'horas_contrato_iisem'  => $academico->horas_contrato_iisem,
                    'nota_anterior'         => $nomina->notaAnterior(),
                    'concepto_anterior'     => $academico->concepto_anterior,
                ],
            ],
            'categorias'             => $categoriasConPeso,
            'pesosReglamento'        => $pesosCalificacion,
            'pesosDeclarados'        => $pesosDeclarados,
            'conteoEvidencias'          => $conteoEvidencias,
            'conteoEvidenciasApelacion'  => $conteoEvidenciasApelacion,
            'miEvaluacion'           => $miEvaluacion ? [
                'puntaje_docencia'         => (float) $miEvaluacion->puntaje_docencia,
                'puntaje_investigacion'    => (float) $miEvaluacion->puntaje_investigacion,
                'puntaje_vinculacion'      => (float) $miEvaluacion->puntaje_vinculacion,
                'puntaje_gestion'          => (float) $miEvaluacion->puntaje_gestion,
                'puntaje_formacion'        => (float) $miEvaluacion->puntaje_formacion,
                'extra_otras_actividades'  => (float) ($miEvaluacion->extra_otras_actividades ?? 0),
                'sin_calificacion'         => (bool) $miEvaluacion->sin_calificacion,
                'motivo_sc'                => $miEvaluacion->motivo_sc,
                'comentario'               => $miEvaluacion->comentario,
                'horas_reales'             => $miEvaluacion->horasRealesArray(),
                'nota_final'               => $miEvaluacion->notaFinalCad($categoria),
                'fecha'                    => $miEvaluacion->updated_at->format('d/m/Y H:i'),
                'evaluador'                => $user->name,
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
            'apelacion'              => $esApelacion && $apelacion ? [
                'motivo' => $apelacion->motivo,
                'fecha'  => $apelacion->fecha_solicitud?->format('d/m/Y'),
            ] : null,
            'calificacionOriginal'   => $calificacionOriginal ? [
                'nota_final'     => (float) ($calificacionOriginal->nota_final ?? 0),
                'concepto_label' => CalificacionCadService::labelConcepto($calificacionOriginal->calificacion),
            ] : null,
            'sinCompromisoApa'       => $sinCompromiso,
            'compromisosSemestres'   => $this->compromisosSemestresPayload($nomina),
        ]);
    }

    public function showCategoria(Nomina $nomina, CategoriaApa $categoria): Response
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        $nomina->load('academico');

        $esApelacion = $nomina->requiereReevaluacionApelacionCca();

        $mapEv = fn ($ev) => [
            'id'             => $ev->id,
            'nombre_archivo' => $ev->nombre_archivo,
            'tamano'         => $ev->tamanoFormateado(),
            'descripcion'    => $ev->descripcion,
            'mime_type'      => $ev->mime_type,
            'created_at'     => $ev->created_at->format('d/m/Y H:i'),
            'url_descarga'   => route('cca.evidencias.download', [$nomina->id, $ev->id]),
            'url_preview'    => route('cca.evidencias.preview',  [$nomina->id, $ev->id]),
        ];

        $evidenciasNormales = $nomina->evidenciasNormales()
            ->where('categoria_id', $categoria->id)
            ->get();

        $evidenciasApelacion = $esApelacion
            ? $nomina->evidenciasApelacion()->where('categoria_id', $categoria->id)->get()
            : collect();

        return Inertia::render('CCA/ExpedienteCategoria', [
            'nomina'      => [
                'id'     => $nomina->id,
                'nombre' => $nomina->academico->name,
            ],
            'categoria'   => [
                'id'          => $categoria->id,
                'nombre'      => $categoria->nombre,
                'descripcion' => $categoria->descripcion,
            ],
            'esApelacion' => $esApelacion,
            'evidenciasNormales'  => $evidenciasNormales->map($mapEv)->values(),
            'evidenciasApelacion' => $evidenciasApelacion->map($mapEv)->values(),
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

    public function previewEvidencia(Nomina $nomina, Evidencia $evidencia)
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

        return Storage::disk('public')->response($evidencia->ruta, $evidencia->nombre_archivo);
    }

    public function store(Request $request, Nomina $nomina)
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        if ($nomina->esSoloDaConocer()) {
            abort(403, $nomina->labelExclusionEvaluacion() ?? 'Este académico no participa del proceso evaluativo.');
        }

        $this->autorizarEvaluacionCca($nomina, $user);

        $apelacion   = $nomina->apelacion;
        $esApelacion = $apelacion && $apelacion->estado === 'resuelta'
                       && !$nomina->calificacionFinal()->where('es_apelacion', true)->exists();

        if (!$esApelacion && $nomina->calificacionFinal()->where('es_apelacion', false)->exists()) {
            return back()->with('error', 'Ya existe una calificación final. No se puede modificar la evaluación.');
        }

        $sinCalificacion = (bool) $request->boolean('sin_calificacion');

        $reglas = [
            'sin_calificacion'         => ['sometimes', 'boolean'],
            'motivo_sc'                => ['nullable', 'string', 'max:2000', 'required_if:sin_calificacion,true'],
            'puntaje_docencia'         => ['nullable', 'numeric', 'min:1', 'max:5'],
            'puntaje_investigacion'    => ['nullable', 'numeric', 'min:1', 'max:5'],
            'puntaje_vinculacion'      => ['nullable', 'numeric', 'min:1', 'max:5'],
            'puntaje_gestion'          => ['nullable', 'numeric', 'min:1', 'max:5'],
            'puntaje_formacion'        => ['nullable', 'numeric', 'min:1', 'max:5'],
            'extra_otras_actividades'  => ['nullable', 'numeric', 'in:0,0.1,0.2,0.3'],
            'comentario'               => ['nullable', 'string', 'max:600'],
        ];

        if (!$sinCalificacion) {
            foreach (Evaluacion::SEMESTRES as $sem) {
                foreach (['docencia', 'investigacion', 'extension', 'administracion'] as $area) {
                    $reglas["horas_reales.{$sem}.hrs_{$area}"] = ['required', 'numeric', 'min:0', 'max:9999'];
                }
                $reglas["horas_reales.{$sem}.hrs_otras"] = ['nullable', 'numeric', 'min:0', 'max:9999'];
            }
        } else {
            foreach (Evaluacion::SEMESTRES as $sem) {
                foreach (Evaluacion::AREAS_HORAS as $area) {
                    $reglas["horas_reales.{$sem}.hrs_{$area}"] = ['nullable', 'numeric', 'min:0', 'max:9999'];
                }
            }
        }

        $data = $request->validate($reglas);

        if (!$sinCalificacion) {
            foreach (['puntaje_docencia', 'puntaje_investigacion', 'puntaje_vinculacion', 'puntaje_gestion'] as $campo) {
                if (!isset($data[$campo])) {
                    return back()->withErrors([$campo => 'La nota es obligatoria salvo marcar Sin calificación.']);
                }
            }
        }

        $horasRealesAttrs = Evaluacion::horasRealesDesdeRequest($data['horas_reales'] ?? null);

        $categoria = $nomina->categoriaEfectiva();

        Evaluacion::updateOrCreate(
            ['nomina_id' => $nomina->id, 'evaluador_id' => $user->id, 'es_apelacion' => $esApelacion],
            array_merge(
                collect($data)->except('horas_reales')->all(),
                $horasRealesAttrs,
                [
                    'sin_calificacion' => $sinCalificacion,
                    'motivo_sc'        => $sinCalificacion ? ($data['motivo_sc'] ?? null) : null,
                    'vigente_hasta'    => CalificacionCadService::vigenteHasta($categoria)->toDateString(),
                ]
            )
        );

        $evaluacion = Evaluacion::where('nomina_id', $nomina->id)
            ->where('evaluador_id', $user->id)
            ->where('es_apelacion', $esApelacion)
            ->first();

        $categoria = $nomina->categoriaEfectiva();
        $nomina->loadMissing('compromisos');
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
        $nomina->load(['academico.facultad', 'academico.departamento', 'compromisos']);
        $academico = $nomina->academico;
        $categoria = $nomina->categoriaEfectiva();
        $pesosDeclarados = $nomina->pesosApa($categoria);
        $categorias = CategoriaApa::orderBy('orden')->get();

        $evaluaciones = Evaluacion::with('evaluador')
            ->where('nomina_id', $nomina->id)
            ->where('es_apelacion', $esApelacion)
            ->get();

        $horasPorSem = CalificacionCadService::horasRealesPromedioPorSemestre($evaluaciones);
        $pesos = $horasPorSem
            ? CalificacionCadService::pesosDesdeHorasSumadas(
                CalificacionCadService::sumarHorasAnualesDesdeSemestres($horasPorSem),
                $categoria
            )
            : $pesosDeclarados;

        $totalHorasIsem = $horasPorSem
            ? round(array_sum($horasPorSem['S1']), 2)
            : ($academico->horas_contrato_isem ?? 0);
        $totalHorasIisem = $horasPorSem
            ? round(array_sum($horasPorSem['S2']), 2)
            : ($academico->horas_contrato_iisem ?? 0);

        $areas = $categorias->map(function ($cat) use ($pesos, $evaluaciones, $horasPorSem, $academico) {
            $slugReg     = CalificacionCadService::SLUG_A_REGLAMENTO[$cat->slug] ?? $cat->slug;
            $campo       = CalificacionCadService::CAMPOS[$slugReg] ?? null;
            $areaHrs     = CalificacionCadService::REG_A_AREA_HRS[$slugReg] ?? null;
            $peso        = (float) ($pesos[$slugReg] ?? 0);
            $nota        = ($campo && $evaluaciones->count() > 0)
                ? round((float) $evaluaciones->avg($campo), 2)
                : 0.0;
            $concepto    = $nota > 0 ? CalificacionCadService::conceptoDesdeNota($nota) : null;
            $ponderacion = round(($peso * $nota) / 100, 2);

            if ($horasPorSem && $areaHrs) {
                $horasIsem  = (float) $horasPorSem['S1'][$areaHrs];
                $horasIisem = (float) $horasPorSem['S2'][$areaHrs];
            } else {
                $horasIsem  = $peso > 0 ? round(($peso * ($academico->horas_contrato_isem ?? 0)) / 100, 2) : 0;
                $horasIisem = $peso > 0 ? round(($peso * ($academico->horas_contrato_iisem ?? 0)) / 100, 2) : 0;
            }

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
            'academico', 'categoria', 'areas', 'totalHorasIsem', 'totalHorasIisem'
        ));
    }

    public function finalize(Request $request, Nomina $nomina)
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        if ($nomina->esSoloDaConocer()) {
            abort(403, $nomina->labelExclusionEvaluacion() ?? 'Este académico no participa del proceso evaluativo.');
        }

        $this->autorizarEvaluacionCca($nomina, $user);

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
            'observacion' => ['nullable', 'string', 'max:600'],
        ]);

        $categoria  = $nomina->categoriaEfectiva();

        $notasCad = $evaluaciones->map(
            fn ($e) => $e->notaFinalCad($categoria)
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

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function compromisosSemestresPayload(Nomina $nomina): \Illuminate\Support\Collection
    {
        return $nomina->compromisos
            ->where('confirmado_en', '!=', null)
            ->sortBy(fn ($c) => $c->semestre === 'S1' ? 0 : 1)
            ->map(fn (CompromisoApa $c) => [
                'semestre'           => $c->semestre,
                'label'              => CompromisoApa::labelSemestre($c->semestre),
                'hrs_docencia'       => (float) ($c->hrs_docencia       ?? 0),
                'hrs_investigacion'  => (float) ($c->hrs_investigacion  ?? 0),
                'hrs_extension'      => (float) ($c->hrs_extension      ?? 0),
                'hrs_administracion' => (float) ($c->hrs_administracion ?? 0),
                'hrs_otras'          => (float) ($c->hrs_otras          ?? 0),
                'pct_docencia'       => (float) $c->pct_docencia,
                'pct_investigacion'  => (float) $c->pct_investigacion,
                'pct_extension'      => (float) $c->pct_extension,
                'pct_administracion' => (float) $c->pct_administracion,
                'pct_otras'          => (float) $c->pct_otras,
            ])
            ->values();
    }

    private function autorizarEvaluacionCca(Nomina $nomina, $user): void
    {
        if (!$user->puedeActuarComoCca($nomina->periodo)) {
            abort(403, 'No está designado en la comisión evaluadora del período activo.');
        }

        if ($nomina->user_id === $user->id) {
            abort(403, 'No puede evaluar su propio expediente.');
        }

        $comisionConfirmada = ComisionCca::where('periodo_id', $nomina->periodo_id)
            ->where('facultad_id', $nomina->facultad_id)
            ->where('estado', 'confirmada')
            ->exists();

        if (!$comisionConfirmada) {
            abort(403, 'La comisión evaluadora de la facultad aún no ha sido confirmada por el analista CCDA.');
        }

        if (!$nomina->listoParaEvaluacionCca()) {
            abort(403, $nomina->motivoNoListoEvaluacionCca() ?? 'El expediente no está listo para evaluación CCA.');
        }
    }
}
