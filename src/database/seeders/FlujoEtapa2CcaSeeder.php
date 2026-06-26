<?php

namespace Database\Seeders;

use App\Models\Cronograma;
use App\Models\Nomina;
use App\Models\Periodo;
use App\Models\PlazoFacultad;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Etapa 2 — Evaluación CCA.
 *
 * Cierra la carga de evidencias y habilita la evaluación por la CCA.
 * Ejecutar DESPUÉS de que el académico subió evidencias y el secretario
 * validó o está listo para validar.
 *
 *   php artisan db:seed --class=FlujoEtapa2CcaSeeder
 *
 * Qué hace:
 *  - carga_evidencias         → fin = ayer  (cerrada)
 *  - validacion_secretario   → fin = ayer  (cerrada → habilita evaluación CCA)
 *  - informe_jefatura        → fin = ayer  (cerrada)
 *  - evaluacion_cca          → inicio = ayer, fin = D+20  (ABIERTA)
 *  - comunicacion_resultados → inicio = D+21, fin = D+30
 *  - apelaciones             → inicio = D+31, fin = D+45
 *  - registro_ccda           → inicio = D+46, fin = D+55
 *  - revision_vicerrectoria  → inicio = D+56, fin = D+65
 *  - plazo_facultad          → cerrado_en = ahora (cierre formal de recepción)
 *  - nominas pendiente/en_carga con evidencias + compromiso → carga_cerrada
 */
class FlujoEtapa2CcaSeeder extends Seeder
{
    public function run(): void
    {
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        if (!$periodo) {
            $this->command->warn('FlujoEtapa2CcaSeeder: no hay período activo.');
            return;
        }

        $hoy  = Carbon::today();
        $ayer = $hoy->copy()->subDay();

        $ventanas = [
            'carga_evidencias'        => [$hoy->copy()->subDays(27), $ayer],
            'validacion_secretario'   => [$hoy->copy()->subDays(27), $ayer],
            'informe_jefatura'        => [$hoy->copy()->subDays(27), $ayer],
            'evaluacion_cca'          => [$ayer,                     $hoy->copy()->addDays(20)],
            'comunicacion_resultados' => [$hoy->copy()->addDays(21), $hoy->copy()->addDays(30)],
            'apelaciones'             => [$hoy->copy()->addDays(31), $hoy->copy()->addDays(45)],
            'registro_ccda'           => [$hoy->copy()->addDays(46), $hoy->copy()->addDays(55)],
            'revision_vicerrectoria'  => [$hoy->copy()->addDays(56), $hoy->copy()->addDays(65)],
        ];

        foreach ($ventanas as $etapa => [$inicio, $fin]) {
            Cronograma::where('periodo_id', $periodo->id)
                ->where('etapa', $etapa)
                ->update([
                    'fecha_inicio' => $inicio->toDateString(),
                    'fecha_fin'    => $fin->toDateString(),
                ]);
        }

        // Cierre formal del plazo de todas las facultades (si aún no lo tienen)
        $secretario = User::findByAssignedRole('secretario');
        PlazoFacultad::where('periodo_id', $periodo->id)
            ->whereNull('cerrado_en')
            ->update([
                'cerrado_en'  => now(),
                'cerrado_por' => $secretario?->id,
            ]);

        // Marcar como carga_cerrada las nominas listas (con compromiso APA confirmado y evidencias)
        $cerradas = 0;

        Nomina::with(['compromisos', 'evidenciasNormales'])
            ->where('periodo_id', $periodo->id)
            ->whereIn('estado', ['pendiente', 'en_carga'])
            ->get()
            ->each(function (Nomina $nomina) use (&$cerradas) {
                if (!$nomina->participaEvaluacionFormal()) {
                    return;
                }

                $tieneCompromiso = $nomina->tieneCompromisoApaConfirmado();
                $tieneEvidencias = $nomina->evidenciasNormales->isNotEmpty();

                if ($tieneCompromiso && $tieneEvidencias) {
                    $nomina->update(['estado' => 'carga_cerrada']);
                    $cerradas++;
                }
            });

        // Nominas sin user_id (importadas desde Excel) también pasan a carga_cerrada
        $sinUser = Nomina::where('periodo_id', $periodo->id)
            ->whereNull('user_id')
            ->whereIn('estado', ['pendiente', 'en_carga'])
            ->update(['estado' => 'carga_cerrada']);

        $cerradas += $sinUser;

        $this->command->info("✓ Etapa 2 activa — evaluacion_cca abierta hasta {$hoy->copy()->addDays(20)->format('d/m/Y')}.");
        $this->command->info("  {$cerradas} expediente(s) marcados como carga_cerrada (listos para CCA).");
        $this->command->newLine();

        $pendientes = Nomina::where('periodo_id', $periodo->id)
            ->whereIn('estado', ['pendiente', 'en_carga'])
            ->count();

        if ($pendientes > 0) {
            $this->command->warn("  {$pendientes} nomina(s) aún en pendiente/en_carga (sin evidencias o sin compromiso APA).");
        }

        $this->command->info('  Rol CCA: ingresa a Expedientes → evalúa cada académico.');
        $this->command->newLine();
        $this->command->info('  Siguiente paso → FlujoEtapa3CierreSeeder  (cuando quieras habilitar apelaciones y cierre).');
    }
}
