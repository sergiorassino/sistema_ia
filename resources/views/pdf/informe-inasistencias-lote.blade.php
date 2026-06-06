{{-- Informes de inasistencias en lote: plantilla compacta; varios alumnos por hoja si el bloque entra. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    @include('pdf.partials.informe-inasistencias-styles')
</head>
<body class="informe-inasistencias-lote">
@foreach ($hojas as $hoja)
    <div class="informe-hoja">
        @include('pdf.partials.informe-inasistencias-hoja', [
            ...$hoja,
            'pdfHeader' => $pdfHeader ?? null,
        ])
    </div>
@endforeach
</body>
</html>
