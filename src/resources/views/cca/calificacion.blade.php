<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de Calificación — {{ $academico->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #111;
            background: #fff;
            padding: 28px 36px;
        }

        /* ── Botón imprimir ─────────────────────── */
        .btn-print {
            display: inline-block;
            background: #1B2D6B;
            color: #fff;
            padding: 7px 18px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            margin-bottom: 20px;
        }
        @media print {
            .btn-print { display: none; }
            body { padding: 10px 18px; }
        }

        /* ── Cabecera ───────────────────────────── */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #1B2D6B;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header h1 {
            font-size: 14px;
            font-weight: bold;
            color: #1B2D6B;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .header p { font-size: 10px; color: #555; margin-top: 3px; }
        .header-meta { text-align: right; font-size: 10px; color: #444; line-height: 1.6; }

        .badge-apelacion {
            display: inline-block;
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            border-radius: 3px;
            padding: 1px 7px;
            font-size: 9px;
            font-weight: bold;
            vertical-align: middle;
            margin-left: 6px;
        }

        /* ── Grilla de datos académicos ─────────── */
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            border: 1px solid #aaa;
            margin-bottom: 12px;
        }
        .profile-cell {
            border: 1px solid #bbb;
            padding: 0;
        }
        .profile-cell .cell-label {
            background: #1B2D6B;
            color: #fff;
            font-size: 9px;
            font-weight: bold;
            text-align: center;
            padding: 3px 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .profile-cell .cell-value {
            text-align: center;
            padding: 4px 4px;
            font-size: 11px;
        }
        .profile-cell.span2 { grid-column: span 2; }
        .profile-cell.span4 { grid-column: span 4; }

        /* ── Sección reglamento ─────────────────── */
        .reglamento-box {
            border: 1px solid #1B2D6B;
            margin-bottom: 12px;
        }
        .reglamento-box .reg-title {
            background: #1B2D6B;
            color: #fff;
            font-size: 10px;
            font-weight: bold;
            text-align: center;
            padding: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .reglamento-box .reg-body {
            padding: 7px 10px;
            font-size: 10px;
            line-height: 1.55;
            color: #222;
            font-style: italic;
        }

        /* ── Títulos de sección ─────────────────── */
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #1B2D6B;
            border-bottom: 1px solid #1B2D6B;
            padding-bottom: 3px;
            margin-bottom: 8px;
            margin-top: 14px;
        }

        /* ── Tabla de áreas ─────────────────────── */
        .table-areas {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-bottom: 6px;
        }
        .table-areas th {
            background: #1B2D6B;
            color: #fff;
            border: 1px solid #1B2D6B;
            padding: 4px 6px;
            text-align: center;
            font-weight: bold;
        }
        .table-areas th.left { text-align: left; }
        .table-areas td {
            border: 1px solid #ccc;
            padding: 4px 6px;
            text-align: center;
            color: #333;
        }
        .table-areas td.left { text-align: left; }
        .table-areas tr:nth-child(even) td { background: #f5f7ff; }
        .table-areas .row-final td {
            background: #e8ecf8;
            font-weight: bold;
            color: #1B2D6B;
        }
        .table-areas .col-num { width: 24px; }
        .table-areas .col-area { width: auto; }
        .table-areas .col-hrs  { width: 52px; }
        .table-areas .col-pct  { width: 52px; }
        .table-areas .col-nota { width: 44px; }
        .table-areas .col-conc { width: 70px; }
        .table-areas .col-pond { width: 60px; }

        .table-note {
            font-size: 9px;
            color: #555;
            margin-top: 3px;
            font-style: italic;
        }

        /* ── Interpretación / Retroalimentación ─── */
        .interp-box {
            border-left: 3px solid #1B2D6B;
            background: #f5f7ff;
            padding: 8px 12px;
            font-size: 10.5px;
            line-height: 1.6;
            color: #222;
            margin-bottom: 8px;
        }
        .interp-box strong { color: #1B2D6B; }

        .retro-box {
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 10.5px;
            line-height: 1.6;
            color: #333;
            background: #fafafa;
            margin-bottom: 8px;
        }

        .eval-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-top: 6px;
        }
        .eval-table th {
            background: #e8ecf8;
            border: 1px solid #bbb;
            padding: 3px 6px;
            text-align: left;
            color: #1B2D6B;
            font-weight: bold;
        }
        .eval-table td {
            border: 1px solid #ddd;
            padding: 3px 6px;
            color: #444;
        }

        /* ── Firmas ─────────────────────────────── */
        .firma-section {
            display: flex;
            flex-wrap: wrap;
            gap: 30px 24px;
            margin-top: 55px;
        }
        .firma-box { flex: 1 1 calc(33% - 24px); min-width: 130px; text-align: center; }
        .firma-space { height: 36px; }
        .firma-line { border-top: 1px solid #444; margin-bottom: 5px; }
        .firma-name { font-size: 10px; color: #111; font-weight: bold; margin-bottom: 2px; min-height: 13px; }
        .firma-label { font-size: 9px; color: #555; line-height: 1.4; }
    </style>
</head>
<body>

<button class="btn-print" onclick="window.print()">Imprimir / Guardar como PDF</button>

{{-- ── CABECERA ────────────────────────────────────────── --}}
<div class="header">
    <div>
        <h1>
            Informe de Calificación Académica
            @if ($calificacion->es_apelacion)
                <span class="badge-apelacion">Apelación</span>
            @endif
        </h1>
        <p>Universidad Católica del Maule — Comisión Calificadora Académica (CCA)</p>
    </div>
    <div class="header-meta">
        <div><strong>Período:</strong> {{ $periodo->nombre }} {{ $periodo->anio }}</div>
        <div><strong>Fecha de emisión:</strong> {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ── I. DATOS DEL ACADÉMICO ─────────────────────────── --}}
@php
    $catLabel = match($categoria) {
        'titular'  => 'Titular',
        'adjunto'  => 'Adjunto',
        'auxiliar' => 'Auxiliar',
        default    => ucfirst($categoria),
    };
    $lineaLabel = match($academico->linea_desarrollo) {
        'docente'      => 'Docente',
        'investigador' => 'Investigador',
        'mixta'        => 'Mixta',
        default        => '—',
    };
    $conceptoAnteriorLabel = match($academico->concepto_anterior ?? '') {
        'excelente'  => 'Excelente',
        'muy_bueno'  => 'Muy Bueno',
        'bueno'      => 'Bueno',
        'regular'    => 'Regular',
        'deficiente' => 'Deficiente',
        default      => ($academico->concepto_anterior ?? '—'),
    };
    $situacion = 'Calificar ' . $catLabel;
@endphp

<div class="profile-grid">
    <div class="profile-cell span2">
        <div class="cell-label">Nombre Completo</div>
        <div class="cell-value">{{ $academico->name }}</div>
    </div>
    <div class="profile-cell">
        <div class="cell-label">RUT</div>
        <div class="cell-value">{{ $academico->rut }}</div>
    </div>
    <div class="profile-cell">
        <div class="cell-label">Correo Electrónico</div>
        <div class="cell-value" style="font-size:10px">{{ $academico->email }}</div>
    </div>

    <div class="profile-cell">
        <div class="cell-label">Facultad</div>
        <div class="cell-value">{{ $academico->facultad?->nombre ?? '—' }}</div>
    </div>
    <div class="profile-cell">
        <div class="cell-label">Departamento</div>
        <div class="cell-value">{{ $academico->departamento?->nombre ?? '—' }}</div>
    </div>
    <div class="profile-cell">
        <div class="cell-label">Categoría Académica</div>
        <div class="cell-value">{{ $catLabel }}</div>
    </div>
    <div class="profile-cell">
        <div class="cell-label">Fecha de Jerarquización</div>
        <div class="cell-value">{{ $academico->fecha_jerarquizacion?->format('d-m-Y') ?? '—' }}</div>
    </div>

    <div class="profile-cell">
        <div class="cell-label">Línea de Desarrollo</div>
        <div class="cell-value">{{ $lineaLabel }}</div>
    </div>
    <div class="profile-cell">
        <div class="cell-label">Horas Contrato I Sem</div>
        <div class="cell-value">{{ $academico->horas_contrato_isem ?? '—' }}</div>
    </div>
    <div class="profile-cell">
        <div class="cell-label">Horas Contrato II Sem</div>
        <div class="cell-value">{{ $academico->horas_contrato_iisem ?? '—' }}</div>
    </div>
    <div class="profile-cell">
        <div class="cell-label">Situación Evaluativa</div>
        <div class="cell-value">{{ $situacion }}</div>
    </div>

    <div class="profile-cell">
        <div class="cell-label">Nota Calificación Anterior</div>
        <div class="cell-value">{{ $academico->nota_anterior ? number_format($academico->nota_anterior, 1) : '—' }}</div>
    </div>
    <div class="profile-cell">
        <div class="cell-label">Concepto Calificación Anterior</div>
        <div class="cell-value">{{ $conceptoAnteriorLabel }}</div>
    </div>
    <div class="profile-cell span2">
        <div class="cell-label">Período Evaluativo</div>
        <div class="cell-value">{{ $periodo->anio }}</div>
    </div>
</div>

{{-- ── REGLAMENTO ACADÉMICO ────────────────────────────── --}}
@php $articuloTexto = config("reglamento_apa.articulo.{$categoria}", ''); @endphp
@if ($articuloTexto)
<div class="reglamento-box">
    <div class="reg-title">Reglamento Académico</div>
    <div class="reg-body">{{ $articuloTexto }}</div>
</div>
@endif

{{-- ── II. RESUMEN DE HORAS Y RESULTADO ───────────────── --}}
<div class="section-title">
    II. Resumen de horas por áreas de desarrollo y resultado de la evaluación académica
</div>

<table class="table-areas">
    <thead>
        <tr>
            <th class="col-num" rowspan="2"></th>
            <th class="col-area left" rowspan="2">Área de Desarrollo Académico</th>
            <th colspan="2">Horas</th>
            <th class="col-pct" rowspan="2">% Tiempo<br>Asignado</th>
            <th class="col-nota" rowspan="2">Nota</th>
            <th class="col-conc" rowspan="2">Concepto</th>
            <th class="col-pond" rowspan="2">Ponderación<br>(%T × N/100)</th>
        </tr>
        <tr>
            <th class="col-hrs">I Sem</th>
            <th class="col-hrs">II Sem</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($areas as $i => $area)
        <tr>
            <td>{{ $i + 1 }}.</td>
            <td class="left">{{ $area['nombre'] }}</td>
            <td>{{ $area['horas_isem'] }}</td>
            <td>{{ $area['horas_iisem'] }}</td>
            <td>{{ $area['peso'] > 0 ? $area['peso'].'%' : '0%' }}</td>
            <td>{{ $area['nota'] }}</td>
            <td>{{ $area['concepto'] }}</td>
            <td>{{ $area['ponderacion'] }}</td>
        </tr>
        @endforeach

        {{-- Fila calificación final --}}
        <tr class="row-final">
            <td colspan="2" class="left">Calificación Final</td>
            <td>{{ isset($totalHorasIsem) ? number_format((float) $totalHorasIsem, 2) : ($academico->horas_contrato_isem ?? '—') }}</td>
            <td>{{ isset($totalHorasIisem) ? number_format((float) $totalHorasIisem, 2) : ($academico->horas_contrato_iisem ?? '—') }}</td>
            <td>100%</td>
            <td>{{ number_format((float) $calificacion->nota_final, 2) }}</td>
            <td>{{ $calificacion->calificacionLabel() }}</td>
            <td>{{ number_format((float) $calificacion->nota_final, 2) }}</td>
        </tr>
    </tbody>
</table>

<p class="table-note">
    Importante: Las horas de libre disposición no deben ser consideradas ni incluidas en este cálculo.
    Nota final = min(Σ(%T<sub>i</sub> × N<sub>i</sub>) / 100, 5.0).
    Determinada el {{ $calificacion->fecha->format('d/m/Y') }} por {{ $calificacion->determinadaPor->name }}.
</p>

{{-- ── III. INTERPRETACIÓN Y RETROALIMENTACIÓN ────────── --}}
@php
    $conceptoKey = $calificacion->calificacion;
    $definicion  = config("reglamento_apa.concepto_definicion.{$conceptoKey}", '');
@endphp

<div class="section-title">
    III. Interpretación del resultado y retroalimentación de la evaluación
</div>

@if ($definicion)
<div class="interp-box">
    <strong>{{ $calificacion->calificacionLabel() }}:</strong> "{{ $definicion }}"
</div>
@endif

@if ($calificacion->observacion)
<div class="retro-box">
    <strong>Observación de la CCA:</strong><br>
    {{ $calificacion->observacion }}
</div>
@endif

@if ($evaluaciones->whereNotNull('comentario')->where('comentario', '!=', '')->count() > 0)
<table class="eval-table">
    <thead>
        <tr>
            <th>Miembro CCA</th>
            <th>Nota calculada</th>
            <th>Retroalimentación</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($evaluaciones->whereNotNull('comentario')->where('comentario', '!=', '') as $ev)
        @php
            $notaEv = \App\Services\CalificacionCadService::calcularDesdeEvaluacion($ev, $categoria ?? 'adjunto');
        @endphp
        <tr>
            <td>{{ $ev->evaluador->name }}</td>
            <td style="text-align:center">{{ number_format($notaEv, 2) }}</td>
            <td>{{ $ev->comentario }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ── FIRMAS ──────────────────────────────────────────── --}}
@php
    // Preferir firmantes explícitos pasados desde el controlador; si no existen, usar evaluadores.
    $slots = ['miembro_cca_1','miembro_cca_2','secretario','decano','miembro_cca_sindicato'];
@endphp

<div class="firma-section">
    @foreach ($slots as $slot)
    <div class="firma-box">
        <div class="firma-space"></div>
        <div class="firma-line"></div>
        <p class="firma-name">{{ $firmantes[$slot] ?? ($evaluaciones->pluck('evaluador')->pluck('name')->first() ?? '— No designado —') }}</p>
        <p class="firma-label">
            @if($slot === 'miembro_cca_1')Miembro CCA 1
            @elseif($slot === 'miembro_cca_2')Miembro CCA 2
            @elseif($slot === 'miembro_cca_sindicato')Miembro CCA (Sindicato)
            @elseif($slot === 'secretario')Secretario/a de Facultad
            @elseif($slot === 'decano')Decano/a de Facultad
            @endif
        </p>
    </div>
    @endforeach

    <div class="firma-box">
        <div class="firma-space"></div>
        <div class="firma-line"></div>
        <p class="firma-name">{{ $academico->name }}</p>
        <p class="firma-label">Académico/a Evaluado/a — Recepción conforme</p>
    </div>
</div>

</body>
</html>
