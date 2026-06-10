<?php

namespace App\Enums;

enum RoleEnum: string
{
    case Admin         = 'admin';
    case AnalistaCCDA  = 'analista_ccda';
    case Secretario    = 'secretario';
    case MiembroCCA    = 'miembro_cca';
    case JefeAcademico = 'jefe_academico';
    case Academico     = 'academico';
    case Vicerrectora  = 'vicerrectora';

    public function label(): string
    {
        return match($this) {
            self::Admin         => 'Administrador',
            self::AnalistaCCDA  => 'Analista CCDA',
            self::Secretario    => 'Secretario',
            self::MiembroCCA    => 'Miembro CCA',
            self::JefeAcademico => 'Jefe Académico',
            self::Academico     => 'Académico',
            self::Vicerrectora  => 'Vicerrectoría',
        };
    }

    /** Roles con acceso institucional (sin restricción de facultad). */
    public static function nivelInstitucional(): array
    {
        return [self::Admin, self::AnalistaCCDA, self::Vicerrectora];
    }

    /** Roles acotados a una facultad. */
    public static function nivelFacultad(): array
    {
        return [self::Secretario, self::MiembroCCA, self::JefeAcademico, self::Academico];
    }
}
