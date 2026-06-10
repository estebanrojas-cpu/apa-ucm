<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class NominaPlantillaExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'RUT', 'Nombre', 'Facultad', 'Categoría', 'Horas de contrato',
        ];
    }

    public function array(): array
    {
        return [];
    }
}
