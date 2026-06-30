<?php

namespace Database\Seeders;

use App\Models\CalificacionFinal;
use App\Models\ComisionIntegrante;
use App\Models\Cronograma;
use App\Models\Evaluacion;
use App\Models\Nomina;
use App\Models\Periodo;
use App\Models\User;
use App\Services\CalificacionCadService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Etapa 3 — Apelaciones, Revisión Vicerrectoría y Cierre.
 *
 * Cierra la evaluación CCA y abre todas las etapas finales del proceso.
 * Ejecutar DESPUÉS de que la CCA haya evaluado los expedientes (o para
 * saltarse la evaluación CCA y probar directamente el cierre).
 *
 *   php artisan db:seed --class=FlujoEtapa3CierreSeeder
 *
 * Qué hace:
 *  - evaluacion_cca          → fecha_fin = ayer  (cerrada)
 *  - comunicacion_resultados → fin = ayer       (cerrada)
 *  - apelaciones             → inicio = hoy, fin = D+14  (abierta)
 *  - registro_ccda           → inicio = D+15, fin = D+25
 *  - revision_vicerrectoria  → inicio = D+26, fin = D+35
 *
 * Nominas aún en carga_cerrada / en_evaluacion (CCA no las terminó):
 *  - Si tienen evaluaciones parciales → genera CalificacionFinal desde ellas.
 *  - Si no tienen ninguna evaluación  → crea una evaluación automática (nota 3.5 = Bueno).
 *  - Las marca como 'evaluado' en ambos casos.
 *
 * Nota: apelaciones y vicerrectora no requieren ventana de fechas abiertas
 * para funcionar (se basan en nomina.estado = 'evaluado'). Las fechas que
 * se actualizan aquí son solo para coherencia visual del cronograma.
 */
class FlujoEtapa3CierreSeeder extends Seeder
{
    public function run(): void
    {
        $periodo = Periodo::where('estado', 'activo')->latest()->first();

        if (!$periodo) {
            $this->command->warn('FlujoEtapa3CierreSeeder: no hay período activo.');
            return;
        }

        $hoy  = Carbon::today();
        $ayer = $hoy->copy()->subDay();

        // ── Avanzar cronograma ────────────────────────────────────────────────

        $ventanas = [
            'evaluacion_cca'          => [null,  $ayer],
            'comunicacion_resultados' => [$ayer->copy()->subDays(14), $ayer],
            'apelaciones'             => [$hoy, $hoy->copy()->addDays(14)],
            'registro_ccda'           => [$hoy->copy()->addDays(15), $hoy->copy()->addDays(25)],
            'revision_vicerrectoria'  => [$hoy->copy()->addDays(26), $hoy->copy()->addDays(35)],
        ];

        foreach ($ventanas as $etapa => [$inicio, $fin]) {
            $data = [];
            if ($inicio !== null) {
                $data['fecha_inicio'] = $inicio->toDateString();
            }
            if ($fin !== null) {
                $data['fecha_fin'] = $fin->toDateString();
            }
            Cronograma::where('periodo_id', $periodo->id)
                ->where('etapa', $etapa)
                ->update($data);
        }

        // ── Auto-completar nominas que la CCA dejó sin calificación final ────

        $cca = ComisionIntegrante::whereHas('comision', fn ($q) => $q
            ->where('periodo_id', $periodo->id)
            ->where('estado', 'confirmada'))
            ->whereHas('nomina', fn ($q) => $q->whereNotNull('user_id'))
            ->with('nomina.academico')
            ->first()
            ?->nomina
            ?->academico;

        $sinFinalizar = Nomina::with(['evaluaciones.nomina', 'compromisos', 'calificacionFinal'])
            ->where('periodo_id', $periodo->id)
            ->whereIn('estado', ['carga_cerrada', 'en_evaluacion'])
            ->whereDoesntHave('calificacionFinal', fn ($q) => $q->where('es_apelacion', false))
            ->get();

        $autoevaluadas = 0;

        foreach ($sinFinalizar as $nomina) {
            $evaluaciones = $nomina->evaluaciones->where('es_apelacion', false);

            if ($evaluaciones->isEmpty() && $cca) {
                // Sin evaluaciones → crear una automática con puntaje neutro (3.5/5)
                Evaluacion::firstOrCreate(
                    [
                        'nomina_id'    => $nomina->id,
                        'evaluador_id' => $cca->id,
                        'es_apelacion' => false,
                    ],
                    [
                        'puntaje_docencia'      => 3.5,
                        'puntaje_investigacion' => 3.5,
                        'puntaje_vinculacion'   => 3.5,
                        'puntaje_gestion'       => 3.5,
                        'puntaje_formacion'     => 3.5,
                        'comentario'            => 'Evaluación generada automáticamente por FlujoEtapa3CierreSeeder.',
                        'vigente_hasta'         => CalificacionCadService::vigenteHasta(
                            $nomina->categoriaEfectiva()
                        )->toDateString(),
                    ]
                );

                $evaluaciones = Evaluacion::where('nomina_id', $nomina->id)
                    ->where('es_apelacion', false)
                    ->get();
            }

            if ($evaluaciones->isEmpty()) {
                // Sin usuario CCA y sin evaluaciones → solo avanza estado
                $nomina->update(['estado' => 'evaluado']);
                $autoevaluadas++;
                continue;
            }

            // Calcular nota final desde las evaluaciones existentes (con horas reales CCA si existen)
            $categoria = $nomina->categoriaEfectiva();

            $notaFinal = round(
                $evaluaciones->map(fn ($e) => $e->notaFinalCad($categoria))->avg(),
                2
            );

            $concepto      = CalificacionCadService::conceptoDesdeNota($notaFinal);
            $puntajeLegacy = (int) round($notaFinal * 20);
            $determinadoPor = $cca?->id ?? $evaluaciones->first()?->evaluador_id;

            CalificacionFinal::create([
                'nomina_id'       => $nomina->id,
                'puntaje_total'   => $puntajeLegacy,
                'nota_final'      => $notaFinal,
                'calificacion'    => $concepto,
                'determinada_por' => $determinadoPor,
                'fecha'           => $hoy->toDateString(),
                'observacion'     => 'Calificación generada automáticamente por FlujoEtapa3CierreSeeder.',
                'es_apelacion'    => false,
            ]);

            $nomina->update(['estado' => 'evaluado']);
            $autoevaluadas++;
        }

        // ── Reporte ───────────────────────────────────────────────────────────

        $this->command->info("✓ Etapa 3 activa — comunicación de resultados, apelaciones, registro CCDA y revisión vicerrectoría habilitados.");
        $this->command->newLine();

        if ($autoevaluadas > 0) {
            $this->command->warn("  {$autoevaluadas} expediente(s) auto-completados con calificación provisional.");
        }

        $evaluados = Nomina::where('periodo_id', $periodo->id)
            ->where('estado', 'evaluado')
            ->count();

        $this->command->info("  {$evaluados} expediente(s) en estado 'evaluado' (visibles para académico, vicerrectora y secretario).");
        $this->command->newLine();
        $this->command->info('  Rol académico:    puede apelar desde su panel.');
        $this->command->info('  Rol secretario:   resuelve apelaciones → envía a CCA o CCDA según concepto.');
        $this->command->info('  Rol analista:     Apelaciones 2° nivel (Regular/Deficiente) → /analista/apelaciones');
        $this->command->info('  Rol vicerrectora: revisa calificaciones y deja comentarios.');
    }
}
