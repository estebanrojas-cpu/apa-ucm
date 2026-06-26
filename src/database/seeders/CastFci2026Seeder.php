<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsCastUsers;
use Illuminate\Database\Seeder;

class CastFci2026Seeder extends Seeder
{
    use SeedsCastUsers;

    public function run(): void
    {
        $cast = require __DIR__ . '/data/fci_cast_2026.php';
        $this->seedCastMembers($cast, 'FCI');

        $this->command?->info('✓ Cast FCI 2026: 12 usuarios con roles multi-perfil.');
    }
}
