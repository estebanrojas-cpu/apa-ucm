<?php

namespace App\Http\Controllers;

use App\Models\CompromisoApa;
use App\Models\Nomina;
use App\Models\Notificacion;
use App\Models\Periodo;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SolicitudController extends Controller
{
    // ── Secretario ────────────────────────────────────────────────────────

    public function indexSecretario(): Response
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        $solicitudes = collect();
        $nominas     = collect();

        if ($periodo && $user->facultad_id) {
            $nominas = Nomina::with('academico')
                ->where('periodo_id', $periodo->id)
                ->whereHas('academico', fn ($q) => $q->where('facultad_id', $user->facultad_id))
                ->orderBy('created_at')
                ->get()
                ->map(fn (Nomina $n) => [
                    'id'    => $n->id,
                    'label' => "{$n->academico->name} ({$n->academico->rut})",
                ]);

            $solicitudes = Solicitud::with(['nomina.academico', 'reincorporadoPor'])
                ->whereHas('nomina', fn ($q) => $q
                    ->where('periodo_id', $periodo->id)
                    ->whereHas('academico', fn ($q2) => $q2->where('facultad_id', $user->facultad_id)))
                ->where('iniciada_por', $user->id)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Solicitud $s) => $this->serializar($s, 'secretario'));
        }

        return Inertia::render('Secretario/Solicitudes', [
            'periodo'     => $periodo?->only(['id', 'anio', 'nombre']),
            'solicitudes' => $solicitudes->values(),
            'nominas'     => $nominas->values(),
        ]);
    }

    public function storeSecretario(Request $request)
    {
        $user = $request->user();
        $data = $this->validarSolicitud($request);

        $nomina = Nomina::with('academico')->findOrFail($data['nomina_id']);
        $this->autorizarNominaFacultad($nomina, $user);
        $this->validarSinConflicto($nomina, $data['tipo']);

        $documentoPath = $this->guardarDocumento($request, $nomina);

        Solicitud::create([
            'nomina_id'         => $nomina->id,
            'tipo'              => $data['tipo'],
            'fecha_inicio'      => $data['fecha_inicio'],
            'fecha_fin'         => $data['fecha_fin'] ?? null,
            'motivo'            => $data['motivo'],
            'documento_adjunto' => $documentoPath,
            'estado'            => 'activa',
            'creado_por'        => $user->id,
            'iniciada_por'      => $user->id,
        ]);

        $this->aplicarEfectosActivacion(
            $nomina,
            $data['tipo'],
            $data['motivo'],
            $data['fecha_fin'] ?? null
        );

        $this->notificarVisibilidadCCDA(
            'solicitud_registrada',
            'Nueva solicitud registrada',
            "El secretario {$user->name} registró una solicitud de {$this->labelTipo($data['tipo'])} "
            . "para {$nomina->academico->name}. La solicitud quedó activa.",
            route('analista.solicitudes')
        );

        if ($data['tipo'] === 'licencia_medica') {
            Notificacion::create([
                'user_id' => $nomina->academico->id,
                'tipo'    => 'licencia_medica',
                'titulo'  => 'Licencia médica registrada',
                'mensaje' => 'Se registró una licencia médica a su nombre. Su acceso al sistema queda suspendido hasta reincorporación.',
                'leida'   => false,
                'url'     => null,
            ]);
        }

        return back()->with('success', 'Solicitud registrada y activa.');
    }

    public function reincorporar(Request $request, Solicitud $solicitud)
    {
        if ($solicitud->estado !== 'activa') {
            return back()->with('error', 'Solo se pueden reincorporar solicitudes activas.');
        }

        $user = $request->user();
        $solicitud->load('nomina.academico.facultad', 'iniciadaPor');

        if ($user->role === 'secretario') {
            $this->autorizarNominaFacultad($solicitud->nomina, $user);
            if ($solicitud->iniciada_por !== $user->id) {
                abort(403, 'Solo puede reincorporar solicitudes que usted inició.');
            }
        } elseif ($user->role !== 'analista_ccda') {
            abort(403);
        }

        $data = $request->validate([
            'nuevo_plazo_evidencias'  => ['required', 'date', 'after_or_equal:today'],
            'motivo_reincorporacion'  => ['nullable', 'string', 'max:2000'],
        ], [
            'nuevo_plazo_evidencias.required'       => 'Debe definir la nueva fecha límite de carga de evidencias.',
            'nuevo_plazo_evidencias.after_or_equal' => 'La fecha límite no puede ser anterior a hoy.',
        ]);

        $ahora           = now();
        $nomina          = $solicitud->nomina;
        $academico       = $nomina->academico;
        $fechaPlazo      = $data['nuevo_plazo_evidencias'];
        $fechaFormateada = \Carbon\Carbon::parse($fechaPlazo)->format('d/m/Y');

        $solicitud->update([
            'estado'                 => 'cerrada',
            'fecha_fin'              => $fechaPlazo,
            'fecha_reincorporacion'  => $ahora,
            'reincorporado_por'      => $user->id,
            'motivo_reincorporacion' => $data['motivo_reincorporacion'] ?? null,
            'nuevo_plazo_evidencias' => $fechaPlazo,
        ]);

        $nomina->update([
            'con_licencia'         => false,
            'observacion_licencia' => null,
            'plazo_licencia'       => $fechaPlazo,
        ]);

        CompromisoApa::where('nomina_id', $nomina->id)->delete();

        Notificacion::create([
            'user_id' => $academico->id,
            'tipo'    => 'reincorporacion',
            'titulo'  => 'Reincorporación al proceso CAD',
            'mensaje' => "Su acceso al sistema ha sido reactivado. Plazo para cargar evidencias: {$fechaFormateada}.",
            'leida'   => false,
            'url'     => route('academico.evidencias'),
        ]);

        return back()->with('success', "Académico reincorporado. Nuevo plazo de evidencias: {$fechaFormateada}.");
    }

    // ── Analista CCDA (solo lectura) ──────────────────────────────────────

    public function indexAnalista(): Response
    {
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        $solicitudes = collect();

        if ($periodo) {
            $solicitudes = Solicitud::with([
                'nomina.academico.facultad', 'iniciadaPor', 'reincorporadoPor',
            ])
                ->whereHas('nomina', fn ($q) => $q->where('periodo_id', $periodo->id))
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Solicitud $s) => $this->serializar($s, 'analista'));
        }

        return Inertia::render('AnalistaCCDA/Solicitudes', [
            'periodo'     => $periodo?->only(['id', 'anio', 'nombre']),
            'solicitudes' => $solicitudes->values(),
        ]);
    }

    public function downloadDocumento(Solicitud $solicitud): HttpResponse
    {
        $this->autorizarDocumento($solicitud);

        if (!$solicitud->documento_adjunto || !Storage::disk('public')->exists($solicitud->documento_adjunto)) {
            abort(404);
        }

        return Storage::disk('public')->download(
            $solicitud->documento_adjunto,
            basename($solicitud->documento_adjunto)
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function validarSolicitud(Request $request): array
    {
        return $request->validate([
            'nomina_id'    => ['required', 'uuid', 'exists:nominas,id'],
            'tipo'         => ['required', 'in:licencia_medica,extension_plazo'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'motivo'       => ['required', 'string', 'min:10', 'max:2000'],
            'documento'    => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
    }

    private function autorizarNominaFacultad(Nomina $nomina, User $secretario): void
    {
        if ($nomina->academico->facultad_id !== $secretario->facultad_id) {
            abort(403, 'No puede registrar solicitudes para académicos de otra facultad.');
        }
    }

    private function validarSinConflicto(Nomina $nomina, string $tipo): void
    {
        if ($tipo !== 'licencia_medica') {
            return;
        }

        $conflicto = $nomina->solicitudes()
            ->where('tipo', 'licencia_medica')
            ->where('estado', 'activa')
            ->exists();

        if ($conflicto || $nomina->tieneLicenciaMedicaActiva()) {
            throw ValidationException::withMessages([
                'nomina_id' => 'Este académico ya tiene una licencia médica activa.',
            ]);
        }
    }

    private function guardarDocumento(Request $request, Nomina $nomina): ?string
    {
        if (!$request->hasFile('documento')) {
            return null;
        }

        return $request->file('documento')->store("solicitudes/{$nomina->id}", 'public');
    }

    private function aplicarEfectosActivacion(Nomina $nomina, string $tipo, string $motivo, ?string $fechaFin): void
    {
        if ($tipo === 'licencia_medica') {
            $nomina->update([
                'con_licencia'         => true,
                'observacion_licencia' => $motivo,
            ]);
        } elseif ($fechaFin) {
            $nomina->update(['plazo_licencia' => $fechaFin]);
        }
    }

    private function notificarVisibilidadCCDA(string $tipo, string $titulo, string $mensaje, ?string $url): void
    {
        User::activos()
            ->deRol('analista_ccda')
            ->each(fn (User $u) => Notificacion::create([
                'user_id' => $u->id,
                'tipo'    => $tipo,
                'titulo'  => $titulo,
                'mensaje' => $mensaje,
                'leida'   => false,
                'url'     => $url,
            ]));
    }

    private function labelTipo(string $tipo): string
    {
        return match ($tipo) {
            'licencia_medica' => 'licencia médica',
            'extension_plazo' => 'extensión de plazo',
            default           => $tipo,
        };
    }

    private function autorizarDocumento(Solicitud $solicitud): void
    {
        $user = auth()->user();
        $solicitud->loadMissing('nomina.academico');

        if ($user->role === 'analista_ccda') {
            return;
        }

        if ($user->role === 'secretario') {
            $esSuFacultad  = $solicitud->nomina->academico->facultad_id === $user->facultad_id;
            $esSuSolicitud = $solicitud->iniciada_por === $user->id;

            if ($esSuFacultad && $esSuSolicitud) {
                return;
            }
        }

        abort(403);
    }

    private function serializar(Solicitud $s, string $contexto): array
    {
        $docRoute = $contexto === 'secretario'
            ? route('secretario.solicitudes.documento', $s)
            : route('analista.solicitudes.documento', $s);

        return [
            'id'                     => $s->id,
            'tipo'                   => $s->tipo,
            'tipo_label'             => $s->labelTipo(),
            'estado'                 => $s->estado,
            'estado_label'           => $s->labelEstado(),
            'fecha_inicio'           => $s->fecha_inicio->format('d/m/Y'),
            'fecha_fin'              => $s->fecha_fin?->format('d/m/Y'),
            'motivo'                 => $s->motivo,
            'documento_adjunto'      => $s->documento_adjunto,
            'documento_url'          => $s->documento_adjunto ? $docRoute : null,
            'academico'              => [
                'name'     => $s->nomina->academico->name,
                'rut'      => $s->nomina->academico->rut,
                'facultad' => $s->nomina->academico->facultad?->nombre,
            ],
            'nomina_id'              => $s->nomina_id,
            'iniciada_por'           => $s->iniciadaPor?->name,
            'fecha_reincorporacion'  => $s->fecha_reincorporacion?->format('d/m/Y H:i'),
            'reincorporado_por'      => $s->reincorporadoPor?->name,
            'motivo_reincorporacion' => $s->motivo_reincorporacion,
            'nuevo_plazo_evidencias' => $s->nuevo_plazo_evidencias?->format('d/m/Y'),
            'created_at'             => $s->created_at->format('d/m/Y H:i'),
        ];
    }
}
