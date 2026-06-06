{{-- Tercer materia: texto + grilla de celdas (sin recuadro exterior; solo bordes en cada celda de nota). --}}
@php
    $conFirmas = $conFirmas ?? false;
    $blank = $blank ?? "\u{00A0}";
    $camposTm = ['tm1', 'tm2', 'tm3', 'tm4', 'tm5', 'tm6', 'tmNota'];
    $wCelTm = '17pt';
    $wCelNota = '34pt';
@endphp
@foreach ($tercerMateria as $tm)
    <div @class(['tm-boletin-wrap', 'tm-boletin-wrap--firmas' => $conFirmas])>
        <table class="tm-boletin-inner" cellspacing="0" cellpadding="0"
               style="border-collapse:collapse;width:auto;">
            <tr>
                <td class="tm-boletin-texto"
                    style="padding:0;vertical-align:middle;white-space:nowrap;border:0;font-size:6.8pt;line-height:1.2;">
                    <span class="tm-boletin-lbl">Tercer Materia:</span>&#32;<span class="tm-boletin-materia">{{ $tm['nombre_boletin'] ?? '' }}</span>
                </td>
                <td class="tm-boletin-celdas" style="padding:0 0 0 3mm;vertical-align:middle;border:0;">
                    <table class="tm-boletin-grid" cellspacing="0" cellpadding="0"
                           style="border-collapse:collapse;width:auto;table-layout:fixed;">
                        <tr>
                            @foreach ($camposTm as $campo)
                                @php
                                    $v = trim((string) ($tm[$campo] ?? ''));
                                    $w = $campo === 'tmNota' ? $wCelNota : $wCelTm;
                                @endphp
                                <td class="tm-boletin-celda"
                                    style="width:{{ $w }};border:0.55pt solid #333;text-align:center;vertical-align:middle;font-size:6pt;padding:1px 1px;height:10pt;line-height:1;">{{ $v !== '' ? $v : $blank }}</td>
                            @endforeach
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
@endforeach
