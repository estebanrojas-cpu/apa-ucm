<?php

namespace App\Console\Commands;

use App\Exports\NominaCastDemoExport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class GenerarNominaDemoExcel extends Command
{
    protected $signature = 'nomina:generar-demo-excel {--path=fixtures/nomina_prueba_sapd.xlsx}';

    protected $description = 'Genera el Excel SAPD de prueba (mismo cast que PeriodoBaseSeeder)';

    public function handle(): int
    {
        $relative = $this->option('path');
        $fullPath = base_path($relative);
        $dir      = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $fullPath,
            Excel::raw(new NominaCastDemoExport(), \Maatwebsite\Excel\Excel::XLSX)
        );

        $this->info("✓ Excel generado: {$relative}");
        $this->line('  Importar desde analista → Período → Nómina → subir archivo SAPD.');
        $this->line('  Mapear «email_ucm» como columna adicional si deseas conservar el correo demo.');

        return self::SUCCESS;
    }
}
