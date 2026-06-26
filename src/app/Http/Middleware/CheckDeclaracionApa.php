<?php

namespace App\Http\Middleware;

use App\Models\CompromisoApa;
use App\Models\Nomina;
use App\Models\Periodo;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class CheckDeclaracionApa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->activeRole() !== 'academico') {
            return $next($request);
        }

        // Excluir rutas de declaración y logout
        if ($request->routeIs('academico.declaracion-apa', 'academico.declaracion-apa.store', 'logout', 'academico.bloqueado')) {
            return $next($request);
        }

        $periodo = Periodo::where('estado', 'activo')->with('semestres')->latest()->first();

        if (!$periodo) {
            return $next($request);
        }

        $nomina = Nomina::with('academico')
            ->where('periodo_id', $periodo->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$nomina) {
            return $next($request);
        }

        if ($nomina->esSoloDaConocer()) {
            return $next($request);
        }

        // Solo verificar si la carga de evidencias está habilitada
        if (!$nomina->cargaEvidenciasHabilitada()) {
            return $next($request);
        }

        if (!$periodo->tieneSemestresApaConfigurados()) {
            return $next($request);
        }

        $semestres = $periodo->semestres;

        // Verificar si el académico declaró S1
        $declaroS1 = CompromisoApa::where('nomina_id', $nomina->id)
            ->where('semestre', 'S1')
            ->whereNotNull('confirmado_en')
            ->exists();

        // Si no declaró S1 → redirigir a declaración S1
        if (!$declaroS1) {
            if ($request->header('X-Inertia')) {
                return Inertia::location(route('academico.declaracion-apa', ['semestre' => 'S1']));
            }
            return redirect()->route('academico.declaracion-apa', ['semestre' => 'S1']);
        }

        // Verificar si S1 ya cerró
        $cierreS1 = $semestres->firstWhere('numero', 1)?->fecha_cierre;
        
        if ($cierreS1 && today()->isAfter($cierreS1)) {
            // Verificar si declaró S2
            $declaroS2 = CompromisoApa::where('nomina_id', $nomina->id)
                ->where('semestre', 'S2')
                ->whereNotNull('confirmado_en')
                ->exists();

            if (!$declaroS2) {
                if ($request->header('X-Inertia')) {
                    return Inertia::location(route('academico.declaracion-apa', ['semestre' => 'S2']));
                }
                return redirect()->route('academico.declaracion-apa', ['semestre' => 'S2']);
            }
        }

        return $next($request);
    }
}
