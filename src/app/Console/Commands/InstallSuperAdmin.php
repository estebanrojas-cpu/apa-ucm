<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class InstallSuperAdmin extends Command
{
    protected $signature = 'apa:install-super-admin
                            {--email= : Email del super administrador}
                            {--name=Super Administrador : Nombre}
                            {--password= : Contraseña (se solicita si no se indica)}';

    protected $description = 'Crea el usuario super administrador inicial del sistema APA';

    public function handle(): int
    {
        if (User::whereHas('userRoles', fn ($q) => $q->where('role', 'super_admin'))->exists()) {
            $this->error('Ya existe un super administrador. Use el panel /super-admin para gestionar usuarios.');

            return self::FAILURE;
        }

        $email = $this->option('email') ?: $this->ask('Email del super administrador');
        $name  = $this->option('name');
        $pass  = $this->option('password') ?: $this->secret('Contraseña');

        if (!$email || !$pass) {
            $this->error('Email y contraseña son obligatorios.');

            return self::FAILURE;
        }

        $user = User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make($pass),
            'role'     => 'super_admin',
            'activo'   => true,
        ]);

        $user->syncUserRoles(['super_admin']);

        $this->info("Super administrador creado: {$email}");
        $this->line('Acceda en /super-admin tras iniciar sesión.');

        return self::SUCCESS;
    }
}
