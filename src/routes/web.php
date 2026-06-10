<?php

use App\Http\Controllers\AnalistaCCDAController;
use App\Http\Controllers\ApelacionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompromisoApaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvaluacionController;
use App\Http\Controllers\EvidenciaController;
use App\Http\Controllers\JefaturaController;
use App\Http\Controllers\NominaController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PeriodoController;
use App\Http\Controllers\SecretarioController;
use App\Http\Controllers\SolicitudController;
use App\Http\Controllers\VicerrectoraController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect(AuthController::dashboardRouteFor(auth()->user()->role))
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones');

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'admin'])->name('admin.dashboard');
    });

    Route::middleware('role:analista_ccda')->prefix('analista')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'analista'])->name('analista.dashboard');
        Route::get('/periodos',       [PeriodoController::class, 'index'])->name('analista.periodos.index');
        Route::get('/periodos/crear', [PeriodoController::class, 'create'])->name('analista.periodos.create');
        Route::post('/periodos',      [PeriodoController::class, 'store'])->name('analista.periodos.store');

        Route::get('/periodos/{periodo}/nominas/crear',           [NominaController::class, 'create'])->name('analista.periodos.nominas.create');
        Route::post('/periodos/{periodo}/nominas',                [NominaController::class, 'store'])->name('analista.periodos.nominas.store');
        Route::post('/periodos/{periodo}/nominas/preview-excel',  [NominaController::class, 'previewExcel'])->name('analista.periodos.nominas.preview-excel');
        Route::post('/periodos/{periodo}/nominas/importar-excel', [NominaController::class, 'importarExcel'])->name('analista.periodos.nominas.importar-excel');
        Route::post('/periodos/{periodo}/nominas/agregar',        [NominaController::class, 'agregarIndividual'])->name('analista.periodos.nominas.agregar');
        Route::get('/periodos/{periodo}/nominas/exportar',        [NominaController::class, 'exportar'])->name('analista.periodos.nominas.exportar');
        Route::get('/periodos/{periodo}/nominas/{nomina}/detalle',[NominaController::class, 'detalle'])->name('analista.periodos.nominas.detalle');
        Route::get('/nominas/plantilla',                          [NominaController::class, 'plantilla'])->name('analista.nominas.plantilla');
        Route::get('/periodos/{periodo}/cronograma/pdf',          [PeriodoController::class, 'imprimirCronograma'])->name('analista.periodos.cronograma.pdf');

        Route::patch('/nominas/{nomina}/licencia', [NominaController::class, 'toggleLicencia'])->name('analista.nominas.licencia');

        Route::get('/estado-proceso',         [AnalistaCCDAController::class, 'estadoProceso'])->name('analista.estado-proceso');
        Route::get('/reporte-calificaciones', [AnalistaCCDAController::class, 'reporteCalificaciones'])->name('analista.reporte-calificaciones');
        Route::get('/incumplimientos',        [AnalistaCCDAController::class, 'incumplimientos'])->name('analista.incumplimientos');

        Route::get('/solicitudes',                       [SolicitudController::class, 'indexAnalista'])->name('analista.solicitudes');
        Route::get('/solicitudes/{solicitud}/documento', [SolicitudController::class, 'downloadDocumento'])->name('analista.solicitudes.documento');
    });

    Route::middleware('role:secretario')->prefix('secretario')->group(function () {
        Route::get('/dashboard',                                             [DashboardController::class,  'secretario'])->name('secretario.dashboard');
        Route::get('/expedientes',                                           [SecretarioController::class, 'expedientes'])->name('secretario.expedientes');
        Route::get('/expedientes/{nomina}',                                  [SecretarioController::class, 'showExpediente'])->name('secretario.expedientes.show');
        Route::patch('/expedientes/{nomina}/validar',                        [SecretarioController::class, 'validarExpediente'])->name('secretario.expedientes.validar');
        Route::patch('/expedientes/{nomina}/reabrir',                        [SecretarioController::class, 'reabrirExpediente'])->name('secretario.expedientes.reabrir');
        Route::post('/expedientes/{nomina}/licencia-plazo',                  [SecretarioController::class, 'setPlazolicencia'])->name('secretario.expedientes.licencia-plazo');
        Route::get('/expedientes/{nomina}/evidencias/{evidencia}/descargar', [SecretarioController::class, 'downloadEvidencia'])->name('secretario.evidencias.download');
        Route::patch('/apelaciones/{apelacion}/resolver',                    [ApelacionController::class,  'resolver'])->name('secretario.apelaciones.resolver');
        Route::patch('/apelaciones/{apelacion}/cerrar',                      [ApelacionController::class,  'cerrar'])->name('secretario.apelaciones.cerrar');
        Route::post('/plazos',                                               [SecretarioController::class, 'storePlazo'])->name('secretario.plazos.store');
        Route::post('/cierre',                                               [SecretarioController::class, 'cerrarRecepcion'])->name('secretario.cierre');
        Route::post('/cierre-proceso',                                       [SecretarioController::class, 'cerrarProceso'])->name('secretario.cierre-proceso');
        Route::get('/acta-cierre/{acta}',                                    [SecretarioController::class, 'imprimirActaCierre'])->name('secretario.acta-cierre');
        Route::get('/solicitudes',                                           [SolicitudController::class, 'indexSecretario'])->name('secretario.solicitudes');
        Route::post('/solicitudes',                                          [SolicitudController::class, 'storeSecretario'])->name('secretario.solicitudes.store');
        Route::patch('/solicitudes/{solicitud}/reincorporar',                [SolicitudController::class, 'reincorporar'])->name('secretario.solicitudes.reincorporar');
        Route::get('/solicitudes/{solicitud}/documento',                     [SolicitudController::class, 'downloadDocumento'])->name('secretario.solicitudes.documento');
    });

    Route::middleware('role:miembro_cca')->prefix('cca')->group(function () {
        Route::get('/dashboard',                                             [DashboardController::class,  'cca'])->name('cca.dashboard');
        Route::get('/expedientes',                                           [EvaluacionController::class, 'index'])->name('cca.expedientes');
        Route::get('/expedientes/{nomina}',                                  [EvaluacionController::class, 'show'])->name('cca.expedientes.show');
        Route::post('/expedientes/{nomina}/evaluar',                         [EvaluacionController::class, 'store'])->name('cca.expedientes.evaluar');
        Route::post('/expedientes/{nomina}/finalizar',                       [EvaluacionController::class, 'finalize'])->name('cca.expedientes.finalizar');
        Route::get('/expedientes/{nomina}/evidencias/{evidencia}/descargar', [EvaluacionController::class, 'downloadEvidencia'])->name('cca.evidencias.download');
        Route::get('/expedientes/{nomina}/calificacion-pdf',                 [EvaluacionController::class, 'imprimirCalificacion'])->name('cca.expedientes.calificacion-pdf');
    });

    Route::middleware('role:vicerrectora')->prefix('vicerrectora')->group(function () {
        Route::get('/dashboard',                             [DashboardController::class,    'vicerrectora'])->name('vicerrectora.dashboard');
        Route::get('/academicos',                            [VicerrectoraController::class, 'index'])->name('vicerrectora.academicos');
        Route::get('/academicos/{nomina}',                   [VicerrectoraController::class, 'show'])->name('vicerrectora.academicos.show');
        Route::post('/evaluaciones/{evaluacion}/comentario', [VicerrectoraController::class, 'storeComentario'])->name('vicerrectora.comentario.store');
    });

    Route::middleware('role:jefe_academico')->prefix('jefe')->group(function () {
        Route::get('/dashboard',                    [DashboardController::class, 'jefe'])->name('jefe.dashboard');
        Route::get('/academicos',                   [JefaturaController::class,  'index'])->name('jefe.academicos');
        Route::get('/academicos/{nomina}',          [JefaturaController::class,  'show'])->name('jefe.academicos.show');
        Route::post('/academicos/{nomina}/informe', [JefaturaController::class,  'store'])->name('jefe.academicos.store');
        Route::get('/academicos/{nomina}/imprimir', [JefaturaController::class,  'imprimir'])->name('jefe.academicos.imprimir');
    });

    Route::middleware(['role:academico', 'academico.no_licencia'])->prefix('academico')->group(function () {
        Route::get('/declaracion-apa',  [CompromisoApaController::class, 'showDeclaracion'])->name('academico.declaracion-apa');
        Route::post('/declaracion-apa', [CompromisoApaController::class, 'storeDeclaracion'])->name('academico.declaracion-apa.store');
    });

    Route::middleware(['role:academico', 'academico.no_licencia', 'compromiso.apa'])->prefix('academico')->group(function () {
        Route::get('/dashboard',                                   [DashboardController::class, 'academico'])->name('academico.dashboard');
        Route::get('/evidencias',                                  [EvidenciaController::class, 'index'])->name('academico.evidencias');
        Route::post('/evidencias',                                 [EvidenciaController::class, 'store'])->name('academico.evidencias.store');
        Route::get('/evidencias/{evidencia}/descargar',            [EvidenciaController::class, 'download'])->name('academico.evidencias.download');
        Route::delete('/evidencias/{evidencia}',                   [EvidenciaController::class, 'destroy'])->name('academico.evidencias.destroy');
        Route::post('/apelacion',                                  [ApelacionController::class, 'store'])->name('academico.apelacion.store');
        Route::post('/evidencias-apelacion',                       [EvidenciaController::class, 'storeApelacion'])->name('academico.evidencias.apelacion.store');
        Route::delete('/evidencias-apelacion/{evidencia}',         [EvidenciaController::class, 'destroyApelacion'])->name('academico.evidencias.apelacion.destroy');
    });

    Route::middleware('role:academico')->get('/academico/bloqueado', fn () => inertia('Academico/BloqueadoLicencia'))
        ->name('academico.bloqueado');
});
