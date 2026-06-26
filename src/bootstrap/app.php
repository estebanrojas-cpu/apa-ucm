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
            'declaracion.apa'      => \App\Http\Middleware\CheckDeclaracionApa::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(
            fn (Request $request) => AuthController::dashboardRouteFor($request->user()?->activeRole())
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception, Request $request) {
            if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
                return redirect()->guest(route('login'));
            }

            return $response;
        });
    })->create();