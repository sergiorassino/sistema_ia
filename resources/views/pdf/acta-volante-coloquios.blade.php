{{--
  Acta volante — DomPDF.
  Anchos de columna: docs/05-preferencias-y-convenciones.md §8
  Proporciones legacy (suma 170): 7+15+78+20+20+20+10
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 10mm 15mm 10mm 25mm; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .hoja { page-break-after: always; }
        .hoja:last-child { page-break-after: auto; }

        .insti {
            width: 100%;
            margin: 0 0 3mm 0;
            font-size: 12pt;
            font-weight: bold;
            text-align: center;
            line-height: 1.2;
        }

        .bloque-meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2mm;
        }
        .bloque-meta > tr > td { vertical-align: top; padding: 0; border: none; font-size: 10pt; line-height: 1.25; }
        .meta-izq { width: 65%; }
        .meta-der { width: 35%; text-align: right; }
        .meta-izq .lbl { font-weight: normal; }
        .meta-izq .val { font-weight: bold; }

        /* Cuadro título: div + border-radius (DomPDF; cf. pdf.partials.header). */
        .caja-acta-wrap { text-align: right; }
        .caja-acta {
            display: inline-block;
            border: 0.75pt solid #000;
            border-radius: 5px;
            padding: 1.15mm 2.5mm 0.95mm 2.5mm;
            font-style: italic;
            font-size: 10pt;
            line-height: 1.05;
            text-align: center;
        }
        .meta-fecha { margin-top: 2mm; font-size: 9pt; line-height: 1.25; text-align: left; }

        .linea-doble { border: none; border-top: 1px solid #000; margin: 1mm 0 0 0; height: 0; }
        .linea-doble + .linea-doble { margin-top: 1mm; margin-bottom: 1mm; }

        table.acta {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        /* Encabezado legacy FPDF (5mm / 3.5mm) −10 %. Cuerpo: ~5.0mm para llenar A4 (40 filas). */
        table.acta th, table.acta td {
            border: 1px solid #000;
            padding: 0 1.8px;
            vertical-align: middle;
            overflow: hidden;
            line-height: 1;
        }
        table.acta thead tr:first-child th { height: 4.5mm; max-height: 4.5mm; font-size: 6pt; font-weight: normal; text-align: center; }
        table.acta thead th[rowspan="2"] { height: 7.65mm; max-height: 7.65mm; }
        table.acta thead tr.sub th { height: 3.15mm; max-height: 3.15mm; font-size: 6pt; }
        table.acta tbody td { height: 5.0mm; max-height: 5.0mm; }
        table.acta tbody td.col-nro { font-size: 6pt; text-align: center; }
        table.acta tbody td.col-dni { font-size: 7pt; text-align: center; }
        table.acta tbody td.col-nom {
            font-size: 8pt;
            text-align: left;
            padding-left: 2mm;
            white-space: nowrap;
            overflow: hidden;
        }
        table.acta tbody td.col-nota { font-size: 7pt; text-align: center; }
        table.acta tbody td.col-perm { font-size: 7pt; text-align: center; }

        .nota-secretario { width: 100%; margin: 2mm 0 0 0; font-size: 6pt; text-align: center; }
        table.firmas {
            width: 100%;
            margin: 15mm 0 0 0;
            border-collapse: collapse;
            font-size: 7pt;
            line-height: 1.2;
        }
        table.firmas td {
            padding: 0 3mm;
            border: none;
            text-align: center;
            vertical-align: top;
            white-space: nowrap;
        }
        table.firmas td.firma-pres { width: 36%; }
        table.firmas td.firma-voc { width: 32%; }
        .firma-linea {
            border-bottom: 0.6pt dotted #000;
            height: 1mm;
            margin: 0 0 1.2mm 0;
            line-height: 1;
            font-size: 1pt;
        }
        .firma-lbl { font-size: 7pt; }
        .pie-tabla { width: 100%; border-collapse: collapse; margin-top: 2mm; font-size: 8pt; }
        .pie-tabla td { border: none; padding: 0; vertical-align: top; line-height: 1.3; }
        .pie-fecha { width: 58%; }
        .pie-totales { width: 42%; text-align: left; padding-left: 3mm; }
    </style>
</head>
<body>
@php
    $filasPorActa = (int) ($filasPorActa ?? \App\Support\ActaVolanteColoquiosSecundario::FILAS_POR_ACTA);
    $blank = "\u{00A0}";
    $base = 170;
    $pct = static fn (int $mm): string => number_format(($mm / $base) * 100, 4, '.', '').'%';
    $sty = static fn (int $mm): string => 'width:'.$pct($mm).';min-width:0;max-width:'.$pct($mm).';overflow:hidden;';
    $wNro = $sty(7);
    $wDni = $sty(15);
    $wNom = $sty(78);
    $wNota = $sty(20);
    $wPerm = $sty(10);
    $wCalif = $sty(60);
@endphp

@foreach ($actas as $idx => $acta)
    @php $filasAlumnos = $acta['filas'] ?? []; @endphp

    <div @class(['hoja' => $idx < count($actas) - 1])>
        @if (trim((string) ($instiNombre ?? '')) !== '')
            <p class="insti">{{ $instiNombre }}</p>
        @endif

        <table class="bloque-meta" cellspacing="0" cellpadding="0">
            <tr>
                <td class="meta-izq">
                    <div><span class="lbl">Alumnos condición:</span> {{ $acta['condicionLabel'] ?? $condicionLabel ?? '' }}</div>
                    <div><span class="lbl">Materia:</span> <span class="val">{{ $acta['materiaLabel'] ?? '' }}</span></div>
                    <div><span class="lbl">Curso:</span> <span class="val">{{ $acta['cursoLabel'] ?? '' }}</span></div>
                </td>
                <td class="meta-der">
                    <div class="caja-acta-wrap">
                        <div class="caja-acta">{{ $tituloCajaActa ?? 'Acta Volante de Coloquios' }}</div>
                    </div>
                    <div class="meta-fecha">Fecha: ........../.........../..........</div>
                    <div class="meta-fecha">Tomo: ...........&nbsp;&nbsp;Folio: ...........</div>
                </td>
            </tr>
        </table>

        <hr class="linea-doble">
        <hr class="linea-doble">

        <table class="acta" cellspacing="0" cellpadding="0">
            <thead>
                <tr>
                    <th rowspan="2" style="{{ $wNro }}">Nº</th>
                    <th rowspan="2" style="{{ $wDni }}">D.N.I.</th>
                    <th rowspan="2" style="{{ $wNom }}">Apellido y Nombres</th>
                    <th colspan="3" style="{{ $wCalif }}">Calificaciones</th>
                    <th rowspan="2" style="{{ $wPerm }}">Nº Perm.</th>
                </tr>
                <tr class="sub">
                    <th style="{{ $wNota }}">Escrito</th>
                    <th style="{{ $wNota }}">Oral</th>
                    <th style="{{ $wNota }}">Prom</th>
                </tr>
            </thead>
            <tbody>
                @for ($n = 1; $n <= $filasPorActa; $n++)
                    @php $fila = $filasAlumnos[$n - 1] ?? null; @endphp
                    <tr>
                        <td class="col-nro" style="{{ $wNro }}">{{ $n }}</td>
                        <td class="col-dni" style="{{ $wDni }}">{{ isset($fila) && ($fila['dni'] ?? '') !== '' ? $fila['dni'] : $blank }}</td>
                        <td class="col-nom" style="{{ $wNom }}">{{ isset($fila) ? ($fila['nombre'] ?? '') : $blank }}</td>
                        <td class="col-nota" style="{{ $wNota }}">{{ $blank }}</td>
                        <td class="col-nota" style="{{ $wNota }}">{{ $blank }}</td>
                        <td class="col-nota" style="{{ $wNota }}">{{ $blank }}</td>
                        <td class="col-perm" style="{{ $wPerm }}">{{ $blank }}</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <p class="nota-secretario">A continuación del último alumno deberá firmar el secretario</p>

        <table class="firmas" cellspacing="0" cellpadding="0">
            <tr>
                <td class="firma-pres">
                    <div class="firma-linea">&nbsp;</div>
                    <div class="firma-lbl">Presidente (firma y aclaración)</div>
                </td>
                <td class="firma-voc">
                    <div class="firma-linea">&nbsp;</div>
                    <div class="firma-lbl">Vocal (firma y aclaración)</div>
                </td>
                <td class="firma-voc">
                    <div class="firma-linea">&nbsp;</div>
                    <div class="firma-lbl">Vocal (firma y aclaración)</div>
                </td>
            </tr>
        </table>

        <table class="pie-tabla" cellspacing="0" cellpadding="0">
            <tr>
                <td class="pie-fecha">......................&nbsp;&nbsp;de&nbsp;&nbsp;................................&nbsp;&nbsp;de&nbsp;&nbsp;20...................</td>
                <td class="pie-totales">
                    Total de Alumnos: ......................<br>
                    Aprobados: ......................<br>
                    Aplazados: ......................<br>
                    Ausentes: .......................
                </td>
            </tr>
        </table>
    </div>
@endforeach
</body>
</html>
