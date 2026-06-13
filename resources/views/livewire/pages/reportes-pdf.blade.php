<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 18px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 8px; }
        .header { width: 100%; margin-bottom: 10px; border-bottom: 1px solid #9ca3af; padding-bottom: 8px; }
        .logo { width: 42px; height: 42px; border: 1px solid #9ca3af; text-align: center; font-size: 25px; }
        .company { font-size: 15px; font-weight: bold; margin: 0; }
        .muted { margin: 1px 0; color: #374151; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #9ca3af; padding: 3px; text-align: left; word-wrap: break-word; }
        th { background: #e5e7eb; font-weight: bold; text-align: center; }
        tr:nth-child(even) td { background: #f9fafb; }
        .total-row td { background: #dbeafe; font-weight: bold; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td class="logo">P</td>
            <td>
                <p class="company">Imprenta Minerva</p>
                <p class="muted">Matagalpa</p>
                <p class="muted"><strong>{{ $title }}</strong></p>
            </td>
            <td>
                <p class="muted"><strong>Periodo:</strong> {{ $period }}</p>
                <p class="muted"><strong>Fecha de generacion:</strong> {{ $generatedAt }}</p>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr @class(['total-row' => !empty($row['_is_total'])])>
                    @foreach($headers as $header)
                        <td>{{ $row[$header] }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}">No hay registros para el periodo seleccionado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
