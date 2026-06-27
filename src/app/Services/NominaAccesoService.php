<?php

namespace App\Services;

use App\Models\Nomina;
use App\Models\User;
use App\Services\ApaContratoService;

class NominaAccesoService
{
    /**
     * Todos los usuarios de nómina son académicos en base.
     * Los cargos del período (secretario, decano, CCA…) se asignan aparte.
     *
     * @return list<string>
     */
    public function inferirRoles(Nomina $nomina): array
    {
        return ['academico'];
    }

    public function emailParaNomina(Nomina $nomina): string
    {
        $almacenado = $nomina->datos_adicionales['email_ucm'] ?? null;

        if (is_string($almacenado) && $almacenado !== '') {
            return $almacenado;
        }

        return $this->resolverEmail($this->emailDesdeNombre($nomina->nombre ?? ''));
    }

    public function provisionarUsuario(Nomina $nomina, bool $soloCrearRolAcademico = true): User
    {
        $rut = $nomina->rut;
        if (!$rut) {
            throw new \InvalidArgumentException('La nómina no tiene RUT.');
        }

        $user = User::where('rut', $rut)->first();
        $esNuevo = !$user;

        if (!$user) {
            $email = $this->emailParaNomina($nomina);

            $user = User::create([
                'name'        => $nomina->nombre,
                'rut'         => $rut,
                'email'       => $email,
                'password'    => $this->passwordDesdeRut($rut),
                'role'        => 'academico',
                'facultad_id' => $nomina->facultad_id,
                'activo'      => true,
            ]);

            $user->syncUserRoles(['academico']);
        } else {
            $user->update(array_filter([
                'name'        => $nomina->nombre ?? $user->name,
                'facultad_id' => $nomina->facultad_id ?? $user->facultad_id,
            ]));

            if ($soloCrearRolAcademico && !$user->hasAnyAssignedRole(['academico'])) {
                $roles = $user->assignedRoles();
                $roles[] = 'academico';
                $user->syncUserRoles(array_values(array_unique($roles)));
            }
        }

        $perfil = app(ApaContratoService::class)->perfilDesdeNomina($nomina);
        $user->update($perfil);

        if (!$nomina->user_id) {
            $nomina->update(['user_id' => $user->id]);
        }

        return $user->fresh();
    }

    public function passwordDesdeRut(string $rut): string
    {
        $clean  = str_replace('.', '', $rut);
        $cuerpo = explode('-', $clean)[0];

        return preg_replace('/\D/', '', $cuerpo) ?: '';
    }

    public function emailDesdeNombre(string $nombre): string
    {
        $normalize = fn (string $s): string => preg_replace('/[^a-z]/', '', strtr(
            mb_strtolower(trim($s)),
            ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']
        ));

        $words    = preg_split('/\s+/', trim($nombre));
        $primero  = $normalize($words[0] ?? 'academico');
        $apellido = $normalize($words[1] ?? '');

        return ($apellido ? "{$primero}.{$apellido}" : $primero) . '@ucm.cl';
    }

    public function resolverEmail(string $baseEmail): string
    {
        if (!User::where('email', $baseEmail)->exists()) {
            return $baseEmail;
        }

        $base    = explode('@', $baseEmail)[0];
        $counter = 1;

        do {
            $candidate = sprintf('%s.%02d@ucm.cl', $base, $counter);
            $counter++;
        } while (User::where('email', $candidate)->exists());

        return $candidate;
    }
}
