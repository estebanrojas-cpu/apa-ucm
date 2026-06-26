<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class BlockAcademicoLicencia
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->activeRole() === 'academico' && $user->tieneLicenciaMedicaActiva()) {
            if ($request->routeIs('logout') || $request->routeIs('academico.bloqueado')) {
                return $next($request);
            }

            if ($request->header('X-Inertia')) {
                return Inertia::location(route('academico.bloqueado'));
            }

            return redirect()->route('academico.bloqueado');
        }

        return $next($request);
    }
}
