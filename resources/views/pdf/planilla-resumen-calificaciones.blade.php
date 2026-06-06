{{-- Planilla resumen: uno o más cursos, materias en columnas, 5 filas por estudiante. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 8mm 5mm 6mm 5mm; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 5pt;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .titulo {
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            margin: 0 0 2px 0;
            font-size: 9pt;
        }
        .subtitulo { text-align: center; margin: 0 0 3px 0; font-size: 6pt; }
        .meta-curso {
            text-align: center;
            font-weight: 700;
            font-size: 7pt;
            margin: 0 0 3px 0;
            text-transform: uppercase;
        }
        .leyenda {
            font-size: 4.6pt;
            line-height: 1.2;
            margin: 0 0 4px 0;
            text-align: justify;
        }
        .seccion-curso {
            margin: 0 0 6px 0;
        }
        .seccion-curso--continuacion {
            page-break-before: always;
        }
        table.resumen {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.resumen thead {
            display: table-header-group;
        }
        table.resumen th, table.resumen td {
            border: 0.4pt solid #333;
            text-align: center;
            vertical-align: middle;
            padding: 0;
        }
        table.resumen thead th {
            font-weight: 700;
            font-size: 4.8pt;
            background: #fff;
            padding: 1px;
        }
        table.resumen th.col-mat {
            font-size: 4.5pt;
            letter-spacing: -0.02em;
        }
        tbody.bloque-alumno {
            page-break-inside: avoid;
        }
        table.resumen td.col-ord {
            font-weight: 700;
        }
        table.resumen td.col-nom {
            text-align: left !important;
            font-weight: 700;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            padding: 0 2px !important;
        }
        table.resumen td.col-vacia {
            border-top: none;
            border-bottom: none;
        }
        td.celda-nota {
            font-weight: 400;
        }
        td.celda-gris { background-color: #b8b8b8 !important; }
        td.celda-rojo { color: #c00 !important; }
        td.celda-prom { font-weight: 700; }
        tr.sep-alumno td {
            border: none !important;
            border-top: 1.2pt solid #000 !important;
            height: 0;
            padding: 0;
            line-height: 0;
            font-size: 1pt;
        }
        tr.fila-pie td {
            text-align: left !important;
            font-size: 4.5pt;
            padding: 1px 3px !important;
            vertical-align: top;
        }
        .pie-linea { line-height: 1.15; }
        .pie-lbl { font-weight: 700; }
    </style>
</head>
<body>
@php
    $secciones = $secciones ?? [];
    if ($secciones === [] && isset($materias, $estudiantes)) {
        $secciones = [[
            'cursoLabel' => $cursoLabel ?? '',
            'materias' => $materias,
            'estudiantes' => $estudiantes,
            'layout' => $layout ?? ['fontPt' => 4.5, 'paddingPx' => 0.8, 'lineHeight' => 1.1],
        ]];
    }
    $ano = $ano ?? ($secciones[0]['ano'] ?? null);
    $variosCursos = count($secciones) > 1;
@endphp

@forelse ($secciones as $sec)
    <div @class(['seccion-curso', 'seccion-curso--continuacion' => $variosCursos && ! $loop->first])>
        @include('pdf.partials.planilla-resumen-encabezado', [
            'pdfHeader' => $pdfHeader ?? null,
            'ano' => $ano,
            'cursoLabel' => $sec['cursoLabel'] ?? '',
        ])
        @include('pdf.partials.planilla-resumen-tabla-curso', [
            'materias' => $sec['materias'] ?? [],
            'estudiantes' => $sec['estudiantes'] ?? [],
            'layout' => $sec['layout'] ?? ['fontPt' => 4.5, 'paddingPx' => 0.8, 'lineHeight' => 1.1],
        ])
    </div>
@empty
    <p class="meta-curso">Sin cursos seleccionados</p>
@endforelse
</body>
</html>
