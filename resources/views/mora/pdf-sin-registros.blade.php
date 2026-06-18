<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sin registros</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: #F4F8F9;
            color: #333333;
        }
        .card {
            background: #FFFFFF;
            border: 1px solid #C1D7DA;
            border-radius: 1rem;
            padding: 2rem 2.5rem;
            text-align: center;
            box-shadow: 0 10px 25px rgba(51, 51, 51, 0.08);
            max-width: 24rem;
        }
        h1 {
            margin: 0 0 0.75rem;
            font-size: 1.125rem;
            font-weight: 700;
        }
        p {
            margin: 0 0 1.5rem;
            font-size: 0.95rem;
            color: #5c6b6e;
        }
        a {
            display: inline-block;
            padding: 0.625rem 1.25rem;
            border-radius: 0.75rem;
            background: #40848D;
            color: #FFFFFF;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
        }
        a:hover {
            background: #356970;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>No hay registros</h1>
        <p>No se encontraron cuotas adeudadas para los filtros indicados.</p>
        <a href="{{ route('mora.gestion-morosos') }}">Volver a Gestión de Morosos</a>
    </div>
</body>
</html>
