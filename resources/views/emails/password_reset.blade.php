<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperación de contraseña</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333;">
    <h2>Hola {{ $name }},</h2>
    <p>{!! nl2br(e($messageBody)) !!}</p>
    <p>
        Para restablecer tu contraseña, haz clic en el siguiente enlace:
    </p>
    <p>
        <a href="{{ $url }}" style="display: inline-block; padding: 10px 18px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px;">
            Restablecer contraseña
        </a>
    </p>
    <p style="margin-top: 20px; color: #666; font-size: 14px;">
        Si no solicitaste este correo, puedes ignorarlo.
    </p>
</body>
</html>
