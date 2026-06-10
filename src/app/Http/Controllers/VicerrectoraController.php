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
        $nomina->load(['academico.facultad', 'evidenciasNormales.categoria', 'evaluaciones.evaluador']);

        return Inertia::render('Vicerrectora/Expediente', [
            'nomina' => [
                'id'        => $nomina->id,
                'estado'    => $nomina->estado,
                'academico' => [
                    'name'     => $nomina->academico->name,
                    'rut'      => $nomina->academico->rut,
                    'facultad' => $nomina->academico->facultad?->nombre,
                    'categoria'=> CalificacionCadService::labelCategoria(
                        $nomina->categoria ?? $nomina->academico->categoria_academica
                    ),
                ],
                'evidencias_count' => $nomina->evidenciasNormales->count(),
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
