<?php

namespace App\Http\Controllers;

use App\Models\Cronograma;
use App\Models\Nomina;
use App\Models\Periodo;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function admin(): Response
    {
        return Inertia::render('Dashboard/Admin');
    }

    public function analista(): Response
    {
        return Inertia::render('Dashboard/AnalistaCCDA', [
            'stats' => [
                'periodos_activos'     => Periodo::where('estado', 'activo')->count(),
                'nominas_cargadas'     => Nomina::count(),
                'cronogramas_vigentes' => Cronograma::vigentes()->count(),
            ],
        ]);
    }

    public function secretario(): Response
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        $stats = ['total' => 0, 'pendientes' => 0, 'con_licencia' => 0];

        if ($periodo && $user->facultad_id) {
            $nominas = Nomina::where('periodo_id', $periodo->id)
                ->whereHas('academico', fn ($q) => $q->where('facultad_id', $user->facultad_id))
                ->get(['estado', 'con_licencia']);

            $stats = [
                'total'        => $nominas->count(),
                'pendientes'   => $nominas->where('estado', 'pendiente')->count(),
                'con_licencia' => $nominas->where('con_licencia', true)->count(),
            ];
        }

        return Inertia::render('Dashboard/Secretario', [
            'stats'   => $stats,
            'periodo' => $periodo?->only(['nombre', 'anio']),
        ]);
    }

    public function cca(): Response
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        $stats = [
            'pendientes'      => 0,
            'en_evaluacion'   => 0,
            'evaluados'       => 0,
            'mis_sin_evaluar' => 0,
        ];

        if ($periodo && $user->facultad_id) {
            $nominas = Nomina::with('evaluaciones')
                ->where('periodo_id', $periodo->id)
                ->whereHas('academico', fn ($q) => $q->where('facultad_id', $user->facultad_id))
                ->whereIn('estado', ['carga_cerrada', 'en_evaluacion', 'evaluado'])
                ->get();

            $stats = [
                'pendientes'      => $nominas->where('estado', 'carga_cerrada')->count(),
                'en_evaluacion'   => $nominas->where('estado', 'en_evaluacion')->count(),
                'evaluados'       => $nominas->where('estado', 'evaluado')->count(),
                'mis_sin_evaluar' => $nominas
                    ->filter(fn (Nomina $n) => $n->estado !== 'evaluado'
                        && !$n->evaluaciones->contains('evaluador_id', $user->id))
                    ->count(),
            ];
        }

        return Inertia::render('Dashboard/MiembroCCA', [
            'stats'   => $stats,
            'periodo' => $periodo?->only(['nombre', 'anio']),
        ]);
    }

    public function jefe(): Response
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        $stats = ['pendientes' => 0, 'emitidas' => 0];

        if ($periodo && $user->facultad_id) {
            $nominas = Nomina::with('calificacionJefatura')
                ->where('periodo_id', $periodo->id)
                ->whereHas('academico', function ($q) use ($user) {
                    $q->where('facultad_id', $user->facultad_id);
                    if ($user->departamento_id) {
                        $q->where('departamento_id', $user->departamento_id);
                    }
                })
                ->whereIn('estado', ['evaluado', 'apelado', 'cerrado'])
                ->get();

            $stats = [
                'pendientes' => $nominas->filter(fn ($n) => $n->calificacionJefatura === null)->count(),
                'emitidas'   => $nominas->filter(fn ($n) => $n->calificacionJefatura !== null)->count(),
            ];
        }

        return Inertia::render('Dashboard/JefeAcademico', [
            'stats'   => $stats,
            'periodo' => $periodo?->only(['nombre', 'anio']),
        ]);
    }

    public function vicerrectora(): Response
    {
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        $evaluados = 0;
        if ($periodo) {
            $evaluados = Nomina::where('periodo_id', $periodo->id)
                ->whereIn('estado', ['evaluado', 'cerrado'])
                ->count();
        }

        return Inertia::render('Dashboard/Vicerrectora', [
            'stats'   => ['evaluados' => $evaluados],
            'periodo' => $periodo?->only(['nombre', 'anio']),
        ]);
    }

    public function academico(): Response
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        $stats = ['evidencias_cargadas' => 0, 'estado_nomina' => null];

        if ($periodo) {
            $nomina = Nomina::with(['evidenciasNormales', 'calificacionFinal', 'apelacion'])
                ->where('periodo_id', $periodo->id)
                ->where('user_id', $user->id)
                ->first();

            if ($nomina) {
                $cf = $nomina->calificacionFinal;
                $ap = $nomina->apelacion;
                $stats = [
                    'evidencias_cargadas' => $nomina->evidenciasNormales->count(),
                    'estado_nomina'       => $nomina->estado,
                    'calificacion'        => $cf ? [
                        'puntaje_total' => $cf->puntaje_total,
                        'calificacion'  => $cf->calificacion,
                        'label'         => $cf->calificacionLabel(),
                        'fecha'         => $cf->fecha->format('d/m/Y'),
                    ] : null,
                    'apelacion'           => $ap ? [
                        'estado'    => $ap->estado,
                        'motivo'    => $ap->motivo,
                        'resolucion'=> $ap->resolucion,
                    ] : null,
                ];
            }
        }

        return Inertia::render('Dashboard/Academico', [
            'stats'   => $stats,
            'periodo' => $periodo?->only(['nombre', 'anio']),
        ]);
    }
}