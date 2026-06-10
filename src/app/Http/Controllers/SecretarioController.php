<?php

namespace App\Http\Controllers;

use App\Models\Acta;
use App\Models\CategoriaApa;
use App\Models\Evidencia;
use App\Models\Nomina;
use App\Models\Notificacion;
use App\Models\Periodo;
use App\Models\PlazoFacultad;
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
                    'estado_especial'      => $n->tieneLicenciaMedicaActiva()
                        ? 'Caso especial - Licencia médica'
                        : ($n->con_licencia ? 'Caso especial' : null),
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
                'url_descarga'   => route('secretario.evidencias.download', [$nomina->id, $ev->id]),
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
            'categorias'             => $categorias->map(fn ($c) => [
                'id'     => $c->id,
                'nombre' => $c->nombre,
                'slug'   => $c->slug,
            ]),
            'evidenciasPorCategoria' => $evidenciasPorCategoria,
            'totalEvidencias'        => $evidencias->count(),
            'apelacion'              => $this->formatApelacion($nomina),
            'calificacionFinal'      => null,
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

        $conApelacionPendiente = $nominas->filter(
            fn ($n) => $n->apelacion && in_array($n->apelacion->estado, ['solicitada', 'en_revision'])
        )->count();

        if ($conApelacionPendiente > 0) {
            return [false, "Hay {$conApelacionPendiente} apelación(es) pendiente(s) de resolver."];
        }

        $sinEvaluar = $nominas->filter(
            fn ($n) => !in_array($n->estado, ['evaluado', 'cerrado'])
        )->count();

        if ($sinEvaluar > 0) {
            return [false, "Hay {$sinEvaluar} expediente(s) sin calificación final."];
        }

        return [true, null];
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
            ->map(fn ($n) => [
                'nombre'       => $n->academico->name,
                'rut'          => $n->academico->rut,
                'departamento' => $n->academico->departamento?->nombre,
                'estado'       => $n->estado,
            ]);

        return view('secretario.acta_cierre', compact('acta', 'periodo', 'facultad', 'nominas'));
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

        Nomina::where('periodo_id', $periodo->id)
            ->whereHas('academico', fn ($q) => $q->where('facultad_id', $user->facultad_id))
            ->whereIn('estado', ['pendiente', 'en_carga'])
            ->update(['estado' => 'carga_cerrada']);

        return back()->with('success', 'Recepción de evidencias cerrada formalmente. Todos los expedientes activos han sido cerrados.');
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
