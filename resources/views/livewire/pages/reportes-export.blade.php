<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; }
        .header { margin-bottom: 18px; }
        .brand { font-size: 20px; font-weight: 700; margin: 0; }
        .muted { color: #4b5563; margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
        th { background: #f3f4f6; font-weight: 700; }
    </style>
</head>
<body>
    <div class="header">
        <p class="brand">Imprenta Minerva</p>
        <p class="muted">Matagalpa</p>
        <p class="muted"><strong>{{ $title }}</strong></p>
        <p class="muted"><strong>Periodo:</strong> {{ $period }}</p>
        <p class="muted"><strong>Fecha de generacion:</strong> {{ $generatedAt }}</p>
    </div>

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
                <tr>
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
