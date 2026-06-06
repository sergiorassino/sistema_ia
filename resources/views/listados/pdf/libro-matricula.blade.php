<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <style>

        @page {

            margin: 13mm 5mm 12mm 5mm;

        }

        body {

            font-family: DejaVu Sans, sans-serif;

            font-size: 7pt;

            color: #111;

            margin: 0;

            padding: 0;

        }

        .libro-membrete {

            border: 0.75pt solid #111;

            text-align: center;

            padding: 5px 8px 4px 8px;

            margin-bottom: 4px;

            page-break-inside: avoid;

        }

        .libro-membrete .membrete-insti {

            font-size: 11pt;

            font-weight: bold;

            margin: 0 0 3px 0;

            letter-spacing: 0.02em;

        }

        .libro-membrete .membrete-titulo {

            font-size: 9.5pt;

            font-weight: normal;

            margin: 0;

        }

        table.libro {

            width: 100%;

            border-collapse: collapse;

            table-layout: fixed;

        }

        table.libro thead {

            display: table-header-group;

        }

        table.libro tbody tr {

            page-break-inside: avoid;

        }

        table.libro th,

        table.libro td {

            border: 0.75pt solid #111;

            padding: 1px 2px;

            vertical-align: top;

            line-height: 1.1;

        }

        table.libro thead tr.fila-columnas {

            background-color: #c8c8c8;

        }

        table.libro thead tr.fila-columnas th {

            font-size: 6.5pt;

            font-weight: bold;

            text-align: center;

        }

        table.libro td {

            font-size: 6.5pt;

            word-wrap: break-word;

        }

        table.libro td.txt-left { text-align: left; }

        table.libro td.txt-center { text-align: center; }

        /* DomPDF: mismo width en th y td; nowrap sin overflow (Cur en una línea). */

        table.libro th.col-nowrap,

        table.libro td.col-nowrap {

            white-space: nowrap;

            word-wrap: normal;

        }

        table.libro th.col-fecha-matr,

        table.libro td.col-fecha-matr { width: 5%; }

        table.libro th.col-estudiante,

        table.libro td.col-estudiante { width: 16%; }

        table.libro th.col-edad,

        table.libro td.col-edad { width: 2.5%; }

        table.libro th.col-dni,

        table.libro td.col-dni { width: 5.5%; }

        table.libro th.col-domicilio,

        table.libro td.col-domicilio { width: 18%; }

        table.libro th.col-fecha-nac,

        table.libro td.col-fecha-nac { width: 5%; }

        table.libro th.col-lugar,

        table.libro td.col-lugar { width: 13%; }

        table.libro th.col-padre,

        table.libro td.col-padre { width: 12%; }

        table.libro th.col-madre,

        table.libro td.col-madre { width: 12%; }

        table.libro th.col-cur,

        table.libro td.col-cur { width: 11%; }

        tr.fila-vacia td {

            height: 11px;

        }

        .totales {

            margin-top: 8px;

            font-size: 9pt;

            line-height: 1.4;

            page-break-inside: avoid;

            white-space: nowrap;

        }

        .totales span {

            margin-right: 28px;

        }

        .totales span:last-child {

            margin-right: 0;

        }

        .hoja-manual {

            page-break-before: always;

        }

    </style>

</head>

<body>

@php

    $listaFilas = $filas ?? [];

    $filasHojaManual = (int) ($filasHojaManual ?? 28);

    $libroMatriculaColumnas = $libroMatriculaColumnas ?? \App\Support\Listados\LibroMatriculaPdfColumnas::todas();

    $inscriptosAlFmt = $inscriptosAl instanceof \Carbon\Carbon

        ? $inscriptosAl->format('d/m/Y')

        : '';

@endphp



@include('listados::pdf.partials.libro-matricula-membrete', ['insti' => $insti, 'ano' => $ano])



<table class="libro" cellspacing="0" cellpadding="0">

    @include('listados::pdf.partials.libro-matricula-colgroup')

    @include('listados::pdf.partials.libro-matricula-thead')

    <tbody>

        @foreach ($listaFilas as $fila)

            @include('listados::pdf.partials.libro-matricula-fila', ['fila' => $fila])

        @endforeach

    </tbody>

</table>



<div class="totales">

    <span>Alumnos inscriptos al: {{ $inscriptosAlFmt }}</span>

    <span>{{ $totales['total'] ?? 0 }}</span>

    <span>Varones: {{ $totales['varones'] ?? 0 }}</span>

    <span>Mujeres: {{ $totales['mujeres'] ?? 0 }}</span>

    <span>Otros: {{ $totales['otros'] ?? 0 }}</span>

</div>



<div class="hoja-manual">

    @include('listados::pdf.partials.libro-matricula-membrete', ['insti' => $insti, 'ano' => $ano])

    <table class="libro" cellspacing="0" cellpadding="0">

        @include('listados::pdf.partials.libro-matricula-colgroup')

        @include('listados::pdf.partials.libro-matricula-thead')

        <tbody>

            @for ($i = 0; $i < $filasHojaManual; $i++)

                @include('listados::pdf.partials.libro-matricula-fila', ['filaVacia' => true])

            @endfor

        </tbody>

    </table>

</div>

</body>

</html>

