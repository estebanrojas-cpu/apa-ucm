<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FacultadesSeeder::class);
        $this->call(CategoriasApaSeeder::class);

        if (app()->isLocal()) {
            $this->call(UsuariosInstitucionalesSeeder::class);
            $this->call(PeriodoBaseSeeder::class);
        }
    }
}
