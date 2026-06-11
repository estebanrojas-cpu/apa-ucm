<?php

namespace App\Http\Controllers;

use App\Models\ComentarioVicerrectora;
use App\Models\Evaluacion;
use App\Models\Nomina;
use App\Models\Periodo;
use App\Services\CalificacionCadService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VicerrectoraController extends Controller
{
    public function index(): Response
    {
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        $academicos = collect();

        if ($periodo) {
            $academicos = Nomina::with([
                'academico.facultad',
                'calificacionFinal',
                'evaluaciones.comentariosVicerrectora',
            ])
                ->where('periodo_id', $periodo->id)
                ->whereIn('estado', ['evaluado', 'cerrado', 'apelado'])
                ->orderBy('created_at')
                ->get()
                ->map(function (Nomina $n) {
                    $cf = $n->calificacionFinal;
                    $eval = $n->evaluaciones->first();
                    $comentario = $eval?->comentariosVicerrectora?->sortByDesc('created_at')->first();

                    return [
                        'nomina_id'       => $n->id,
                        'evaluacion_id'   => $eval?->id,
                        'name'            => $n->academico->name,
                        'rut'             => $n->academico->rut,
                        'facultad'        => $n->academico->facultad?->nombre,
                        'categoria'       => CalificacionCadService::labelCategoria(
                            $n->categoria ?? $n->academico->categoria_academica
                        ),
                        'nota_final'      => $cf ? (float) ($cf->nota_final ?? 0) : null,
                        'calificacion'    => $cf?->calificacion,
                        'concepto'        => $cf
                            ? CalificacionCadService::labelConcepto($cf->calificacion)
                            : null,
                        'vigente_hasta'   => $eval?->vigente_hasta?->format('d/m/Y'),
                        'comentario'      => $comentario?->comentario,
                        'comentario_fecha'=> $comentario?->created_at?->format('d/m/Y'),
                    ];
                });
        }

        return Inertia::render('Vicerrectora/Academicos', [
            'periodo'    => $periodo?->only(['id', 'anio', 'nombre']),
            'academicos' => $academicos->values(),
        ]);
    }

    public function show(Nomina $nomina): Response
    {
        $nomina->load([
            'academico.facultad',
            'evidenciasNormales.categoria',
            'evaluaciones.evaluador',
            'calificacionFinal',
            'apelacion',
            'compromisoApa',
        ]);

        $cf       = $nomina->calificacionFinal;
        $categoria = $nomina->categoriaEfectiva();

        return Inertia::render('Vicerrectora/Expediente', [
            'nomina' => [
                'id'     => $nomina->id,
                'estado' => $nomina->estado,
                'academico' => [
                    'name'     => $nomina->academico->name,
                    'rut'      => $nomina->academico->rut,
                    'email'    => $nomina->academico->email,
                    'facultad' => $nomina->academico->facultad?->nombre,
                    'categoria'=> CalificacionCadService::labelCategoria($categoria),
                ],
                'calificacion' => $cf ? [
                    'nota_final'  => number_format((float) $cf->nota_final, 2),
                    'concepto'    => $cf->calificacionLabel(),
                    'observacion' => $cf->observacion,
                    'fecha'       => $cf->fecha?->format('d/m/Y'),
                    'es_apelacion'=> $cf->es_apelacion,
                ] : null,
                'evaluaciones' => $nomina->evaluaciones
                    ->where('es_apelacion', false)
                    ->map(fn ($e) => [
                        'evaluador'  => $e->evaluador?->name,
                        'comentario' => $e->comentario,
                    ])->values(),
                'evidencias' => $nomina->evidenciasNormales->map(fn ($ev) => [
                    'nombre'    => $ev->nombre_archivo,
                    'categoria' => $ev->categoria?->nombre,
                    'fecha'     => $ev->created_at?->format('d/m/Y'),
                ])->values(),
                'apelacion' => $nomina->apelacion ? [
                    'estado'  => $nomina->apelacion->estado,
                    'motivo'  => $nomina->apelacion->motivo,
                    'destino' => $nomina->apelacion->destino ?? 'cca',
                ] : null,
            ],
        ]);
    }

    public function storeComentario(Request $request, Evaluacion $evaluacion)
    {
        $data = $request->validate([
            'comentario' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        ComentarioVicerrectora::create([
            'evaluacion_id' => $evaluacion->id,
            'comentario'    => $data['comentario'],
            'creado_por'    => auth()->id(),
            'created_at'    => now(),
        ]);

        return back()->with('success', 'Comentario registrado.');
    }
}
