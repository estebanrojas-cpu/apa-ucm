<?php

namespace App\Services;

use App\Models\Nomina;
use App\Models\User;
use App\Services\ApaContratoService;

class NominaAccesoService
{
    /**
     * Roles de acceso según cargo en nómina SAPD (multi-perfil).
     * El secretario conserva perfil académico y participa del proceso evaluativo.
     *
     * @return list<string>
     */
    public function inferirRoles(Nomina $nomina): array
    {
        $posicion = mb_strtolower($nomina->nombre_posicion ?? '');

        if (str_contains($posicion, 'decana') || str_contains($posicion, 'decano')) {
            return ['jefe_academico'];
        }

        if ((str_contains($posicion, 'director') || str_contains($posicion, 'directora'))
            && (str_contains($posicion, 'departamento') || str_contains($posicion, 'depto'))) {
            return ['jefe_academico'];
        }

        if (str_contains($posicion, 'secretario') || str_contains($posicion, 'secretaria')) {
            return ['secretario', 'academico'];
        }

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

    public function provisionarUsuario(Nomina $nomina): User
    {
        $rut = $nomina->rut;
        if (!$rut) {
            throw new \InvalidArgumentException('La nómina no tiene RUT.');
        }

        $user = User::where('rut', $rut)->first();

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
        } else {
            $user->update([
                'name'        => $nomina->nombre ?? $user->name,
                'facultad_id' => $nomina->facultad_id ?? $user->facultad_id,
            ]);
        }

        $user->update(app(ApaContratoService::class)->perfilDesdeNomina($nomina));

        $user->syncUserRoles($this->inferirRoles($nomina));

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
