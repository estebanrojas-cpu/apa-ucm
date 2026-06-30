<?php

namespace App\Enums;

enum CargoFacultad: string
{
    case Secretario            = 'secretario';
    case Decano                = 'decano';
    case DirectorEscuela       = 'director_escuela';
    case DirectorDepartamento  = 'director_departamento';
    case MiembroCca            = 'miembro_cca';
    case MiembroCcaSindicato   = 'miembro_cca_sindicato';

  /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Secretario           => 'Secretario/a de Facultad',
            self::Decano               => 'Decano/a',
            self::DirectorEscuela      => 'Director/a de Escuela',
            self::DirectorDepartamento => 'Director/a de Departamento',
            self::MiembroCca           => 'Miembro CCA',
            self::MiembroCcaSindicato  => 'Miembro CCA (Sindicato)',
        };
    }

    /** Cargos que el decano evalúa con informe de jefatura. */
    public static function directivosParaDecano(): array
    {
        return [
            self::Secretario,
            self::DirectorEscuela,
        ];
    }

    /** Cargos CCA (incluye sindicato). */
    public static function miembrosCca(): array
    {
        return [self::MiembroCca, self::MiembroCcaSindicato];
    }

    /** Perfiles de sesión con vista propia. */
    public function rolSesion(): ?string
    {
        return match ($this) {
            self::Secretario           => 'secretario',
            self::Decano               => 'decano',
            self::DirectorDepartamento => 'director_departamento',
            self::MiembroCca,
            self::MiembroCcaSindicato  => 'miembro_cca',
            self::DirectorEscuela      => null,
        };
    }
}
