<?php

namespace Database\Seeders;

use App\Models\Facultad;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuariosPruebaSeeder extends Seeder
{
    public function run(): void
    {
        $fci  = Facultad::where('codigo', 'FCI')->first();
        $fcaf = Facultad::where('codigo', 'FCAF')->first();

        $usuarios = [
            [
                'email'       => 'admin@ucm.cl',
                'name'        => 'Administrador Sistema',
                'role'        => 'admin',
                'facultad_id' => null,
            ],
            [
                'email'       => 'analista@ucm.cl',
                'name'        => 'Analista CCDA',
                'role'        => 'analista_ccda',
                'facultad_id' => null,
            ],
            [
                'email'       => 'secretario@ucm.cl',
                'name'        => 'Secretario FCI',
                'role'        => 'secretario',
                'facultad_id' => $fci?->id,
            ],
            [
                'email'       => 'cca@ucm.cl',
                'name'        => 'Miembro CCA',
                'role'        => 'miembro_cca',
                'facultad_id' => $fci?->id,
            ],
            [
                'email'       => 'jefe@ucm.cl',
                'name'        => 'Jefe Académico FCI',
                'role'        => 'jefe_academico',
                'facultad_id' => $fci?->id,
            ],
            [
                'email'       => 'vicerrectora@ucm.cl',
                'name'        => 'Vicerrectora Académica',
                'role'        => 'vicerrectora',
                'facultad_id' => null,
            ],
            [
                'email'       => 'academico@ucm.cl',
                'name'        => 'Académico Prueba',
                'role'        => 'academico',
                'facultad_id' => $fci?->id,
                'rut'         => '11.111.111-1',
                'categoria_academica'   => 'adjunto',
                'linea_desarrollo'      => 'docente',
                'fecha_jerarquizacion'  => '2018-03-15',
                'horas_contrato_isem'   => 18,
                'horas_contrato_iisem'  => 18,
                'nota_anterior'         => 4.2,
                'concepto_anterior'     => 'Muy Bueno',
            ],
            [
                'email'       => 'secretario.fcaf@ucm.cl',
                'name'        => 'Secretario FCAF',
                'role'        => 'secretario',
                'facultad_id' => $fcaf?->id,
            ],
            [
                'email'       => 'cca.fcaf@ucm.cl',
                'name'        => 'Miembro CCA FCAF',
                'role'        => 'miembro_cca',
                'facultad_id' => $fcaf?->id,
            ],
            [
                'email'       => 'jefe.fcaf@ucm.cl',
                'name'        => 'Jefe Académico FCAF',
                'role'        => 'jefe_academico',
                'facultad_id' => $fcaf?->id,
            ],
            [
                'email'       => 'academico.fcaf@ucm.cl',
                'name'        => 'Académico FCAF Demo',
                'role'        => 'academico',
                'facultad_id' => $fcaf?->id,
                'rut'         => '17.890.123-4',
                'categoria_academica'   => 'titular',
                'linea_desarrollo'      => 'investigador',
                'fecha_jerarquizacion'  => '2012-09-01',
                'horas_contrato_isem'   => 22,
                'horas_contrato_iisem'  => 22,
                'nota_anterior'         => 4.5,
                'concepto_anterior'     => 'Excelente',
            ],
        ];

        foreach ($usuarios as $datos) {
            User::updateOrCreate(
                ['email' => $datos['email']],
                array_merge($datos, ['password' => Hash::make('password')])
            );
        }
    }
}
