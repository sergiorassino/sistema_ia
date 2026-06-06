{{--
  Parte diario — hoja A4; una o más páginas (un curso por hoja).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 0;
            size: A4 portrait;
        }
        html, body {
            margin: 0;
            padding: 0;
        }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 7pt;
            line-height: 1;
            color: #000;
        }
        p {
            margin: 0;
            padding: 0;
        }
        .hoja-parte {
            page-break-after: always;
        }
        .hoja-parte:last-child {
            page-break-after: auto;
        }
        table.pagina-margenes {
            width: 15cm;
            max-width: 15cm;
            border-collapse: collapse;
            border-spacing: 0;
            margin: 0 0 0 22mm;
        }
        table.pagina-margenes > tr > td.celda-pagina {
            vertical-align: top;
            text-align: left;
            width: 15cm;
            max-width: 15cm;
            box-sizing: border-box;
            padding: 12mm 8mm 10mm 8mm;
            border: 0;
        }
        .cabecera-institucional {
            width: calc(100% - 12px - 1.5pt);
            max-width: calc(100% - 12px - 1.5pt);
            margin: 0 0 6px 0;
            border: 0.75pt solid #111;
            border-radius: 8px;
            padding: 4px 6px;
            overflow: hidden;
        }
        .cabecera-institucional table {
            width: 100%;
            border-collapse: collapse;
        }
        .cabecera-institucional td {
            border: 0;
            vertical-align: middle;
            padding: 0;
        }
        .cabecera-institucional .logo-cell,
        .cabecera-institucional .spacer-cell {
            width: 40px;
        }
        .cabecera-institucional .logo-img {
            max-height: 36px;
            max-width: 40px;
        }
        .cabecera-institucional .text-cell {
            text-align: center;
        }
        .cabecera-institucional .insti {
            font-weight: 700;
            font-size: 8.5pt;
            line-height: 1.1;
            margin: 0;
        }
        .cabecera-institucional .line {
            font-size: 6.5pt;
            line-height: 1.15;
            margin: 1px 0 0 0;
        }
        .cabecera-institucional .line.ids {
            font-size: 6pt;
        }
        .fila-meta {
            width: 100%;
            margin: 2px 0 4px 0;
            font-size: 6.5pt;
        }
        .fila-meta table { width: 100%; border-collapse: collapse; }
        .fila-meta td { vertical-align: middle; border: none; }
        .fila-meta td.celda-titulo { text-align: center; padding: 0 0 3px 0; }
        .fila-meta td.meta-celda {
            padding: 1px 3.5mm 0 3.5mm;
            box-sizing: border-box;
        }
        table.meta-campo {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }
        table.meta-campo td { border: none; padding: 0; vertical-align: bottom; }
        table.meta-campo .meta-rotulo {
            width: 1%;
            white-space: nowrap;
            font-weight: 700;
            padding-right: 2px;
        }
        table.meta-campo .meta-linea {
            border-bottom: 0.5pt dotted #333;
        }
        .rotulo-inline { font-weight: 700; white-space: nowrap; }
        .contenido-formulario {
            width: 100%;
            margin: 0;
            padding: 0;
        }
        table.grid,
        table.fila-meta {
            width: 100%;
            max-width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 4px;
            box-sizing: border-box;
        }
        table.grid th, table.grid td {
            border: 0.5pt solid #000;
            padding: 1px 2px;
            vertical-align: top;
            min-width: 0;
            overflow: hidden;
            word-wrap: break-word;
        }
        table.grid th {
            text-align: center;
            font-weight: 700;
            font-size: 6pt;
            padding: 2px;
        }
        .celda-manual { height: 11px; }
        .celda-hora {
            text-align: center;
            font-size: 5.8pt;
            font-weight: 700;
            padding: 1px 2px;
        }
        .celda-espacio {
            text-align: left;
            font-size: 6pt;
            line-height: 1.1;
        }
        .celda-firma { height: 14px; }
    </style>
</head>
<body>
@foreach ($paginas as $pagina)
    <div class="hoja-parte">
        @include('pdf.partials.parte-diario-preceptor-hoja', [
            'pdfHeader' => $pdfHeader,
            'metaCiclo' => $metaCiclo,
            'subtitulo' => $pagina['subtitulo'],
            'lineaDia' => $pagina['lineaDia'],
            'fechaTexto' => $pagina['fechaTexto'],
            'turnoTitulo' => $pagina['turnoTitulo'],
            'filasHorario' => $pagina['filasHorario'],
        ])
    </div>
@endforeach
</body>
</html>
