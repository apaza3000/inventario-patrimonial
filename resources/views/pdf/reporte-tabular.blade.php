<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 26px 26px 34px; }
        body { font-family: DejaVu Sans, sans-serif; color: #30382b; font-size: 11px; }
        h1 { margin: 0 0 6px; font-size: 18px; color: #59694b; }
        .meta { margin-bottom: 10px; color: #535b4c; font-size: 10px; }
        .filters { margin: 8px 0 14px; padding: 8px; background: #f3eedf; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #c3cbb9; padding: 7px; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
        th { background: #e7eedf; text-align: left; font-weight: bold; font-size: 10px; }
        tr:nth-child(even) td { background: #faf8f1; }
        .details { margin-top: 6px; line-height: 1.5; color: #535b4c; font-size: 9px; }
        .footer { position: fixed; bottom: -22px; right: 0; font-size: 9px; color: #535b4c; }
        .footer:after { content: counter(page); }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <div class="meta">
        Alcance: {{ $alcance }} · Generado: {{ $generadoEn->format('Y-m-d H:i:s') }} · Registros: {{ count($filas) }}
    </div>
    <div class="filters">{{ $filtrosAplicados }}</div>
    <div class="footer">Página </div>

    @php
        $adicionales = array_diff(array_keys($encabezados), $columnasPdf);
    @endphp
    <table>
        <colgroup>
            @foreach ($columnasPdf as $indice)
                <col style="width: {{ $indice === $columnaDetalle ? 44 : 56 / (count($columnasPdf) - 1) }}%">
            @endforeach
        </colgroup>
        <thead>
            <tr>
                @foreach ($columnasPdf as $indice)
                    <th style="width: {{ $indice === $columnaDetalle ? 44 : 56 / (count($columnasPdf) - 1) }}%">{{ $encabezados[$indice] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    @foreach ($columnasPdf as $indice)
                        <td>
                            {{ $fila[$indice] === null || $fila[$indice] === '' ? '—' : $fila[$indice] }}
                            @if ($indice === $columnaDetalle)
                                <div class="details">
                                    @foreach ($adicionales as $adicional)
                                        <span><strong>{{ $encabezados[$adicional] }}:</strong> {{ $fila[$adicional] === null || $fila[$adicional] === '' ? '—' : $fila[$adicional] }}</span>@unless ($loop->last) · @endunless
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
