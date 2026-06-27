<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user'         => $request->user()?->load('facultad'),
                'active_role'  => $request->user()?->activeRole(),
                'user_roles'   => $request->user()
                    ? $request->user()->rolesParaSesion()
                    : [],
                'cargo_badges' => $request->user()
                    ? app(\App\Services\CargoPeriodoService::class)->badgesCargosSinVista($request->user())
                    : [],
            ],
            'flash' => [
                'success'       => fn () => $request->session()->get('success'),
                'error'         => fn () => $request->session()->get('error'),
                'excel_preview' => fn () => $request->session()->get('excel_preview'),
                'import_errores'=> fn () => $request->session()->get('import_errores'),
            ],
            'notificaciones_no_leidas' => fn () => $request->user()
                ? $request->user()->notificaciones()->noLeidas()->count()
                : 0,
        ]);
    }
}