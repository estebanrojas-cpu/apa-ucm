<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Credenciales incorrectas.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect(self::dashboardRouteFor(Auth::user()->role));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public static function dashboardRouteFor(?string $role): string
    {
        return match ($role) {
            'admin'          => route('admin.dashboard'),
            'analista_ccda'  => route('analista.dashboard'),
            'secretario'     => route('secretario.dashboard'),
            'miembro_cca'    => route('cca.dashboard'),
            'jefe_academico' => route('jefe.dashboard'),
            'vicerrectora'   => route('vicerrectora.dashboard'),
            'academico'      => route('academico.dashboard'),
            default          => route('login'),
        };
    }

    private function dashboardRoute(string $role): string
    {
        return self::dashboardRouteFor($role);
    }
}
