<?php

namespace Database\Seeders;

use App\Models\CompromisoApa;
use App\Models\CategoriaApa;
use App\Models\Cronograma;
use App\Models\Evidencia;
use App\Models\Facultad;
use App\Models\Nomina;
use App\Models\Periodo;
use App\Models\PlazoFacultad;
use App\Models\Solicitud;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Carga datos realistas para la demo de Sprints 1-3.
 *
 * Crea (idempotente):
 *  - 1 período activo con cronograma completo (6 etapas secuenciales).
 *  - Plazos vigentes FCI y FCAF.
 *  - FCI: 6 académicos (declaración APA, licencias, evidencias demo).
 *  - FCAF: 2 académicos (segunda facultad operativa en demo).
 *
 * Diseñado para correrse después de `UsuariosPruebaSeeder` en entorno local.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $analista   = User::where('email', 'analista@ucm.cl')->first();
        $secretario = User::where('email', 'secretario@ucm.cl')->first();
        $academico  = User::where('email', 'academico@ucm.cl')->first();
        $fci        = Facultad::where('codigo', 'FCI')->first();

        if (!$analista || !$secretario || !$academico || !$fci) {
            $this->command?->warn(
                'DemoSeeder: faltan usuarios base (analista/secretario/academico) o la facultad FCI. '
                . 'Ejecute FacultadesSeeder + UsuariosPruebaSeeder antes.'
            );
            return;
        }

        $periodo = $this->crearPeriodoActivo($analista);
        $this->crearCronograma($periodo);
        $this->crearPlazoFacultad($periodo, $fci, $secretario);

        $academicos = $this->crearAcademicosFCI($academico, $fci);
        $this->crearNominas($periodo, $academicos, $analista, $secretario);
        $this->crearEvidenciasDemo($periodo, $academico);
        $this->crearCompromisosDemo($periodo, $academicos);

        $this->seedFacultadFCAF($periodo);
    }

    private function crearPeriodoActivo(User $analista): Periodo
    {
        $hoy  = Carbon::today();
        $anio = $hoy->year;

        return Periodo::firstOrCreate(
            ['nombre' => "{$anio}-1 - Calificación APA " . ($anio - 1)],
            [
                'anio'         => $anio,
                'estado'       => 'activo',
                'fecha_inicio' => $hoy->toDateString(),
                'fecha_cierre' => $hoy->copy()->addDays(150)->toDateString(),
                'creado_por'   => $analista->id,
            ]
        );
    }

    private function crearCronograma(Periodo $periodo): void
    {
        $inicio = Carbon::parse($periodo->fecha_inicio);

        $fines = [
            ['etapa' => 'carga_evidencias',       'fecha_fin' => $inicio->copy()->addDays(30)->toDateString()],
            ['etapa' => 'validacion_secretario',  'fecha_fin' => $inicio->copy()->addDays(60)->toDateString()],
            ['etapa' => 'evaluacion_cca',         'fecha_fin' => $inicio->copy()->addDays(90)->toDateString()],
            ['etapa' => 'consejo_facultad',       'fecha_fin' => $inicio->copy()->addDays(110)->toDateString()],
            ['etapa' => 'apelaciones',            'fecha_fin' => $inicio->copy()->addDays(130)->toDateString()],
            ['etapa' => 'revision_vicerrectoria', 'fecha_fin' => $inicio->copy()->addDays(140)->toDateString()],
            ['etapa' => 'cierre',                 'fecha_fin' => $inicio->copy()->addDays(150)->toDateString()],
        ];

        $preparado = Cronograma::prepararParaGuardar($inicio->toDateString(), $fines);

        foreach ($preparado as $e) {
            Cronograma::firstOrCreate(
                ['periodo_id' => $periodo->id, 'etapa' => $e['etapa']],
                [
                    'fecha_inicio' => $e['fecha_inicio'],
                    'fecha_fin'    => $e['fecha_fin'],
                ]
            );
        }
    }

    private function crearPlazoFacultad(Periodo $periodo, Facultad $facultad, User $secretario): void
    {
        PlazoFacultad::firstOrCreate(
            ['periodo_id' => $periodo->id, 'facultad_id' => $facultad->id],
            [
                'fecha_limite' => Carbon::today()->addDays(10)->toDateString(),
                'creado_por'   => $secretario->id,
            ]
        );
    }

    /** Segunda facultad demo: FCAF (Ciencias Agrarias y Forestales). */
    private function seedFacultadFCAF(Periodo $periodo): void
    {
        $fcaf       = Facultad::where('codigo', 'FCAF')->first();
        $secretario = User::where('email', 'secretario.fcaf@ucm.cl')->first();
        $academico  = User::where('email', 'academico.fcaf@ucm.cl')->first();

        if (!$fcaf || !$secretario || !$academico) {
            $this->command?->warn('DemoSeeder: omitiendo FCAF (faltan facultad o usuarios base).');
            return;
        }

        $this->crearPlazoFacultad($periodo, $fcaf, $secretario);

        $paula = User::firstOrCreate(
            ['email' => 'paula.morales@ucm.cl'],
            [
                'name'        => 'Paula Andrea Morales Vega',
                'rut'         => '18.901.234-5',
                'role'        => 'academico',
                'facultad_id' => $fcaf->id,
                'password'    => Hash::make('password'),
            ]
        );

        $this->aplicarPerfil($paula, [
            'categoria' => 'adjunto',
            'linea'     => 'docente',
            'nota'      => 4.1,
            'concepto'  => 'Muy Bueno',
        ]);

        foreach ([$academico, $paula] as $i => $u) {
            Nomina::firstOrCreate(
                ['periodo_id' => $periodo->id, 'user_id' => $u->id],
                [
                    'facultad_id' => $fcaf->id,
                    'estado'      => $i === 0 ? 'en_carga' : 'pendiente',
                ]
            );
        }

        $this->crearEvidenciasParaAcademico($periodo, $academico, [
            [
                'slug'        => 'docencia',
                'nombre'      => 'programa-asignatura-agronomia.pdf',
                'descripcion' => 'Programa y evaluaciones de Agronomía Sustentable (2025-1).',
            ],
            [
                'slug'        => 'investigacion',
                'nombre'      => 'informe-proyecto-fondef.pdf',
                'descripcion' => 'Avance de proyecto FONDECYT en producción agrícola sustentable.',
            ],
        ]);

        $nominaFcaf = Nomina::where('periodo_id', $periodo->id)
            ->where('user_id', $academico->id)
            ->first();

        if ($nominaFcaf) {
            CompromisoApa::updateOrCreate(
                ['nomina_id' => $nominaFcaf->id],
                [
                    'periodo_id'         => $periodo->id,
                    'pct_docencia'       => 45,
                    'pct_investigacion'  => 35,
                    'pct_extension'      => 10,
                    'pct_administracion' => 5,
                    'pct_otras'          => 5,
                    'fuente'             => 'manual',
                    'confirmado_en'      => now(),
                ]
            );
        }
    }

    /**
     * @return array<int, User> Lista de académicos FCI: índice 0 = academico@ucm.cl.
     */
    private function crearAcademicosFCI(User $academicoBase, Facultad $fci): array
    {
        $perfiles = [
            ['categoria' => 'adjunto',  'linea' => 'docente',      'nota' => 4.2, 'concepto' => 'Muy Bueno'],
            ['categoria' => 'titular',  'linea' => 'investigador', 'nota' => 4.6, 'concepto' => 'Excelente'],
            ['categoria' => 'auxiliar', 'linea' => 'docente',      'nota' => 3.8, 'concepto' => 'Bueno'],
            ['categoria' => 'adjunto',  'linea' => 'mixta',        'nota' => 4.0, 'concepto' => 'Muy Bueno'],
            ['categoria' => 'titular',  'linea' => 'docente',      'nota' => 4.4, 'concepto' => 'Muy Bueno'],
            ['categoria' => 'adjunto',  'linea' => 'docente',      'nota' => 3.6, 'concepto' => 'Bueno'],
        ];

        $this->aplicarPerfil($academicoBase, $perfiles[0]);

        $extras = [
            ['name' => 'María Elena Soto Ríos',       'email' => 'maria.soto@ucm.cl',    'rut' => '12.345.678-9'],
            ['name' => 'Juan Carlos Pérez Muñoz',     'email' => 'juan.perez@ucm.cl',    'rut' => '13.456.789-0'],
            ['name' => 'Andrea Fernanda Lagos Díaz',  'email' => 'andrea.lagos@ucm.cl',  'rut' => '14.567.890-1'],
            ['name' => 'Roberto Esteban Vidal Bravo', 'email' => 'roberto.vidal@ucm.cl', 'rut' => '15.678.901-2'],
            ['name' => 'Camila Paz Núñez Sandoval',   'email' => 'camila.nunez@ucm.cl',  'rut' => '16.789.012-3'],
        ];

        $academicos = [$academicoBase];

        foreach ($extras as $i => $datos) {
            $user = User::firstOrCreate(
                ['email' => $datos['email']],
                [
                    'name'        => $datos['name'],
                    'rut'         => $datos['rut'],
                    'role'        => 'academico',
                    'facultad_id' => $fci->id,
                    'password'    => Hash::make('password'),
                ]
            );
            $this->aplicarPerfil($user, $perfiles[$i + 1] ?? $perfiles[0]);
            $academicos[] = $user;
        }

        return $academicos;
    }

    private function aplicarPerfil(User $user, array $perfil): void
    {
        $user->update([
            'categoria_academica'  => $perfil['categoria'],
            'linea_desarrollo'     => $perfil['linea'],
            'fecha_jerarquizacion' => '2015-06-01',
            'horas_contrato_isem'  => 18,
            'horas_contrato_iisem' => 18,
            'nota_anterior'        => $perfil['nota'],
            'concepto_anterior'    => $perfil['concepto'],
        ]);
    }

    /**
     * Asignaciones de estado:
     *  - índice 0 (academico@ucm.cl) → `en_carga` (tendrá 2 evidencias).
     *  - índice 1                    → `pendiente` + licencia médica activa (aprobada por CCDA).
     *  - índice 2                    → `pendiente` + licencia pendiente de aprobación CCDA.
     *  - índices 3..5                → `pendiente`.
     *
     * @param  array<int, User>  $academicos
     */
    private function crearNominas(Periodo $periodo, array $academicos, User $analista, User $secretario): void
    {
        foreach ($academicos as $i => $u) {
            $datos = [
                'estado' => $i === 0 ? 'en_carga' : 'pendiente',
            ];

            $nomina = Nomina::firstOrCreate(
                ['periodo_id' => $periodo->id, 'user_id' => $u->id],
                $datos
            );

            if ($i === 1) {
                $motivo = 'Licencia médica vigente: reposo por 60 días desde el inicio del período.';
                $inicio = Carbon::today();

                Solicitud::updateOrCreate(
                    [
                        'nomina_id' => $nomina->id,
                        'tipo'      => 'licencia_medica',
                        'estado'    => 'activa',
                    ],
                    [
                        'fecha_inicio'     => $inicio->toDateString(),
                        'fecha_fin'        => $inicio->copy()->addDays(60)->toDateString(),
                        'motivo'           => $motivo,
                        'creado_por'       => $secretario->id,
                        'iniciada_por'     => $secretario->id,
                        'aprobada_por'     => $analista->id,
                        'fecha_aprobacion' => $inicio,
                    ]
                );

                $nomina->update([
                    'con_licencia'         => true,
                    'observacion_licencia' => $motivo,
                ]);
            }

            if ($i === 2) {
                $motivo = 'Director informa licencia médica de 30 días.';

                Solicitud::updateOrCreate(
                    [
                        'nomina_id' => $nomina->id,
                        'tipo'      => 'licencia_medica',
                        'estado'    => 'activa',
                    ],
                    [
                        'fecha_inicio' => Carbon::today()->toDateString(),
                        'fecha_fin'    => Carbon::today()->addDays(30)->toDateString(),
                        'motivo'       => $motivo,
                        'creado_por'   => $secretario->id,
                        'iniciada_por' => $secretario->id,
                    ]
                );

                $nomina->update([
                    'con_licencia'         => true,
                    'observacion_licencia' => $motivo,
                ]);
            }
        }
    }

    private function crearEvidenciasDemo(Periodo $periodo, User $academico): void
    {
        $this->crearEvidenciasParaAcademico($periodo, $academico, [
            [
                'slug'        => 'docencia',
                'nombre'      => 'planificacion-asignatura-2025.pdf',
                'descripcion' => 'Planificación y syllabus de Programación II (semestre 2025-1).',
            ],
            [
                'slug'        => 'investigacion',
                'nombre'      => 'articulo-revista-ingenieria.pdf',
                'descripcion' => 'Artículo aceptado en revista indexada Scopus (Q3).',
            ],
        ]);
    }

    /** @param  array<int, array{slug: string, nombre: string, descripcion: string}>  $archivos */
    private function crearEvidenciasParaAcademico(Periodo $periodo, User $academico, array $archivos): void
    {
        $nomina = Nomina::where('periodo_id', $periodo->id)
            ->where('user_id', $academico->id)
            ->first();

        if (!$nomina) {
            return;
        }

        $disk = Storage::disk('public');
        foreach ($nomina->evidencias as $ev) {
            $disk->delete($ev->ruta);
        }
        $nomina->evidencias()->delete();

        $directorio = "evidencias/{$nomina->id}";

        foreach ($archivos as $info) {
            $categoria = CategoriaApa::where('slug', $info['slug'])->first();
            if (!$categoria) {
                continue;
            }

            $contenido = $this->generarPdfDummy($info['nombre'], $info['descripcion']);
            $ruta      = "{$directorio}/" . Str::random(16) . '.pdf';

            $disk->put($ruta, $contenido);

            Evidencia::create([
                'nomina_id'      => $nomina->id,
                'categoria_id'   => $categoria->id,
                'nombre_archivo' => $info['nombre'],
                'ruta'           => $ruta,
                'tamano'         => strlen($contenido),
                'mime_type'      => 'application/pdf',
                'subido_por'     => $academico->id,
                'es_apelacion'   => false,
                'descripcion'    => $info['descripcion'],
            ]);
        }
    }

    /** Compromiso APA precargado solo para academico@ (ejemplo de académico que ya declaró). */
    private function crearCompromisosDemo(Periodo $periodo, array $academicos): void
    {
        if (!isset($academicos[0])) {
            return;
        }

        $nomina = Nomina::where('periodo_id', $periodo->id)
            ->where('user_id', $academicos[0]->id)
            ->first();

        if (!$nomina) {
            return;
        }

        CompromisoApa::updateOrCreate(
            ['nomina_id' => $nomina->id],
            [
                'periodo_id'         => $periodo->id,
                'pct_docencia'       => 50,
                'pct_investigacion'  => 25,
                'pct_extension'      => 10,
                'pct_administracion' => 10,
                'pct_otras'          => 5,
                'fuente'             => 'manual',
                'confirmado_en'      => now(),
            ]
        );
    }

    /**
     * Genera un PDF dummy mínimo (~1 KB de texto plano con encabezado PDF).
     * No es un PDF totalmente válido para visores estrictos, pero es suficiente
     * para mostrar carga, listado y descarga durante la demo.
     */
    private function generarPdfDummy(string $titulo, string $descripcion): string
    {
        $contenido = "%PDF-1.4\n";
        $contenido .= "% Evidencia dummy para demo APA UCM\n";
        $contenido .= "% Título: {$titulo}\n";
        $contenido .= "% Descripción: {$descripcion}\n";
        $contenido .= "% Generado por DemoSeeder el " . now()->toIso8601String() . "\n";
        $contenido .= str_repeat("% Contenido de demostración - reemplazar con archivo real en producción.\n", 8);
        $contenido .= "%%EOF\n";

        return $contenido;
    }
}
