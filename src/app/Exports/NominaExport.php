<?php

namespace App\Exports;

use App\Models\Nomina;
use App\Models\Periodo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NominaExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    public function __construct(
        private readonly ?Periodo $periodo,
        private readonly ?string  $facultadId = null,
        private readonly bool     $soloPlantilla = false,
    ) {}

    public function collection()
    {
        if ($this->soloPlantilla || !$this->periodo) {
            return collect();
        }

        return Nomina::with(['academico.facultad'])
            ->where('periodo_id', $this->periodo->id)
            ->when($this->facultadId, fn ($q) =>
                $q->whereHas('academico', fn ($q2) =>
                    $q2->where('facultad_id', $this->facultadId)
                )
            )
            ->orderBy('created_at')
            ->get()
            ->map(fn (Nomina $n) => [
                $n->numero_personal                             ?? '—',
                $n->rut   ?? $n->academico?->rut               ?? '—',
                $n->nombre ?? $n->academico?->name             ?? '—',
                $n->adscripcion_academica                       ?? '—',
                $n->unidad_superior ?? $n->academico?->facultad?->nombre ?? '—',
                $n->unidad                                      ?? '—',
                $n->nombre_posicion                             ?? '—',
                $n->tipo_trabajador                             ?? '—',
                $n->fecha_inicio_contrato?->format('d/m/Y')    ?? '—',
                $n->horas_contrato                              ?? '—',
                $n->categoria ?? $n->academico?->categoria_academica ?? '—',
                $n->fecha_categorizacion?->format('d/m/Y')     ?? '—',
                ucfirst($n->estado),
                $n->con_licencia ? 'Sí' : 'No',
                $n->observacion_licencia                        ?? '—',
            ]);
    }

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
            'Categoría 2025',
            'Fecha Categoría 2025',
            'Estado',
            'Con Licencia',
            'Observación Licencia',
        ];
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
            'A' => 16, 'B' => 20, 'C' => 30, 'D' => 22,
            'E' => 28, 'F' => 22, 'G' => 26, 'H' => 18,
            'I' => 22, 'J' => 14, 'K' => 16, 'L' => 20,
            'M' => 14, 'N' => 14, 'O' => 30,
        ];
    }
}
