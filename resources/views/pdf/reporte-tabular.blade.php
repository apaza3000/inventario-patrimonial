<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 24px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 8px; }
        h1 { margin: 0 0 4px; font-size: 16px; }
        .meta { margin-bottom: 12px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #9ca3af; padding: 3px; overflow-wrap: break-word; }
        th { background: #d9eaf7; text-align: left; font-weight: bold; }
        tr:nth-child(even) td { background: #f9fafb; }
        .empty { padding: 16px; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <div class="meta">
        Alcance: {{ $alcance }} · Generado: {{ $generadoEn->format('Y-m-d H:i:s') }} · Registros: {{ count($filas) }}
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($encabezados as $encabezado)
                    <th>{{ $encabezado }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr>
                    @foreach ($fila as $valor)
                        <td>{{ $valor ?? '—' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="empty" colspan="{{ count($encabezados) }}">No hay registros para este alcance.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
