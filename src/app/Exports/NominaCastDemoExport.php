<?php

namespace App\Exports;

use Carbon\Carbon;
use Database\Seeders\Concerns\CastHorasContrato;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel SAPD con el mismo cast que PeriodoBaseSeeder (FCI + FCAF).
 * Sirve para probar la importación desde analista → Nómina → subir archivo.
 */
class NominaCastDemoExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    use CastHorasContrato;

    public function headings(): array
    {
        return [
            'N° Personal',
            'Cédula de Identidad',
            'Nombre del Trabajador',
            'Adscripción Académica',
            'Unidad Superior',
            'Unidad',
            'Nombre de la Posición',
            'Tipo de Trabajador',
            'Fecha de Inicio de Contrato',
            'Horas de Contrato',
            'Categoría 2026',
            'Fecha Categoría 2026',
            'Calificación 2024',
            'Concepto 2024',
            'Calificación 2025',
            'Concepto 2025',
            'email_ucm',
        ];
    }

    public function array(): array
    {
        $filas = [];

        foreach ($this->personasCast() as $persona) {
            $nomina    = $persona['nomina'];
            $historial = $nomina['historial'] ?? [];

            $filas[] = [
                $persona['numero_personal'],
                $persona['rut'],
                $persona['name'],
                $nomina['adscripcion_academica'] ?? '',
                $nomina['unidad_superior'] ?? '',
                $nomina['unidad'] ?? '',
                $nomina['nombre_posicion'] ?? '',
                $nomina['tipo_trabajador'] ?? '',
                $this->fechaExcel($nomina['fecha_inicio_contrato'] ?? null),
                $nomina['horas_contrato'] ?? self::horasContratoDemo($nomina['tipo_trabajador'] ?? null),
                $nomina['categoria'] ?? '',
                $this->fechaExcel($nomina['fecha_categorizacion'] ?? null),
                $historial[2024]['nota'] ?? '',
                $historial[2024]['concepto'] ?? '',
                $historial[2025]['nota'] ?? '',
                $historial[2025]['concepto'] ?? '',
                $persona['email'] ?? '',
            ];
        }

        return $filas;
    }

    /** @return list<array<string, mixed>> */
    private function personasCast(): array
    {
        $casts = [
            require base_path('database/seeders/data/fci_cast_2026.php'),
            require base_path('database/seeders/data/fcaf_cast_2026.php'),
        ];

        return array_merge(...$casts);
    }

    private function fechaExcel(?string $fecha): string
    {
        if (!$fecha) {
            return '';
        }

        try {
            return Carbon::parse($fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return $fecha;
        }
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 18, 'C' => 32, 'D' => 20, 'E' => 36,
            'F' => 28, 'G' => 26, 'H' => 22, 'I' => 22, 'J' => 16,
            'K' => 16, 'L' => 18, 'M' => 16, 'N' => 16, 'O' => 16,
            'P' => 16, 'Q' => 28,
        ];
    }
}
