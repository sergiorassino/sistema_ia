{{-- Legacy DomPDF (referencia). Generación activa: App\Support\CalificacionesSecundario\PlanillaCalificacionesTcpdf --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 10mm 7mm 8mm 7mm; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 6pt;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .layer { position: relative; }
        .titulo {
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            margin: 0 0 3px 0;
            font-size: 10pt;
            letter-spacing: 0.02em;
        }
        .subtitulo { text-align: center; margin: 0 0 4px 0; font-size: 6.5pt; }
        .meta { margin: 0 0 4px 0; font-size: 6.5pt; line-height: 1.25; }
        .meta strong.meta-materia { font-size: 8pt; }
        .meta strong.meta-curso { font-size: 6.5pt; font-weight: 700; }
        .seccion-materia { margin: 0; }
        .seccion-materia--continuacion { page-break-before: always; }

        table.outer {
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
        }
        table.outer tbody tr.fila-alumno td {
            vertical-align: middle;
        }
        table.outer tbody tr.fila-alumno td.bay-ec {
            overflow: hidden;
        }
        th.bay, td.bay {
            border: 0.75pt solid #333333;
            border-radius: 1.5pt;
            padding: 0;
            vertical-align: middle;
            overflow: hidden;
            background-color: #fff;
        }
        th.bay-ec, td.bay-ec,
        th.bay-ord, td.bay-ord {
            border: 0.75pt solid #333333;
            border-radius: 1.5pt;
            background-color: #fff;
        }
        th.bay-ord, td.bay-ord {
            font-weight: 700;
            font-size: 5pt;
            text-align: center;
            padding: 0 1px !important;
            line-height: 1.05;
        }
        td.bay-ord {
            font-weight: 400;
        }
        th.bay-ec {
            font-weight: 700;
            font-size: 5.5pt;
            padding: 2px 3px;
            text-align: center;
            line-height: 1.1;
        }
        td.bay-ec {
            text-align: left !important;
            font-weight: 400;
            text-transform: uppercase;
            padding: 0 !important;
            line-height: 1;
            white-space: nowrap;
            overflow: hidden;
        }
        table.inner tr.data td.celda-nombre {
            text-align: left !important;
            white-space: nowrap;
            overflow: hidden;
        }
        table.inner {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin: 0;
            font-size: 5.5pt;
        }
        table.inner th, table.inner td {
            padding: 0 1px;
            text-align: center;
            vertical-align: middle;
            border: none;
        }
        table.inner tr:first-child th {
            font-weight: 700;
            font-size: 5pt;
            background-color: #fff;
            border-bottom: 0.55pt solid #333;
            padding: 1px;
        }
        table.inner tr:nth-child(2) th {
            font-weight: 700;
            font-size: 4.8pt;
            background-color: #fff;
            border-bottom: 0.45pt solid #666;
        }
        table.inner tr:nth-child(2) th:not(:last-child) {
            border-right: 0.4pt solid #888;
        }
        table.inner tr.data td {
            border-top: 0.3pt solid #bbb;
            border-right: 0.3pt solid #999;
        }
        table.inner tr.data td:last-child { border-right: none; }
        table.outer tbody tr.fila-alumno {
            page-break-inside: auto;
            page-break-after: auto;
        }

        th.bay-col, td.bay-col {
            font-weight: 700;
            font-size: 5pt;
            text-align: center;
            padding: 1px !important;
            line-height: 1.05;
            background-color: #fff;
        }
        td.bay-col {
            font-weight: 400;
        }
        td.bay-prom {
            font-weight: 700;
        }
        td.bay.bay-desaprobado,
        td.bay.bay-desaprobado table.inner tr.data td {
            background-color: #b8b8b8 !important;
        }

        .pie-footer { width: 100%; margin-top: 12mm; }
        .pie-footer-tabla {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .pie-footer-tabla td { vertical-align: bottom; padding: 0; border: 0; }
        .pie-firmas-gutter {
            width: 2cm;
            min-width: 2cm;
            max-width: 2cm;
            padding: 0;
            border: 0;
            font-size: 1pt;
            line-height: 1;
        }
        .pie-firma-spacer {
            width: auto;
            padding: 0;
            border: 0;
            font-size: 1pt;
            line-height: 1;
        }
        .pie-firma-izq,
        .pie-firma-der {
            width: 68mm;
            vertical-align: bottom;
            padding: 0;
            border: 0;
        }
        .pie-firma-izq { text-align: left; }
        .pie-firma-der { text-align: right; }
        .firma-bloque {
            margin: 0;
            padding: 0;
            width: 65mm;
        }
        .pie-firma-der .firma-bloque {
            margin-left: auto;
            margin-right: 0;
        }
        .firma-linea {
            border-bottom: 0.55pt dotted #333;
            height: 6mm;
            width: 65mm;
            min-width: 65mm;
            display: block;
            margin: 0;
        }
        .firma-label {
            font-size: 6pt;
            text-align: center;
            margin: 1px 0 0 0;
            line-height: 1.15;
            font-weight: 400;
            width: 65mm;
        }
    </style>
</head>
<body>
@php
    $seccionesLista = $secciones ?? [];
    if ($seccionesLista === [] && isset($filas)) {
        $seccionesLista = [[
            'filas' => $filas,
            'layoutFilas' => $layoutFilas ?? [],
            'materiaLabel' => $materiaLabel ?? '',
            'profesoresLinea' => $profesoresLinea ?? '',
        ]];
    }
@endphp
@forelse ($seccionesLista as $idx => $sec)
    <div @class(['seccion-materia', 'seccion-materia--continuacion' => $idx > 0])>
        @include('pdf.partials.planilla-calificaciones-hoja', [
            'pdfHeader' => $pdfHeader ?? null,
            'ano' => $ano ?? null,
            'cursoLabel' => $cursoLabel ?? '',
            'materiaLabel' => $sec['materiaLabel'] ?? '',
            'profesoresLinea' => $sec['profesoresLinea'] ?? '',
            'filas' => $sec['filas'] ?? [],
            'layoutFilas' => $sec['layoutFilas'] ?? [],
            'mostrarEncabezado' => $idx === 0,
        ])
    </div>
@empty
    <p class="meta">Sin materias seleccionadas.</p>
@endforelse
</body>
</html>
