@php
    $materias = $materias ?? [];
    $estudiantes = $estudiantes ?? [];
    $layout = $layout ?? ['fontPt' => 4.5, 'paddingPx' => 0.8, 'lineHeight' => 1.1];
    $fontPt = $layout['fontPt'] ?? 4.5;
    $pad = $layout['paddingPx'] ?? 0.8;
    $lh = $layout['lineHeight'] ?? 1.1;
    $styCell = sprintf('font-size:%spt;padding:%spx %spx;line-height:%s;', $fontPt, $pad, $pad * 0.5, $lh);
    $blank = "\u{00A0}";
    $nMat = count($materias);
    $colsMateria = max(1, $nMat) * 4;
    $pctNom = 17.5;
    $pctOrd = 2.2;
    $wOrd = number_format($pctOrd, 2, '.', '').'%';
    $wNom = number_format($pctNom, 2, '.', '').'%';
    $pctSubCol = (100 - $pctOrd - $pctNom) / max(1, $colsMateria);
    $wSubCol = number_format($pctSubCol, 4, '.', '').'%';
    $wMat = number_format($pctSubCol * 4, 4, '.', '').'%';
    $wPar = number_format($pctSubCol * 2, 4, '.', '').'%';
    $colSpanTotal = 2 + $colsMateria;
    $styWOrd = 'width:'.$wOrd.';min-width:'.$wOrd.';max-width:'.$wOrd.';';
    $styWNom = 'width:'.$wNom.';min-width:'.$wNom.';max-width:'.$wNom.';';
    $styWSub = 'width:'.$wSubCol.';min-width:'.$wSubCol.';max-width:'.$wSubCol.';';
    $styWPar = 'width:'.$wPar.';min-width:'.$wPar.';max-width:'.$wPar.';';
    $styWPie = 'width:'.number_format(100 - $pctOrd - $pctNom, 4, '.', '').'%;';
    $styWMat = 'width:'.$wMat.';min-width:'.$wMat.';max-width:'.$wMat.';';

    $renderCelda = static function (array $cel) use ($styCell, $styWSub, $blank): string {
        $txt = trim((string) ($cel['texto'] ?? ''));
        if ($txt === '') {
            $txt = $blank;
        }
        $cls = 'celda-nota';
        if (! empty($cel['gris'])) {
            $cls .= ' celda-gris';
        }
        if (! empty($cel['rojo'])) {
            $cls .= ' celda-rojo';
        }

        $display = ($txt === '' || $txt === $blank) ? $blank : e($txt);

        return '<td class="'.$cls.'" style="'.$styWSub.$styCell.'">'.$display.'</td>';
    };
@endphp

<table class="resumen" cellspacing="0">
    <colgroup>
        <col style="width:{{ $wOrd }}">
        <col style="width:{{ $wNom }}">
        @for ($i = 0; $i < $colsMateria; $i++)
            <col style="width:{{ $wSubCol }}">
        @endfor
    </colgroup>
    <thead>
    <tr>
        <th style="{{ $styWOrd }}">Nº</th>
        <th style="{{ $styWNom }}">Estudiante</th>
        @foreach ($materias as $m)
            <th colspan="4" class="col-mat" style="{{ $styWMat }}">{{ $m['abrev'] ?? '—' }}</th>
        @endforeach
    </tr>
    </thead>
@forelse ($estudiantes as $est)
    <tbody class="bloque-alumno">
        @php
            $matCells = $est['materias'] ?? [];
            $res = $est['resumen'] ?? [];
            $partesPie = [];
            $numRep = (int) ($res['numRep'] ?? 0);
            $clsRep = $numRep > 0 ? 'pie-rep-rojo' : '';
            $partesPie[] = '<span class="'.$clsRep.'"><span class="pie-lbl">Nº Rep:</span> '.$numRep.'</span>';
            if (trim((string) ($res['inas'] ?? '')) !== '') {
                $partesPie[] = '<span class="pie-lbl">Inas:</span> '.e($res['inas']);
            }
            if (trim((string) ($res['amon'] ?? '')) !== '') {
                $partesPie[] = '<span class="pie-lbl">Amon:</span> '.e($res['amon']);
            }
            if (trim((string) ($res['edFi'] ?? '')) !== '') {
                $partesPie[] = '<span class="pie-lbl">Ed.Fi:</span> '.e($res['edFi']);
            }
            if (trim((string) ($res['promGral'] ?? '')) !== '') {
                $partesPie[] = '<span class="pie-lbl">Prom.Gral:</span> '.e($res['promGral']);
            }
            if (trim((string) ($res['previas'] ?? '')) !== '') {
                $partesPie[] = '<span class="pie-lbl">Previas:</span> '.e($res['previas']);
            }
            $lineaPie = implode(' — ', $partesPie);
        @endphp
        @if (! $loop->first)
            <tr class="sep-alumno"><td colspan="{{ $colSpanTotal }}">&nbsp;</td></tr>
        @endif
        <tr>
            <td class="col-ord" style="{{ $styWOrd.$styCell }}">{{ $est['ord'] ?? '' }}</td>
            <td class="col-nom" style="{{ $styWNom.$styCell }}">{{ mb_strtoupper((string) ($est['alumno'] ?? '')) }}</td>
            @foreach ($materias as $m)
                @php $c = $matCells[$m['id']] ?? []; $mods = $c['modulos'] ?? []; @endphp
                {!! $renderCelda($mods[0] ?? ['texto' => '', 'rojo' => false, 'gris' => false]) !!}
                {!! $renderCelda($mods[1] ?? ['texto' => '', 'rojo' => false, 'gris' => false]) !!}
                {!! $renderCelda($mods[2] ?? ['texto' => '', 'rojo' => false, 'gris' => false]) !!}
                {!! $renderCelda($mods[3] ?? ['texto' => '', 'rojo' => false, 'gris' => false]) !!}
            @endforeach
        </tr>
        <tr>
            <td class="col-ord col-vacia" style="{{ $styWOrd.$styCell }}">{{ $blank }}</td>
            <td class="col-nom col-vacia" style="{{ $styWNom.$styCell }}">{{ $blank }}</td>
            @foreach ($materias as $m)
                @php $c = $matCells[$m['id']] ?? []; $mods = $c['modulos'] ?? []; @endphp
                {!! $renderCelda($mods[4] ?? ['texto' => '', 'rojo' => false, 'gris' => false]) !!}
                {!! $renderCelda($mods[5] ?? ['texto' => '', 'rojo' => false, 'gris' => false]) !!}
                {!! $renderCelda($mods[6] ?? ['texto' => '', 'rojo' => false, 'gris' => false]) !!}
                {!! $renderCelda($mods[7] ?? ['texto' => '', 'rojo' => false, 'gris' => false]) !!}
            @endforeach
        </tr>
        <tr>
            <td class="col-ord col-vacia" style="{{ $styWOrd.$styCell }}">{{ $blank }}</td>
            <td class="col-nom col-vacia" style="{{ $styWNom.$styCell }}">{{ $blank }}</td>
            @foreach ($materias as $m)
                @php $c = $matCells[$m['id']] ?? []; @endphp
                {!! $renderCelda($c['jis1'] ?? ['texto' => '', 'rojo' => false, 'gris' => false]) !!}
                {!! $renderCelda($c['jis2'] ?? ['texto' => '', 'rojo' => false, 'gris' => false]) !!}
                <td colspan="2" class="celda-nota celda-prom" style="{{ $styWPar.$styCell }}">
                    @php $pa = trim((string) ($c['promAnual'] ?? '')); @endphp
                    {{ $pa !== '' ? $pa : $blank }}
                </td>
            @endforeach
        </tr>
        <tr>
            <td class="col-ord col-vacia" style="{{ $styWOrd.$styCell }}">{{ $blank }}</td>
            <td class="col-nom col-vacia" style="{{ $styWNom.$styCell }}">{{ $blank }}</td>
            @foreach ($materias as $m)
                @php
                    $c = $matCells[$m['id']] ?? [];
                    $dic = trim((string) ($c['dic'] ?? ''));
                    $feb = trim((string) ($c['feb'] ?? ''));
                @endphp
                <td colspan="2" style="{{ $styWPar.$styCell }}">{{ $dic !== '' ? $dic : $blank }}</td>
                <td colspan="2" style="{{ $styWPar.$styCell }}">{{ $feb !== '' ? $feb : $blank }}</td>
            @endforeach
        </tr>
        <tr class="fila-pie">
            <td class="col-ord col-vacia" style="{{ $styWOrd.$styCell }}">{{ $blank }}</td>
            <td class="col-nom col-vacia" style="{{ $styWNom.$styCell }}">{{ $blank }}</td>
            <td colspan="{{ $colsMateria }}" class="pie-linea" style="{{ $styWPie }}">{!! $lineaPie !!}</td>
        </tr>
    </tbody>
@empty
    <tbody>
        <tr>
            <td colspan="{{ $colSpanTotal }}" style="text-align:center;padding:8px;">Sin estudiantes regulares en este curso.</td>
        </tr>
    </tbody>
@endforelse
</table>
