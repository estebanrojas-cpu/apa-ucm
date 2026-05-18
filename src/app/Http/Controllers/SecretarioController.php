<?php

namespace App\Http\Controllers;

use App\Models\Nomina;
use App\Models\Periodo;
use Inertia\Inertia;
use Inertia\Response;

class SecretarioController extends Controller
{
    public function expedientes(): Response
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        $expedientes = collect();

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
        }

        return Inertia::render('Secretario/Expedientes', [
            'periodo'     => $periodo?->only(['id', 'anio', 'nombre', 'fecha_cierre']),
            'expedientes' => $expedientes->values(),
        ]);
    }
}
