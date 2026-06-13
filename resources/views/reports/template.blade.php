<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
</head>
<body>

    <center>
        <h1>Imprenta Minerva</h1>
        <h2>{{ $title }}</h2>
        <p>Generado el: {{ $date }}</p>
    </center>

    <br>

    <table border="1" cellpadding="6" cellspacing="0" width="100%">
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th align="center" bgcolor="#e2e8f0">{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @if(count($data) > 0)
                @foreach($data as $row)
                    <tr>
                        @foreach($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="{{ count($headers) }}" align="center">No hay registros para este periodo.</td>
                </tr>
            @endif
        </tbody>

        @if(!empty($totals))
            <tfoot>
                <tr>
                    @foreach($totals as $total)
                        <th align="center" bgcolor="#f8fafc">{{ $total }}</th>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>

    <br><br>

    <div align="right">
        <font size="1" color="#999999">
            Documento generado automáticamente por el ERP ImApp.
        </font>
    </div>

</body>
</html>
