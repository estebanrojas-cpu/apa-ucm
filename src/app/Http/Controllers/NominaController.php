<?php

namespace App\Http\Controllers;

use App\Exports\NominaExport;
use App\Http\Requests\StoreNominaRequest;
use App\Models\Facultad;
use App\Models\HistorialCalificacion;
use App\Models\HistorialCategoria;
use App\Models\Nomina;
use App\Models\Periodo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class NominaController extends Controller
{
    // ── Mapeo canónico de encabezados SAPD → campo de nómina ─────────────────
    private const HEADER_MAP = [
        'n_personal'                  => 'numero_personal',
        'numero_personal'             => 'numero_personal',
        'cedula_de_identidad'         => 'rut',
        'rut'                         => 'rut',
        'nombre_del_trabajador'       => 'nombre',
        'nombre'                      => 'nombre',
        'adscripcion_academica'       => 'adscripcion_academica',
        'unidad_superior'             => 'unidad_superior',
        'unidad'                      => 'unidad',
        'nombre_de_la_posicion'       => 'nombre_posicion',
        'nombre_posicion'             => 'nombre_posicion',
        'tipo_de_trabajador'          => 'tipo_trabajador',
        'tipo_trabajador'             => 'tipo_trabajador',
        'fecha_de_inicio_de_contrato' => 'fecha_inicio_contrato',
        'fecha_inicio_contrato'       => 'fecha_inicio_contrato',
        'horas_de_contrato'           => 'horas_contrato',
        'horas_contrato'              => 'horas_contrato',
    ];

    private function normalizeHeader(string $h): string
    {
        $h = mb_strtolower(trim($h));
        $h = strtr($h, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n',
        ]);
        $h = preg_replace('/[°\.\s\/]+/', '_', $h);
        $h = preg_replace('/_+/', '_', $h);
        return trim($h, '_');
    }

    /**
     * Analiza encabezados y devuelve:
     *   'campos'    → campo_nomina => índice columna (auto-detectado)
     *   'historial' → año => ['calificacion'=>idx, 'concepto'=>idx, ...]
     *   'sin_mapear' → [índice => encabezado original]
     */
    private function detectarMapeo(array $headers): array
    {
        $campos    = [];
        $historial = [];
        $sinMapear = [];
        $maxAnioCat = null;

        foreach ($headers as $idx => $raw) {
            $norm = $this->normalizeHeader($raw);

            if (isset(self::HEADER_MAP[$norm])) {
                $campo = self::HEADER_MAP[$norm];
                if (!isset($campos[$campo])) {
                    $campos[$campo] = $idx;
                }
                continue;
            }

            if (preg_match('/^categoria_(\d{4})$/', $norm, $m)) {
                $anio = (int) $m[1];
                $historial[$anio]['categoria'] = $idx;
                if ($maxAnioCat === null || $anio > $maxAnioCat) {
                    $maxAnioCat = $anio;
                    $campos['categoria'] = $idx;
                }
                continue;
            }
            if (preg_match('/^fecha_categoria_(\d{4})$/', $norm, $m)) {
                $anio = (int) $m[1];
                $historial[$anio]['fecha_categorizacion'] = $idx;
                if ($anio === $maxAnioCat) {
                    $campos['fecha_categorizacion'] = $idx;
                }
                continue;
            }
            if (preg_match('/^calificacion_(\d{4})$/', $norm, $m)) {
                $historial[(int) $m[1]]['calificacion'] = $idx;
                continue;
            }
            if (preg_match('/^concepto(?:_resultado)?_(\d{4})$/', $norm, $m)) {
                $historial[(int) $m[1]]['concepto'] = $idx;
                continue;
            }
            if (preg_match('/^observacion_calificacion_(\d{4})$/', $norm, $m)) {
                $historial[(int) $m[1]]['observacion'] = $idx;
                continue;
            }
            if (preg_match('/^resumen_(?:evaluacion|calificacion)_(\d{4})$/', $norm, $m)) {
                $historial[(int) $m[1]]['resumen'] = $idx;
                continue;
            }
            if (preg_match('/^proceso_(?:de_)?calificacion_(\d{4})$/', $norm, $m)) {
                $historial[(int) $m[1]]['proceso'] = $idx;
                continue;
            }

            if ($raw !== '') {
                $sinMapear[$idx] = $raw;
            }
        }

        return compact('campos', 'historial', 'sinMapear');
    }

    // ── Vista principal de nómina ─────────────────────────────────────────────

    public function create(Periodo $periodo): Response
    {
        $facultades = Facultad::orderBy('nombre')->get(['id', 'nombre', 'codigo']);

        $academicos = User::activos()
            ->deRol('academico')
            ->whereNotNull('facultad_id')
            ->orderBy('name')
            ->get(['id', 'name', 'rut', 'facultad_id']);

        $nominasEnPeriodo = Nomina::where('periodo_id', $periodo->id)
            ->get([
                'id', 'user_id', 'estado', 'con_licencia', 'observacion_licencia', 'updated_at',
                'numero_personal', 'rut', 'nombre', 'unidad_superior', 'unidad',
                'nombre_posicion', 'tipo_trabajador', 'fecha_inicio_contrato',
                'horas_contrato', 'categoria', 'fecha_categorizacion',
            ]);

        return Inertia::render('Nomina/Create', [
            'periodo'          => $periodo->only(['id', 'anio', 'nombre', 'estado']),
            'facultades'       => $facultades,
            'academicos'       => $academicos,
            'nominasEnPeriodo' => $nominasEnPeriodo,
        ]);
    }

    // ── Agregar académicos seleccionando manualmente ──────────────────────────

    public function store(StoreNominaRequest $request, Periodo $periodo)
    {
        $userIds = $request->validated()['user_ids'];

        $yaEnNomina = Nomina::where('periodo_id', $periodo->id)
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->all();

        $nuevos = array_values(array_diff($userIds, $yaEnNomina));

        if (empty($nuevos)) {
            return back()->with('error', 'Todos los académicos seleccionados ya están en la nómina.');
        }

        $now = now();
        DB::table('nominas')->insert(array_map(fn ($uid) => [
            'id'           => (string) Str::uuid(),
            'periodo_id'   => $periodo->id,
            'user_id'      => $uid,
            'estado'       => 'pendiente',
            'con_licencia' => false,
            'created_at'   => $now,
            'updated_at'   => $now,
        ], $nuevos));

        $agregados = count($nuevos);
        $omitidos  = count($userIds) - $agregados;
        $msg = "{$agregados} académico(s) agregado(s) a la nómina.";
        if ($omitidos > 0) {
            $msg .= " {$omitidos} ya estaba(n) en la nómina y fue(ron) omitido(s).";
        }

        return redirect()
            ->route('analista.periodos.nominas.create', $periodo->id)
            ->with('success', $msg);
    }

    // ── Excel preview: encabezados + auto-mapeo ──────────────────────────────

    public function previewExcel(Request $request, Periodo $periodo)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [
            'archivo.mimes' => 'Solo se aceptan archivos .xlsx, .xls o .csv.',
            'archivo.max'   => 'El archivo no puede superar los 5 MB.',
        ]);

        $path     = $request->file('archivo')->store('tmp_nominas');
        $fullPath = storage_path('app/' . $path);

        try {
            $spreadsheet = IOFactory::load($fullPath);
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = [];

            foreach ($sheet->getRowIterator(1, 6) as $row) {
                $cells = [];
                foreach ($row->getCellIterator() as $cell) {
                    $cells[] = (string) $cell->getValue();
                }
                while (count($cells) > 0 && $cells[array_key_last($cells)] === '') {
                    array_pop($cells);
                }
                if (!empty(array_filter($cells))) {
                    $rows[] = $cells;
                }
            }
        } catch (\Throwable $e) {
            unlink($fullPath);
            return back()->withErrors(['archivo' => 'No se pudo leer el archivo: ' . $e->getMessage()]);
        }

        if (empty($rows)) {
            unlink($fullPath);
            return back()->withErrors(['archivo' => 'El archivo está vacío.']);
        }

        $mapeoDetectado = $this->detectarMapeo($rows[0]);

        session(['nomina_excel_path' => $path, 'nomina_excel_periodo' => $periodo->id]);

        return back()->with([
            'excel_preview' => [
                'columnas'     => $rows[0],
                'preview_rows' => array_slice($rows, 1, 4),
                'path'         => $path,
                'auto_mapeo'   => $mapeoDetectado['campos'],
                'sin_mapear'   => $mapeoDetectado['sinMapear'],
            ],
        ]);
    }

    // ── Importación con mapeo + historiales ──────────────────────────────────

    public function importarExcel(Request $request, Periodo $periodo)
    {
        $data = $request->validate([
            'path'                => ['required', 'string'],
            'tiene_encabezado'    => ['boolean'],
            'mapeo'               => ['required', 'array'],
            'mapeo.*'             => ['nullable', 'integer', 'min:0'],
            'datos_adicionales'   => ['nullable', 'array'],
            'datos_adicionales.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $fullPath = storage_path('app/' . $data['path']);

        if (!file_exists($fullPath)) {
            return back()->withErrors(['path' => 'El archivo ya no existe. Vuelve a subirlo.']);
        }

        try {
            $spreadsheet = IOFactory::load($fullPath);
            $sheet       = $spreadsheet->getActiveSheet();
            $allRows     = [];
            foreach ($sheet->getRowIterator() as $row) {
                $cells = [];
                foreach ($row->getCellIterator() as $cell) {
                    $cells[] = (string) $cell->getValue();
                }
                $allRows[] = $cells;
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['path' => 'Error al leer el archivo: ' . $e->getMessage()]);
        }

        $headers        = $data['tiene_encabezado'] ? ($allRows[0] ?? []) : [];
        $mapeoHistorial = $headers ? $this->detectarMapeo($headers)['historial'] : [];

        if ($data['tiene_encabezado']) {
            array_shift($allRows);
        }

        $mapeo           = $data['mapeo'];
        $colsAdicionales = $data['datos_adicionales'] ?? [];
        $creados = $omitidos = $errores = 0;
        $detalleErrores = [];

        foreach ($allRows as $i => $cells) {
            $fila   = $i + ($data['tiene_encabezado'] ? 2 : 1);
            $rut    = trim($cells[$mapeo['rut']    ?? -1] ?? '');
            $nombre = trim($cells[$mapeo['nombre'] ?? -1] ?? '');

            if (!$rut || !$nombre) {
                $errores++;
                $detalleErrores[] = "Fila {$fila}: RUT o Nombre vacío.";
                continue;
            }

            // Campos SAPD de texto directo
            $camposNomina = ['rut' => $rut, 'nombre' => $nombre];
            foreach ([
                'numero_personal', 'adscripcion_academica', 'unidad_superior', 'unidad',
                'nombre_posicion', 'tipo_trabajador', 'categoria',
            ] as $campo) {
                if (isset($mapeo[$campo]) && $mapeo[$campo] !== null) {
                    $val = trim($cells[$mapeo[$campo]] ?? '');
                    $camposNomina[$campo] = $val !== '' ? $val : null;
                }
            }

            // Fechas
            foreach (['fecha_inicio_contrato', 'fecha_categorizacion'] as $campo) {
                if (isset($mapeo[$campo]) && $mapeo[$campo] !== null) {
                    $val = trim($cells[$mapeo[$campo]] ?? '');
                    $camposNomina[$campo] = $val !== '' ? $this->parseFecha($val) : null;
                }
            }

            // Horas numéricas
            if (isset($mapeo['horas_contrato']) && $mapeo['horas_contrato'] !== null) {
                $val = trim($cells[$mapeo['horas_contrato']] ?? '');
                $camposNomina['horas_contrato'] = is_numeric($val) ? (int) $val : null;
            }

            // Columnas sin mapear → datos_adicionales
            $adicionales = [];
            foreach ($colsAdicionales as $label => $colIdx) {
                if ($colIdx !== null && isset($cells[$colIdx])) {
                    $adicionales[$label] = $cells[$colIdx];
                }
            }
            if ($adicionales) {
                $camposNomina['datos_adicionales'] = $adicionales;
            }

            // Crear o enlazar usuario
            $user = User::where('rut', $rut)->first();

            if (!$user) {
                $emailBase = Str::slug($nombre, '.') . '@ucm.cl';
                $email     = User::where('email', $emailBase)->exists()
                    ? Str::slug($nombre, '.') . '.' . Str::random(4) . '@ucm.cl'
                    : $emailBase;

                $user = User::create([
                    'name'     => $nombre,
                    'rut'      => $rut,
                    'email'    => $email,
                    'password' => Hash::make(Str::random(16)),
                    'role'     => 'academico',
                ]);
            }

            // Crear o actualizar nómina
            $nomina = Nomina::where('periodo_id', $periodo->id)
                ->where('user_id', $user->id)
                ->first();

            if ($nomina) {
                $nomina->update($camposNomina);
                $omitidos++;
            } else {
                $nomina = Nomina::create(array_merge($camposNomina, [
                    'periodo_id'   => $periodo->id,
                    'user_id'      => $user->id,
                    'estado'       => 'pendiente',
                    'con_licencia' => false,
                ]));
                $creados++;
            }

            $this->poblarHistoriales($nomina, $cells, $mapeoHistorial);
        }

        @unlink($fullPath);

        $msg = "{$creados} académico(s) importado(s).";
        if ($omitidos) { $msg .= " {$omitidos} ya estaban en la nómina (datos actualizados)."; }
        if ($errores)  { $msg .= " {$errores} fila(s) con errores omitidas."; }

        return redirect()
            ->route('analista.periodos.nominas.create', $periodo->id)
            ->with('success', $msg)
            ->with('import_errores', $detalleErrores);
    }

    private function poblarHistoriales(Nomina $nomina, array $cells, array $mapeoHistorial): void
    {
        foreach ($mapeoHistorial as $anio => $cols) {
            if (isset($cols['calificacion']) || isset($cols['concepto'])) {
                $nota     = isset($cols['calificacion']) ? trim($cells[$cols['calificacion']] ?? '') : null;
                $concepto = isset($cols['concepto'])     ? trim($cells[$cols['concepto']]     ?? '') : null;
                $obs      = isset($cols['observacion'])  ? trim($cells[$cols['observacion']]  ?? '') : null;
                $resumen  = isset($cols['resumen'])      ? trim($cells[$cols['resumen']]      ?? '') : null;
                $proceso  = isset($cols['proceso'])      ? trim($cells[$cols['proceso']]      ?? '') : null;

                HistorialCalificacion::updateOrCreate(
                    ['nomina_id' => $nomina->id, 'anio' => $anio],
                    [
                        'nota'        => is_numeric($nota) ? (float) $nota : null,
                        'concepto'    => $concepto ?: null,
                        'observacion' => $obs      ?: null,
                        'resumen'     => $resumen  ?: null,
                        'proceso'     => $proceso  ?: null,
                    ]
                );
            }

            if (isset($cols['categoria'])) {
                $cat   = trim($cells[$cols['categoria']] ?? '');
                $fecha = isset($cols['fecha_categorizacion'])
                    ? $this->parseFecha(trim($cells[$cols['fecha_categorizacion']] ?? ''))
                    : null;

                if ($cat) {
                    HistorialCategoria::updateOrCreate(
                        ['nomina_id' => $nomina->id, 'anio' => $anio],
                        ['categoria' => $cat, 'fecha_categorizacion' => $fecha]
                    );
                }
            }
        }
    }

    private function parseFecha(string $val): ?string
    {
        if (!$val) {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $val);
            if ($d && $d->format($fmt) === $val) {
                return $d->format('Y-m-d');
            }
        }
        try {
            return Carbon::parse($val)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Agregar académico individual ──────────────────────────────────────────

    public function agregarIndividual(Request $request, Periodo $periodo)
    {
        $data = $request->validate([
            'rut'             => ['required', 'string', 'max:20'],
            'nombre'          => ['required', 'string', 'max:200'],
            'facultad_id'     => ['nullable', 'uuid', 'exists:facultades,id'],
            'categoria'       => ['nullable', 'in:auxiliar,adjunto,titular,jerarquizado'],
            'tipo_trabajador' => ['nullable', 'string', 'max:50'],
            'unidad_superior' => ['nullable', 'string', 'max:150'],
            'unidad'          => ['nullable', 'string', 'max:150'],
            'horas_contrato'  => ['nullable', 'integer', 'min:0'],
        ]);

        $user = User::where('rut', $data['rut'])->first();

        if (!$user) {
            $emailBase = Str::slug($data['nombre'], '.') . '@ucm.cl';
            $email     = User::where('email', $emailBase)->exists()
                ? Str::slug($data['nombre'], '.') . '.' . Str::random(4) . '@ucm.cl'
                : $emailBase;

            $user = User::create([
                'name'        => $data['nombre'],
                'rut'         => $data['rut'],
                'email'       => $email,
                'password'    => Hash::make(Str::random(16)),
                'role'        => 'academico',
                'facultad_id' => $data['facultad_id'] ?? null,
            ]);
        }

        $yaEsta = Nomina::where('periodo_id', $periodo->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($yaEsta) {
            return back()->with('error', "{$user->name} ya está en la nómina de este período.");
        }

        Nomina::create([
            'periodo_id'      => $periodo->id,
            'user_id'         => $user->id,
            'estado'          => 'pendiente',
            'con_licencia'    => false,
            'rut'             => $data['rut'],
            'nombre'          => $data['nombre'],
            'categoria'       => $data['categoria']       ?? null,
            'tipo_trabajador' => $data['tipo_trabajador'] ?? null,
            'unidad_superior' => $data['unidad_superior'] ?? null,
            'unidad'          => $data['unidad']          ?? null,
            'horas_contrato'  => $data['horas_contrato']  ?? null,
        ]);

        return redirect()
            ->route('analista.periodos.nominas.create', $periodo->id)
            ->with('success', "{$user->name} agregado a la nómina.");
    }

    // ── Detalle de un académico en la nómina ──────────────────────────────────

    public function detalle(Periodo $periodo, Nomina $nomina): Response
    {
        $nomina->load(['academico', 'historialCalificaciones', 'historialCategorias']);

        return Inertia::render('Nomina/Detalle', [
            'periodo' => $periodo->only(['id', 'anio', 'nombre']),
            'nomina'  => [
                'id'                    => $nomina->id,
                'numero_personal'       => $nomina->numero_personal,
                'rut'                   => $nomina->rut ?? $nomina->academico?->rut,
                'nombre'                => $nomina->nombre ?? $nomina->academico?->name,
                'adscripcion_academica' => $nomina->adscripcion_academica,
                'unidad_superior'       => $nomina->unidad_superior,
                'unidad'                => $nomina->unidad,
                'nombre_posicion'       => $nomina->nombre_posicion,
                'tipo_trabajador'       => $nomina->tipo_trabajador,
                'fecha_inicio_contrato' => $nomina->fecha_inicio_contrato?->format('d/m/Y'),
                'horas_contrato'        => $nomina->horas_contrato,
                'categoria'             => $nomina->categoria,
                'fecha_categorizacion'  => $nomina->fecha_categorizacion?->format('d/m/Y'),
                'estado'                => $nomina->estado,
                'nota_vigente'          => $nomina->notaAnterior(),
                'nota_vigente_activa'   => $nomina->notaVigente(),
                'vencimiento_nota'      => $nomina->fechaVencimientoNota()?->format('d/m/Y'),
            ],
            'historial_calificaciones' => $nomina->historialCalificaciones->map(fn ($h) => [
                'anio'        => $h->anio,
                'nota'        => $h->nota,
                'concepto'    => $h->concepto,
                'observacion' => $h->observacion,
                'resumen'     => $h->resumen,
                'proceso'     => $h->proceso,
            ]),
            'historial_categorias' => $nomina->historialCategorias->map(fn ($h) => [
                'anio'                 => $h->anio,
                'categoria'            => $h->categoria,
                'fecha_categorizacion' => $h->fecha_categorizacion?->format('d/m/Y'),
            ]),
        ]);
    }

    // ── Exportar nómina a Excel ───────────────────────────────────────────────

    public function exportar(Request $request, Periodo $periodo)
    {
        $facultadId = $request->query('facultad_id');

        $codigoFacultad = $facultadId
            ? (Facultad::find($facultadId)?->codigo ?? 'TODAS')
            : 'TODAS';

        $filename = 'nomina_' . $codigoFacultad . '_' . $periodo->anio . '.xlsx';

        return Excel::download(
            new NominaExport($periodo, $facultadId ?: null),
            $filename
        );
    }

    // ── Descargar plantilla UCM vacía ─────────────────────────────────────────

    public function plantilla()
    {
        return Excel::download(new NominaExport(null, null, true), 'plantilla_nomina_ucm.xlsx');
    }

    // ── Caso especial (licencia) ──────────────────────────────────────────────

    public function toggleLicencia(Request $request, Nomina $nomina)
    {
        $data = $request->validate([
            'con_licencia'         => ['required', 'boolean'],
            'observacion_licencia' => ['nullable', 'string', 'max:500', 'required_if:con_licencia,true'],
        ], [
            'observacion_licencia.required_if' => 'El motivo del caso especial es obligatorio.',
        ]);

        $nomina->update([
            'con_licencia'         => $data['con_licencia'],
            'observacion_licencia' => $data['con_licencia'] ? $data['observacion_licencia'] : null,
        ]);

        return back()->with('success', $data['con_licencia']
            ? 'Caso especial registrado correctamente.'
            : 'Caso especial removido.');
    }
}
