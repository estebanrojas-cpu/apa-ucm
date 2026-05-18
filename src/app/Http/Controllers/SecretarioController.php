<?php

namespace App\Http\Controllers;

use App\Models\Nomina;
use App\Models\Periodo;
use App\Models\PlazoFacultad;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
                ];
            }
        }

        return Inertia::render('Secretario/Expedientes', [
            'periodo'     => $periodo?->only(['id', 'anio', 'nombre', 'fecha_cierre']),
            'expedientes' => $expedientes->values(),
            'plazo'       => $plazo,
        ]);
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
