<?php

namespace App\Http\Controllers;

use App\Models\Acta;
use App\Models\CalificacionFinal;
use App\Models\Evaluacion;
use App\Models\Facultad;
use App\Models\Nomina;
use App\Models\Periodo;
use App\Models\PlazoFacultad;
use App\Models\ReporteHistorial;
use App\Services\CalificacionCadService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class AnalistaCCDAController extends Controller
{
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
}
