<?php

/**
 * Cast FCI 2026 — 6 académicos con casos de prueba representativos.
 * Usado por PeriodoBaseSeeder (solo nómina; sin usuarios precargados).
 * Horas de contrato: 40 (jornada completa) · 24 (media jornada / hora).
 * El campo `roles` es siempre ['academico']: los roles de cargo (decano, secretario, etc.)
 * los asigna CargoPeriodoService al cargar o enviar cargos desde el analista CCDA.
 */
return [
    [
        'numero_personal' => '001',
        'rut'             => '20.111.222-3',
        'name'            => 'María Elena Rodríguez Mora',
        'email'           => 'maria.rodriguez@ucm.cl',
        'roles'           => ['academico'],
        'nomina'          => [
            'adscripcion_academica'  => 'Planta',
            'unidad_superior'        => 'Facultad de Ciencias de la Ingeniería',
            'unidad'                 => 'Decanatura FCI',
            'nombre_posicion'        => 'Decana de Facultad',
            'tipo_trabajador'        => 'Académico Jornada Completa',
            'fecha_inicio_contrato'  => '2008-03-01',
            'horas_contrato'         => 40,
            'categoria'              => 'titular',
            'fecha_categorizacion'   => '2012-03-15',
            'historial'              => [
                2024 => ['categoria' => 'titular', 'fecha_categorizacion' => '2012-03-15', 'nota' => 4.7, 'concepto' => 'Excelente'],
                2026 => ['categoria' => 'titular', 'fecha_categorizacion' => '2012-03-15'],
            ],
        ],
    ],
    [
        'numero_personal' => '002',
        'rut'             => '20.222.333-4',
        'name'            => 'Carlos Eduardo Fuentes Pinto',
        'email'           => 'carlos.fuentes@ucm.cl',
        'roles'           => ['academico'],
        'nomina'          => [
            'adscripcion_academica'  => 'Planta',
            'unidad_superior'        => 'Facultad de Ciencias de la Ingeniería',
            'unidad'                 => 'Secretaría de Facultad FCI',
            'nombre_posicion'        => 'Secretario de Facultad',
            'tipo_trabajador'        => 'Académico Media Jornada',
            'fecha_inicio_contrato'  => '2015-03-15',
            'horas_contrato'         => 24,
            'categoria'              => 'adjunto',
            'fecha_categorizacion'   => '2019-03-15',
            'historial'              => [
                2026 => ['categoria' => 'adjunto', 'fecha_categorizacion' => '2019-03-15'],
            ],
        ],
    ],
    [
        'numero_personal' => '005',
        'rut'             => '20.555.666-7',
        'name'            => 'Roberto Ignacio Jiménez Díaz',
        'email'           => 'roberto.jimenez@ucm.cl',
        'roles'           => ['academico'],
        'nomina'          => [
            'adscripcion_academica'  => 'Planta',
            'unidad_superior'        => 'Facultad de Ciencias de la Ingeniería',
            'unidad'                 => 'Depto. Computación e Informática',
            'nombre_posicion'        => 'Profesor Auxiliar',
            'tipo_trabajador'        => 'Académico Media Jornada',
            'fecha_inicio_contrato'  => '2020-08-01',
            'horas_contrato'         => 24,
            'categoria'              => 'auxiliar',
            'fecha_categorizacion'   => '2020-08-01',
            'historial'              => [
                2026 => ['categoria' => 'auxiliar', 'fecha_categorizacion' => '2020-08-01'],
            ],
        ],
    ],
    [
        'numero_personal' => '009',
        'rut'             => '20.999.000-1',
        'name'            => 'Gabriel Antonio Morales Cid',
        'email'           => 'gabriel.morales@ucm.cl',
        'roles'           => ['academico'],
        'nomina'          => [
            'adscripcion_academica'  => 'Planta',
            'unidad_superior'        => 'Facultad de Ciencias de la Ingeniería',
            'unidad'                 => 'Depto. Computación e Informática',
            'nombre_posicion'        => 'Profesor',
            'tipo_trabajador'        => 'Académico Media Jornada',
            'fecha_inicio_contrato'  => '2018-03-01',
            'horas_contrato'         => 24,
            'categoria'              => 'adjunto',
            'fecha_categorizacion'   => '2020-03-01',
            'historial'              => [
                2024 => ['categoria' => 'adjunto', 'fecha_categorizacion' => '2020-03-01', 'nota' => 3.6, 'concepto' => 'Bueno'],
                2026 => ['categoria' => 'adjunto', 'fecha_categorizacion' => '2020-03-01'],
            ],
        ],
    ],
    [
        'numero_personal' => '010',
        'rut'             => '21.000.111-2',
        'name'            => 'Claudia Fernanda Vega Soto',
        'email'           => 'claudia.vega@ucm.cl',
        'roles'           => ['academico'],
        'nomina'          => [
            'adscripcion_academica'  => 'Planta',
            'unidad_superior'        => 'Facultad de Ciencias de la Ingeniería',
            'unidad'                 => 'Depto. Matemáticas',
            'nombre_posicion'        => 'Profesora Titular',
            'tipo_trabajador'        => 'Académico Jornada Completa',
            'fecha_inicio_contrato'  => '2010-03-01',
            'horas_contrato'         => 40,
            'categoria'              => 'titular',
            'fecha_categorizacion'   => '2014-03-01',
            'historial'              => [
                2024 => ['categoria' => 'titular', 'fecha_categorizacion' => '2014-03-01', 'nota' => 4.8, 'concepto' => 'Excelente'],
                2026 => ['categoria' => 'titular', 'fecha_categorizacion' => '2014-03-01'],
            ],
        ],
    ],
    [
        'numero_personal' => '011',
        'rut'             => '21.111.222-3',
        'name'            => 'Diego Mauricio Espinoza Araya',
        'email'           => 'diego.espinoza@ucm.cl',
        'roles'           => ['academico'],
        'nomina'          => [
            'adscripcion_academica'  => 'Contrata',
            'unidad_superior'        => 'Facultad de Ciencias de la Ingeniería',
            'unidad'                 => 'Depto. Computación e Informática',
            'nombre_posicion'        => 'Profesor',
            'tipo_trabajador'        => 'Académico Media Jornada',
            'fecha_inicio_contrato'  => '2019-08-01',
            'horas_contrato'         => 24,
            'categoria'              => 'adjunto',
            'fecha_categorizacion'   => '2019-08-01',
            'historial'              => [
                2024 => ['categoria' => 'adjunto', 'fecha_categorizacion' => '2019-08-01', 'nota' => 3.2, 'concepto' => 'Regular'],
                2026 => ['categoria' => 'adjunto', 'fecha_categorizacion' => '2019-08-01'],
            ],
        ],
    ],
];
