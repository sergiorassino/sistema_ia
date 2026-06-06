{{-- Informes de progreso escolar: un boletín por hoja (misma plantilla que el PDF individual). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    @include('pdf.partials.consulta-calificaciones-alumno-styles')
    <style>
        .boletin-hoja + .boletin-hoja {
            page-break-before: always;
        }
    </style>
</head>
<body>
@foreach ($consultas as $consulta)
    <div class="boletin-hoja">
        @include('pdf.partials.consulta-calificaciones-alumno-sheet', [
            'consulta' => $consulta,
            'pdfHeader' => $pdfHeader ?? null,
            'tituloDocumento' => $tituloDocumento ?? 'Informe de Progreso Escolar',
            'mostrarMarcaAgua' => false,
            'mostrarFirmas' => true,
        ])
    </div>
@endforeach
</body>
</html>
