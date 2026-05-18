<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NominaController;
use App\Http\Controllers\PeriodoController;
use App\Http\Controllers\SecretarioController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', fn () => redirect()->route('login'));
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'admin'])->name('admin.dashboard');
    });

    Route::middleware('role:analista_ccda')->prefix('analista')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'analista'])->name('analista.dashboard');
        Route::get('/periodos',       [PeriodoController::class, 'index'])->name('analista.periodos.index');
        Route::get('/periodos/crear', [PeriodoController::class, 'create'])->name('analista.periodos.create');
        Route::post('/periodos',      [PeriodoController::class, 'store'])->name('analista.periodos.store');

        Route::get('/periodos/{periodo}/nominas/crear', [NominaController::class, 'create'])->name('analista.periodos.nominas.create');
        Route::post('/periodos/{periodo}/nominas',      [NominaController::class, 'store'])->name('analista.periodos.nominas.store');

        Route::patch('/nominas/{nomina}/licencia', [NominaController::class, 'toggleLicencia'])->name('analista.nominas.licencia');
    });

    Route::middleware('role:secretario')->prefix('secretario')->group(function () {
        Route::get('/dashboard',    [DashboardController::class,  'secretario'])->name('secretario.dashboard');
        Route::get('/expedientes',  [SecretarioController::class, 'expedientes'])->name('secretario.expedientes');
    });

    Route::middleware('role:miembro_cca')->prefix('cca')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'cca'])->name('cca.dashboard');
    });

    Route::middleware('role:jefe_academico')->prefix('jefe')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'jefe'])->name('jefe.dashboard');
    });

    Route::middleware('role:academico')->prefix('academico')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'academico'])->name('academico.dashboard');
    });
});