<?php

namespace App\Http\Controllers;

use App\Models\Apelacion;
use App\Models\Nomina;
use App\Models\Notificacion;
use App\Models\Periodo;
use App\Services\CalificacionCadService;
use Illuminate\Http\Request;

class ApelacionController extends Controller
{
    public function store(Request $request)
    {
        $user    = auth()->user();
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        if (!$periodo) {
            return back()->with('error', 'No hay un período activo.');
        }

        $nomina = Nomina::where('periodo_id', $periodo->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$nomina || $nomina->estado !== 'evaluado') {
            return back()->with('error', 'Solo puede apelar un expediente con calificación final registrada.');
        }

        if (Apelacion::where('nomina_id', $nomina->id)
            ->whereIn('estado', ['solicitada', 'en_revision'])
            ->exists()) {
            return back()->with('error', 'Ya tiene una apelación activa para este período.');
        }

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:20', 'max:2000'],
        ], [
            'motivo.required' => 'Debe indicar el motivo de la apelación.',
            'motivo.min'      => 'El motivo debe tener al menos 20 caracteres.',
        ]);

        Apelacion::create([
            'nomina_id'       => $nomina->id,
            'motivo'          => $data['motivo'],
            'estado'          => 'solicitada',
            'fecha_solicitud' => now()->toDateString(),
        ]);

        $nomina->update(['estado' => 'apelado']);

        return back()->with('success', 'Apelación solicitada correctamente. El secretario revisará su solicitud.');
    }

    public function resolver(Request $request, Apelacion $apelacion)
    {
        $user = auth()->user();

        if ($apelacion->nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        if ($apelacion->estado !== 'solicitada') {
            return back()->with('error', 'Esta apelación ya fue procesada.');
        }

        $data = $request->validate([
            'accion'     => ['required', 'in:aprobar,rechazar'],
            'resolucion' => ['nullable', 'string', 'max:1000'],
        ], [
            'accion.required' => 'Debe seleccionar una acción.',
            'accion.in'       => 'Acción no válida.',
        ]);

        if ($data['accion'] === 'aprobar') {
            $apelacion->update([
                'estado'           => 'en_revision',
                'resolucion'       => $data['resolucion'] ?? null,
                'fecha_resolucion' => now()->toDateString(),
            ]);

            Notificacion::create([
                'user_id' => $apelacion->nomina->user_id,
                'tipo'    => 'apelacion_aprobada',
                'titulo'  => 'Apelación aprobada',
                'mensaje' => 'Su apelación fue aprobada. Puede cargar nuevas evidencias para re-evaluación.'
                           . ($data['resolucion'] ? " Observación: {$data['resolucion']}" : ''),
            ]);

            return back()->with('success', 'Apelación aprobada. El académico puede cargar nuevas evidencias.');
        }

        $apelacion->update([
            'estado'           => 'rechazada',
            'resolucion'       => $data['resolucion'] ?? null,
            'fecha_resolucion' => now()->toDateString(),
        ]);
        $apelacion->nomina->update(['estado' => 'evaluado']);

        Notificacion::create([
            'user_id' => $apelacion->nomina->user_id,
            'tipo'    => 'apelacion_rechazada',
            'titulo'  => 'Apelación rechazada',
            'mensaje' => 'Su apelación fue rechazada. La calificación original se mantiene.'
                       . ($data['resolucion'] ? " Resolución: {$data['resolucion']}" : ''),
        ]);

        return back()->with('success', 'Apelación rechazada. El expediente mantiene su calificación original.');
    }

    public function cerrar(Apelacion $apelacion)
    {
        $user = auth()->user();

        if ($apelacion->nomina->academico->facultad_id !== $user->facultad_id) {
            abort(403);
        }

        if ($apelacion->estado !== 'en_revision') {
            return back()->with('error', 'Solo se puede cerrar una apelación en revisión.');
        }

        $nomina  = $apelacion->nomina;
        $destino = $nomina->destinoApelacionTrasCierre();

        $apelacion->update([
            'estado'  => 'resuelta',
            'destino' => $destino,
        ]);
        $nomina->update(['estado' => 'en_evaluacion']);

        $destinoLabel = CalificacionCadService::labelDestinoApelacion($destino);

        return back()->with(
            'success',
            "Apelación enviada a {$destinoLabel} para re-evaluación."
        );
    }
}
