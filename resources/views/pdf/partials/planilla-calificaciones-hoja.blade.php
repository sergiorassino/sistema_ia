{{-- Una hoja A4: planilla de calificaciones de una materia. --}}
@php
    use App\Support\PromedioAnualCalificacionesSecundario;
    $filas = $filas ?? [];
    $layout = $layoutFilas ?? [
        'fontDataPt' => 6.2,
        'fontEcPt' => 6.1,
        'fontColPt' => 6.0,
        'espacioFilasPx' => 0.94,
        'paddingCeldaVertPx' => 1.4,
        'lineHeightFila' => 1.44,
    ];
    $padV = $layout['paddingCeldaVertPx'] ?? 1.2;
    $lh = $layout['lineHeightFila'] ?? 1.2;
    $styCeldaVert = sprintf('padding-top:%spx;padding-bottom:%spx;line-height:%s;', $padV, $padV, $lh);
    $styCeldaNombre = sprintf('padding:%spx 3px !important;line-height:%s;', $padV, $lh);
    $styCeldaOrd = sprintf('padding:%spx 1px !important;line-height:%s;', $padV, $lh);
    $styBayCol = sprintf('padding:%spx 1px !important;line-height:%s;', $padV, $lh);
    $blank = "\u{00A0}";
    $celda = static function (array $fila, string $col) use ($blank): string {
        $s = trim((string) ($fila[$col] ?? ''));
        return $s === '' ? $blank : $s;
    };
    $promCelda = static function (array $fila) use ($blank): string {
        $s = trim((string) ($fila['prom'] ?? ''));
        if ($s === '') {
            return $blank;
        }
        $n = str_replace(',', '.', $s);
        if (is_numeric($n)) {
            return number_format((float) $n, 2, ',', '');
        }
        return $s;
    };
    $pctCols = \App\Support\PlanillaCalificacionesSecundario::anchosColumnasPorcentaje();
    $wOrdPct = $pctCols['ord'];
    $wEcPct = $pctCols['ec'];
    $wEvPct = $pctCols['eval'][0];
    $wJisPct = $pctCols['jis'][0];
    $wDicPct = $pctCols['dic'];
    $wFebPct = $pctCols['feb'];
    $wPromPct = $pctCols['prom'];
    $wOrd = number_format($wOrdPct, 2, '.', '').'%';
    $wEc = number_format($wEcPct, 2, '.', '').'%';
    $wEv = number_format($wEvPct, 2, '.', '').'%';
    $wJis = number_format($wJisPct, 2, '.', '').'%';
    $wDic = number_format($wDicPct, 2, '.', '').'%';
    $wFeb = number_format($wFebPct, 2, '.', '').'%';
    $wProm = number_format($wPromPct, 2, '.', '').'%';
    $wOrdCell = 'width:'.$wOrd.';min-width:0;overflow:hidden;';
    $wCell = 'width:'.$wEc.';min-width:0;overflow:hidden;';
    $wEvCell = 'width:'.$wEv.';min-width:0;overflow:hidden;';
    $wJisCell = 'width:'.$wJis.';min-width:0;overflow:hidden;';
    $wDicCell = 'width:'.$wDic.';min-width:0;overflow:hidden;';
    $wFebCell = 'width:'.$wFeb.';min-width:0;overflow:hidden;';
    $wPromCell = 'width:'.$wProm.';min-width:0;overflow:hidden;';
    $filasLista = array_values($filas);
    $styFontEc = 'font-size:'.$layout['fontEcPt'].'pt;';
    $styFontData = 'font-size:'.$layout['fontDataPt'].'pt;';
    $styFontCol = 'font-size:'.$layout['fontColPt'].'pt;';
    $spacingFilas = $layout['espacioFilasPx'].'px';
    $mostrarEncabezado = $mostrarEncabezado ?? true;
@endphp

<div class="layer">
    @if ($mostrarEncabezado)
        @include('pdf.partials.header', ['header' => $pdfHeader ?? null])

        <p class="titulo">Planilla de calificaciones</p>
        <p class="subtitulo">
            @if (! empty($ano))
                Ciclo lectivo {{ $ano }}
            @endif
        </p>
    @endif

    <p class="meta">
        <strong class="meta-materia">{{ mb_strtoupper((string) ($materiaLabel ?? '')) }}</strong>
        @if (trim((string) ($cursoLabel ?? '')) !== '')
            &nbsp;·&nbsp;<strong class="meta-curso">{{ $cursoLabel }}</strong>
        @endif
        @if (trim((string) ($profesoresLinea ?? '')) !== '' && ($profesoresLinea ?? '') !== '—')
            <br><span>Prof: {{ $profesoresLinea }}</span>
        @endif
    </p>

    <table class="outer" cellspacing="0" width="100%" style="border-spacing:2px {{ $spacingFilas }};">
        <thead>
        <tr>
            <th class="bay-ord" style="{{ $wOrdCell }}">Nº</th>
            <th class="bay-ec" style="{{ $wCell }}">Estudiante</th>
            @for ($e = 1; $e <= 8; $e++)
                <th class="bay" style="{{ $wEvCell }}">
                    <table class="inner" cellspacing="0" width="100%">
                        <tr><th colspan="3">Eval. {{ $e }}</th></tr>
                        <tr>
                            <th>N</th>
                            <th>R1</th>
                            <th>R2</th>
                        </tr>
                    </table>
                </th>
            @endfor
            <th class="bay" style="{{ $wJisCell }}">
                <table class="inner" cellspacing="0" width="100%">
                    <tr><th colspan="2">JIS 1</th></tr>
                    <tr><th>N</th><th>R</th></tr>
                </table>
            </th>
            <th class="bay" style="{{ $wJisCell }}">
                <table class="inner" cellspacing="0" width="100%">
                    <tr><th colspan="2">JIS 2</th></tr>
                    <tr><th>N</th><th>R</th></tr>
                </table>
            </th>
            <th class="bay bay-col" style="{{ $wDicCell }}">Coloq.<br>Dic</th>
            <th class="bay bay-col" style="{{ $wFebCell }}">Coloq.<br>Feb</th>
            <th class="bay bay-col" style="{{ $wPromCell }}">Prom.<br>Final</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($filasLista as $idx => $fila)
            <tr class="fila-alumno">
                <td class="bay-ord" style="{{ $wOrdCell }}">
                    <table class="inner" cellspacing="0" width="100%">
                        <tr class="data">
                            <td class="celda-ord" style="{{ $styCeldaOrd }}{{ $styFontData }}">{{ $idx + 1 }}</td>
                        </tr>
                    </table>
                </td>
                <td class="bay-ec" style="{{ $wCell }}">
                    <table class="inner" cellspacing="0" width="100%">
                        <tr class="data">
                            <td class="celda-nombre" style="{{ $styCeldaNombre }}{{ $styFontEc }}">{{ mb_strtoupper((string) ($fila['alumno'] ?? '')) }}</td>
                        </tr>
                    </table>
                </td>
                @for ($e = 1; $e <= 8; $e++)
                    @php
                        $b = ($e - 1) * 3 + 1;
                        $c1 = 'ic'.str_pad((string) $b, 2, '0', STR_PAD_LEFT);
                        $c2 = 'ic'.str_pad((string) ($b + 1), 2, '0', STR_PAD_LEFT);
                        $c3 = 'ic'.str_pad((string) ($b + 2), 2, '0', STR_PAD_LEFT);
                        $camposEval = [$c1, $c2, $c3];
                        $desaprobado = PromedioAnualCalificacionesSecundario::bloqueDesaprobado($camposEval, $fila);
                    @endphp
                    <td @class(['bay', 'bay-desaprobado' => $desaprobado]) style="{{ $wEvCell }}">
                        <table class="inner" cellspacing="0" width="100%">
                            <tr class="data">
                                <td style="{{ $styCeldaVert }}{{ $styFontData }}">{{ $celda($fila, $c1) }}</td>
                                <td style="{{ $styCeldaVert }}{{ $styFontData }}">{{ $celda($fila, $c2) }}</td>
                                <td style="{{ $styCeldaVert }}{{ $styFontData }}">{{ $celda($fila, $c3) }}</td>
                            </tr>
                        </table>
                    </td>
                @endfor
                @php
                    $desapJis1 = PromedioAnualCalificacionesSecundario::bloqueDesaprobado(['ic25', 'ic26'], $fila);
                    $desapJis2 = PromedioAnualCalificacionesSecundario::bloqueDesaprobado(['ic27', 'ic28'], $fila);
                @endphp
                <td @class(['bay', 'bay-desaprobado' => $desapJis1]) style="{{ $wJisCell }}">
                    <table class="inner" cellspacing="0" width="100%">
                        <tr class="data">
                            <td style="{{ $styCeldaVert }}{{ $styFontData }}">{{ $celda($fila, 'ic25') }}</td>
                            <td style="{{ $styCeldaVert }}{{ $styFontData }}">{{ $celda($fila, 'ic26') }}</td>
                        </tr>
                    </table>
                </td>
                <td @class(['bay', 'bay-desaprobado' => $desapJis2]) style="{{ $wJisCell }}">
                    <table class="inner" cellspacing="0" width="100%">
                        <tr class="data">
                            <td style="{{ $styCeldaVert }}{{ $styFontData }}">{{ $celda($fila, 'ic27') }}</td>
                            <td style="{{ $styCeldaVert }}{{ $styFontData }}">{{ $celda($fila, 'ic28') }}</td>
                        </tr>
                    </table>
                </td>
                <td class="bay bay-col" style="{{ $wDicCell }}{{ $styBayCol }}{{ $styFontCol }}">{{ $celda($fila, 'dic') }}</td>
                <td class="bay bay-col" style="{{ $wFebCell }}{{ $styBayCol }}{{ $styFontCol }}">{{ $celda($fila, 'feb') }}</td>
                <td class="bay bay-col bay-prom" style="{{ $wPromCell }}{{ $styBayCol }}{{ $styFontCol }}">{{ $promCelda($fila) }}</td>
            </tr>
        @empty
            <tr>
                <td class="bay-ec" colspan="15" style="text-align:center;">Sin estudiantes con calificaciones para esta materia.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div class="pie-footer pie-footer--solo-firmas">
        <table class="pie-footer-tabla" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td class="pie-firmas-gutter">&nbsp;</td>
                <td class="pie-firma-izq" valign="bottom">
                    <div class="firma-bloque">
                        <div class="firma-linea"></div>
                        <p class="firma-label">Firma Preceptor/a</p>
                    </div>
                </td>
                <td class="pie-firma-spacer">&nbsp;</td>
                <td class="pie-firma-der" valign="bottom" align="right">
                    <div class="firma-bloque">
                        <div class="firma-linea"></div>
                        <p class="firma-label">Firma Director/a</p>
                    </div>
                </td>
                <td class="pie-firmas-gutter">&nbsp;</td>
            </tr>
        </table>
    </div>
</div>
