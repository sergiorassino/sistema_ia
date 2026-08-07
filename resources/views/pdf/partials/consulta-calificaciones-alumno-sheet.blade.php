@php
    $tituloDocumento = $tituloDocumento ?? 'Consulta de Calificaciones';
    $mostrarMarcaAgua = $mostrarMarcaAgua ?? true;
    $mostrarFirmas = $mostrarFirmas ?? false;
@endphp
<div class="layer">
    @include('pdf.partials.header', ['header' => $pdfHeader ?? null])

    <p class="titulo">{{ $tituloDocumento }}</p>
    <p class="subtitulo">
        @if (! empty($consulta['anoLectivo']))
            Ciclo lectivo {{ $consulta['anoLectivo'] }}
        @endif
    </p>

    <p class="meta">
        <strong class="meta-alumno">{{ $consulta['alumnoLinea'] }}</strong>
        @if (trim($consulta['dni']) !== '')
            &nbsp;Â·&nbsp;D.N.I. {{ $consulta['dni'] }}
        @endif
        @if (trim($consulta['cursoLabel']) !== '')
            &nbsp;Â·&nbsp;<strong class="meta-curso">{{ $consulta['cursoLabel'] }}</strong>
        @endif
    </p>

    @php
        $blank = "\u{00A0}";
        $ic = static function (object $row, string $col) use ($blank): string {
            $s = trim((string) ($row->{$col} ?? ''));
            return $s === '' ? $blank : $s;
        };
        $prom = static function (object $row) use ($blank): string {
            $s = trim((string) ($row->calif ?? ''));
            if ($s === '') {
                return $blank;
            }
            $n = str_replace(',', '.', $s);
            if (is_numeric($n)) {
                return number_format((float) $n, 2, ',', '');
            }
            return $s;
        };
        /*
         * Anchos en % en cada th/td (Dompdf).
         * Eval: 10% menos que 8.1% base, y un achique extra del bloque N+R1+R2; lo ahorrado extra va solo a Prom. Final.
         * El primer ahorro (8Ã—0.81%) sigue repartido en Dic/Feb/Prom por igual (+2.16% c/u en coloquios y prom).
         */
        $wEvPctNarrow = 7.05;
        $wEvPctBase = 8.1 * 0.9;
        $wEv = number_format($wEvPctNarrow, 2, '.', '').'%';
        $freedEval = 8 * 8.1 * 0.1;
        $addColoq = $freedEval / 3;
        $toPromOnly = 8 * ($wEvPctBase - $wEvPctNarrow);
        $wEc = '19.54%';
        $wJis = '5.8%';
        $wDic = number_format(1.8 + $addColoq, 2, '.', '').'%';
        $wFeb = number_format(1.8 + $addColoq, 2, '.', '').'%';
        $wProm = number_format(1.26 + $addColoq + $toPromOnly, 2, '.', '').'%';
        $wCell = 'width:'.$wEc.';min-width:0;overflow:hidden;';
        $wEvCell = 'width:'.$wEv.';min-width:0;overflow:hidden;';
        $wJisCell = 'width:'.$wJis.';min-width:0;overflow:hidden;';
        $wDicCell = 'width:'.$wDic.';min-width:0;overflow:hidden;';
        $wFebCell = 'width:'.$wFeb.';min-width:0;overflow:hidden;';
        $wPromCell = 'width:'.$wProm.';min-width:0;overflow:hidden;';
    @endphp

    <div class="sheet-wrap">
    <table class="outer" cellspacing="0" width="100%">
        <thead>
        <tr>
            <th class="bay-ec" style="{{ $wCell }}">Espacio<br>Curricular</th>
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
        @forelse ($consulta['rows'] as $row)
            <tr>
                <td class="bay-ec" style="{{ $wCell }}">{{ $row->espacio_curricular ?? '' }}</td>
                @for ($e = 1; $e <= 8; $e++)
                    @php
                        $b = ($e - 1) * 3 + 1;
                        $c1 = 'ic'.str_pad((string) $b, 2, '0', STR_PAD_LEFT);
                        $c2 = 'ic'.str_pad((string) ($b + 1), 2, '0', STR_PAD_LEFT);
                        $c3 = 'ic'.str_pad((string) ($b + 2), 2, '0', STR_PAD_LEFT);
                    @endphp
                    <td class="bay" style="{{ $wEvCell }}">
                        <table class="inner" cellspacing="0" width="100%">
                            <tr class="data">
                                <td>{{ $ic($row, $c1) }}</td>
                                <td>{{ $ic($row, $c2) }}</td>
                                <td>{{ $ic($row, $c3) }}</td>
                            </tr>
                        </table>
                    </td>
                @endfor
                <td class="bay" style="{{ $wJisCell }}">
                    <table class="inner" cellspacing="0" width="100%">
                        <tr class="data">
                            <td>{{ $ic($row, 'ic25') }}</td>
                            <td>{{ $ic($row, 'ic26') }}</td>
                        </tr>
                    </table>
                </td>
                <td class="bay" style="{{ $wJisCell }}">
                    <table class="inner" cellspacing="0" width="100%">
                        <tr class="data">
                            <td>{{ $ic($row, 'ic27') }}</td>
                            <td>{{ $ic($row, 'ic28') }}</td>
                        </tr>
                    </table>
                </td>
                <td class="bay bay-col" style="{{ $wDicCell }}">{{ $ic($row, 'dic') }}</td>
                <td class="bay bay-col" style="{{ $wFebCell }}">{{ $ic($row, 'feb') }}</td>
                <td class="bay bay-col bay-prom" style="{{ $wPromCell }}">{{ $prom($row) }}</td>
            </tr>
        @empty
            <tr>
                <td class="bay-ec" colspan="14" style="text-align:center;">Sin calificaciones registradas para esta matrÃ­cula.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
    @if ($mostrarMarcaAgua)
    <div class="wm-overlay">
        <div class="wm">SIN VALOR LEGAL</div>
    </div>
    @endif
    </div>

    @php
        $adeudadas = $consulta['materias_adeudadas'] ?? [];
        $tercerMateria = $consulta['tercer_materia'] ?? [];
        $itemsBoletin = $consulta['items_boletin'] ?? [];
        $hayPieTexto = count($adeudadas) > 0 || count($tercerMateria) > 0 || count($itemsBoletin) > 0;
    @endphp
    @if (! $mostrarFirmas && count($adeudadas) > 0)
        <div class="adeu">
            <p class="adeu-title">MATERIAS PREVIAS:</p>
            <p class="adeu-body">@foreach ($adeudadas as $a){{ $a->linea }}@if (! $loop->last) - @endif@endforeach</p>
        </div>
    @endif

    @if (! $mostrarFirmas && count($tercerMateria) > 0)
        @include('pdf.partials.consulta-calificaciones-tercer-materia-pie', [
            'tercerMateria' => $tercerMateria,
            'conFirmas' => false,
            'blank' => $blank,
        ])
    @endif

    @if (! $mostrarFirmas && count($itemsBoletin) > 0)
        <div class="disc">
            @foreach ($itemsBoletin as $it)
                @php
                    $pres = \App\Support\ConsultaCalificacionesAlumno::presentacionItemBoletin($it);
                @endphp
                <p @class(['disc-item-tight' => $pres['tight']])><span class="disc-lbl">{{ $it->etiqueta }}:</span> @if ($pres['mostrar']){{ $pres['texto'] }}@else{{ $blank }}@endif</p>
            @endforeach
        </div>
    @endif

    @if ($mostrarFirmas)
        <div @class(['pie-footer', 'pie-footer--solo-firmas' => ! $hayPieTexto])>
            <table class="pie-footer-tabla" cellspacing="0" cellpadding="0">
                <tr>
                    @if ($hayPieTexto)
                    <td class="pie-texto" valign="top">
                        @if (count($adeudadas) > 0)
                            <div class="adeu adeu--con-firmas">
                                <p class="adeu-title">MATERIAS PREVIAS:</p>
                                <p class="adeu-body">@foreach ($adeudadas as $a){{ $a->linea }}@if (! $loop->last) - @endif@endforeach</p>
                            </div>
                        @endif
                        @if (count($tercerMateria) > 0)
                            @include('pdf.partials.consulta-calificaciones-tercer-materia-pie', [
                                'tercerMateria' => $tercerMateria,
                                'conFirmas' => true,
                                'blank' => $blank,
                            ])
                        @endif
                        @if (count($itemsBoletin) > 0)
                            <div class="disc disc--con-firmas">
                                @foreach ($itemsBoletin as $it)
                                    @php
                                        $pres = \App\Support\ConsultaCalificacionesAlumno::presentacionItemBoletin($it);
                                    @endphp
                                    <p @class(['disc-item-tight' => $pres['tight']])><span class="disc-lbl">{{ $it->etiqueta }}:</span> @if ($pres['mostrar']){{ $pres['texto'] }}@else{{ $blank }}@endif</p>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    @endif
                    <td class="pie-firmas-cel" valign="bottom">
                        <div class="firma-bloque">
                            <table class="firma-tabla" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td class="firma-cel firma-cel-padre" style="padding-left:20mm;">
                                        <div class="firma-linea"></div>
                                        <p class="firma-label">Firma Padre / Madre / Tutor</p>
                                    </td>
                                    <td class="firma-sep" style="width:20mm;min-width:20mm;max-width:20mm;">&nbsp;</td>
                                    <td class="firma-cel">
                                        <div class="firma-linea"></div>
                                        <p class="firma-label">Firma Directivo</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    @endif

</div>
