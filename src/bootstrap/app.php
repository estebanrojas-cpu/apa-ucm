<?php

use App\Http\Controllers\AuthController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'role'                 => \App\Http\Middleware\RoleMiddleware::class,
            'academico.no_licencia'=> \App\Http\Middleware\BlockAcademicoLicencia::class,
            'compromiso.apa'       => \App\Http\Middleware\EnsureCompromisoApa::class,
        ]);
        $middleware->redirectUsersTo(
            fn (Request $request) => AuthController::dashboardRouteFor($request->user()?->role)
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();