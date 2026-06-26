<?php

/**
 * Genera fixtures/nomina_prueba_sapd.csv sin bootstrap Laravel (solo PHP).
 * Uso: php fixtures/build_nomina_csv.php
 */

$personas = array_merge(
    require __DIR__ . '/../database/seeders/data/fci_cast_2026.php',
    require __DIR__ . '/../database/seeders/data/fcaf_cast_2026.php',
);

$headers = [
    'N° Personal', 'Cédula de Identidad', 'Nombre del Trabajador', 'Adscripción Académica',
    'Unidad Superior', 'Unidad', 'Nombre de la Posición', 'Tipo de Trabajador',
    'Fecha de Inicio de Contrato', 'Horas de Contrato', 'Categoría 2026', 'Fecha Categoría 2026',
    'Calificación 2024', 'Concepto 2024', 'Calificación 2025', 'Concepto 2025', 'email_ucm',
];

$out = fopen(__DIR__ . '/nomina_prueba_sapd.csv', 'w');
fprintf($out, "\xEF\xBB\xBF");
fputcsv($out, $headers);

foreach ($personas as $p) {
    $n = $p['nomina'];
    $h = $n['historial'] ?? [];

    fputcsv($out, [
        $p['numero_personal'],
        $p['rut'],
        $p['name'],
        $n['adscripcion_academica'] ?? '',
        $n['unidad_superior'] ?? '',
        $n['unidad'] ?? '',
        $n['nombre_posicion'] ?? '',
        $n['tipo_trabajador'] ?? '',
        isset($n['fecha_inicio_contrato']) ? date('d/m/Y', strtotime($n['fecha_inicio_contrato'])) : '',
        $n['horas_contrato'] ?? '',
        $n['categoria'] ?? '',
        isset($n['fecha_categorizacion']) ? date('d/m/Y', strtotime($n['fecha_categorizacion'])) : '',
        $h[2024]['nota'] ?? '',
        $h[2024]['concepto'] ?? '',
        $h[2025]['nota'] ?? '',
        $h[2025]['concepto'] ?? '',
        $p['email'] ?? '',
    ]);
}

fclose($out);
echo "✓ fixtures/nomina_prueba_sapd.csv (" . count($personas) . " filas)\n";
