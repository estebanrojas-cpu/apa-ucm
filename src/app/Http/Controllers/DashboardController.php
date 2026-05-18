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
        return Inertia::render('Dashboard/Secretario');
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