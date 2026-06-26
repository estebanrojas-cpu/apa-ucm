<?php

namespace Database\Seeders\Concerns;

use App\Models\Facultad;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

trait SeedsCastUsers
{
    protected function seedCastMembers(array $cast, string $facultadCodigo): void
    {
        $facultad = Facultad::where('codigo', $facultadCodigo)->firstOrFail();

        foreach ($cast as $persona) {
            $user = User::updateOrCreate(
                ['email' => $persona['email']],
                [
                    'name'        => $persona['name'],
                    'rut'         => $persona['rut'],
                    'password'    => Hash::make('password'),
                    'role'        => $persona['roles'][0],
                    'facultad_id' => $facultad->id,
                    'activo'      => true,
                ]
            );

            $user->syncUserRoles($persona['roles']);
        }
    }
}
