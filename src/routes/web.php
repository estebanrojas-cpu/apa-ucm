<?php

use App\Http\Controllers\AnalistaCCDAController;
use App\Http\Controllers\ApelacionController;
use App\Http\Controllers\AsignacionFacultadController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompromisoApaController;
use App\Http\Controllers\ComisionCcaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DecanoController;
use App\Http\Controllers\EvaluacionController;
use App\Http\Controllers\EvidenciaController;
use App\Http\Controllers\JefaturaController;
use App\Http\Controllers\NominaController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PeriodoController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\SecretarioController;
use App\Http\Controllers\SolicitudController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\VicerrectoraController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect(AuthController::dashboardRouteFor(auth()->user()->activeRole()))
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/seleccionar-perfil',  [PerfilController::class, 'seleccionar'])->name('perfil.seleccionar');
    Route::post('/cambiar-perfil',     [PerfilController::class, 'cambiar'])->name('perfil.cambiar');
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones');

    Route::middleware('role:super_admin')->prefix('super-admin')->group(function () {
        Route::get('/usuarios',        [SuperAdminController::class, 'index'])->name('super-admin.usuarios');
        Route::post('/usuarios',       [SuperAdminController::class, 'store'])->name('super-admin.usuarios.store');
        Route::patch('/usuarios/{user}/activo', [SuperAdminController::class, 'toggleActivo'])->name('super-admin.usuarios.activo');
    });

    Route::middleware('role:analista_ccda')->prefix('analista')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'analista'])->name('analista.dashboard');
        Route::get('/periodos',       [PeriodoController::class, 'index'])->name('analista.periodos.index');
        Route::get('/periodos/crear', [PeriodoController::class, 'create'])->name('analista.periodos.create');
        Route::post('/periodos',      [PeriodoController::class, 'store'])->name('analista.periodos.store');
        Route::get('/periodos/{periodo}/comisiones',                    [ComisionCcaController::class, 'index'])->name('analista.periodos.comisiones.index');
        Route::get('/periodos/{periodo}/comisiones/{facultad}',         [ComisionCcaController::class, 'edit'])->name('analista.periodos.comisiones.edit');
        Route::put('/periodos/{periodo}/comisiones/{facultad}',         [ComisionCcaController::class, 'update'])->name('analista.periodos.comisiones.update');
        Route::post('/periodos/{periodo}/comisiones/{facultad}/confirmar', [ComisionCcaController::class, 'confirmar'])->name('analista.periodos.comisiones.confirmar');

        Route::get('/periodos/{periodo}/cargos',                              [AsignacionFacultadController::class, 'index'])->name('analista.periodos.cargos.index');
        Route::get('/periodos/{periodo}/cargos/{facultad}',                   [AsignacionFacultadController::class, 'edit'])->name('analista.periodos.cargos.edit');
        Route::put('/periodos/{periodo}/cargos/{facultad}',                   [AsignacionFacultadController::class, 'update'])->name('analista.periodos.cargos.update');
        Route::post('/periodos/{periodo}/cargos/{facultad}/confirmar-comision', [AsignacionFacultadController::class, 'confirmarComision'])->name('analista.periodos.cargos.confirmar');
        Route::get('/periodos/{periodo}/cargos/{facultad}/buscar',            [AsignacionFacultadController::class, 'buscarCandidatos'])->name('analista.periodos.cargos.buscar');

        Route::get('/periodos/{periodo}/nominas/crear',           [NominaController::class, 'create'])->name('analista.periodos.nominas.create');
        Route::post('/periodos/{periodo}/nominas',                [NominaController::class, 'store'])->name('analista.periodos.nominas.store');
        Route::post('/periodos/{periodo}/nominas/preview-excel',  [NominaController::class, 'previewExcel'])->name('analista.periodos.nominas.preview-excel');
        Route::post('/periodos/{periodo}/nominas/importar-excel', [NominaController::class, 'importarExcel'])->name('analista.periodos.nominas.importar-excel');
        Route::post('/periodos/{periodo}/nominas/agregar',        [NominaController::class, 'agregarIndividual'])->name('analista.periodos.nominas.agregar');
        Route::post('/periodos/{periodo}/nominas/enviar-credenciales', [NominaController::class, 'enviarCredenciales'])->name('analista.periodos.nominas.enviar-credenciales');
        Route::post('/periodos/{periodo}/nominas/columna',        [NominaController::class, 'agregarColumna'])->name('analista.periodos.nominas.columna');
        Route::delete('/periodos/{periodo}/nominas/columna',      [NominaController::class, 'eliminarColumna'])->name('analista.periodos.nominas.columna.destroy');
        Route::patch('/periodos/{periodo}/nominas/{nomina}',      [NominaController::class, 'update'])->name('analista.periodos.nominas.update');
        Route::get('/periodos/{periodo}/nominas/exportar',        [NominaController::class, 'exportar'])->name('analista.periodos.nominas.exportar');
        Route::get('/periodos/{periodo}/nominas/{nomina}/detalle',[NominaController::class, 'detalle'])->name('analista.periodos.nominas.detalle');
        Route::get('/nominas/plantilla',                          [NominaController::class, 'plantilla'])->name('analista.nominas.plantilla');
        Route::get('/periodos/{periodo}/cronograma/pdf',          [PeriodoController::class, 'imprimirCronograma'])->name('analista.periodos.cronograma.pdf');

        Route::get('/estado-proceso',         [AnalistaCCDAController::class, 'estadoProceso'])->name('analista.estado-proceso');
        Route::get('/reporte-calificaciones', [AnalistaCCDAController::class, 'reporteCalificaciones'])->name('analista.reporte-calificaciones');
        Route::get('/incumplimientos',        [AnalistaCCDAController::class, 'incumplimientos'])->name('analista.incumplimientos');

        Route::get('/solicitudes',                       [SolicitudController::class, 'indexAnalista'])->name('analista.solicitudes');
        Route::get('/solicitudes/{solicitud}/documento', [SolicitudController::class, 'downloadDocumento'])->name('analista.solicitudes.documento');

        Route::get('/registro-ccda',                     [AnalistaCCDAController::class, 'registroCcda'])->name('analista.registro-ccda');
        Route::post('/registro-ccda/{facultad}',         [AnalistaCCDAController::class, 'storeVerificacion'])->name('analista.registro-ccda.store');

        Route::get('/apelaciones',                                                  [AnalistaCCDAController::class, 'apelaciones'])->name('analista.apelaciones');
        Route::get('/apelaciones/{nomina}',                                         [AnalistaCCDAController::class, 'showApelacion'])->name('analista.apelaciones.show');
        Route::post('/apelaciones/{nomina}/evaluar',                                [AnalistaCCDAController::class, 'storeApelacion'])->name('analista.apelaciones.evaluar');
        Route::post('/apelaciones/{nomina}/finalizar',                              [AnalistaCCDAController::class, 'finalizeApelacion'])->name('analista.apelaciones.finalizar');
        Route::get('/apelaciones/{nomina}/evidencias/{evidencia}/descargar',        [AnalistaCCDAController::class, 'downloadEvidenciaApelacion'])->name('analista.apelaciones.evidencia.download');
        Route::get('/apelaciones/{nomina}/evidencias/{evidencia}/previsualizar',   [AnalistaCCDAController::class, 'previewEvidenciaApelacion'])->name('analista.apelaciones.evidencia.preview');
    });

    Route::middleware('role:secretario')->prefix('secretario')->group(function () {
        Route::get('/dashboard',                                             [DashboardController::class,  'secretario'])->name('secretario.dashboard');
        Route::get('/expedientes',                                           [SecretarioController::class, 'expedientes'])->name('secretario.expedientes');
        Route::get('/expedientes/{nomina}',                                  [SecretarioController::class, 'showExpediente'])->name('secretario.expedientes.show');
        Route::patch('/expedientes/{nomina}/validar',                        [SecretarioController::class, 'validarExpediente'])->name('secretario.expedientes.validar');
        Route::patch('/expedientes/{nomina}/reabrir',                        [SecretarioController::class, 'reabrirExpediente'])->name('secretario.expedientes.reabrir');
        Route::post('/expedientes/{nomina}/licencia-plazo',                  [SecretarioController::class, 'setPlazolicencia'])->name('secretario.expedientes.licencia-plazo');
        Route::get('/expedientes/{nomina}/categoria/{categoria}',               [SecretarioController::class, 'showCategoria'])->name('secretario.expedientes.categoria');
        Route::get('/expedientes/{nomina}/evidencias/{evidencia}/descargar',      [SecretarioController::class, 'downloadEvidencia'])->name('secretario.evidencias.download');
        Route::get('/expedientes/{nomina}/evidencias/{evidencia}/previsualizar', [SecretarioController::class, 'previewEvidencia'])->name('secretario.evidencias.preview');
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
        Route::get('/expedientes/{nomina}/categoria/{categoria}',            [EvaluacionController::class, 'showCategoria'])->name('cca.expedientes.categoria');
        Route::post('/expedientes/{nomina}/evaluar',                         [EvaluacionController::class, 'store'])->name('cca.expedientes.evaluar');
        Route::post('/expedientes/{nomina}/finalizar',                       [EvaluacionController::class, 'finalize'])->name('cca.expedientes.finalizar');
        Route::get('/expedientes/{nomina}/evidencias/{evidencia}/descargar',      [EvaluacionController::class, 'downloadEvidencia'])->name('cca.evidencias.download');
        Route::get('/expedientes/{nomina}/evidencias/{evidencia}/previsualizar', [EvaluacionController::class, 'previewEvidencia'])->name('cca.evidencias.preview');
        Route::get('/expedientes/{nomina}/calificacion-pdf',                 [EvaluacionController::class, 'imprimirCalificacion'])->name('cca.expedientes.calificacion-pdf');
    });

    Route::middleware('role:vicerrectora')->prefix('vicerrectora')->group(function () {
        Route::get('/dashboard',                             [DashboardController::class,    'vicerrectora'])->name('vicerrectora.dashboard');
        Route::get('/academicos',                            [VicerrectoraController::class, 'index'])->name('vicerrectora.academicos');
        Route::get('/academicos/{nomina}',                   [VicerrectoraController::class, 'show'])->name('vicerrectora.academicos.show');
        Route::post('/evaluaciones/{evaluacion}/comentario', [VicerrectoraController::class, 'storeComentario'])->name('vicerrectora.comentario.store');
    });

    Route::middleware('role:director_departamento,jefe_academico')->prefix('jefe')->group(function () {
        Route::get('/dashboard',                    [DashboardController::class, 'jefe'])->name('jefe.dashboard');
        Route::get('/academicos',                   [JefaturaController::class,  'index'])->name('jefe.academicos');
        Route::get('/academicos/{nomina}',          [JefaturaController::class,  'show'])->name('jefe.academicos.show');
        Route::post('/academicos/{nomina}/informe', [JefaturaController::class,  'store'])->name('jefe.academicos.store');
        Route::get('/academicos/{nomina}/imprimir', [JefaturaController::class,  'imprimir'])->name('jefe.academicos.imprimir');
    });

    Route::middleware('role:decano')->prefix('decano')->group(function () {
        Route::get('/directivos',                   [DecanoController::class, 'index'])->name('decano.directivos');
        Route::get('/directivos/{nomina}',          [DecanoController::class, 'show'])->name('decano.directivos.show');
        Route::post('/directivos/{nomina}/informe', [DecanoController::class, 'store'])->name('decano.directivos.store');
    });

    Route::middleware(['role:academico', 'academico.no_licencia'])->prefix('academico')->group(function () {
        Route::get('/declaracion-apa/{semestre?}',  [CompromisoApaController::class, 'showDeclaracion'])->name('academico.declaracion-apa');
        Route::post('/declaracion-apa', [CompromisoApaController::class, 'storeDeclaracion'])->name('academico.declaracion-apa.store');
    });

    Route::middleware(['role:academico', 'academico.no_licencia', 'declaracion.apa'])->prefix('academico')->group(function () {
        Route::get('/dashboard',                                   [DashboardController::class, 'academico'])->name('academico.dashboard');
        Route::get('/evidencias',                                  [EvidenciaController::class, 'index'])->name('academico.evidencias');
        Route::post('/evidencias',                                 [EvidenciaController::class, 'store'])->name('academico.evidencias.store');
        Route::get('/evidencias/categoria/{categoria}',            [EvidenciaController::class, 'showCategoria'])->name('academico.evidencias.categoria');
        Route::get('/evidencias/{evidencia}/descargar',            [EvidenciaController::class, 'download'])->name('academico.evidencias.download');
        Route::get('/evidencias/{evidencia}/previsualizar',        [EvidenciaController::class, 'preview'])->name('academico.evidencias.preview');
        Route::delete('/evidencias/{evidencia}',                   [EvidenciaController::class, 'destroy'])->name('academico.evidencias.destroy');
        Route::post('/evidencias-apelacion',                       [EvidenciaController::class, 'storeApelacion'])->name('academico.evidencias.apelacion.store');
        Route::delete('/evidencias-apelacion/{evidencia}',         [EvidenciaController::class, 'destroyApelacion'])->name('academico.evidencias.apelacion.destroy');
    });

    Route::middleware('role:academico')->get('/academico/bloqueado', fn () => inertia('Academico/BloqueadoLicencia'))
        ->name('academico.bloqueado');
});
