<?php

namespace Database\Seeders;

use App\Models\Cronograma;
use App\Models\Nomina;
use App\Models\Periodo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Adelanta el cronograma para habilitar la evaluación CCA en demo local.
 *
 * Ejecutar DESPUÉS de probar declaración APA + carga de evidencias:
 *
 *   php artisan db:seed --class=DemoEvaluacionCcaSeeder
 *
 * Efectos (idempotente):
 *  - Cierra la etapa institucional de carga de evidencias (fecha_fin = ayer).
 *  - Abre la etapa de evaluación CCA desde ayer.
 *  - Marca como carga_cerrada los expedientes listos (compromiso + evidencias).
 */
class DemoEvaluacionCcaSeeder extends Seeder
{
    public function run(): void
    {
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        if (!$periodo) {
            $this->command?->warn('DemoEvaluacionCcaSeeder: no hay período activo.');
            return;
        }

        $ayer  = Carbon::yesterday()->toDateString();
        $inicio = Carbon::parse($periodo->fecha_inicio)->toDateString();

        Cronograma::where('periodo_id', $periodo->id)
            ->where('etapa', 'carga_evidencias')
            ->update([
                'fecha_inicio' => $inicio,
                'fecha_fin'    => $ayer,
            ]);

        $finEvaluacion = Cronograma::where('periodo_id', $periodo->id)
            ->where('etapa', 'evaluacion_cca')
            ->value('fecha_fin');

        Cronograma::where('periodo_id', $periodo->id)
            ->where('etapa', 'evaluacion_cca')
            ->update([
                'fecha_inicio' => $ayer,
                'fecha_fin'    => $finEvaluacion ?? Carbon::today()->addDays(30)->toDateString(),
            ]);

        $cerrados = 0;

        Nomina::with(['academico', 'compromisoApa', 'evidenciasNormales'])
            ->where('periodo_id', $periodo->id)
            ->whereIn('estado', ['pendiente', 'en_carga'])
            ->get()
            ->each(function (Nomina $nomina) use (&$cerrados) {
                if (!$nomina->compromisoApa?->estaConfirmado()) {
                    return;
                }

                if ($nomina->evidenciasNormales->isEmpty()) {
                    return;
                }

                $nomina->update(['estado' => 'carga_cerrada']);
                $cerrados++;
            });

        // academico@ siempre listo en demo base (evidencias dummy del DemoSeeder)
        $academico = User::where('email', 'academico@ucm.cl')->first();
        if ($academico) {
            $actualizado = Nomina::where('periodo_id', $periodo->id)
                ->where('user_id', $academico->id)
                ->whereIn('estado', ['pendiente', 'en_carga'])
                ->update(['estado' => 'carga_cerrada']);

            if ($actualizado) {
                $cerrados++;
            }
        }

        $this->command?->info("DemoEvaluacionCcaSeeder: carga cerrada ({$ayer}). {$cerrados} expediente(s) en carga_cerrada.");
        $this->command?->info('Ingrese con cca@ucm.cl para evaluar.');
    }
}
