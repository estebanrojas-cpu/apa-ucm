<?php

namespace App\Enums;

enum RoleEnum: string
{
    case SuperAdmin            = 'super_admin';
    case AnalistaCCDA          = 'analista_ccda';
    case Secretario            = 'secretario';
    case MiembroCCA            = 'miembro_cca';
    case JefeAcademico         = 'jefe_academico';
    case DirectorDepartamento  = 'director_departamento';
    case Decano                = 'decano';
    case Academico             = 'academico';
    case Vicerrectora          = 'vicerrectora';

    public function label(): string
    {
        return match($this) {
            self::SuperAdmin           => 'Super Administrador',
            self::AnalistaCCDA         => 'Analista CCDA',
            self::Secretario           => 'Secretario',
            self::MiembroCCA           => 'Miembro CCA',
            self::JefeAcademico        => 'Jefe Académico',
            self::DirectorDepartamento => 'Director de Departamento',
            self::Decano               => 'Decano/a',
            self::Academico            => 'Académico',
            self::Vicerrectora         => 'Vicerrectoría',
        };
    }

    /** Roles con acceso institucional (sin restricción de facultad). */
    public static function nivelInstitucional(): array
    {
        return [self::SuperAdmin, self::AnalistaCCDA, self::Vicerrectora];
    }

    /** Roles acotados a una facultad. */
    public static function nivelFacultad(): array
    {
        return [
            self::Secretario, self::MiembroCCA, self::JefeAcademico,
            self::DirectorDepartamento, self::Decano, self::Academico,
        ];
    }
}
