<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 10mm 8mm 12mm 8mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8pt;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .doc-titulo {
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 4px 0;
            color: #111;
        }
        .doc-sub {
            text-align: center;
            font-size: 8.5pt;
            margin: 0 0 8px 0;
            color: #444;
        }
        .doc-meta {
            font-size: 7.5pt;
            margin: 0 0 10px 0;
            color: #555;
        }
        .grupo-titulo {
            font-size: 9pt;
            font-weight: bold;
            background: #C1D7DA;
            padding: 4px 6px;
            margin: 10px 0 4px 0;
            border: 0.5pt solid #999;
        }
        table.datos {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 0 0 6px 0;
        }
        table.datos th,
        table.datos td {
            border: 0.5pt solid #bbb;
            padding: 2px 3px;
            vertical-align: top;
            overflow: hidden;
            font-size: 7pt;
            line-height: 1.15;
        }
        table.datos th {
            background: #f4f8f9;
            font-weight: bold;
            font-size: 6.5pt;
        }
        .salto-grupo { page-break-inside: avoid; }
    </style>
</head>
<body>
@php
    $porMateriaCurso = (bool) ($porMateriaCurso ?? false);
    $cols = [
        ['key' => 'apellido', 'label' => 'Apellido', 'w' => 15],
        ['key' => 'nombre', 'label' => 'Nombre', 'w' => 13],
        ['key' => 'ano_lectivo', 'label' => 'Año lect.', 'w' => 7],
        ['key' => 'curso', 'label' => 'Curso', 'w' => 17],
        ['key' => 'materia', 'label' => 'Materia', 'w' => 24],
        ['key' => 'condicion', 'label' => 'Cond.', 'w' => 7],
        ['key' => 'inscripto', 'label' => 'Inscr.', 'w' => 9],
    ];
    $colOrdenW = 4;
@endphp

@include('pdf.partials.header', ['header' => $pdfHeader ?? []])

<p class="doc-titulo">Listado de materias adeudadas</p>
<p class="doc-sub">{{ $nivelNombre ?? '' }} · {{ $tituloAgrupacion ?? '' }}</p>
@if (! empty($filtrosActivos))
    <p class="doc-meta">Filtros: {{ implode(' · ', $filtrosActivos) }} · Total: {{ (int) ($totalFilas ?? 0) }} registro(s)</p>
@else
    <p class="doc-meta">Total: {{ (int) ($totalFilas ?? 0) }} registro(s)</p>
@endif

@forelse ($bloques as $bloque)
    @php $cantidadGrupo = count($bloque['filas']); @endphp
    <div class="salto-grupo">
        <p class="grupo-titulo">
            {{ $bloque['titulo'] }}
            — {{ $cantidadGrupo }}
            {{ $porMateriaCurso ? ($cantidadGrupo === 1 ? 'alumno' : 'alumnos') : ($cantidadGrupo === 1 ? 'materia' : 'materias') }}
        </p>
        <table class="datos" cellspacing="0" cellpadding="0">
            <thead>
                <tr>
                    <th style="width:{{ $colOrdenW }}%;min-width:0;max-width:{{ $colOrdenW }}%;overflow:hidden;text-align:center;">Nº</th>
                    @foreach ($cols as $col)
                        <th style="width:{{ $col['w'] }}%;min-width:0;max-width:{{ $col['w'] }}%;overflow:hidden;">{{ $col['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($bloque['filas'] as $fila)
                    <tr>
                        <td style="width:{{ $colOrdenW }}%;min-width:0;max-width:{{ $colOrdenW }}%;overflow:hidden;text-align:center;">{{ $loop->iteration }}</td>
                        @foreach ($cols as $col)
                            @php
                                $val = $fila[$col['key']] ?? '';
                                if ($col['key'] === 'condicion' && $val === '') {
                                    $val = '—';
                                }
                            @endphp
                            <td style="width:{{ $col['w'] }}%;min-width:0;max-width:{{ $col['w'] }}%;overflow:hidden;">{{ $val }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@empty
    <p class="doc-meta">No hay registros para los filtros indicados.</p>
@endforelse
</body>
</html>
