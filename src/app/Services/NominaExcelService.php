<?php

namespace App\Services;

use App\Models\Facultad;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class NominaExcelService
{
    public const COLUMNAS_BASE = [
        'rut', 'nombre', 'facultad', 'categoria', 'horas_contrato',
    ];

    /** @var array<string, string> */
    public const ALIAS = [
        'rut'               => 'rut',
        'nombre'            => 'nombre',
        'facultad'          => 'facultad',
        'categoría'         => 'categoria',
        'categoria'         => 'categoria',
        'horas de contrato' => 'horas_contrato',
        'horas_contrato'    => 'horas_contrato',
    ];

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function parsearFilas(array $rows): array
    {
        if (count($rows) < 2) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no contiene datos.',
            ]);
        }

        $headers = array_map(fn ($h) => $this->normalizarHeader((string) $h), $rows[0]);
        $parsed  = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if ($this->filaVacia($row)) {
                continue;
            }

            $item = ['_row' => $i + 1, 'datos_adicionales' => []];

            foreach ($headers as $col => $key) {
                $valor = $row[$col] ?? null;
                $headerRaw = trim((string) ($rows[0][$col] ?? ''));

                if ($key === null) {
                    if ($valor !== null && $valor !== '' && $headerRaw !== '') {
                        $item['datos_adicionales'][$headerRaw] = $valor;
                    }
                    continue;
                }

                $item[$key] = is_string($valor) ? trim($valor) : $valor;
            }

            $parsed[] = $this->normalizarFila($item);
        }

        return $parsed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     * @return array<int, array<string, mixed>>
     */
    public function validarFilas(array $filas): array
    {
        $rutsVistos = [];

        return collect($filas)->map(function (array $fila) use (&$rutsVistos) {
            $errores = [];
            $rut     = $this->normalizarRut($fila['rut'] ?? '');

            if ($rut === '') {
                $errores[] = 'RUT obligatorio';
            } elseif (isset($rutsVistos[$rut])) {
                $errores[] = 'RUT duplicado en la grilla';
            } else {
                $rutsVistos[$rut] = true;
            }

            if (empty($fila['nombre'])) {
                $errores[] = 'Nombre obligatorio';
            }

            $categoria = $this->normalizarCategoria($fila['categoria'] ?? '');
            if ($categoria === null) {
                $errores[] = 'Categoría inválida (Auxiliar/Adjunto/Titular)';
            }

            $user = $rut ? User::activos()->deRol('academico')->where('rut', $rut)->first() : null;
            if ($rut && !$user) {
                $errores[] = 'RUT no encontrado en el sistema';
            }

            $facultad = null;
            if (!empty($fila['facultad'])) {
                $facultad = Facultad::whereRaw('LOWER(nombre) = ?', [mb_strtolower($fila['facultad'])])->first();
                if (!$facultad) {
                    $errores[] = 'Facultad no reconocida';
                }
            }

            return array_merge($fila, [
                'rut'            => $rut,
                'categoria'      => $categoria,
                'user_id'        => $user?->id,
                'facultad_id'    => $facultad?->id ?? $user?->facultad_id,
                'facultad_nombre'=> $facultad?->nombre ?? $fila['facultad'] ?? $user?->facultad?->nombre,
                'errores'        => $errores,
                'valido'         => empty($errores),
            ]);
        })->all();
    }

    private function normalizarHeader(string $header): ?string
    {
        $key = mb_strtolower(trim($header));

        return self::ALIAS[$key] ?? null;
    }

    private function filaVacia(array $row): bool
    {
        return collect($row)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizarFila(array $item): array
    {
        if (isset($item['horas_contrato']) && $item['horas_contrato'] !== '') {
            $item['horas_contrato'] = (int) $item['horas_contrato'];
        }

        return $item;
    }

    private function normalizarRut(string $rut): string
    {
        return strtoupper(preg_replace('/[.\s-]/', '', $rut));
    }

    private function normalizarCategoria(?string $cat): ?string
    {
        if (!$cat) {
            return null;
        }

        $c = mb_strtolower(trim($cat));

        return match ($c) {
            'auxiliar' => 'auxiliar',
            'adjunto'  => 'adjunto',
            'titular'  => 'titular',
            default    => null,
        };
    }
}
