<?php

/**
 * Cast FCAF 2026 — 4 académicos con roles multi-perfil y casos básicos.
 * Horas de contrato: 40 (jornada completa) · 24 (media jornada / hora).
 */
return [
    [
        'numero_personal' => '101',
        'rut'             => '22.111.222-3',
        'name'            => 'Rosa Edith Morales Vega',
        'email'           => 'rosa.morales@ucm.cl',
        'roles'           => ['academico'],
        'nomina'          => [
            'adscripcion_academica'  => 'Planta',
            'unidad_superior'        => 'Facultad de Ciencias Agrarias y Forestales',
            'unidad'                 => 'Secretaría de Facultad FCAF',
            'nombre_posicion'        => 'Secretario de Facultad',
            'tipo_trabajador'        => 'Académico Media Jornada',
            'fecha_inicio_contrato'  => '2014-03-01',
            'horas_contrato'         => 24,
            'categoria'              => 'adjunto',
            'fecha_categorizacion'   => '2018-03-01',
            'historial'              => [
                2024 => ['categoria' => 'adjunto', 'fecha_categorizacion' => '2018-03-01', 'nota' => 4.1, 'concepto' => 'Muy Bueno'],
                2025 => ['nota' => 4.0, 'concepto' => 'Muy Bueno'],
                2026 => ['categoria' => 'adjunto', 'fecha_categorizacion' => '2018-03-01'],
            ],
        ],
    ],
    [
        'numero_personal' => '102',
        'rut'             => '22.222.333-4',
        'name'            => 'Jorge Andrés Silva Mora',
        'email'           => 'jorge.silva@ucm.cl',
        'roles'           => ['academico'],
        'nomina'          => [
            'adscripcion_academica'  => 'Planta',
            'unidad_superior'        => 'Facultad de Ciencias Agrarias y Forestales',
            'unidad'                 => 'Depto. Agronomía',
            'nombre_posicion'        => 'Profesor',
            'tipo_trabajador'        => 'Académico Media Jornada',
            'fecha_inicio_contrato'  => '2013-08-01',
            'horas_contrato'         => 24,
            'categoria'              => 'adjunto',
            'fecha_categorizacion'   => '2017-08-01',
            'historial'              => [
                2024 => ['categoria' => 'adjunto', 'fecha_categorizacion' => '2017-08-01', 'nota' => 4.4, 'concepto' => 'Muy Bueno'],
                2026 => ['categoria' => 'adjunto', 'fecha_categorizacion' => '2017-08-01'],
            ],
        ],
    ],
    [
        'numero_personal' => '103',
        'rut'             => '22.333.444-5',
        'name'            => 'Patricia Carmen Lagos Ríos',
        'email'           => 'patricia.lagos@ucm.cl',
        'roles'           => ['academico'],
        'nomina'          => [
            'adscripcion_academica'  => 'Planta',
            'unidad_superior'        => 'Facultad de Ciencias Agrarias y Forestales',
            'unidad'                 => 'Decanatura FCAF',
            'nombre_posicion'        => 'Decana de Facultad',
            'tipo_trabajador'        => 'Académico Jornada Completa',
            'fecha_inicio_contrato'  => '2009-03-01',
            'horas_contrato'         => 40,
            'categoria'              => 'titular',
            'fecha_categorizacion'   => '2013-03-01',
            'historial'              => [
                2024 => ['categoria' => 'titular', 'fecha_categorizacion' => '2013-03-01', 'nota' => 4.6, 'concepto' => 'Excelente'],
                2026 => ['categoria' => 'titular', 'fecha_categorizacion' => '2013-03-01'],
            ],
        ],
    ],
    [
        'numero_personal' => '105',
        'rut'             => '22.555.666-7',
        'name'            => 'Paula Andrea Morales Vega',
        'email'           => 'paula.morales@ucm.cl',
        'roles'           => ['academico'],
        'nomina'          => [
            'adscripcion_academica'  => 'Planta',
            'unidad_superior'        => 'Facultad de Ciencias Agrarias y Forestales',
            'unidad'                 => 'Depto. Silvicultura',
            'nombre_posicion'        => 'Profesora Titular',
            'tipo_trabajador'        => 'Académico Jornada Completa',
            'fecha_inicio_contrato'  => '2011-03-01',
            'horas_contrato'         => 40,
            'categoria'              => 'titular',
            'fecha_categorizacion'   => '2015-03-01',
            'historial'              => [
                2024 => ['categoria' => 'titular', 'fecha_categorizacion' => '2015-03-01', 'nota' => 4.5, 'concepto' => 'Excelente'],
                2025 => ['nota' => 4.6, 'concepto' => 'Excelente'],
                2026 => ['categoria' => 'titular', 'fecha_categorizacion' => '2015-03-01'],
            ],
        ],
    ],
];
