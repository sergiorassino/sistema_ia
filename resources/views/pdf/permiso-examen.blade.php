{{--
  Permiso de examen por alumno — DomPDF.
  Anchos de columna: docs/05-preferencias-y-convenciones.md §8
  Columnas: Nº | Espacios Curriculares | Curso | Plan | Cond. | Fecha | Calificación | Firma Docente
--}}
@php
    $wNro = 4;
    $wMateria = 28;
    $wCurso = 14;
    $wPlan = 8;
    $wCon = 6;
    $wFecha = 10;
    $wCalif = 12;
    $wFirma = 18;
    $cell = static fn (float $pct): string => sprintf(
        'width:%s%%;min-width:0;max-width:%s%%;overflow:hidden;',
        rtrim(rtrim(sprintf('%.4f', $pct), '0'), '.'),
        rtrim(rtrim(sprintf('%.4f', $pct), '0'), '.'),
    );
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 12mm 14mm 12mm 18mm; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            color: #000;
            margin: 0;
            padding: 0;
            font-size: 9pt;
        }
        .hoja { page-break-after: always; }
        .hoja:last-child { page-break-after: auto; }

        .caja-titulo-wrap { text-align: center; margin-bottom: 4mm; }
        .caja-titulo {
            display: inline-block;
            border: 0.75pt solid #000;
            border-radius: 4px;
            padding: 1.5mm 4mm;
            font-size: 11pt;
            font-weight: normal;
        }

        .insti {
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            margin: 0 0 4mm 0;
            line-height: 1.15;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3mm;
            font-size: 10pt;
        }
        .meta td { vertical-align: top; padding: 0; border: none; }
        .meta-izq { width: 55%; }
        .meta-der { width: 45%; text-align: right; }

        .intro {
            font-size: 9.5pt;
            line-height: 1.35;
            margin: 0 0 3mm 0;
            text-align: justify;
        }
        .intro strong { font-weight: bold; }

        table.permiso {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.permiso th, table.permiso td {
            border: 0.75pt solid #000;
            padding: 0 2px;
            vertical-align: middle;
            overflow: hidden;
            line-height: 1.1;
            font-size: 7pt;
        }
        table.permiso thead th {
            font-weight: normal;
            text-align: center;
            height: 5mm;
        }
        table.permiso tbody td {
            height: 5.5mm;
        }
        table.permiso td.col-nro { text-align: center; font-size: 6.5pt; }
        table.permiso td.col-materia {
            text-align: left;
            padding-left: 1.5mm;
            font-size: 7pt;
            white-space: nowrap;
        }
        table.permiso td.col-centro { text-align: center; }
        table.permiso td.col-fecha { text-align: center; letter-spacing: 1px; }

        .pie-lugar { margin-top: 5mm; font-size: 10pt; }
        .firmas {
            width: 100%;
            margin-top: 14mm;
            border-collapse: collapse;
        }
        .firmas td {
            width: 50%;
            vertical-align: top;
            text-align: center;
            font-size: 9pt;
            padding-top: 12mm;
            border: none;
        }

        .notas {
            margin-top: 8mm;
            font-size: 7.5pt;
            line-height: 1.35;
        }
    </style>
</head>
<body>
@foreach ($permisos as $permiso)
    <div class="hoja">
        <div class="caja-titulo-wrap">
            <span class="caja-titulo">Permiso de Examen</span>
        </div>

        <p class="insti">{{ $instiNombre }}</p>

        <table class="meta">
            <tr>
                <td class="meta-izq">{{ $etiquetaTurno }}</td>
                <td class="meta-der"><strong>Permiso Nº: {{ $permiso['numero'] }}</strong></td>
            </tr>
        </table>

        <p class="intro">
            Conste por el presente que el Alumno/a:
            <strong>{{ $permiso['nombreCompleto'] }}@if ($permiso['dni'] !== '') — D.N.I.: {{ $permiso['dni'] }}@endif</strong>
            está habilitado para rendir las asignaturas correspondientes al año de estudio que indica a continuación,
            lo que hizo en las fechas señaladas.
        </p>

        <table class="permiso">
            <thead>
                <tr>
                    <th style="{{ $cell($wNro) }}"></th>
                    <th style="{{ $cell($wMateria) }}">Espacios Curriculares</th>
                    <th style="{{ $cell($wCurso) }}">Curso</th>
                    <th style="{{ $cell($wPlan) }}">Plan</th>
                    <th style="{{ $cell($wCon) }}">Cond.</th>
                    <th style="{{ $cell($wFecha) }}">Fecha</th>
                    <th style="{{ $cell($wCalif) }}">Calificación</th>
                    <th style="{{ $cell($wFirma) }}">Firma Docente</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($permiso['filas'] as $fila)
                    <tr>
                        <td class="col-nro" style="{{ $cell($wNro) }}">{{ $fila['nro'] }}</td>
                        <td class="col-materia" style="{{ $cell($wMateria) }}">{{ $fila['materia'] }}</td>
                        <td class="col-centro" style="{{ $cell($wCurso) }}">{{ $fila['curso'] }}</td>
                        <td class="col-centro" style="{{ $cell($wPlan) }}">{{ $fila['plan'] }}</td>
                        <td class="col-centro" style="{{ $cell($wCon) }}">{{ $fila['condicion'] }}</td>
                        <td class="col-fecha" style="{{ $cell($wFecha) }}">/ /</td>
                        <td class="col-centro" style="{{ $cell($wCalif) }}"></td>
                        <td style="{{ $cell($wFirma) }}"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="pie-lugar">{{ $pieLugarFecha }}</p>

        <table class="firmas">
            <tr>
                <td>Sello</td>
                <td>Firma manuscrita de la Secretaría</td>
            </tr>
        </table>

        <div class="notas">
            <p><strong>Notas:</strong></p>
            <p>1) Para poder rendir examen, el alumno deberá presentar a la mesa examinadora, este permiso y sus documentos de identidad</p>
            <p>2) Los exámenes deberán ser hechos con tinta.</p>
        </div>
    </div>
@endforeach
</body>
</html>
