@php
    $h = is_array($pdfHeader ?? null) ? $pdfHeader : [];
    $logoFile = isset($h['logo_file']) && is_string($h['logo_file']) ? trim($h['logo_file']) : '';
    $insti = isset($h['insti']) && is_string($h['insti']) ? trim($h['insti']) : '';
    $direccion = isset($h['direccion']) && is_string($h['direccion']) ? trim($h['direccion']) : '';
    $localidad = isset($h['localidad']) && is_string($h['localidad']) ? trim($h['localidad']) : '';
    $cue = isset($h['cue']) && is_string($h['cue']) ? trim($h['cue']) : '';
    $ee = isset($h['ee']) && is_string($h['ee']) ? trim($h['ee']) : '';
    $lineaDir = trim($direccion.($direccion !== '' && $localidad !== '' ? ' — ' : '').$localidad);
    $lineaIds = trim(($cue !== '' ? "CUE: {$cue}" : '').(($cue !== '' && $ee !== '') ? '   ' : '').($ee !== '' ? "EE: {$ee}" : ''));
    $detalleDocumento = collect([
        $metaCiclo ?? '',
        $lineaDia ?? '',
        ! empty($turnoTitulo) ? 'Turno: '.$turnoTitulo : '',
    ])->map(fn ($v) => trim((string) $v))->filter()->implode(' · ');

    // Impreso sobre media A4 vertical / A5 (148 × 210 mm), centrada en bandeja A4.
    $altoImpresoMm = 210.0;
    $padTopMm = 8.0;
    $padBottomMm = 6.0;
    $altoUtilMm = $altoImpresoMm - $padTopMm - $padBottomMm;

    $renglonesManual = 12;
    $altoCabeceraMm = 16.5;
    $altoMetaMm = 10.0;
    $gapMm = 1.5;
    // Filas de título de ambas grillas: compactas para no desbordar la media hoja.
    $altoThMm = 3.0;
    $altoFilaManualMm = 4.0;

    $filasHorario = is_array($filasHorario ?? null) ? $filasHorario : [];
    $nFirmas = max(1, count($filasHorario));

    $altoBloqueManualMm = $altoThMm + ($renglonesManual * $altoFilaManualMm);
    $altoFijoMm = $altoCabeceraMm + $altoMetaMm + $gapMm
        + $altoBloqueManualMm + $gapMm + $altoThMm;
    // Holgura: el render DomPDF suele superar un poco las estimaciones de cabecera/meta.
    $holguraMm = 3.0;
    $altoCuerpoFirmasMm = max($nFirmas * 5.5, $altoUtilMm - $altoFijoMm - $holguraMm);
    $altoFilaFirmaMm = round(($altoCuerpoFirmasMm / $nFirmas) * 0.98, 2);

    $hManual = number_format($altoFilaManualMm, 2, '.', '');
    $hTh = number_format($altoThMm, 2, '.', '');
    $hFirma = number_format($altoFilaFirmaMm, 2, '.', '');
    $hImpreso = number_format($altoImpresoMm, 2, '.', '');
    $padTop = number_format($padTopMm, 2, '.', '');
    $padBottom = number_format($padBottomMm, 2, '.', '');
@endphp

<table class="pagina-margenes" cellspacing="0" cellpadding="0" border="0" style="width:15cm;max-width:15cm;height:{{ $hImpreso }}mm;margin:0 0 0 22mm;border-collapse:collapse;border-spacing:0;">
<tr>
<td class="celda-pagina" style="vertical-align:top;text-align:left;box-sizing:border-box;width:15cm;max-width:15cm;height:{{ $hImpreso }}mm;padding:{{ $padTop }}mm 8mm {{ $padBottom }}mm 8mm;border:0;margin:0;">

<div class="cabecera-institucional" style="width:calc(100% - 12px - 1.5pt);max-width:calc(100% - 12px - 1.5pt);overflow:hidden;">
    <table cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td class="logo-cell">
                @if ($logoFile !== '')
                    <img class="logo-img" src="{{ $logoFile }}" alt="">
                @endif
            </td>
            <td class="text-cell">
                <p class="insti">{{ $insti !== '' ? $insti : schoolNombre() }}</p>
                @if ($lineaDir !== '')
                    <p class="line">{{ $lineaDir }}</p>
                @endif
                @if ($lineaIds !== '')
                    <p class="line ids">{{ $lineaIds }}</p>
                @endif
            </td>
            <td class="spacer-cell"></td>
        </tr>
    </table>
</div>

<div class="contenido-formulario">
@php
    $wCol1 = 33.34;
    $wCol2 = 33.33;
    $wCol3 = 33.33;
@endphp
<table class="fila-meta" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td colspan="4" class="celda-titulo">
            <span style="font-weight:700;font-size:7.5pt;">{{ $subtitulo }}</span>
            @if ($detalleDocumento !== '')
                <span style="font-size:6.5pt;font-weight:normal;"> — {{ $detalleDocumento }}</span>
            @endif
        </td>
    </tr>
    @php $pMeta = '25.00'; @endphp
    <tr>
        <td class="meta-celda" style="width:{{ $pMeta }}%;min-width:0;max-width:{{ $pMeta }}%;white-space:nowrap;">
            <span class="rotulo-inline">Fecha:</span>
            <span>{{ $fechaTexto }}</span>
        </td>
        <td class="meta-celda" style="width:{{ $pMeta }}%;min-width:0;max-width:{{ $pMeta }}%;">
            <table class="meta-campo" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="meta-rotulo">Cantidad de alumnos:</td>
                    <td class="meta-linea">&nbsp;</td>
                </tr>
            </table>
        </td>
        <td class="meta-celda" style="width:{{ $pMeta }}%;min-width:0;max-width:{{ $pMeta }}%;">
            <table class="meta-campo" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="meta-rotulo">Ausentes:</td>
                    <td class="meta-linea">&nbsp;</td>
                </tr>
            </table>
        </td>
        <td class="meta-celda" style="width:{{ $pMeta }}%;min-width:0;max-width:{{ $pMeta }}%;">
            <table class="meta-campo" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="meta-rotulo">Presentes:</td>
                    <td class="meta-linea">&nbsp;</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@php
    $pA = number_format($wCol1, 2, '.', '');
    $pB = number_format($wCol2, 2, '.', '');
    $pC = number_format($wCol3, 2, '.', '');
@endphp
<table class="grid">
    <thead>
    <tr>
        <th style="width:{{ $pA }}%;min-width:0;max-width:{{ $pA }}%;height:{{ $hTh }}mm;overflow:hidden;">Estudiantes Ausentes</th>
        <th style="width:{{ $pB }}%;min-width:0;max-width:{{ $pB }}%;height:{{ $hTh }}mm;overflow:hidden;">Estudiantes Retirados</th>
        <th style="width:{{ $pC }}%;min-width:0;max-width:{{ $pC }}%;height:{{ $hTh }}mm;overflow:hidden;">Observaciones</th>
    </tr>
    </thead>
    <tbody>
    @for ($r = 0; $r < $renglonesManual; $r++)
        <tr>
            <td class="celda-manual" style="width:{{ $pA }}%;min-width:0;max-width:{{ $pA }}%;height:{{ $hManual }}mm;overflow:hidden;">&nbsp;</td>
            <td class="celda-manual" style="width:{{ $pB }}%;min-width:0;max-width:{{ $pB }}%;height:{{ $hManual }}mm;overflow:hidden;">&nbsp;</td>
            <td class="celda-manual" style="width:{{ $pC }}%;min-width:0;max-width:{{ $pC }}%;height:{{ $hManual }}mm;overflow:hidden;">&nbsp;</td>
        </tr>
    @endfor
    </tbody>
</table>

@php
    $wH = 14.00;
    $wE = 56.00;
    $wF = 30.00;
    $pH = number_format($wH, 2, '.', '');
    $pE = number_format($wE, 2, '.', '');
    $pF = number_format($wF, 2, '.', '');
@endphp

<table class="grid" style="margin-bottom:0;">
    <thead>
    <tr>
        <th style="width:{{ $pH }}%;min-width:0;max-width:{{ $pH }}%;height:{{ $hTh }}mm;overflow:hidden;">&nbsp;</th>
        <th style="width:{{ $pE }}%;min-width:0;max-width:{{ $pE }}%;height:{{ $hTh }}mm;overflow:hidden;">Espacios curriculares</th>
        <th style="width:{{ $pF }}%;min-width:0;max-width:{{ $pF }}%;height:{{ $hTh }}mm;overflow:hidden;">Firma del profesor</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($filasHorario as $fila)
        <tr>
            <td class="celda-hora" style="width:{{ $pH }}%;min-width:0;max-width:{{ $pH }}%;height:{{ $hFirma }}mm;overflow:hidden;">
                {{ $fila['etiquetaReloj'] }}
            </td>
            <td class="celda-espacio" style="width:{{ $pE }}%;min-width:0;max-width:{{ $pE }}%;height:{{ $hFirma }}mm;overflow:hidden;">
                @php $lineasEsp = preg_split("/\r\n|\n|\r/", (string) ($fila['espacio'] ?? '')) ?: []; @endphp
                @foreach ($lineasEsp as $ln)
                    @if (trim($ln) !== '')
                        <div>{{ $ln }}</div>
                    @endif
                @endforeach
            </td>
            <td class="celda-firma" style="width:{{ $pF }}%;min-width:0;max-width:{{ $pF }}%;height:{{ $hFirma }}mm;overflow:hidden;">&nbsp;</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>

</td>
</tr>
</table>
