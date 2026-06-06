{{-- Horario semanal: una hoja A4 apaisada por turno. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 8mm 6mm 8mm 6mm; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 7pt;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .pagina { page-break-after: always; }
        .pagina:last-child { page-break-after: auto; }
        .titulo {
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            margin: 0 0 2px 0;
            font-size: 11pt;
        }
        .subtitulo { text-align: center; margin: 0 0 2px 0; font-size: 8pt; }
        .turno { text-align: center; margin: 0 0 5px 0; font-size: 9pt; font-weight: 700; }
        table.horario {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }
        table.horario th,
        table.horario td {
            border: 0.6pt solid #333;
            padding: 2px 3px;
            vertical-align: middle;
            text-align: center;
            min-width: 0;
            overflow: hidden;
            word-wrap: break-word;
        }
        th.col-reloj { width: 14%; font-size: 6.5pt; }
        th.col-dia { font-size: 7pt; font-weight: 700; }
        td.celda-reloj { font-size: 6.5pt; text-align: center; font-weight: normal; }
        td.celda-dato { font-size: 6.5pt; text-align: left; line-height: 1.15; }
        tr.fila-hora td { height: 22px; }
    </style>
</head>
<body>
@php
    use App\Support\HorariosProfesores;
@endphp

@foreach ($paginas as $idx => $pagina)
    @php
        $grilla = $pagina['grilla'];
        $dias = $grilla['dias'];
        $horas = $grilla['horas'];
        $reloj = $grilla['reloj'];
        $celdas = $grilla['celdas'];
        $nDias = max(count($dias), 1);
        $pctDia = (100 - 14) / $nDias;
    @endphp
    <div class="pagina">
        @include('pdf.partials.header', ['header' => $pdfHeader ?? null])
        <p class="titulo">{{ $pagina['titulo'] ?? $titulo }}</p>
        <p class="subtitulo">{{ $pagina['subtitulo'] ?? $subtitulo }}</p>
        <p class="turno">Turno {{ $pagina['tituloTurno'] ?? '' }}</p>

        <table class="horario">
            <thead>
                <tr>
                    <th class="col-reloj" style="width:14%; min-width:0; overflow:hidden;">Hora</th>
                    @foreach ($dias as $diaId)
                        <th class="col-dia" style="width:{{ number_format($pctDia, 2, '.', '') }}%; min-width:0; overflow:hidden;">
                            {{ HorariosProfesores::DIAS_CORTO[$diaId] ?? '' }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($horas as $h)
                    <tr class="fila-hora">
                        @php
                            $txtReloj = trim((string) ($reloj[$h] ?? ''));
                        @endphp
                        <td class="celda-reloj" style="width:14%; min-width:0; overflow:hidden;">
                            @if ($txtReloj !== '')
                                <span style="font-weight:700">{{ $h }}º HORA:</span>
                                {{ $txtReloj }}
                            @else
                                <span style="font-weight:700">{{ $h }}º HORA</span>
                            @endif
                        </td>
                        @foreach ($dias as $diaId)
                            @php
                                $key = HorariosProfesores::celdaKey($diaId, $h);
                                $lineas = $celdas[$key] ?? [];
                            @endphp
                            <td class="celda-dato" style="width:{{ number_format($pctDia, 2, '.', '') }}%; min-width:0; overflow:hidden;">
                                @foreach ($lineas as $linea)
                                    <div>{{ $linea }}</div>
                                @endforeach
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach
</body>
</html>
