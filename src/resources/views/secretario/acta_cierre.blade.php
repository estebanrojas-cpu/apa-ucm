<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acta de Cierre — {{ $facultad->nombre }} — {{ $periodo->nombre }} {{ $periodo->anio }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #111;
            background: #fff;
            padding: 30px 40px;
        }

        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            border-bottom: 2px solid #1B2D6B;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }

        .header-title h1 {
            font-size: 16px;
            font-weight: bold;
            color: #1B2D6B;
        }

        .header-title p {
            font-size: 11px;
            color: #555;
            margin-top: 2px;
        }

        .header-meta {
            text-align: right;
            font-size: 11px;
            color: #555;
        }

        .section {
            margin-bottom: 18px;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1B2D6B;
            border-bottom: 1px solid #ddd;
            padding-bottom: 4px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-row {
            display: flex;
            gap: 6px;
            margin-bottom: 4px;
        }

        .info-label {
            font-weight: bold;
            color: #444;
            min-width: 120px;
        }

        .table-acta {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .table-acta th {
            background: #f0f4fb;
            border: 1px solid #ccc;
            padding: 6px 8px;
            text-align: left;
            color: #333;
            font-weight: bold;
        }

        .table-acta td {
            border: 1px solid #e0e0e0;
            padding: 5px 8px;
            color: #333;
        }

        .table-acta tr:nth-child(even) td {
            background: #fafafa;
        }

        .badge-calificacion {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }

        .badge-excelente  { background: #d1fae5; color: #065f46; }
        .badge-muy-bueno  { background: #dbeafe; color: #1e40af; }
        .badge-bueno      { background: #e0e7ff; color: #3730a3; }
        .badge-regular    { background: #fef9c3; color: #854d0e; }
        .badge-deficiente { background: #fee2e2; color: #991b1b; }
        .badge-sin-calif  { background: #f3f4f6; color: #6b7280; }

        .resumen-box {
            display: flex;
            gap: 20px;
            margin-bottom: 12px;
        }

        .resumen-item {
            background: #f4f6fb;
            border: 1px solid #dde2f0;
            border-radius: 6px;
            padding: 8px 16px;
            text-align: center;
        }

        .resumen-item .val {
            font-size: 20px;
            font-weight: bold;
            color: #1B2D6B;
        }

        .resumen-item .lbl {
            font-size: 10px;
            color: #555;
            margin-top: 2px;
        }

        .texto-cierre {
            line-height: 1.8;
            color: #333;
            margin-bottom: 10px;
        }

        .firma-section {
            margin-top: 50px;
            display: flex;
            gap: 50px;
        }

        .firma-box {
            flex: 1;
            text-align: center;
        }

        .firma-line {
            border-top: 1px solid #555;
            margin-bottom: 4px;
        }

        .firma-label {
            font-size: 10px;
            color: #555;
        }

        .btn-print {
            display: inline-block;
            background: #1B2D6B;
            color: white;
            padding: 8px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            margin-bottom: 24px;
        }

        @media print {
            .btn-print { display: none; }
            body { padding: 15px 20px; }
        }
    </style>
</head>
<body>

<button class="btn-print" onclick="window.print()">Imprimir / Guardar como PDF</button>

<div class="header">
    <div class="header-title">
        <h1>Acta de Cierre del Proceso de Calificación Académica</h1>
        <p>Universidad Católica del Maule — Vicerrectoría Académica</p>
    </div>
    <div class="header-meta">
        <p><strong>Período:</strong> {{ $periodo->nombre }} {{ $periodo->anio }}</p>
        <p><strong>Facultad:</strong> {{ $facultad->nombre }}</p>
        <p><strong>Fecha de emisión:</strong> {{ $acta->fecha->format('d/m/Y') }}</p>
    </div>
</div>

{{-- Texto formal de cierre --}}
<div class="section">
    <p class="section-title">Declaración de Cierre</p>
    <p class="texto-cierre">
        En la ciudad de Talca, a {{ $acta->fecha->format('d/m/Y') }},
        la Comisión Calificadora Académica de la <strong>{{ $facultad->nombre }}</strong> da por concluido el proceso
        de Calificación Académica Docente correspondiente al período <strong>{{ $periodo->nombre }} {{ $periodo->anio }}</strong>,
        habiendo evaluado a todos los académicos que conforman la nómina de la facultad.
    </p>
    <p class="texto-cierre">
        El presente documento certifica el cierre formal del proceso, con los resultados de calificación que se detallan a continuación.
        Acta generada por: <strong>{{ $acta->generadaPor->name }}</strong>.
    </p>
</div>

{{-- Resumen estadístico --}}
@php
    $califs = $nominas->pluck('calificacion')->filter()->countBy();
    $total  = $nominas->count();
    $conCalif = $nominas->filter(fn($n) => $n['calificacion'])->count();
@endphp
<div class="section">
    <p class="section-title">Resumen</p>
    <div class="resumen-box">
        <div class="resumen-item">
            <div class="val">{{ $total }}</div>
            <div class="lbl">Total académicos</div>
        </div>
        <div class="resumen-item">
            <div class="val">{{ $conCalif }}</div>
            <div class="lbl">Con calificación</div>
        </div>
        <div class="resumen-item">
            <div class="val">{{ $califs['excelente'] ?? 0 }}</div>
            <div class="lbl">Excelente</div>
        </div>
        <div class="resumen-item">
            <div class="val">{{ $califs['muy_bueno'] ?? 0 }}</div>
            <div class="lbl">Muy Bueno</div>
        </div>
        <div class="resumen-item">
            <div class="val">{{ $califs['bueno'] ?? 0 }}</div>
            <div class="lbl">Bueno</div>
        </div>
        <div class="resumen-item">
            <div class="val">{{ $califs['regular'] ?? 0 }}</div>
            <div class="lbl">Regular</div>
        </div>
        <div class="resumen-item">
            <div class="val">{{ $califs['deficiente'] ?? 0 }}</div>
            <div class="lbl">Deficiente</div>
        </div>
    </div>
</div>

{{-- Tabla de académicos --}}
<div class="section">
    <p class="section-title">Resultados de Calificación</p>
    <table class="table-acta">
        <thead>
            <tr>
                <th>#</th>
                <th>Académico</th>
                <th>RUT</th>
                <th>Departamento</th>
                <th>Calificación</th>
                <th>Puntaje</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($nominas as $i => $n)
            @php
                $badgeClass = match($n['calificacion'] ?? '') {
                    'excelente'  => 'badge-excelente',
                    'muy_bueno'  => 'badge-muy-bueno',
                    'bueno'      => 'badge-bueno',
                    'regular'    => 'badge-regular',
                    'deficiente' => 'badge-deficiente',
                    default      => 'badge-sin-calif',
                };
                $labelCalif = match($n['calificacion'] ?? '') {
                    'excelente'  => 'Excelente',
                    'muy_bueno'  => 'Muy Bueno',
                    'bueno'      => 'Bueno',
                    'regular'    => 'Regular',
                    'deficiente' => 'Deficiente',
                    default      => 'Sin calificación',
                };
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $n['nombre'] }}</td>
                <td>{{ $n['rut'] ?? '—' }}</td>
                <td>{{ $n['departamento'] ?? '—' }}</td>
                <td>
                    <span class="badge-calificacion {{ $badgeClass }}">{{ $labelCalif }}</span>
                    @if ($n['es_apelacion'] ?? false)
                        <span style="font-size:9px; color:#92400e;">(apelación)</span>
                    @endif
                </td>
                <td>{{ $n['puntaje'] !== null ? $n['puntaje'].' pts' : '—' }}</td>
                <td style="font-size:10px; color:#555;">{{ $n['observacion'] ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Firma --}}
<div class="firma-section">
    <div class="firma-box">
        <div class="firma-line"></div>
        <p class="firma-label">Secretario/a de Facultad</p>
    </div>
    <div class="firma-box">
        <div class="firma-line"></div>
        <p class="firma-label">Presidente de la CCA</p>
    </div>
    <div class="firma-box">
        <div class="firma-line"></div>
        <p class="firma-label">Decano/a de Facultad</p>
    </div>
    <div class="firma-box">
        <div class="firma-line"></div>
        <p class="firma-label">Timbre y Fecha</p>
    </div>
</div>

</body>
</html>
