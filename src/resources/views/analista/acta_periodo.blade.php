<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acta de Período — {{ $periodo->nombre }} {{ $periodo->anio }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #111;
            background: #fff;
            padding: 28px 36px;
        }

        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            border-bottom: 2px solid #1B2D6B;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }

        .header-title h1 { font-size: 15px; font-weight: bold; color: #1B2D6B; }
        .header-title h2 { font-size: 12px; color: #333; margin-top: 3px; }
        .header-title p  { font-size: 10px; color: #666; margin-top: 2px; }

        .header-meta { text-align: right; font-size: 10px; color: #555; }
        .header-meta p { margin-top: 2px; }

        .section { margin-bottom: 16px; }

        .section-title {
            font-size: 10px;
            font-weight: bold;
            color: #1B2D6B;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 3px;
            margin-bottom: 8px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 4px 12px;
            margin-bottom: 10px;
        }

        .info-item label { color: #777; display: block; font-size: 9px; text-transform: uppercase; }
        .info-item span  { font-weight: bold; }

        /* Distribución */
        .dist-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .dist-table td, .dist-table th { padding: 3px 6px; font-size: 10px; }
        .dist-table th { text-align: left; color: #555; border-bottom: 1px solid #eee; font-weight: normal; }
        .dist-table td:first-child { color: #333; }
        .dist-table td:last-child { font-weight: bold; text-align: right; }

        /* Tabla de académicos */
        .acad-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            font-size: 10px;
        }

        .acad-table th {
            background: #f3f4f6;
            text-align: left;
            padding: 4px 6px;
            font-weight: bold;
            color: #1B2D6B;
            border-bottom: 1px solid #ddd;
            font-size: 9px;
            text-transform: uppercase;
        }

        .acad-table td {
            padding: 5px 6px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: top;
        }

        .acad-table tr:last-child td { border-bottom: none; }

        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 9px;
            font-size: 9px;
            font-weight: bold;
        }

        .badge-excelente  { background: #d1fae5; color: #065f46; }
        .badge-muy_bueno  { background: #ccfbf1; color: #0f766e; }
        .badge-bueno      { background: #dbeafe; color: #1d4ed8; }
        .badge-regular    { background: #fef3c7; color: #92400e; }
        .badge-deficiente { background: #fee2e2; color: #991b1b; }

        .facultad-title {
            font-size: 11px;
            font-weight: bold;
            color: #1B2D6B;
            margin-top: 14px;
            margin-bottom: 5px;
            padding-bottom: 3px;
            border-bottom: 1px solid #1B2D6B;
        }

        .da-conocer-section { margin-top: 16px; }
        .da-conocer-title { font-size: 10px; font-weight: bold; color: #555; margin-bottom: 5px; font-style: italic; }

        .firma-section {
            margin-top: 32px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 40px;
        }

        .firma-box {
            border-top: 1px solid #333;
            padding-top: 6px;
            text-align: center;
        }

        .firma-box p { font-size: 10px; color: #333; }
        .firma-space { height: 36px; }

        .page-break { page-break-before: always; }

        @media print {
            body { padding: 12px 18px; }
            .no-print { display: none; }
            a { color: inherit; text-decoration: none; }
        }
    </style>
</head>
<body>

<div class="no-print" style="margin-bottom: 14px;">
    <button onclick="window.print()"
        style="padding: 6px 18px; background: #1B2D6B; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 12px;">
        Imprimir / Guardar PDF
    </button>
</div>

{{-- Encabezado --}}
<div class="header">
    <div class="header-title">
        <h1>Universidad Católica del Maule</h1>
        <h2>Acta de Calificación Académica — Período {{ $periodo->nombre }}</h2>
        <p>Sistema de Gestión APA — CCDA</p>
    </div>
    <div class="header-meta">
        <p><strong>Año:</strong> {{ $periodo->anio }}</p>
        <p><strong>Inicio:</strong> {{ $periodo->fecha_inicio?->format('d/m/Y') ?? '—' }}</p>
        <p><strong>Cierre proceso:</strong> {{ $periodo->fecha_cierre?->format('d/m/Y') ?? '—' }}</p>
        @if($periodo->cerrado_en)
        <p><strong>Fecha de cierre:</strong> {{ $periodo->cerrado_en->format('d/m/Y') }}</p>
        @endif
        <p><strong>Generado por:</strong> {{ $generadoPor }}</p>
        <p><strong>Fecha emisión:</strong> {{ now()->format('d/m/Y') }}</p>
    </div>
</div>

{{-- Distribución global --}}
@if($distribucion->isNotEmpty())
<div class="section">
    <div class="section-title">Distribución global de calificaciones</div>
    @php
        $totalDist = $distribucion->sum();
        $ordenConceptos = ['excelente', 'muy_bueno', 'bueno', 'regular', 'deficiente'];
        $labelConceptos = [
            'excelente'  => 'Excelente',
            'muy_bueno'  => 'Muy Bueno',
            'bueno'      => 'Bueno',
            'regular'    => 'Regular',
            'deficiente' => 'Deficiente',
        ];
    @endphp
    <table class="dist-table">
        <tr>
            @foreach($ordenConceptos as $k)
                @if(isset($distribucion[$k]) && $distribucion[$k] > 0)
                <th>{{ $labelConceptos[$k] }}</th>
                @endif
            @endforeach
            <th style="text-align:right;">Total</th>
        </tr>
        <tr>
            @foreach($ordenConceptos as $k)
                @if(isset($distribucion[$k]) && $distribucion[$k] > 0)
                <td><strong>{{ $distribucion[$k] }}</strong> ({{ round($distribucion[$k] / $totalDist * 100) }}%)</td>
                @endif
            @endforeach
            <td style="font-weight:bold; text-align:right;">{{ $totalDist }}</td>
        </tr>
    </table>
</div>
@endif

{{-- Académicos por facultad --}}
@foreach($porFacultad as $facultadNombre => $academicos)
<div class="facultad-title">{{ $facultadNombre }}</div>

<table class="acad-table">
    <thead>
        <tr>
            <th style="width:20%">Nombre</th>
            <th style="width:9%">RUT</th>
            <th style="width:16%">Cargo</th>
            <th style="width:10%">Categoría</th>
            <th style="width:10%">Tipo / Hrs</th>
            <th style="width:10%; text-align:center;">Calificación</th>
            <th style="width:25%">Obs. Vicerrectoría</th>
        </tr>
    </thead>
    <tbody>
        @foreach($academicos->sortBy('nombre') as $ac)
        @php
            $badgeCls = 'badge-' . ($ac['nota']
                ? (in_array(str_replace(' ', '_', strtolower($ac['concepto'] ?? '')), ['excelente','muy_bueno','bueno','regular','deficiente'])
                    ? str_replace(' ', '_', strtolower($ac['concepto'] ?? ''))
                    : 'bueno')
                : 'bueno');
        @endphp
        <tr>
            <td>
                <strong>{{ $ac['nombre'] }}</strong>
            </td>
            <td>{{ $ac['rut'] }}</td>
            <td>{{ $ac['cargo'] }}</td>
            <td style="text-transform: capitalize;">{{ $ac['categoria'] }}</td>
            <td>{{ $ac['tipo_trabajador'] }}<br><span style="color:#777">{{ $ac['horas_contrato'] ? $ac['horas_contrato'].' hrs' : '' }}</span></td>
            <td style="text-align:center;">
                @if($ac['nota'])
                    <span class="badge {{ $badgeCls }}">{{ $ac['nota'] }} — {{ $ac['concepto'] }}</span>
                    @if($ac['fecha_calificacion'])
                    <br><span style="color:#999; font-size:9px;">{{ $ac['fecha_calificacion'] }}</span>
                    @endif
                @else
                    <span style="color:#aaa;">Sin calif.</span>
                @endif
            </td>
            <td style="color:#444;">{{ $ac['comentario_vice'] ?? '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endforeach

{{-- Se da a conocer --}}
@if($daConocer->isNotEmpty())
<div class="da-conocer-section">
    <div class="da-conocer-title">Se da a conocer — no participan de la evaluación CCA</div>
    <table class="acad-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>RUT</th>
                <th>Cargo</th>
                <th>Facultad</th>
            </tr>
        </thead>
        <tbody>
            @foreach($daConocer->sortBy('nombre') as $ac)
            <tr>
                <td>{{ $ac['nombre'] }}</td>
                <td>{{ $ac['rut'] }}</td>
                <td>{{ $ac['cargo'] }}</td>
                <td>{{ $ac['facultad'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Firmas --}}
<div class="firma-section">
    <div class="firma-box">
        <div class="firma-space"></div>
        <p><strong>Analista CCDA</strong></p>
        <p>{{ $generadoPor }}</p>
    </div>
    <div class="firma-box">
        <div class="firma-space"></div>
        <p><strong>Vicerrector/a Académico/a</strong></p>
        <p>&nbsp;</p>
    </div>
</div>

</body>
</html>
