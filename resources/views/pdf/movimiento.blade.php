<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Movimiento patrimonial {{ $movimiento->id }}</title>
    <style>
        @page { margin: 35px 42px; }
        body { font-family: DejaVu Sans, sans-serif; color: #222; font-size: 11px; }
        h1 { margin: 0 0 4px; text-align: center; font-size: 18px; }
        .subtitle { margin-bottom: 22px; text-align: center; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        th, td { border: 1px solid #777; padding: 7px; vertical-align: top; }
        th { width: 28%; background: #eeeeee; text-align: left; }
        .section { margin: 18px 0 8px; font-size: 13px; font-weight: bold; }
        .signature { height: 120px; margin-top: 50px; border: 1px solid #777; text-align: center; }
        .signature-line { width: 62%; margin: 76px auto 5px; border-top: 1px solid #222; }
        .small { color: #555; font-size: 9px; }
    </style>
</head>
<body>
    <h1>Constancia de movimiento patrimonial</h1>
    <div class="subtitle">Movimiento N.° {{ $movimiento->id }}</div>

    <div class="section">Datos del movimiento</div>
    <table>
        <tr><th>Fecha</th><td>{{ $movimiento->fecha_movimiento->format('d/m/Y H:i:s') }}</td></tr>
        <tr><th>Bien</th><td>CBI: {{ $movimiento->bien->cbi }}<br>{{ $movimiento->bien->descripcion }}</td></tr>
        <tr><th>Ambiente de origen</th><td>{{ $movimiento->ambienteOrigen?->nombre ?? 'Sin ubicación asignada' }}</td></tr>
        <tr><th>Ambiente de destino</th><td>{{ $movimiento->ambienteDestino->nombre }}</td></tr>
        <tr><th>Ordenado por</th><td>{{ $movimiento->ordenadoPor->nombres }} {{ $movimiento->ordenadoPor->apellidos }}</td></tr>
        <tr><th>Ejecutado por</th><td>{{ $movimiento->ejecutadoPor->nombres }} {{ $movimiento->ejecutadoPor->apellidos }}</td></tr>
        <tr><th>Motivo</th><td>{{ $movimiento->motivo }}</td></tr>
        <tr><th>Observaciones</th><td>{{ $movimiento->observaciones ?: 'Sin observaciones' }}</td></tr>
    </table>

    <div class="signature">
        <div class="signature-line"></div>
        <strong>Firma de quien ordena</strong><br>
        {{ $movimiento->ordenadoPor->nombres }} {{ $movimiento->ordenadoPor->apellidos }}
    </div>

    <p class="small">Este documento debe ser firmado por la persona indicada en “Ordenado por” y cargado nuevamente al sistema.</p>
</body>
</html>
