<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsCastUsers;
use Illuminate\Database\Seeder;

class CastFcaf2026Seeder extends Seeder
{
    use SeedsCastUsers;

    public function run(): void
    {
        $cast = require __DIR__ . '/data/fcaf_cast_2026.php';
        $this->seedCastMembers($cast, 'FCAF');

        $this->command?->info('✓ Cast FCAF 2026: 6 usuarios con roles multi-perfil.');
    }
}
