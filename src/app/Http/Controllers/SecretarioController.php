<?php

namespace App\Http\Controllers;

use App\Models\CategoriaApa;
use App\Models\Evidencia;
use App\Models\Nomina;
use App\Models\Periodo;
use App\Models\PlazoFacultad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        if ($periodo && $user->facultad_id) {
            $expedientes = Nomina::with('academico')
                ->where('periodo_id', $periodo->id)
                ->whereHas('academico', fn ($q) => $q->where('facultad_id', $user->facultad_id))
                ->orderBy('created_at')
                ->get()
                ->map(fn ($n) => [
                    'id'                   => $n->id,
                    'estado'               => $n->estado,
                    'con_licencia'         => $n->con_licencia,
                    'observacion_licencia' => $n->observacion_licencia,
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
        }

        return Inertia::render('Secretario/Expedientes', [
            'periodo'     => $periodo?->only(['id', 'anio', 'nombre', 'fecha_cierre']),
            'expedientes' => $expedientes->values(),
            'plazo'       => $plazo,
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
            'calificacionFinal'      => $nomina->calificacionFinal ? [
                'puntaje_total' => $nomina->calificacionFinal->puntaje_total,
                'calificacion'  => $nomina->calificacionFinal->calificacion,
            ] : null,
        ]);
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
