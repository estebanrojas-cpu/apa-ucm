#!/usr/bin/env php
<?php

/**
 * Genera fixtures/nomina_prueba_sapd.xlsx (mismo cast que PeriodoBaseSeeder).
 * Uso: php generar_nomina_demo.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Exports\NominaCastDemoExport;
use Maatwebsite\Excel\Facades\Excel;

$path = __DIR__ . '/fixtures/nomina_prueba_sapd.xlsx';

if (!is_dir(dirname($path))) {
    mkdir(dirname($path), 0755, true);
}

file_put_contents($path, Excel::raw(new NominaCastDemoExport(), \Maatwebsite\Excel\Excel::XLSX));

echo "✓ Generado: {$path}\n";
