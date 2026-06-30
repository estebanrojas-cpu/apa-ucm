<?php

namespace App\Http\Controllers;

use App\Models\Acta;
use App\Models\CategoriaApa;
use App\Models\Evidencia;
use App\Models\Nomina;
use App\Models\Notificacion;
use App\Models\Periodo;
use App\Models\PlazoFacultad;
use App\Models\User;
use App\Services\CalificacionCadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecretarioController extends Controller
{
    public function expedientes(): Response
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        $expedientes = collect();
        $plazo       = null;

        $actaCierre        = null;
        $puedesCerrarProceso = false;
        $motivoNoPuede     = null;
        $requisitosCierre  = [];

        if ($periodo && $user->facultad_id) {
            $expedientes = Nomina::with(['academico', 'apelacion', 'solicitudes'])
                ->where('periodo_id', $periodo->id)
                ->whereHas('academico', fn ($q) => $q->where('facultad_id', $user->facultad_id))
                ->orderBy('created_at')
                ->get()
                ->map(fn ($n) => [
                    'id'                   => $n->id,
                    'estado'               => $n->estado,
                    'con_licencia'         => $n->con_licencia,
                    'observacion_licencia' => $n->observacion_licencia,
                    'tiene_licencia_activa'=> $n->tieneLicenciaMedicaActiva(),
                    'estado_especial'      => $n->labelExclusionEvaluacion()
                        ?? (!$n->participaEvaluacionFormal()
                        ? 'Solo registro APA'
                        : ($n->tieneLicenciaMedicaActiva()
                        ? 'Caso especial - Licencia médica'
                        : ($n->con_licencia ? 'Caso especial' : null))),
                    'academico'            => [
                        'name' => $n->academico->name,
                        'rut'  => $n->academico->rut,
                    ],
                ]);

            $plazoModel = PlazoFacultad::where('periodo_id', $periodo->id)
                ->where('facultad_id', $user->facultad_id)
                ->first();

            if ($plazoModel) {
                $plazo = [
                    'fecha_limite' => $plazoModel->fecha_limite->format('Y-m-d'),
                    'vigente'      => $plazoModel->estaVigente(),
                    'actualizado'  => $plazoModel->updated_at->format('d/m/Y'),
                    'cerrado'      => $plazoModel->estaCerradoFormalmente(),
                    'cerrado_en'   => $plazoModel->cerrado_en?->format('d/m/Y H:i'),
                ];
            }

            $actaModel = Acta::where('periodo_id', $periodo->id)
                ->where('facultad_id', $user->facultad_id)
                ->where('tipo', 'cierre_proceso')
                ->first();

            if ($actaModel) {
                $actaCierre = [
                    'id'    => $actaModel->id,
                    'fecha' => $actaModel->fecha->format('d/m/Y'),
                    'url'   => route('secretario.acta-cierre', $actaModel->id),
                ];
            } else {
                $requisitosCierre = $this->requisitosCierreProceso(
                    $periodo->id, $user->facultad_id, $plazoModel
                );
                [$puedesCerrarProceso, $motivoNoPuede] = $this->verificarCierreProceso(
                    $periodo->id, $user->facultad_id, $plazoModel
                );
            }
        }

        return Inertia::render('Secretario/Expedientes', [
            'periodo'              => $periodo?->only(['id', 'anio', 'nombre', 'fecha_cierre']),
            'expedientes'          => $expedientes->values(),
            'plazo'                => $plazo,
            'actaCierre'           => $actaCierre,
            'puedesCerrarProceso'  => $puedesCerrarProceso,
            'motivoNoPuede'        => $motivoNoPuede,
            'requisitosCierre'     => $requisitosCierre,
        ]);
    }

    public function showExpediente(Nomina $nomina): Response
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
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
                'mime_type'      => $ev->mime_type,
                'url_descarga'   => route('secretario.evidencias.download', [$nomina->id, $ev->id]),
                'url_preview'    => route('secretario.evidencias.preview',  [$nomina->id, $ev->id]),
            ];
        }

        $evidenciasApelacion = $nomina->evidenciasApelacion()->with('categoria')->get();
        $evidenciasApelacionPorCategoria = [];
        foreach ($evidenciasApelacion as $ev) {
            $evidenciasApelacionPorCategoria[$ev->categoria_id][] = [
                'id'             => $ev->id,
                'nombre_archivo' => $ev->nombre_archivo,
                'tamano'         => $ev->tamanoFormateado(),
                'descripcion'    => $ev->descripcion,
                'created_at'     => $ev->created_at->format('d/m/Y H:i'),
                'mime_type'      => $ev->mime_type,
                'url_descarga'   => route('secretario.evidencias.download', [$nomina->id, $ev->id]),
                'url_preview'    => route('secretario.evidencias.preview',  [$nomina->id, $ev->id]),
            ];
        }

        return Inertia::render('Secretario/ExpedienteDetalle', [
            'nomina' => [
                'id'                     => $nomina->id,
                'estado'                 => $nomina->estado,
                'con_licencia'           => $nomina->con_licencia,
                'observacion_licencia'   => $nomina->observacion_licencia,
                'plazo_licencia'         => $nomina->plazo_licencia?->format('Y-m-d'),
                'url_documento_licencia' => $nomina->documento_licencia
                    ? Storage::disk('public')->url($nomina->documento_licencia)
                    : null,
                'observacion_secretario' => $nomina->observacion_secretario,
                'academico'              => [
                    'name'  => $nomina->academico->name,
                    'rut'   => $nomina->academico->rut,
                    'email' => $nomina->academico->email,
                ],
            ],
            'categorias'                       => $categorias->map(fn ($c) => [
                'id'     => $c->id,
                'nombre' => $c->nombre,
                'slug'   => $c->slug,
            ]),
            'evidenciasPorCategoria'           => $evidenciasPorCategoria,
            'evidenciasApelacionPorCategoria'  => $evidenciasApelacionPorCategoria,
            'totalEvidencias'                  => $evidencias->count(),
            'apelacion'                        => $this->formatApelacion($nomina),
            'destinoApelacionCierre'           => $nomina->estado === 'evaluado' || $nomina->estado === 'apelado'
                ? [
                    'destino' => $nomina->destinoApelacionTrasCierre(),
                    'label'   => \App\Services\CalificacionCadService::labelDestinoApelacion(
                        $nomina->destinoApelacionTrasCierre()
                    ),
                ]
                : null,
            'calificacionFinal'                => null,
        ]);
    }

    public function showCategoria(Nomina $nomina, CategoriaApa $categoria): Response
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        $mapEv = fn ($ev) => [
            'id'             => $ev->id,
            'nombre_archivo' => $ev->nombre_archivo,
            'tamano'         => $ev->tamanoFormateado(),
            'descripcion'    => $ev->descripcion,
            'mime_type'      => $ev->mime_type,
            'created_at'     => $ev->created_at->format('d/m/Y H:i'),
            'url_descarga'   => route('secretario.evidencias.download', [$nomina->id, $ev->id]),
            'url_preview'    => route('secretario.evidencias.preview',  [$nomina->id, $ev->id]),
        ];

        return Inertia::render('Secretario/ExpedienteCategoria', [
            'nomina'             => [
                'id'     => $nomina->id,
                'nombre' => $nomina->academico->name,
            ],
            'categoria'          => [
                'id'          => $categoria->id,
                'nombre'      => $categoria->nombre,
                'descripcion' => $categoria->descripcion,
            ],
            'evidenciasNormales'  => $nomina->evidenciasNormales()
                ->where('categoria_id', $categoria->id)
                ->get()->map($mapEv)->values(),
            'evidenciasApelacion' => $nomina->evidenciasApelacion()
                ->where('categoria_id', $categoria->id)
                ->get()->map($mapEv)->values(),
        ]);
    }

    private function verificarCierreProceso(string $periodoId, string $facultadId, ?PlazoFacultad $plazo): array
    {
        if (!$plazo || !$plazo->estaCerradoFormalmente()) {
            return [false, 'Primero debes cerrar formalmente la recepción de evidencias.'];
        }

        $nominas = Nomina::with('apelacion')
            ->where('periodo_id', $periodoId)
            ->whereHas('academico', fn ($q) => $q->where('facultad_id', $facultadId))
            ->get();

        $evaluables = $nominas->filter(
            fn ($n) => $n->participaEvaluacionFormal() && !$n->esSoloDaConocer()
        );

        $conApelacionPendiente = $evaluables->filter(
            fn ($n) => $n->apelacion && in_array($n->apelacion->estado, ['solicitada', 'en_revision'])
        )->count();

        if ($conApelacionPendiente > 0) {
            return [false, "Hay {$conApelacionPendiente} apelación(es) pendiente(s) de resolver."];
        }

        $reevalCcaPendiente = $evaluables->filter(
            fn ($n) => $n->requiereReevaluacionApelacionCca()
        )->count();

        if ($reevalCcaPendiente > 0) {
            return [false, "Hay {$reevalCcaPendiente} apelación(es) pendiente(s) de re-evaluación por la CCA."];
        }

        $sinEvaluar = $evaluables->filter(
            fn ($n) => !in_array($n->estado, ['evaluado', 'cerrado'])
        )->count();

        if ($sinEvaluar > 0) {
            return [false, "Hay {$sinEvaluar} expediente(s) sin calificación final."];
        }

        return [true, null];
    }

    /** @return list<array{label: string, ok: bool}> */
    private function requisitosCierreProceso(string $periodoId, string $facultadId, ?PlazoFacultad $plazo): array
    {
        $nominas = Nomina::with('apelacion')
            ->where('periodo_id', $periodoId)
            ->whereHas('academico', fn ($q) => $q->where('facultad_id', $facultadId))
            ->get();

        $evaluables = $nominas->filter(
            fn ($n) => $n->participaEvaluacionFormal() && !$n->esSoloDaConocer()
        );

        $recepcionCerrada = $plazo?->estaCerradoFormalmente() ?? false;

        $apelPendientes = $evaluables->filter(
            fn ($n) => $n->apelacion && in_array($n->apelacion->estado, ['solicitada', 'en_revision'])
        )->count();

        $reevalCca = $evaluables->filter(fn ($n) => $n->requiereReevaluacionApelacionCca())->count();

        $sinEvaluar = $evaluables->filter(
            fn ($n) => !in_array($n->estado, ['evaluado', 'cerrado'])
        )->count();

        return [
            [
                'label' => 'Recepción de evidencias cerrada formalmente',
                'ok'    => $recepcionCerrada,
            ],
            [
                'label' => 'Todos los expedientes evaluables con calificación final',
                'ok'    => $evaluables->isNotEmpty() && $sinEvaluar === 0,
            ],
            [
                'label' => 'Sin apelaciones pendientes de resolver',
                'ok'    => $apelPendientes === 0,
            ],
            [
                'label' => 'Sin apelaciones pendientes de re-evaluación CCA',
                'ok'    => $reevalCca === 0,
            ],
        ];
    }

    public function cerrarProceso()
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        if (!$periodo || !$user->facultad_id) {
            return back()->with('error', 'No hay un período activo configurado.');
        }

        $actaExistente = Acta::where('periodo_id', $periodo->id)
            ->where('facultad_id', $user->facultad_id)
            ->where('tipo', 'cierre_proceso')
            ->exists();

        if ($actaExistente) {
            return back()->with('error', 'El proceso de esta facultad ya fue cerrado formalmente.');
        }

        $plazo = PlazoFacultad::where('periodo_id', $periodo->id)
            ->where('facultad_id', $user->facultad_id)
            ->first();

        [$puede, $motivo] = $this->verificarCierreProceso($periodo->id, $user->facultad_id, $plazo);

        if (!$puede) {
            return back()->with('error', $motivo);
        }

        $acta = Acta::create([
            'periodo_id'  => $periodo->id,
            'facultad_id' => $user->facultad_id,
            'generada_por' => $user->id,
            'fecha'       => now()->toDateString(),
            'tipo'        => 'cierre_proceso',
        ]);

        Nomina::where('periodo_id', $periodo->id)
            ->whereHas('academico', fn ($q) => $q->where('facultad_id', $user->facultad_id))
            ->where('estado', 'evaluado')
            ->update(['estado' => 'cerrado']);

        $nominasIds = Nomina::where('periodo_id', $periodo->id)
            ->whereHas('academico', fn ($q) => $q->where('facultad_id', $user->facultad_id))
            ->pluck('user_id');

        $notificaciones = $nominasIds->map(fn ($uid) => [
            'id'         => Str::uuid(),
            'user_id'    => $uid,
            'tipo'       => 'cierre_proceso',
            'titulo'     => 'Proceso de calificación cerrado',
            'mensaje'    => 'El proceso de Calificación Académica Docente ha sido cerrado formalmente por la secretaría de su facultad.',
            'leida'      => false,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        Notificacion::insert($notificaciones);

        return back()->with('success', 'Proceso cerrado formalmente. Se generó el acta de cierre.');
    }

    public function imprimirActaCierre(Acta $acta): View
    {
        $user = auth()->user();

        if ($acta->facultad_id !== $user->facultad_id || $acta->tipo !== 'cierre_proceso') {
            abort(403);
        }

        $periodo  = $acta->periodo;
        $facultad = $acta->facultad;

        $nominas = Nomina::with(['academico.departamento', 'calificacionFinal'])
            ->where('periodo_id', $acta->periodo_id)
            ->whereHas('academico', fn ($q) => $q->where('facultad_id', $acta->facultad_id))
            ->orderBy('created_at')
            ->get()
            ->map(function (Nomina $n) {
                if ($n->esSoloDaConocer()) {
                    $nota = $n->notaAnterior();

                    return [
                        'nombre'       => $n->academico->name,
                        'rut'          => $n->academico->rut,
                        'departamento' => $n->academico->departamento?->nombre,
                        'estado'       => $n->estado,
                        'calificacion' => $nota !== null
                            ? CalificacionCadService::conceptoDesdeNota($nota)
                            : null,
                        'puntaje'      => $nota !== null ? (int) round($nota * 20) : null,
                        'observacion'  => 'Se da a conocer',
                        'es_apelacion' => false,
                        'da_conocer'   => true,
                    ];
                }

                return [
                    'nombre'       => $n->academico->name,
                    'rut'          => $n->academico->rut,
                    'departamento' => $n->academico->departamento?->nombre,
                    'estado'       => $n->estado,
                    'calificacion' => $n->calificacionFinal?->calificacion,
                    'puntaje'      => $n->calificacionFinal?->puntaje_total,
                    'observacion'  => $n->calificacionFinal?->observacion,
                    'es_apelacion' => $n->calificacionFinal?->es_apelacion ?? false,
                    'da_conocer'   => false,
                ];
            });

        $secretario = $acta->generadaPor;

        $decano = User::where('facultad_id', $acta->facultad_id)
            ->whereNull('departamento_id')
            ->whereHas('userRoles', fn ($q) => $q->where('role', 'jefe_academico'))
            ->first();

        $miembrosCca = User::integrantesComisionPeriodo($acta->periodo_id, $acta->facultad_id);

        return view('secretario.acta_cierre', compact(
            'acta', 'periodo', 'facultad', 'nominas',
            'secretario', 'decano', 'miembrosCca'
        ));
    }

    private function formatApelacion(Nomina $nomina): ?array
    {
        $ap = $nomina->apelacion;
        if (!$ap) {
            return null;
        }
        return [
            'id'               => $ap->id,
            'estado'           => $ap->estado,
            'destino'          => $ap->destino,
            'motivo'           => $ap->motivo,
            'resolucion'       => $ap->resolucion,
            'fecha_solicitud'  => $ap->fecha_solicitud->format('d/m/Y'),
            'fecha_resolucion' => $ap->fecha_resolucion?->format('d/m/Y'),
        ];
    }

    public function reabrirExpediente(Nomina $nomina)
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        if ($nomina->estado !== 'carga_cerrada') {
            return back()->with('error', 'Solo se pueden reabrir expedientes marcados como completos.');
        }

        $nomina->update(['estado' => 'en_carga']);

        return back()->with('success', 'Expediente reabierto. El académico puede cargar nuevamente.');
    }

    public function validarExpediente(Request $request, Nomina $nomina)
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        if ($nomina->esSoloDaConocer()) {
            return back()->with('error', $nomina->labelExclusionEvaluacion() ?? 'Este académico no participa del proceso evaluativo.');
        }

        if (!$nomina->participaEvaluacionFormal()) {
            return back()->with('error', 'Este académico solo registra declaración APA este período; no requiere validación de expediente.');
        }

        if (!in_array($nomina->estado, ['pendiente', 'en_carga'])) {
            return back()->with('error', 'El expediente no está en un estado que permita validación.');
        }

        $data = $request->validate([
            'accion'      => ['required', 'in:completo,observaciones'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ], [
            'accion.required' => 'Debe seleccionar una acción.',
            'accion.in'       => 'Acción no válida.',
        ]);

        if ($data['accion'] === 'completo') {
            if (!$nomina->tieneCompromisoApaConfirmado()) {
                return back()->with('error', 'No se puede marcar como completo sin declaración APA de I y II Semestre confirmada.');
            }

            if (!$nomina->evidenciasNormales()->exists()) {
                return back()->with('error', 'No se puede marcar como completo sin evidencias cargadas.');
            }

            $nomina->update([
                'estado'                 => 'carga_cerrada',
                'observacion_secretario' => null,
            ]);
            return back()->with('success', 'Expediente marcado como completo. Ya está disponible para la CCA.');
        }

        $nomina->update(['observacion_secretario' => $data['observacion'] ?? null]);
        return back()->with('success', 'Observaciones registradas en el expediente.');
    }

    public function cerrarRecepcion()
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        if (!$periodo || !$user->facultad_id) {
            return back()->with('error', 'No hay un período activo configurado.');
        }

        $plazo = PlazoFacultad::firstOrCreate(
            ['periodo_id' => $periodo->id, 'facultad_id' => $user->facultad_id],
            ['creado_por' => $user->id, 'fecha_limite' => now()->toDateString()]
        );

        if ($plazo->estaCerradoFormalmente()) {
            return back()->with('error', 'La recepción ya fue cerrada formalmente.');
        }

        $plazo->update([
            'cerrado_en'  => now(),
            'cerrado_por' => $user->id,
        ]);

        return back()->with('success', 'Recepción de evidencias cerrada formalmente. Los académicos ya no pueden subir nuevas evidencias.');
    }

    public function setPlazolicencia(Request $request, Nomina $nomina)
    {
        $user = auth()->user();

        if ($nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        if (!$nomina->con_licencia) {
            return back()->with('error', 'Este expediente no tiene una licencia registrada.');
        }

        $data = $request->validate([
            'plazo_licencia' => ['required', 'date', 'after_or_equal:today'],
            'documento'      => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'plazo_licencia.required'       => 'La fecha límite es obligatoria.',
            'plazo_licencia.after_or_equal' => 'La fecha límite no puede ser anterior a hoy.',
            'documento.mimes'               => 'Solo se permiten archivos PDF e imágenes (JPG, PNG).',
            'documento.max'                 => 'El documento no puede superar los 5 MB.',
        ]);

        $updates = ['plazo_licencia' => $data['plazo_licencia']];

        if ($request->hasFile('documento')) {
            if ($nomina->documento_licencia) {
                Storage::disk('public')->delete($nomina->documento_licencia);
            }
            $updates['documento_licencia'] = $request->file('documento')
                ->store("licencias/{$nomina->id}", 'public');
        }

        $nomina->update($updates);

        $fechaFormateada = \Carbon\Carbon::parse($data['plazo_licencia'])->format('d/m/Y');
        Notificacion::create([
            'user_id' => $nomina->user_id,
            'tipo'    => 'plazo_licencia',
            'titulo'  => 'Plazo especial asignado',
            'mensaje' => "Se le ha asignado un plazo especial de entrega de evidencias hasta el {$fechaFormateada}. Ingrese a su sección de evidencias para cargar sus documentos.",
        ]);

        return back()->with('success', 'Plazo especial actualizado correctamente.');
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

    public function storePlazo(Request $request)
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        if (!$periodo || !$user->facultad_id) {
            return back()->with('error', 'No hay un período activo configurado.');
        }

        $data = $request->validate([
            'fecha_limite' => ['required', 'date'],
        ], [
            'fecha_limite.required' => 'La fecha límite es obligatoria.',
            'fecha_limite.date'     => 'La fecha límite debe ser una fecha válida.',
        ]);

        PlazoFacultad::updateOrCreate(
            [
                'periodo_id'  => $periodo->id,
                'facultad_id' => $user->facultad_id,
            ],
            [
                'fecha_limite' => $data['fecha_limite'],
                'creado_por'   => $user->id,
            ]
        );

        return back()->with('success', 'Plazo de entrega configurado correctamente.');
    }
}
