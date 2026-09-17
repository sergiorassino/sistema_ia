<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    @include('pdf.partials.informe-inasistencias-styles')
</head>
<body>
    @include('pdf.partials.informe-inasistencias-hoja', [
        'ano' => $ano,
        'alumnoLinea' => $alumnoLinea,
        'dni' => $dni,
        'cursoLabel' => $cursoLabel,
        'fechaDesde' => $fechaDesde,
        'fechaHasta' => $fechaHasta,
        'filtroFechasActivo' => $filtroFechasActivo ?? false,
        'inasistencias' => $inasistencias,
        'totalesCatalogo' => $totalesCatalogo ?? [],
        'totalesPieInforme' => $totalesPieInforme ?? [],
        'pdfHeader' => $pdfHeader ?? null,
    ])
</body>
</html>
