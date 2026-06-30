<?php

namespace Database\Seeders;

use App\Models\Cronograma;
use App\Models\Facultad;
use App\Models\Nomina;
use App\Models\Periodo;
use App\Models\PlazoFacultad;
use App\Models\SemestreAcademico;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Concerns\SeedsNominaHistorial;
use Illuminate\Database\Seeder;

/**
 * Período activo vacío: cronograma, semestres, plazos y nóminas en estado pendiente.
 * Sin evidencias, compromisos APA ni evaluaciones precargadas.
 * Al final alinea el cronograma a Etapa 1 (carga activa hoy).
 */
class PeriodoBaseSeeder extends Seeder
{
    use SeedsNominaHistorial;
    use \Database\Seeders\Concerns\CastHorasContrato;

    public function run(): void
    {
        $analista = User::where('email', 'analista@ucm.cl')->first();

        if (!$analista) {
            $this->command?->warn('PeriodoBaseSeeder: falta analista@ucm.cl.');
            return;
        }

        $periodo = $this->crearPeriodo($analista);
        $this->crearCronograma($periodo);
        $this->crearSemestres($periodo);
        $this->crearNominas($periodo);
        $this->crearPlazos($periodo, $analista);

        $this->call(FlujoEtapa1CargaSeeder::class);

        $this->command?->info("✓ Período {$periodo->nombre} listo — etapa 1 activa, nóminas en pendiente.");
        $this->command?->info('  Comisión CCA: designar manualmente desde analista → Períodos → Comisión CCA.');
        $this->command?->info('  Cuentas de académicos: se crean al enviar acceso desde Nómina (sin usuarios precargados).');
    }

    private function crearPeriodo(User $analista): Periodo
    {
        $hoy  = Carbon::today();
        $anio = $hoy->year;

        return Periodo::create([
            'anio'         => $anio,
            'nombre'       => "{$anio}-1 - Calificación APA " . ($anio - 1),
            'fecha_inicio' => $hoy->toDateString(),
            'fecha_cierre' => $hoy->copy()->addDays(150)->toDateString(),
            'estado'       => 'activo',
            'creado_por'   => $analista->id,
        ]);
    }

    private function crearCronograma(Periodo $periodo): void
    {
        $inicio = Carbon::parse($periodo->fecha_inicio);

        $fines = [
            ['etapa' => 'carga_evidencias',        'fecha_fin' => $inicio->copy()->addDays(30)->toDateString()],
            ['etapa' => 'validacion_secretario',   'fecha_fin' => $inicio->copy()->addDays(30)->toDateString()],
            ['etapa' => 'informe_jefatura',        'fecha_fin' => $inicio->copy()->addDays(30)->toDateString()],
            ['etapa' => 'evaluacion_cca',          'fecha_fin' => $inicio->copy()->addDays(60)->toDateString()],
            ['etapa' => 'comunicacion_resultados', 'fecha_fin' => $inicio->copy()->addDays(60)->toDateString()],
            ['etapa' => 'apelaciones',             'fecha_fin' => $inicio->copy()->addDays(80)->toDateString()],
            ['etapa' => 'registro_ccda',           'fecha_fin' => $inicio->copy()->addDays(95)->toDateString()],
            ['etapa' => 'revision_vicerrectoria',  'fecha_fin' => $inicio->copy()->addDays(110)->toDateString()],
        ];

        foreach (Cronograma::prepararParaGuardar($inicio->toDateString(), $fines) as $etapa) {
            Cronograma::create([
                'periodo_id'   => $periodo->id,
                'etapa'        => $etapa['etapa'],
                'fecha_inicio' => $etapa['fecha_inicio'],
                'fecha_fin'    => $etapa['fecha_fin'],
            ]);
        }
    }

    private function crearSemestres(Periodo $periodo): void
    {
        $inicio = Carbon::parse($periodo->fecha_inicio);

        SemestreAcademico::create([
            'periodo_id'   => $periodo->id,
            'numero'       => 1,
            'fecha_cierre' => $inicio->copy()->addDays(70)->toDateString(),
        ]);

        SemestreAcademico::create([
            'periodo_id'   => $periodo->id,
            'numero'       => 2,
            'fecha_cierre' => $inicio->copy()->addDays(140)->toDateString(),
        ]);
    }

    private function crearNominas(Periodo $periodo): void
    {
        $casts = [
            'FCI'  => require __DIR__ . '/data/fci_cast_2026.php',
            'FCAF' => require __DIR__ . '/data/fcaf_cast_2026.php',
        ];

        foreach ($casts as $codigo => $personas) {
            $facultad = Facultad::where('codigo', $codigo)->firstOrFail();

            foreach ($personas as $persona) {
                $nominaData = $persona['nomina'];
                $historial  = $nominaData['historial'] ?? [];
                unset($nominaData['historial']);

                $nominaData['horas_contrato'] = self::horasContratoDemo($nominaData['tipo_trabajador'] ?? null);

                $datosAdicionales = $nominaData['datos_adicionales'] ?? [];
                if (!empty($persona['email'])) {
                    $datosAdicionales['email_ucm'] = $persona['email'];
                }

                $nomina = Nomina::create(array_merge($nominaData, [
                    'periodo_id'      => $periodo->id,
                    'user_id'         => null,
                    'facultad_id'     => $facultad->id,
                    'rut'             => $persona['rut'],
                    'nombre'          => $persona['name'],
                    'numero_personal' => $persona['numero_personal'],
                    'datos_adicionales' => $datosAdicionales ?: null,
                    'estado'          => 'pendiente',
                    'con_licencia'    => false,
                ]));

                $this->seedNominaHistorial($nomina, $historial);
            }
        }
    }

    private function crearPlazos(Periodo $periodo, User $analista): void
    {
        $hoy = Carbon::today();

        foreach (['FCI', 'FCAF'] as $codigo) {
            $facultad = Facultad::where('codigo', $codigo)->firstOrFail();

            PlazoFacultad::create([
                'periodo_id'   => $periodo->id,
                'facultad_id'  => $facultad->id,
                'fecha_limite' => $hoy->copy()->addDays(20)->toDateString(),
                'creado_por'   => $analista->id,
            ]);
        }
    }
}
