<?php

namespace App\Http\Controllers;

use App\Models\Acta;
use App\Models\Apelacion;
use App\Models\CalificacionFinal;
use App\Models\CategoriaApa;
use App\Models\CompromisoApa;
use App\Models\Cronograma;
use App\Models\Evaluacion;
use App\Models\Evidencia;
use App\Models\Facultad;
use App\Models\Nomina;
use App\Models\Notificacion;
use App\Models\Periodo;
use App\Models\PlazoFacultad;
use App\Models\ReporteHistorial;
use App\Models\VerificacionCcda;
use App\Services\CalificacionCadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalistaCCDAController extends Controller
{
    // ─── Registro CCDA ───────────────────────────────────────────────────────

    public function registroCcda(): Response
    {
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        $etapa       = null;
        $facultades  = collect();

        if ($periodo) {
            $etapa = Cronograma::where('periodo_id', $periodo->id)
                ->where('etapa', 'registro_ccda')
                ->first();

            $verificaciones = VerificacionCcda::where('periodo_id', $periodo->id)
                ->get()
                ->keyBy('facultad_id');

            $facultades = Facultad::orderBy('nombre')
                ->get()
                ->map(function (Facultad $f) use ($periodo, $verificaciones) {
                    $nominas = Nomina::with([
                        'academico',
                        'apelacion',
                        'calificacionFinal',
                        'evaluaciones' => fn ($q) => $q->where('es_apelacion', false),
                    ])
                        ->where('periodo_id', $periodo->id)
                        ->whereHas('academico', fn ($q) => $q->where('facultad_id', $f->id))
                        ->get();

                    if ($nominas->isEmpty()) {
                        return null;
                    }

                    $nominasVerificacion = $nominas->filter(
                        fn (Nomina $n) => !$n->esSoloDaConocer()
                    );

                    $total     = $nominasVerificacion->count();
                    $evaluados = $nominasVerificacion
                        ->whereIn('estado', ['evaluado', 'cerrado'])
                        ->count();

                    $apelPendientes = $nominasVerificacion->filter(
                        fn ($n) => $n->apelacion && in_array($n->apelacion->estado, ['solicitada', 'en_revision'])
                    )->count();

                    $reevalCcaPendientes = $nominasVerificacion->filter(
                        fn ($n) => $n->requiereReevaluacionApelacionCca()
                    )->count();

                    $ccdaPendientes = $nominasVerificacion->filter(
                        fn (Nomina $n) => $n->requiereEvaluacionApelacionCcda()
                    )->count();

                    $actaCierre = Acta::where('periodo_id', $periodo->id)
                        ->where('facultad_id', $f->id)
                        ->where('tipo', 'cierre_proceso')
                        ->exists();

                    $v = $verificaciones->get($f->id);

                    $academicos = $nominasVerificacion->map(function (Nomina $n) {
                        $calif = $n->calificacionFinal;
                        $nota  = $calif ? (float) $calif->nota_final : null;

                        $notaConceptoOk = false;
                        if ($nota !== null && $calif) {
                            $conceptoEsperado = CalificacionCadService::conceptoDesdeNota($nota);
                            $notaConceptoOk   = $conceptoEsperado === $calif->calificacion;
                        }

                        $apelResuelta = !$n->apelacion
                            || !in_array($n->apelacion->estado, ['solicitada', 'en_revision']);

                        $retroRegistrada = $n->evaluaciones
                            ->whereNotNull('comentario')
                            ->where('comentario', '!=', '')
                            ->isNotEmpty();

                        $todosOk = $nota !== null && $notaConceptoOk && $apelResuelta && $retroRegistrada;
                        $estado  = !$nota ? 'pendiente'
                            : ($todosOk ? 'verificado' : 'con_observaciones');

                        return [
                            'id'               => $n->id,
                            'nombre'           => $n->academico->name,
                            'rut'              => $n->academico->rut,
                            'nota'             => $nota ? number_format($nota, 1) : null,
                            'concepto'         => $calif?->calificacionLabel(),
                            'nota_concepto_ok' => $notaConceptoOk,
                            'apel_resuelta'    => $apelResuelta,
                            'retro_registrada' => $retroRegistrada,
                            'estado'           => $estado,
                            'apelacion_info'   => $n->apelacion ? [
                                'estado'       => $n->apelacion->estado,
                                'nota_original' => $n->apelacion->nota_original,
                                'nota_final'   => $n->apelacion->nota_final,
                            ] : null,
                        ];
                    })->values();

                    $daConocer = $nominas
                        ->filter(fn (Nomina $n) => $n->esSoloDaConocer())
                        ->map(fn (Nomina $n) => [
                            'id'     => $n->id,
                            'nombre' => $n->academico->name,
                            'rut'    => $n->academico->rut,
                            'cargo'  => $n->labelExclusionEvaluacion() ?? 'Se da a conocer',
                        ])
                        ->values();

                    return [
                        'id'        => $f->id,
                        'nombre'    => $f->nombre,
                        'academicos' => $academicos,
                        'da_conocer' => $daConocer,
                        'stats'  => [
                            'total'                => $total,
                            'evaluados'            => $evaluados,
                            'apel_pendientes'      => $apelPendientes,
                            'reeval_cca_pendientes'=> $reevalCcaPendientes,
                            'ccda_pendientes'      => $ccdaPendientes,
                            'proceso_cerrado'      => $actaCierre,
                            'lista_para_verificar' => $evaluados === $total
                                && $total > 0
                                && $apelPendientes === 0
                                && $reevalCcaPendientes === 0
                                && $ccdaPendientes === 0
                                && $actaCierre,
                        ],
                        'verificacion' => $v ? [
                            'id'                   => $v->id,
                            'doc_fisica_archivada'  => $v->doc_fisica_archivada,
                            'notas_comunicadas'     => $v->notas_comunicadas,
                            'observaciones'         => $v->observaciones,
                            'verificado_en'         => $v->verificado_en?->format('d/m/Y H:i'),
                        ] : null,
                    ];
                })
                ->filter()
                ->values();
        }

        $totalFacultades      = $facultades->count();
        $facultadesVerificadas = $facultades->filter(
            fn ($f) => $f['verificacion'] && $f['verificacion']['verificado_en'] !== null
        )->count();

        $todasVerificadas = $totalFacultades > 0 && $facultadesVerificadas === $totalFacultades;

        return Inertia::render('AnalistaCCDA/RegistroCcda', [
            'periodo'              => $periodo ? [
                'id'          => $periodo->id,
                'anio'        => $periodo->anio,
                'nombre'      => $periodo->nombre,
                'cerrado_en'  => $periodo->cerrado_en?->format('d/m/Y H:i'),
            ] : null,
            'etapa'                => $etapa ? [
                'fecha_inicio' => $etapa->fecha_inicio->format('d/m/Y'),
                'fecha_fin'    => $etapa->fecha_fin->format('d/m/Y'),
                'esta_vigente' => $etapa->estaVigente(),
            ] : null,
            'facultades'             => $facultades,
            'total_facultades'       => $totalFacultades,
            'facultades_verificadas' => $facultadesVerificadas,
            'puede_cerrar'           => $todasVerificadas && $periodo && !$periodo->cerrado_en,
        ]);
    }

    public function storeVerificacion(Request $request, Facultad $facultad)
    {
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        if (!$periodo) {
            return back()->with('error', 'No hay período activo.');
        }

        $data = $request->validate([
            'doc_fisica_archivada' => ['boolean'],
            'notas_comunicadas'    => ['boolean'],
            'observaciones'        => ['nullable', 'string', 'max:1000'],
            'cerrar'               => ['sometimes', 'boolean'],
        ]);

        $cerrar = (bool) ($data['cerrar'] ?? false);
        unset($data['cerrar']);

        $verificacion = VerificacionCcda::updateOrCreate(
            ['periodo_id' => $periodo->id, 'facultad_id' => $facultad->id],
            array_merge($data, [
                'verificado_por' => auth()->id(),
                'verificado_en'  => $cerrar ? now() : null,
            ])
        );

        $msg = $cerrar
            ? "Verificación de {$facultad->nombre} cerrada correctamente."
            : "Cambios guardados para {$facultad->nombre}.";

        return back()->with('success', $msg);
    }

    // ─── Apelaciones 2do nivel CCDA ──────────────────────────────────────────

    public function apelaciones(): Response
    {
        $periodo    = Periodo::where('estado', 'activo')->latest()->first();
        $pendientes = collect();

        if ($periodo) {
            $pendientes = Nomina::with([
                'academico.facultad',
                'apelacion',
                'calificacionFinal',
            ])
                ->where('periodo_id', $periodo->id)
                ->where('estado', 'en_evaluacion')
                ->whereHas('apelaciones', fn ($q) =>
                    $q->where('estado', 'resuelta')->where('destino', 'ccda')
                )
                ->get()
                ->map(function (Nomina $n) {
                    $cf = $n->calificacionFinal()->where('es_apelacion', false)->first();
                    $cfAp = $n->calificacionFinal()->where('es_apelacion', true)->first();

                    return [
                        'id'       => $n->id,
                        'academico' => [
                            'name' => $n->academico->name,
                            'rut'  => $n->academico->rut,
                        ],
                        'facultad'              => $n->academico->facultad?->nombre,
                        'categoria'             => CalificacionCadService::labelCategoria($n->categoriaEfectiva()),
                        'calificacion_original' => $cf ? [
                            'nota_final'  => (float) $cf->nota_final,
                            'concepto'    => CalificacionCadService::labelConcepto($cf->calificacion),
                        ] : null,
                        'ya_resuelta'           => $cfAp !== null,
                        'concepto_resolucion'   => $cfAp
                            ? CalificacionCadService::labelConcepto($cfAp->calificacion)
                            : null,
                    ];
                });
        }

        return Inertia::render('AnalistaCCDA/Apelaciones', [
            'periodo'    => $periodo?->only(['id', 'anio', 'nombre']),
            'pendientes' => $pendientes->values(),
        ]);
    }

    public function showApelacion(Nomina $nomina): Response
    {
        $apelacion = $nomina->apelaciones()
            ->where('estado', 'resuelta')
            ->where('destino', 'ccda')
            ->latest()
            ->firstOrFail();

        if ($nomina->estado !== 'en_evaluacion') {
            return redirect()->route('analista.apelaciones')
                ->with('error', 'Este expediente no está en evaluación CCDA.');
        }

        $nomina->load(['academico.facultad', 'academico.departamento', 'compromisos']);

        $user      = auth()->user();
        $categorias = CategoriaApa::orderBy('orden')->get();
        $evidencias = $nomina->evidenciasApelacion()->with('categoria')->get();

        $evidenciasPorCategoria = [];
        foreach ($evidencias as $ev) {
            $evidenciasPorCategoria[$ev->categoria_id][] = [
                'id'             => $ev->id,
                'nombre_archivo' => $ev->nombre_archivo,
                'tamano'         => $ev->tamanoFormateado(),
                'descripcion'    => $ev->descripcion,
                'created_at'     => $ev->created_at->format('d/m/Y H:i'),
                'url_descarga'   => route('analista.apelaciones.evidencia.download',  [$nomina->id, $ev->id]),
                'url_preview'    => route('analista.apelaciones.evidencia.preview',   [$nomina->id, $ev->id]),
                'mime_type'      => $ev->mime_type,
            ];
        }

        $categoria     = $nomina->categoriaEfectiva();
        $pesos         = $nomina->pesosApa($categoria);
        $sinCompromiso = $nomina->compromisos->filter(fn ($c) => $c->estaConfirmado())->isEmpty();

        $categoriasConPeso = $categorias->map(fn ($c) => [
            'id'     => $c->id,
            'nombre' => $c->nombre,
            'slug'   => $c->slug,
            'peso'   => $pesos[CalificacionCadService::SLUG_A_REGLAMENTO[$c->slug] ?? $c->slug] ?? 0,
        ]);

        $miEvaluacion = Evaluacion::where('nomina_id', $nomina->id)
            ->where('evaluador_id', $user->id)
            ->where('es_apelacion', true)
            ->first();

        $calificacionOriginal = $nomina->calificacionFinal()->where('es_apelacion', false)->first();
        $calificacionFinalAp  = $nomina->calificacionFinal()->where('es_apelacion', true)->first();

        return Inertia::render('AnalistaCCDA/EvaluarApelacion', [
            'nomina' => [
                'id'           => $nomina->id,
                'estado'       => $nomina->estado,
                'con_licencia' => $nomina->con_licencia,
                'academico'    => [
                    'name'                => $nomina->academico->name,
                    'rut'                 => $nomina->academico->rut,
                    'email'               => $nomina->academico->email,
                    'facultad'            => $nomina->academico->facultad?->nombre,
                    'departamento'        => $nomina->academico->departamento?->nombre,
                    'categoria_academica' => CalificacionCadService::labelCategoria($categoria),
                    'categoria_key'       => $categoria,
                    'nota_anterior'       => $nomina->notaAnterior(),
                    'concepto_anterior'   => $nomina->academico->concepto_anterior,
                ],
            ],
            'apelacion' => [
                'motivo'     => $apelacion->motivo,
                'resolucion' => $apelacion->resolucion,
            ],
            'calificacionOriginal' => $calificacionOriginal ? [
                'nota_final'   => (float) $calificacionOriginal->nota_final,
                'concepto'     => CalificacionCadService::labelConcepto($calificacionOriginal->calificacion),
                'observacion'  => $calificacionOriginal->observacion,
            ] : null,
            'categorias'             => $categoriasConPeso,
            'pesosReglamento'        => $pesos,
            'evidenciasPorCategoria' => $evidenciasPorCategoria,
            'miEvaluacion'           => $miEvaluacion ? [
                'puntaje_docencia'        => (float) $miEvaluacion->puntaje_docencia,
                'puntaje_investigacion'   => (float) $miEvaluacion->puntaje_investigacion,
                'puntaje_vinculacion'     => (float) $miEvaluacion->puntaje_vinculacion,
                'puntaje_gestion'         => (float) $miEvaluacion->puntaje_gestion,
                'puntaje_formacion'       => (float) $miEvaluacion->puntaje_formacion,
                'extra_otras_actividades' => (float) ($miEvaluacion->extra_otras_actividades ?? 0),
                'sin_calificacion'        => (bool) $miEvaluacion->sin_calificacion,
                'motivo_sc'               => $miEvaluacion->motivo_sc,
                'comentario'              => $miEvaluacion->comentario,
                'nota_final'              => $miEvaluacion->notaFinalCad($categoria, $pesos),
            ] : null,
            'calificacionFinal' => $calificacionFinalAp ? [
                'nota_final'    => (float) $calificacionFinalAp->nota_final,
                'calificacion'  => $calificacionFinalAp->calificacion,
                'concepto_label'=> CalificacionCadService::labelConcepto($calificacionFinalAp->calificacion),
                'fecha'         => $calificacionFinalAp->fecha->format('d/m/Y'),
                'observacion'   => $calificacionFinalAp->observacion,
            ] : null,
            'sinCompromisoApa'     => $sinCompromiso,
            'compromisosSemestres' => $nomina->compromisos
                ->where('confirmado_en', '!=', null)
                ->map(fn ($c) => [
                    'semestre'           => $c->semestre,
                    'label'              => CompromisoApa::labelSemestre($c->semestre),
                    'pct_docencia'       => (float) $c->pct_docencia,
                    'pct_investigacion'  => (float) $c->pct_investigacion,
                    'pct_extension'      => (float) $c->pct_extension,
                    'pct_administracion' => (float) $c->pct_administracion,
                ])->values(),
        ]);
    }

    public function storeApelacion(Request $request, Nomina $nomina)
    {
        $apelacion = $nomina->apelaciones()
            ->where('estado', 'resuelta')
            ->where('destino', 'ccda')
            ->latest()
            ->firstOrFail();

        if ($nomina->estado !== 'en_evaluacion') {
            return back()->with('error', 'Expediente no disponible para evaluación CCDA.');
        }

        $user = auth()->user();

        $data = $request->validate([
            'sin_calificacion'        => ['sometimes', 'boolean'],
            'motivo_sc'               => ['nullable', 'string', 'max:2000', 'required_if:sin_calificacion,true'],
            'puntaje_docencia'        => ['nullable', 'numeric', 'min:1', 'max:5'],
            'puntaje_investigacion'   => ['nullable', 'numeric', 'min:1', 'max:5'],
            'puntaje_vinculacion'     => ['nullable', 'numeric', 'min:1', 'max:5'],
            'puntaje_gestion'         => ['nullable', 'numeric', 'min:1', 'max:5'],
            'extra_otras_actividades' => ['nullable', 'numeric', 'in:0,0.1,0.2,0.3'],
            'comentario'              => ['nullable', 'string', 'max:600'],
        ]);

        $sinCalificacion = (bool) ($data['sin_calificacion'] ?? false);

        if (!$sinCalificacion) {
            foreach (['puntaje_docencia', 'puntaje_investigacion', 'puntaje_vinculacion', 'puntaje_gestion'] as $campo) {
                if (!isset($data[$campo])) {
                    return back()->withErrors([$campo => 'La nota es obligatoria.']);
                }
            }
        }

        $categoria = $nomina->categoriaEfectiva();

        Evaluacion::updateOrCreate(
            ['nomina_id' => $nomina->id, 'evaluador_id' => $user->id, 'es_apelacion' => true],
            array_merge($data, [
                'sin_calificacion' => $sinCalificacion,
                'motivo_sc'        => $sinCalificacion ? ($data['motivo_sc'] ?? null) : null,
                'vigente_hasta'    => CalificacionCadService::vigenteHasta($categoria)->toDateString(),
            ])
        );

        return back()->with('success', 'Evaluación CCDA guardada. Revise los valores y finalice para registrar la calificación.');
    }

    public function finalizeApelacion(Request $request, Nomina $nomina)
    {
        $apelacion = $nomina->apelaciones()
            ->where('estado', 'resuelta')
            ->where('destino', 'ccda')
            ->latest()
            ->firstOrFail();

        if ($nomina->estado !== 'en_evaluacion') {
            return back()->with('error', 'Expediente no disponible para finalización CCDA.');
        }

        if ($nomina->calificacionFinal()->where('es_apelacion', true)->exists()) {
            return back()->with('error', 'Este expediente ya tiene calificación CCDA registrada.');
        }

        $user = auth()->user();

        $evaluaciones = Evaluacion::where('nomina_id', $nomina->id)
            ->where('es_apelacion', true)
            ->get();

        if ($evaluaciones->isEmpty()) {
            return back()->with('error', 'Debe guardar la evaluación antes de finalizar.');
        }

        $data = $request->validate([
            'observacion' => ['nullable', 'string', 'max:600'],
        ]);

        $categoria  = $nomina->categoriaEfectiva();
        $compromiso = CompromisoApa::where('nomina_id', $nomina->id)->first();

        $notasCad  = $evaluaciones->map(
            fn ($e) => CalificacionCadService::calcularDesdeEvaluacion($e, $categoria, $compromiso)
        );
        $notaFinal    = round($notasCad->avg(), 2);
        $concepto     = CalificacionCadService::conceptoDesdeNota($notaFinal);
        $labelCalif   = CalificacionCadService::labelConcepto($concepto);

        CalificacionFinal::create([
            'nomina_id'       => $nomina->id,
            'puntaje_total'   => (int) round($notaFinal * 20),
            'nota_final'      => $notaFinal,
            'calificacion'    => $concepto,
            'determinada_por' => $user->id,
            'fecha'           => now()->toDateString(),
            'observacion'     => $data['observacion'] ?? null,
            'es_apelacion'    => true,
        ]);

        $nomina->update(['estado' => 'evaluado']);

        Notificacion::create([
            'user_id' => $nomina->user_id,
            'tipo'    => 'calificacion_final',
            'titulo'  => 'Resolución de apelación CCDA',
            'mensaje' => "La CCDA ha resuelto su apelación. Calificación definitiva: {$labelCalif} ({$notaFinal}/5.0).",
        ]);

        return back()->with('success', "Apelación CCDA resuelta: {$labelCalif} ({$notaFinal}/5.0).");
    }

    public function downloadEvidenciaApelacion(Nomina $nomina, Evidencia $evidencia): StreamedResponse
    {
        if ($evidencia->nomina_id !== $nomina->id || !$evidencia->es_apelacion) {
            abort(404);
        }

        if (!Storage::disk('public')->exists($evidencia->ruta)) {
            abort(404);
        }

        return Storage::disk('public')->download($evidencia->ruta, $evidencia->nombre_archivo);
    }

    public function previewEvidenciaApelacion(Nomina $nomina, Evidencia $evidencia)
    {
        if ($evidencia->nomina_id !== $nomina->id || !$evidencia->es_apelacion) {
            abort(404);
        }

        if (!Storage::disk('public')->exists($evidencia->ruta)) {
            abort(404);
        }

        return Storage::disk('public')->response($evidencia->ruta, $evidencia->nombre_archivo);
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function estadoProceso(): Response
    {
        $periodos = Periodo::orderByDesc('anio')->get(['id', 'anio', 'nombre', 'estado']);
        $periodo  = $periodos->firstWhere('estado', 'activo') ?? $periodos->first();

        $facultades = collect();

        if ($periodo) {
            $facultades = Facultad::orderBy('nombre')
                ->get()
                ->map(function ($f) use ($periodo) {
                    $nominas = Nomina::where('periodo_id', $periodo->id)
                        ->whereHas('academico', fn ($q) => $q->where('facultad_id', $f->id))
                        ->get(['estado', 'con_licencia']);

                    if ($nominas->isEmpty()) {
                        return null;
                    }

                    $plazo = PlazoFacultad::where('periodo_id', $periodo->id)
                        ->where('facultad_id', $f->id)
                        ->first();

                    $actaCierre = Acta::where('periodo_id', $periodo->id)
                        ->where('facultad_id', $f->id)
                        ->where('tipo', 'cierre_proceso')
                        ->first();

                    $estados = $nominas->countBy('estado')->toArray();
                    $total   = $nominas->count();

                    return [
                        'id'           => $f->id,
                        'nombre'       => $f->nombre,
                        'total'        => $total,
                        'con_licencia' => $nominas->where('con_licencia', true)->count(),
                        'estados'      => $estados,
                        'evaluados'    => ($estados['evaluado'] ?? 0) + ($estados['cerrado'] ?? 0),
                        'recepcion_cerrada' => $plazo?->estaCerradoFormalmente() ?? false,
                        'proceso_cerrado'   => $actaCierre !== null,
                        'acta_id'      => $actaCierre?->id,
                    ];
                })
                ->filter()
                ->values();
        }

        return Inertia::render('AnalistaCCDA/EstadoProceso', [
            'periodo'   => $periodo?->only(['id', 'anio', 'nombre', 'estado']),
            'periodos'  => $periodos->map->only(['id', 'anio', 'nombre', 'estado']),
            'facultades' => $facultades,
        ]);
    }

    public function reporteCalificaciones(Request $request): View
    {
        $periodo = Periodo::where('estado', 'activo')->latest()->first()
            ?? Periodo::latest()->first();

        if (!$periodo) {
            abort(404, 'No hay períodos registrados.');
        }

        $facultadFiltro = $request->query('facultad_id');
        $todasFacultades = Facultad::orderBy('nombre')->get(['id', 'nombre']);

        $facultadesQuery = Facultad::orderBy('nombre');
        if ($facultadFiltro) {
            $facultadesQuery->where('id', $facultadFiltro);
        }

        $facultades = $facultadesQuery->get()->map(function ($f) use ($periodo) {
            $nominas = Nomina::with([
                'academico.departamento', 'academico.facultad',
                'calificacionFinal', 'evaluaciones', 'solicitudes',
            ])
                ->where('periodo_id', $periodo->id)
                ->whereHas('academico', fn ($q) => $q->where('facultad_id', $f->id))
                ->orderBy('created_at')
                ->get();

            if ($nominas->isEmpty()) {
                return null;
            }

            $academicos = $nominas->map(function (Nomina $n) {
                $ac = $n->academico;
                $cf = $n->calificacionFinal;

                $evals = $n->evaluaciones->where('es_apelacion', false);
                $promedioAreas = $evals->isNotEmpty() ? [
                    'docencia'      => round($evals->avg('puntaje_docencia'), 1),
                    'investigacion' => round($evals->avg('puntaje_investigacion'), 1),
                    'vinculacion'   => round($evals->avg('puntaje_vinculacion'), 1),
                    'gestion'       => round($evals->avg('puntaje_gestion'), 1),
                    'formacion'     => round($evals->avg('puntaje_formacion'), 1),
                ] : null;

                return [
                    'nombre'              => $ac->name,
                    'rut'                 => $ac->rut,
                    'facultad'            => $ac->facultad?->nombre,
                    'categoria'           => CalificacionCadService::labelCategoria($ac->categoria_academica),
                    'nota_anterior'       => $ac->nota_anterior,
                    'concepto_anterior'   => $ac->concepto_anterior,
                    'nota_docencia'       => $promedioAreas ? $promedioAreas['docencia'] : null,
                    'nota_investigacion'  => $promedioAreas ? $promedioAreas['investigacion'] : null,
                    'nota_vinculacion'    => $promedioAreas ? $promedioAreas['vinculacion'] : null,
                    'nota_gestion'        => $promedioAreas ? $promedioAreas['gestion'] : null,
                    'nota_formacion'      => $promedioAreas ? $promedioAreas['formacion'] : null,
                    'nota_final'          => $cf?->nota_final,
                    'concepto_final'      => $cf ? CalificacionCadService::labelConcepto($cf->calificacion) : null,
                    'estado_reporte'      => $n->estadoReporte(),
                    'tiene_licencia'      => $n->tieneLicenciaMedicaActiva(),
                ];
            });

            $califs = $academicos->pluck('concepto_final')->filter()->countBy();

            return [
                'nombre'     => $f->nombre,
                'academicos' => $academicos,
                'resumen'    => [
                    'total'      => $academicos->count(),
                    'con_calif'  => $academicos->filter(fn ($a) => $a['nota_final'])->count(),
                    'excelente'  => $califs['Excelente'] ?? 0,
                    'muy_bueno'  => $califs['Muy Bueno'] ?? 0,
                    'bueno'      => $califs['Bueno'] ?? 0,
                    'regular'    => $califs['Regular'] ?? 0,
                    'deficiente' => $califs['Deficiente'] ?? 0,
                ],
            ];
        })->filter()->values();

        ReporteHistorial::create([
            'periodo_id'    => $periodo->id,
            'facultad_id'   => $facultadFiltro ?: null,
            'generado_por'  => auth()->id(),
            'tipo'          => 'calificaciones',
            'created_at'    => now(),
        ]);

        return view('analista.reporte_calificaciones', compact(
            'periodo', 'facultades', 'todasFacultades', 'facultadFiltro'
        ));
    }

    public function incumplimientos(): View
    {
        $periodo = Periodo::where('estado', 'activo')->latest()->first()
            ?? Periodo::latest()->first();

        if (!$periodo) {
            abort(404, 'No hay períodos registrados.');
        }

        $facultades = Facultad::orderBy('nombre')->get()->map(function ($f) use ($periodo) {
            $plazo = PlazoFacultad::where('periodo_id', $periodo->id)
                ->where('facultad_id', $f->id)
                ->whereNotNull('cerrado_en')
                ->first();

            if (!$plazo) {
                return null;
            }

            $nominas = Nomina::with(['academico.departamento'])
                ->where('periodo_id', $periodo->id)
                ->whereHas('academico', fn ($q) => $q->where('facultad_id', $f->id))
                ->where('con_licencia', false)
                ->whereNotIn('estado', ['evaluado', 'cerrado'])
                ->orderBy('created_at')
                ->get();

            if ($nominas->isEmpty()) {
                return null;
            }

            return [
                'nombre'     => $f->nombre,
                'academicos' => $nominas->map(fn ($n) => [
                    'nombre'       => $n->academico->name,
                    'rut'          => $n->academico->rut,
                    'departamento' => $n->academico->departamento?->nombre,
                    'estado'       => $n->estado,
                    'evidencias'   => $n->evidenciasNormales()->count(),
                ]),
            ];
        })->filter()->values();

        return view('analista.incumplimientos', compact('periodo', 'facultades'));
    }

    // ─── Cierre de período ────────────────────────────────────────────────────

    public function cerrarPeriodo(Periodo $periodo)
    {
        if ($periodo->cerrado_en) {
            return back()->with('error', 'El período ya está cerrado.');
        }

        $totalFacultades = Facultad::whereHas(
            'nominas', fn ($q) => $q->where('periodo_id', $periodo->id)
        )->count();

        $verificadas = VerificacionCcda::where('periodo_id', $periodo->id)
            ->whereNotNull('verificado_en')
            ->count();

        if ($totalFacultades === 0 || $verificadas < $totalFacultades) {
            return back()->with('error', 'Todas las facultades deben estar verificadas antes de cerrar el período.');
        }

        $periodo->update([
            'cerrado_en' => now(),
            'cerrado_por' => auth()->id(),
            'estado'     => 'cerrado',
        ]);

        return back()->with('success', "Período {$periodo->nombre} cerrado correctamente. Ya no puede recibir modificaciones.");
    }

    // ─── Historial de períodos ────────────────────────────────────────────────

    public function historial(Request $request): Response
    {
        $anioFiltro = $request->query('anio');

        $anios = Periodo::distinct()->orderByDesc('anio')->pluck('anio');

        $periodos = Periodo::when($anioFiltro, fn ($q) => $q->where('anio', $anioFiltro))
            ->orderByDesc('anio')
            ->get()
            ->map(function (Periodo $p) {
                $distribucion = CalificacionFinal::whereHas(
                    'nomina', fn ($q) => $q->where('periodo_id', $p->id)
                )
                    ->where('es_apelacion', false)
                    ->selectRaw('calificacion, count(*) as total')
                    ->groupBy('calificacion')
                    ->pluck('total', 'calificacion');

                $totalEvaluados = CalificacionFinal::whereHas(
                    'nomina', fn ($q) => $q->where('periodo_id', $p->id)
                )->where('es_apelacion', false)->count();

                return [
                    'id'             => $p->id,
                    'anio'           => $p->anio,
                    'nombre'         => $p->nombre,
                    'estado'         => $p->estado,
                    'fecha_inicio'   => $p->fecha_inicio?->format('d/m/Y'),
                    'fecha_cierre'   => $p->fecha_cierre?->format('d/m/Y'),
                    'cerrado_en'     => $p->cerrado_en?->format('d/m/Y'),
                    'total_evaluados'=> $totalEvaluados,
                    'distribucion'   => $distribucion,
                ];
            });

        return Inertia::render('AnalistaCCDA/Historial', [
            'periodos'    => $periodos,
            'anios'       => $anios,
            'filtro_anio' => $anioFiltro ? (int) $anioFiltro : null,
        ]);
    }

    public function historialDetalle(Periodo $periodo): Response
    {
        $nominas = Nomina::with([
            'calificacionFinal',
            'apelacion',
            'evaluaciones' => fn ($q) => $q
                ->where('es_apelacion', false)
                ->with(['comentariosVicerrectora' => fn ($q) => $q->orderByDesc('created_at')]),
        ])
            ->where('periodo_id', $periodo->id)
            ->get();

        $evaluables = $nominas->filter(fn (Nomina $n) => $n->estado !== 'cerrado' && !in_array($n->estado, ['pendiente', 'en_carga']));
        $daConocer  = $nominas->filter(fn (Nomina $n) => $n->estado === 'cerrado');

        $mapAcademico = function (Nomina $n): array {
            $calif = $n->calificacionFinal;
            $comentarioVice = $n->evaluaciones
                ->flatMap(fn ($e) => $e->comentariosVicerrectora)
                ->sortByDesc('created_at')
                ->first();

            return [
                'id'               => $n->id,
                'nombre'           => $n->nombre,
                'rut'              => $n->rut,
                'cargo'            => $n->nombre_posicion,
                'unidad'           => $n->unidad,
                'facultad'         => $n->unidad_superior,
                'categoria'        => $n->categoria,
                'tipo_trabajador'  => $n->tipo_trabajador,
                'horas_contrato'   => $n->horas_contrato,
                'adscripcion'      => $n->adscripcion_academica,
                'nota'             => $calif ? number_format((float) $calif->nota_final, 1) : null,
                'concepto'         => $calif?->calificacionLabel(),
                'fecha_calificacion'=> $calif?->fecha?->format('d/m/Y'),
                'sin_calificacion' => (bool) ($n->evaluaciones->first()?->sin_calificacion),
                'comentario_vice'  => $comentarioVice?->comentario,
                'tiene_apelacion'  => (bool) $n->apelacion,
                'estado'           => $n->estado,
            ];
        };

        $porFacultad = $evaluables
            ->map($mapAcademico)
            ->sortBy('nombre')
            ->groupBy('facultad')
            ->map(fn ($grupo, $nombre) => [
                'nombre'     => $nombre,
                'academicos' => $grupo->values(),
                'stats'      => [
                    'total'        => $grupo->count(),
                    'con_nota'     => $grupo->filter(fn ($a) => $a['nota'] !== null)->count(),
                    'sin_nota'     => $grupo->filter(fn ($a) => $a['nota'] === null)->count(),
                    'promedio'     => $grupo->filter(fn ($a) => $a['nota'] !== null)->count() > 0
                        ? number_format(
                            $grupo->filter(fn ($a) => $a['nota'] !== null)
                                ->avg(fn ($a) => (float) $a['nota']),
                            2
                        )
                        : null,
                ],
            ])
            ->sortKeys()
            ->values();

        $distribucion = CalificacionFinal::whereHas(
            'nomina', fn ($q) => $q->where('periodo_id', $periodo->id)
        )
            ->where('es_apelacion', false)
            ->selectRaw('calificacion, count(*) as total')
            ->groupBy('calificacion')
            ->pluck('total', 'calificacion');

        return Inertia::render('AnalistaCCDA/HistorialPeriodo', [
            'periodo'     => [
                'id'          => $periodo->id,
                'nombre'      => $periodo->nombre,
                'anio'        => $periodo->anio,
                'estado'      => $periodo->estado,
                'fecha_inicio'=> $periodo->fecha_inicio?->format('d/m/Y'),
                'fecha_cierre'=> $periodo->fecha_cierre?->format('d/m/Y'),
                'cerrado_en'  => $periodo->cerrado_en?->format('d/m/Y'),
            ],
            'por_facultad'=> $porFacultad,
            'da_conocer'  => $daConocer->map($mapAcademico)->values(),
            'distribucion'=> $distribucion,
            'total'       => $evaluables->count(),
        ]);
    }

    public function imprimirActaPeriodo(Periodo $periodo): View
    {
        $nominas = Nomina::with([
            'calificacionFinal',
            'evaluaciones' => fn ($q) => $q
                ->where('es_apelacion', false)
                ->with(['comentariosVicerrectora' => fn ($q) => $q->orderByDesc('created_at')]),
        ])
            ->where('periodo_id', $periodo->id)
            ->get();

        $evaluables = $nominas->filter(fn ($n) => $n->estado !== 'cerrado' && !in_array($n->estado, ['pendiente', 'en_carga']));
        $daConocer  = $nominas->filter(fn ($n) => $n->estado === 'cerrado');

        $mapRow = function (Nomina $n): array {
            $calif = $n->calificacionFinal;
            $comentarioVice = $n->evaluaciones
                ->flatMap(fn ($e) => $e->comentariosVicerrectora)
                ->sortByDesc('created_at')
                ->first();

            return [
                'nombre'           => $n->nombre,
                'rut'              => $n->rut,
                'cargo'            => $n->nombre_posicion,
                'categoria'        => $n->categoria,
                'tipo_trabajador'  => $n->tipo_trabajador,
                'horas_contrato'   => $n->horas_contrato,
                'nota'             => $calif ? number_format((float) $calif->nota_final, 1) : null,
                'concepto'         => $calif?->calificacionLabel(),
                'comentario_vice'  => $comentarioVice?->comentario,
                'facultad'         => $n->unidad_superior,
            ];
        };

        $porFacultad = $evaluables
            ->map($mapRow)
            ->sortBy('nombre')
            ->groupBy('facultad')
            ->sortKeys();

        $distribucion = CalificacionFinal::whereHas(
            'nomina', fn ($q) => $q->where('periodo_id', $periodo->id)
        )
            ->where('es_apelacion', false)
            ->selectRaw('calificacion, count(*) as total')
            ->groupBy('calificacion')
            ->pluck('total', 'calificacion');

        $generadoPor = auth()->user()->name ?? 'Sistema';

        return view('analista.acta_periodo', compact('periodo', 'porFacultad', 'daConocer', 'distribucion', 'generadoPor'));
    }
}
