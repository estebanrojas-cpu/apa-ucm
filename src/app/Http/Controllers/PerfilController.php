<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PerfilController extends Controller
{
    public function seleccionar(): Response|\Illuminate\Http\RedirectResponse
    {
        $user  = auth()->user();
        $roles = $user->rolesParaSesion();

        if (count($roles) <= 1) {
            session(['active_role' => $roles[0] ?? $user->role]);
            return redirect(AuthController::dashboardRouteFor($user->activeRole()));
        }

        return Inertia::render('Auth/SeleccionarPerfil', [
            'roles'       => $roles,
            'active_role' => $user->activeRole(),
        ]);
    }

    public function cambiar(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user  = auth()->user();
        $roles = $user->rolesParaSesion();

        $request->validate([
            'role' => ['required', 'string', Rule::in($roles)],
        ]);

        session(['active_role' => $request->role]);

        return redirect(AuthController::dashboardRouteFor($request->role));
    }
}
