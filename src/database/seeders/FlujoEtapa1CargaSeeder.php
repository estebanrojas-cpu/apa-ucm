<?php

namespace Database\Seeders;

use App\Models\Cronograma;
use App\Models\Nomina;
use App\Models\Periodo;
use App\Models\PlazoFacultad;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Etapa 1 — Carga de evidencias + Validación secretario.
 *
 * Reinicia (o configura) el cronograma para que el período actual esté
 * en la ventana de carga activa HOY. Úsalo al inicio de cada prueba del flujo.
 *
 *   php artisan db:seed --class=FlujoEtapa1CargaSeeder
 *
 * Qué hace:
 *  - carga_evidencias         → inicio D-7,  fin D+20  (abierta)
 *  - validacion_secretario    → inicio D-7,  fin D+20  (paralela, abierta)
 *  - informe_jefatura         → inicio D-7,  fin D+20  (paralela, abierta)
 *  - evaluacion_cca           → inicio D+21, fin D+40  (futura)
 *  - comunicacion_resultados  → inicio D+41, fin D+50  (futura)
 *  - apelaciones              → inicio D+51, fin D+65  (futura)
 *  - registro_ccda            → inicio D+66, fin D+75  (futura)
 *  - revision_vicerrectoria   → inicio D+76, fin D+90  (futura)
 *  - plazo_facultad           → fecha_limite D+20, cerrado_en = NULL
 *  - nominas                  → estado = pendiente (limpia estados avanzados)
 */
class FlujoEtapa1CargaSeeder extends Seeder
{
    public function run(): void
    {
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        if (!$periodo) {
            $this->command->warn('FlujoEtapa1CargaSeeder: no hay período activo. Ejecute db:seed primero.');
            return;
        }

        $hoy = Carbon::today();

        $ventanas = [
            'carga_evidencias'        => [$hoy->copy()->subDays(7),  $hoy->copy()->addDays(20)],
            'validacion_secretario'   => [$hoy->copy()->subDays(7),  $hoy->copy()->addDays(20)],
            'informe_jefatura'        => [$hoy->copy()->subDays(7),  $hoy->copy()->addDays(20)],
            'evaluacion_cca'          => [$hoy->copy()->addDays(21), $hoy->copy()->addDays(40)],
            'comunicacion_resultados' => [$hoy->copy()->addDays(41), $hoy->copy()->addDays(50)],
            'apelaciones'             => [$hoy->copy()->addDays(51), $hoy->copy()->addDays(65)],
            'registro_ccda'           => [$hoy->copy()->addDays(66), $hoy->copy()->addDays(75)],
            'revision_vicerrectoria'  => [$hoy->copy()->addDays(76), $hoy->copy()->addDays(90)],
        ];

        foreach ($ventanas as $etapa => [$inicio, $fin]) {
            Cronograma::where('periodo_id', $periodo->id)
                ->where('etapa', $etapa)
                ->update([
                    'fecha_inicio' => $inicio->toDateString(),
                    'fecha_fin'    => $fin->toDateString(),
                ]);
        }

        // Reabrir plazo de todas las facultades del período
        PlazoFacultad::where('periodo_id', $periodo->id)->update([
            'fecha_limite' => $hoy->copy()->addDays(20)->toDateString(),
            'cerrado_en'   => null,
            'cerrado_por'  => null,
        ]);

        // Volver nominas a estado inicial (solo las que ya avanzaron)
        Nomina::where('periodo_id', $periodo->id)
            ->whereNotIn('estado', ['pendiente', 'en_carga'])
            ->evaluables()
            ->update(['estado' => 'pendiente']);

        $this->command->info("✓ Etapa 1 activa — carga_evidencias abierta hasta {$hoy->copy()->addDays(20)->format('d/m/Y')}.");
        $this->command->info('  Rol académico:    sube evidencias y confirma compromiso APA.');
        $this->command->info('  Rol secretario:   configura/revisa plazo, valida expedientes.');
        $this->command->newLine();
        $this->command->info('  Siguiente paso → FlujoEtapa2CcaSeeder  (cuando quieras pasar a evaluación CCA).');
    }
}
