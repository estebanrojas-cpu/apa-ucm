<?php

namespace App\Http\Middleware;

use App\Http\Controllers\AuthController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $activeRole = $user->activeRole();

        if (!in_array($activeRole, $roles, true)) {
            $assigned = $user->rolesParaSesion();
            $tieneRolRequerido = count(array_intersect($roles, $assigned)) > 0;

            if ($tieneRolRequerido && count($assigned) > 1) {
                return redirect()
                    ->route('perfil.seleccionar')
                    ->with('error', 'Seleccione el perfil adecuado para acceder a esta sección.');
            }

            return redirect()
                ->to(AuthController::dashboardRouteFor($activeRole))
                ->with('error', 'No tiene permiso para acceder a esa sección.');
        }

        if ($activeRole === 'miembro_cca' && in_array('miembro_cca', $roles, true) && !$user->puedeActuarComoCca()) {
            $fallback = in_array('academico', $user->rolesParaSesion(), true) ? 'academico' : $activeRole;

            return redirect()
                ->to(AuthController::dashboardRouteFor($fallback))
                ->with('error', 'No está designado en la comisión evaluadora del período activo.');
        }

        if ($activeRole === 'academico' && $user->bloqueado_por_licencia && !$request->routeIs('logout')) {
            return redirect()
                ->route('academico.dashboard')
                ->with('error', 'Su acceso está suspendido por licencia médica activa. Contacte al secretario de su facultad.');
        }

        return $next($request);
    }
}
