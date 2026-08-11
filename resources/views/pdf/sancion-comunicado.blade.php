<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 15mm 12mm 15mm 12mm; }
        /* Ajuste de tipografías para acercarse al modelo TCPDF */
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; line-height: 1.25; color: #111; }
        .wrap { width: 100%; }
        .titulo-doc { font-weight: 700; font-size: 10.5pt; text-transform: uppercase; text-align: center; margin: 2px 0 7px 0; line-height: 1.15; }
        .alumno-linea { font-size: 10.5pt; text-align: left; margin: 0 0 5px 0; }
        .alumno-nombre { font-weight: 700; text-transform: uppercase; }
        .alumno-curso { font-weight: 400; text-transform: none; }
        .lugar-fecha { text-align: right; margin: 0 0 8px 0; font-size: 8pt; }
        p { margin: 0 0 5px 0; }
        /* Zona narrativa (motivo / solicitada por): letra más chica, negrita intacta */
        .zona-motivo { font-size: 8pt; line-height: 1.25; }
        .zona-motivo p { margin: 0 0 4px 0; }
        .label { font-weight: 700; }
        .totales { margin-top: 6px; font-size: 8pt; line-height: 1.25; }
        .totales .label { font-weight: 700; }
        .totales ul { margin: 4px 0 0 0; padding: 0; list-style: none; }
        .totales li { margin: 0; }
        .firmas { margin-top: 10mm; width: 100%; }
        .firma-linea { border-top: 1px solid #111; width: 72%; margin: 16px auto 0 auto; }
        .firma-txt { font-size: 7pt; text-align: center; margin-top: 3px; }
        .hr { border-top: 1px solid #111; margin: 16px 0; }
        .muted { color: #333; }
        .mt-3mm { margin-top: 3mm; }
        .acta-pagina { page-break-before: always; }
        .acta-titulo { font-weight: 700; font-size: 10.5pt; text-transform: uppercase; text-align: center; margin: 2px 0 10px 0; }
        .acta-cuerpo { font-size: 10pt; line-height: 1.35; text-align: justify; }
        .acta-cuerpo p { margin: 0 0 6px 0; }
        .acta-cuerpo ul, .acta-cuerpo ol { margin: 0 0 6px 18px; padding: 0; }
    </style>
</head>
<body>
<div class="wrap">
    {{-- BLOQUE 1 --}}
    @include('pdf.partials.header', ['header' => $pdfHeader ?? null])
    <p class="titulo-doc">COMUNICADO DE SEGUIMIENTO DISCIPLINARIO</p>

    <p class="alumno-linea">
        <span class="alumno-nombre">{{ $alumnoNombre }}</span>
        @if(trim($cursoLabel) !== '')
            <span class="alumno-curso"> de {{ $cursoLabel }}</span>
        @endif
    </p>
    <div class="zona-motivo">
        <p class="lugar-fecha">{{ $lineaLugarFecha }}</p>

        <p>Solicito que al/a la mencionado/a estudiante se le aplique una medida disciplinaria por:</p>
        <p><strong>{{ $motivo }}</strong></p>
        <p>Solicitada por: <strong>{{ $solicitadaPor !== '' ? $solicitadaPor : '—' }}</strong></p>
    </div>

    <div class="totales">
        <p>Hasta la fecha registra un total de:</p>
        <ul>
            <li>{{ (int) $totalApercib }} Apercibimientos</li>
            <li>{{ (int) $totalAmonest }} Amonestaciones</li>
        </ul>
    </div>

    <p class="muted mt-3mm">De acuerdo con las causas invocadas y antecedentes, aplíquese al/a la estudiante: <strong>{{ (int) $cantidad }} {{ $tipoSancion }}</strong></p>

    <table class="firmas" cellspacing="0" cellpadding="0">
        <tr>
            <td style="width:33.33%; padding-right:10px;">
                <div class="firma-linea"></div>
                <div class="firma-txt">Notificación del/de la estudiante</div>
            </td>
            <td style="width:33.33%; padding:0 10px;">
                <div class="firma-linea"></div>
                <div class="firma-txt">Notificación Padre/Madre/Responsable</div>
            </td>
            <td style="width:33.33%; padding-left:10px;">
                <div class="firma-linea"></div>
                <div class="firma-txt">Autoridad Responsable</div>
            </td>
        </tr>
    </table>

    <div class="hr"></div>

    {{-- BLOQUE 2 --}}
    <div class="zona-motivo">
        <p class="lugar-fecha">{{ $lineaLugarFecha }}</p>

        <p>
            Me dirijo a Uds. para comunicarles que el/la estudiante
            <span class="alumno-nombre">{{ $alumnoNombre }}</span>@if(trim($cursoLabel) !== '')<span class="alumno-curso"> de {{ $cursoLabel }}</span>@endif,
            ha sido sancionado/a con <strong>{{ (int) $cantidad }} {{ $tipoSancion }}</strong> por el siguiente motivo:
        </p>
        <p><strong>{{ $motivo }}</strong></p>
        <p>Solicitada por: <strong>{{ $solicitadaPor !== '' ? $solicitadaPor : '—' }}</strong></p>
    </div>

    <div class="totales">
        <p>Hasta la fecha registra un total de:</p>
        <ul>
            <li>{{ (int) $totalApercib }} Apercibimientos</li>
            <li>{{ (int) $totalAmonest }} Amonestaciones</li>
        </ul>
    </div>

    <table class="firmas" cellspacing="0" cellpadding="0">
        <tr>
            <td style="width:33.33%; padding-right:10px;">
                <div class="firma-linea"></div>
                <div class="firma-txt">Notificación del/de la Estudiante</div>
            </td>
            <td style="width:33.33%; padding:0 10px;">
                <div class="firma-linea"></div>
                <div class="firma-txt">Notificación Padre/Madre/Responsable</div>
            </td>
            <td style="width:33.33%; padding-left:10px;">
                <div class="firma-linea"></div>
                <div class="firma-txt">Autoridad Responsable</div>
            </td>
        </tr>
    </table>

    @if (! empty($actaHtml))
        <div class="acta-pagina">
            @include('pdf.partials.header', ['header' => $pdfHeader ?? null])
            <p class="acta-titulo">Acta</p>
            <div class="acta-cuerpo">
                {!! $actaHtml !!}
            </div>
        </div>
    @endif
</div>
</body>
</html>

