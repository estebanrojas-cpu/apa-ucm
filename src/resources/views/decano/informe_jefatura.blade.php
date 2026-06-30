<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de Jefatura — {{ $nomina->academico->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #111; background: #fff; padding: 36px 48px; }

        .no-print { margin-bottom: 16px; display: flex; gap: 10px; align-items: center; }
        @media print { .no-print { display: none !important; } body { padding: 18px 28px; } }

        .header { border-bottom: 3px solid #1B2D6B; padding-bottom: 14px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; }
        .header-left h1 { font-size: 16px; font-weight: bold; color: #1B2D6B; }
        .header-left h2 { font-size: 11px; color: #555; margin-top: 3px; }
        .header-right { text-align: right; font-size: 10px; color: #666; }
        .header-right p { margin-top: 2px; }

        .ucm-logo { font-size: 13px; font-weight: bold; color: #1B2D6B; letter-spacing: 0.5px; border: 2px solid #1B2D6B; display: inline-block; padding: 4px 10px; border-radius: 4px; margin-bottom: 6px; }

        .body-text { font-size: 11px; line-height: 1.8; color: #222; margin-bottom: 20px; }
        .body-text strong { color: #1B2D6B; }

        .obs-box { border: 1px solid #ccc; border-radius: 6px; padding: 14px 16px; font-size: 11px; line-height: 1.8; color: #333; white-space: pre-wrap; min-height: 80px; background: #fafafa; margin-bottom: 30px; }

        .firmas { display: flex; justify-content: space-between; margin-top: 60px; gap: 40px; }
        .firma-box { flex: 1; text-align: center; }
        .firma-line { border-top: 1px solid #333; margin-bottom: 8px; }
        .firma-box p { font-size: 10px; color: #444; margin-top: 2px; }
        .firma-box strong { font-size: 11px; color: #111; }
        .firma-space { height: 52px; }

        .footer { margin-top: 40px; border-top: 1px solid #ddd; padding-top: 8px; font-size: 9px; color: #999; text-align: center; }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()"
        style="padding: 6px 18px; background:#1B2D6B; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:12px;">
        Imprimir / Guardar PDF
    </button>
    <a href="/decano/directivos/{{ $nomina->id }}" style="font-size:11px; color:#1B2D6B; text-decoration:underline;">
        ← Volver al informe
    </a>
</div>

{{-- Encabezado --}}
<div class="header">
    <div class="header-left">
        <div class="ucm-logo">UCM</div>
        <h1>Universidad Católica del Maule</h1>
        <h2>Vicerrectoría Académica · Proceso de Calificación Académica Docente {{ $nomina->periodo->anio }}</h2>
    </div>
    <div class="header-right">
        <p><strong>N° Período:</strong> {{ $nomina->periodo->nombre }}</p>
        <p><strong>Fecha emisión:</strong> {{ now()->format('d/m/Y') }}</p>
        <p><strong>Tipo:</strong> Informe de Jefatura</p>
    </div>
</div>

{{-- Cuerpo institucional --}}
<p class="body-text">
    Talca, {{ now()->format('d') }} de {{ \Carbon\Carbon::now()->locale('es')->isoFormat('MMMM') }} de {{ now()->year }}
</p>

<p class="body-text" style="margin-bottom: 16px;">
    Por medio del presente documento, quien suscribe, en calidad de <strong>{{ $decano->name }}</strong>,
    Decano/a de la <strong>{{ $nomina->unidad_superior ?? 'Facultad' }}</strong> de la Universidad Católica del Maule,
    emite el siguiente informe institucional en el contexto del Proceso de Calificación Académica Docente
    correspondiente al período académico <strong>{{ $nomina->periodo->anio }}</strong>,
    respecto del/la académico/a:
</p>

<p class="body-text" style="margin-left: 24px; margin-bottom: 20px;">
    <strong>Nombre:</strong> {{ $nomina->academico->name }}<br>
    <strong>RUT:</strong> {{ $nomina->academico->rut }}<br>
    <strong>Cargo:</strong> {{ $nomina->asignacionesCargo->first()?->label() ?? '—' }}<br>
    <strong>Categoría académica:</strong> {{ ucfirst($nomina->categoria ?? '—') }}
</p>

{{-- Observaciones --}}
<p class="body-text" style="margin-bottom: 8px;"><strong>Observaciones:</strong></p>
<div class="obs-box">{{ $informe->observacionGeneral() ?: 'Sin observaciones registradas.' }}</div>

{{-- Firmas --}}
<div class="firmas">
    <div class="firma-box">
        <div class="firma-space"></div>
        <div class="firma-line"></div>
        <p><strong>{{ $decano->name }}</strong></p>
        <p>Decano/a · {{ $nomina->unidad_superior ?? 'Facultad' }}</p>
        <p>Universidad Católica del Maule</p>
    </div>
    <div class="firma-box">
        <div class="firma-space"></div>
        <div class="firma-line"></div>
        <p><strong>{{ $nomina->academico->name }}</strong></p>
        <p>Académico/a · {{ $nomina->unidad_superior ?? 'Facultad' }}</p>
        <p>Universidad Católica del Maule</p>
    </div>
</div>

<div class="footer">
    Documento generado por el Sistema APA UCM · Universidad Católica del Maule · Talca, Chile
</div>

</body>
</html>
