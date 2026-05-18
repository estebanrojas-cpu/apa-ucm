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
        return Inertia::render('Dashboard/MiembroCCA');
    }

    public function jefe(): Response
    {
        return Inertia::render('Dashboard/JefeAcademico');
    }

    public function academico(): Response
    {
        return Inertia::render('Dashboard/Academico');
    }
}