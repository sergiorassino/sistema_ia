<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — PDF no disponible</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #F4F8F9; color: #333; margin: 0; padding: 2rem; }
        .box { max-width: 28rem; margin: 2rem auto; background: #fff; border: 1px solid #C1D7DA; border-radius: 1rem; padding: 1.5rem; }
        h1 { font-size: 1.125rem; margin: 0 0 0.75rem; }
        p { margin: 0 0 1rem; line-height: 1.5; font-size: 0.9375rem; }
        a { color: #40848D; font-weight: 600; }
    </style>
</head>
<body>
    <div class="box">
        <h1>No se pudo generar el PDF</h1>
        <p>{{ $mensaje }}</p>
        <p><a href="{{ se_route_url('alumnos.comunicaciones.index') }}">Volver al portal de estudiantes</a></p>
    </div>
</body>
</html>
