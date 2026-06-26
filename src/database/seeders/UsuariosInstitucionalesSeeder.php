<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuariosInstitucionalesSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            [
                'email' => 'analista@ucm.cl',
                'name'  => 'Analista CCDA',
                'roles' => ['analista_ccda'],
            ],
            [
                'email' => 'vicerrectora@ucm.cl',
                'name'  => 'Vicerrectora Académica',
                'roles' => ['vicerrectora'],
            ],
        ];

        foreach ($usuarios as $datos) {
            $user = User::updateOrCreate(
                ['email' => $datos['email']],
                [
                    'name'        => $datos['name'],
                    'password'    => Hash::make('password'),
                    'role'        => $datos['roles'][0],
                    'facultad_id' => null,
                    'activo'      => true,
                ]
            );

            $user->syncUserRoles($datos['roles']);
        }
    }
}
