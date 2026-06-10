<?php

namespace App\Http\Middleware;

use App\Models\CompromisoApa;
use App\Models\Nomina;
use App\Models\Periodo;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompromisoApa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== 'academico') {
            return $next($request);
        }

        if ($request->routeIs('academico.declaracion-apa', 'academico.declaracion-apa.store', 'logout', 'academico.bloqueado')) {
            return $next($request);
        }

        $periodo = Periodo::where('estado', 'activo')->latest()->first();

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

        if (!$nomina->cargaEvidenciasHabilitada()) {
            return $next($request);
        }

        $tieneCompromiso = CompromisoApa::where('nomina_id', $nomina->id)
            ->where('periodo_id', $periodo->id)
            ->whereNotNull('confirmado_en')
            ->exists();

        if (!$tieneCompromiso) {
            if ($request->header('X-Inertia')) {
                return Inertia::location(route('academico.declaracion-apa'));
            }

            return redirect()->route('academico.declaracion-apa');
        }

        return $next($request);
    }
}
