<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePeriodoRequest;
use App\Jobs\EnviarInicioProcesoJob;
use App\Models\Cronograma;
use App\Models\Notificacion;
use App\Models\Periodo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PeriodoController extends Controller
{
    public function index(): Response
    {
        $periodos = Periodo::with(['creadoPor', 'cronogramas'])
            ->withCount('nominas')
            ->orderByDesc('anio')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Periodo/Index', [
            'periodos' => $periodos,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Periodo/Create');
    }

    public function store(StorePeriodoRequest $request)
    {
        if (Periodo::where('estado', '!=', 'cerrado')->exists()) {
            return back()->withErrors([
                'periodo' => 'No se puede crear un nuevo período mientras exista uno sin cerrar.',
            ]);
        }

        $data = $request->validated();

        DB::transaction(function () use ($data, $request) {
            $periodo = Periodo::create([
                'anio'         => (int) date('Y', strtotime($data['fecha_inicio'])),
                'nombre'       => $data['nombre'],
                'fecha_inicio' => $data['fecha_inicio'],
                'fecha_cierre' => $data['fecha_cierre'],
                'estado'       => 'activo',
                'creado_por'   => $request->user()->id,
            ]);

            foreach ($data['cronograma'] as $entry) {
                Cronograma::create([
                    'periodo_id'   => $periodo->id,
                    'etapa'        => $entry['etapa'],
                    'fecha_inicio' => $entry['fecha_inicio'],
                    'fecha_fin'    => $entry['fecha_fin'],
                ]);
            }

            $this->notificarInicio($periodo);
        });

        return redirect()->route('analista.periodos.index')
            ->with('success', 'Período registrado. Notificaciones y correos en cola de envío.');
    }

    public function imprimirCronograma(Periodo $periodo)
    {
        $orden = array_flip(Cronograma::ETAPAS);

        $cronogramas = $periodo->cronogramas()
            ->get()
            ->sortBy(fn ($c) => $orden[$c->etapa] ?? 99)
            ->map(fn ($c) => [
                'etapa'        => Cronograma::etiqueta($c->etapa),
                'fecha_inicio' => $c->fecha_inicio->format('d/m/Y'),
                'fecha_fin'    => $c->fecha_fin->format('d/m/Y'),
                'vigente'      => $c->estaVigente(),
                'terminado'    => $c->haTerminado(),
            ]);

        return view('cronograma.imprimir', compact('periodo', 'cronogramas'));
    }

    private function notificarInicio(Periodo $periodo): void
    {
        $inicio = \Carbon\Carbon::parse($periodo->fecha_inicio)->format('d/m/Y');
        $cierre = \Carbon\Carbon::parse($periodo->fecha_cierre)->format('d/m/Y');
        $mensaje = "Se ha registrado el período \"{$periodo->nombre}\". Inicio: {$inicio}. Cierre: {$cierre}.";

        // In-app: solo secretarios (los académicos reciben notificación + correo en un solo registro)
        User::activos()
            ->deRol('secretario')
            ->each(fn (User $user) => Notificacion::create([
                'user_id' => $user->id,
                'tipo'    => 'inicio_proceso',
                'titulo'  => "Inicio del proceso CAD {$periodo->anio}",
                'mensaje' => $mensaje,
                'leida'   => false,
                'url'     => null,
            ]));

        // Email masivo (HU-021): un registro por académico con trazabilidad de envío
        User::activos()
            ->deRol('academico')
            ->each(function (User $academico) use ($periodo, $mensaje) {
                $notif = Notificacion::create([
                    'user_id'      => $academico->id,
                    'tipo'         => 'inicio_proceso',
                    'titulo'       => "Inicio del proceso CAD {$periodo->anio}",
                    'mensaje'      => $mensaje,
                    'leida'        => false,
                    'url'          => config('app.url'),
                    'estado_envio' => 'pendiente',
                ]);

                EnviarInicioProcesoJob::dispatch($periodo->id, $academico->id, $notif->id);
            });
    }
}
