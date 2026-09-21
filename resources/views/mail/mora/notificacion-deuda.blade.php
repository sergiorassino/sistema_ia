<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notificación de deuda</title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 0; background: #F4F8F9; color: #333333; }
    .wrapper { max-width: 720px; margin: 24px auto; background: #fff; border-radius: 12px; overflow: hidden; border: 1px solid #C1D7DA; }
    .header { background: #40848D; color: #fff; padding: 20px 24px; }
    .header h1 { margin: 0; font-size: 18px; font-weight: 700; }
    .header p { margin: 6px 0 0; font-size: 13px; opacity: .9; }
    .body { padding: 22px 24px; font-size: 14px; line-height: 1.55; }
    .lugar { text-align: right; color: #555; margin: 0 0 12px; }
    .saludo { margin: 0 0 14px; font-weight: 600; }
    .texto { white-space: pre-wrap; margin: 0 0 16px; }
    .titulo-fam { margin: 0 0 8px; font-size: 13px; font-weight: 700; text-transform: uppercase; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th, td { border: 1px solid #C1D7DA; padding: 6px 7px; }
    th { background: #F4F8F9; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; text-align: left; }
    td.num { text-align: right; white-space: nowrap; }
    tfoot td { font-weight: 700; background: #F4F8F9; }
    .footer { background: #F4F8F9; border-top: 1px solid #C1D7DA; padding: 14px 24px; font-size: 11px; color: #739FA5; text-align: center; }
</style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>{{ $nombreColegio !== '' ? $nombreColegio : 'Notificación de deuda' }}</h1>
        <p>NOTIFICACIÓN DE DEUDA</p>
    </div>
    <div class="body">
        @php
            $lineaLugar = trim($localidad) !== '' ? trim($localidad).',  '.$fechaCarta : $fechaCarta;
        @endphp
        @if ($lineaLugar !== '')
            <p class="lugar">{{ $lineaLugar }}</p>
        @endif
        <p class="saludo">Sr/Sra/Srta: {{ $familiaLinea }}</p>
        @if (trim($textoInicial) !== '')
            <p class="texto">{{ $textoInicial }}</p>
        @endif
        @if (trim($tituloFamilia) !== '')
            <p class="titulo-fam">{{ $tituloFamilia }}</p>
        @endif
        <table>
            <thead>
                <tr>
                    <th>Estudiante</th>
                    <th>Cuota</th>
                    <th>Año</th>
                    <th>1º Venc</th>
                    <th>Saldo</th>
                    <th>A pagar</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($filas as $fila)
                    <tr>
                        <td>{{ $fila['estudiante'] ?? '' }}</td>
                        <td>{{ $fila['cuota'] ?? '' }}</td>
                        <td>{{ $fila['ano'] ?? '' }}</td>
                        <td>{{ $fila['venc1'] ?? '' }}</td>
                        <td class="num">{{ $fila['saldo'] ?? '' }}</td>
                        <td class="num">{{ $fila['aPagar'] ?? '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">Sin cuotas.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="num">Totales</td>
                    <td class="num">{{ $totales['saldo'] ?? '' }}</td>
                    <td class="num">{{ $totales['aPagar'] ?? '' }}</td>
                </tr>
            </tfoot>
        </table>
        @if (trim($textoFinal) !== '')
            <p class="texto" style="margin-top: 16px;">{{ $textoFinal }}</p>
        @endif
    </div>
    <div class="footer">
        Correo automático del sistema de gestión escolar. Por favor no responda a este mensaje.
    </div>
</div>
</body>
</html>
