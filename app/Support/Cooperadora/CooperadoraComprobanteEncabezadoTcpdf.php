<?php

namespace App\Support\Cooperadora;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfLogoInstitucional;
use TCPDF;

/**
 * Encabezado institucional compartido — recibo y orden de pago cooperadora.
 */
final class CooperadoraComprobanteEncabezadoTcpdf
{
    /**
     * @param  array<string, mixed>  $header
     * @param  array{
     *     titulo: string,
     *     numero_texto: string,
     *     fecha_texto: string,
     *     mostrar_aviso_no_factura?: bool,
     *     anulado?: bool,
     * }  $documento
     */
    public static function dibujar(
        TCPDF $pdf,
        array $header,
        float $x0,
        float $y0,
        float $ancho,
        float $logoAncho,
        float $logoAlto,
        array $documento,
    ): float {
        $colDer = $x0 + ($ancho * 0.55);

        $textX = $x0 + 2;
        TcpdfLogoInstitucional::dibujar(
            $pdf,
            $textX,
            $y0 + 3,
            $logoAncho,
            $logoAlto,
            $header['logo_file'] ?? null,
        );
        $textX += $logoAncho + 2;
        $textW = $colDer - $textX - 2;

        $yTexto = $y0 + 2;
        TcpdfFuenteArial::aplicar($pdf, 'B', 10);
        $pdf->SetXY($textX, $yTexto);
        $pdf->MultiCell($textW, 4, (string) ($header['nombre'] ?? ''), 0, 'L', false, 1);
        $yTexto = $pdf->GetY();

        TcpdfFuenteArial::aplicar($pdf, '', 8);
        foreach (['direccion', 'localidad'] as $campo) {
            $valor = trim((string) ($header[$campo] ?? ''));
            if ($valor === '') {
                continue;
            }
            $pdf->SetXY($textX, $yTexto);
            $pdf->Cell($textW, 4, $valor, 0, 2, 'L');
            $yTexto = $pdf->GetY();
        }

        $tel = trim((string) ($header['telefono'] ?? ''));
        if ($tel !== '') {
            $pdf->SetXY($textX, $yTexto);
            $pdf->Cell($textW, 4, 'TELÉFONO '.$tel, 0, 2, 'L');
            $yTexto = $pdf->GetY();
        }

        $cuit = trim((string) ($header['cuit'] ?? ''));
        if ($cuit !== '') {
            $pdf->SetXY($textX, $yTexto);
            $pdf->Cell($textW, 4, 'CUIT '.$cuit, 0, 2, 'L');
            $yTexto = $pdf->GetY();
        }

        $repace = trim((string) ($header['repace'] ?? ''));
        if ($repace !== '') {
            $pdf->SetXY($textX, $yTexto);
            $pdf->Cell($textW, 4, 'REPACE '.$repace, 0, 2, 'L');
            $yTexto = $pdf->GetY();
        }

        $wColDer = $x0 + $ancho - $colDer;
        $mostrarAviso = (bool) ($documento['mostrar_aviso_no_factura'] ?? false);
        $wTitulo = $mostrarAviso
            ? ($wColDer * 0.45) - 8
            : $wColDer - 8;

        TcpdfFuenteArial::aplicar($pdf, 'B', 16);
        $pdf->SetXY($colDer + 4, $y0 + 4);
        $pdf->Cell($wTitulo, 8, (string) ($documento['titulo'] ?? ''), 0, 2, 'L');
        TcpdfFuenteArial::aplicar($pdf, '', 9);
        $pdf->Cell($wTitulo, 5, 'Nº: '.(string) ($documento['numero_texto'] ?? ''), 0, 2, 'L');
        $pdf->Cell($wTitulo, 5, 'Fecha: '.(string) ($documento['fecha_texto'] ?? ''), 0, 2, 'L');

        if (! empty($documento['anulado'])) {
            TcpdfFuenteArial::aplicar($pdf, 'B', 10);
            $pdf->SetTextColor(180, 0, 0);
            $pdf->Cell($wTitulo, 6, 'ANULADO', 0, 2, 'L');
            $pdf->SetTextColor(0, 0, 0);
        }

        if ($mostrarAviso) {
            $xCelda = $colDer + ($wColDer * 0.55);
            $wCelda = ($x0 + $ancho - 2) - $xCelda;
            $yCeldaTop = $y0 + 4;
            $yCeldaH = 24.0;
            $boxW = 22.0;
            $boxH = 10.0;
            $lineH = 2.5;
            $textoH = 5.0;
            $bloqueH = $boxH + 1.0 + $textoH;
            $boxY = $yCeldaTop + (($yCeldaH - $bloqueH) / 2);
            $boxX = $xCelda + (($wCelda - $boxW) / 2);

            $pdf->Rect($boxX, $boxY, $boxW, $boxH);
            TcpdfFuenteArial::aplicar($pdf, 'B', 14);
            $pdf->SetXY($boxX, $boxY + (($boxH - 8) / 2));
            $pdf->Cell($boxW, 8, 'X', 0, 0, 'C');
            TcpdfFuenteArial::aplicar($pdf, '', 5);
            $pdf->SetXY($xCelda, $boxY + $boxH + 1);
            $pdf->MultiCell($wCelda, $lineH, 'DOCUMENTO NO VALIDO COMO FACTURA', 0, 'C');
        }

        return max($yTexto + 2, $y0 + 30);
    }

    /**
     * @param  array<string, mixed>  $header
     */
    public static function estimarYHeaderFin(
        array $header,
        float $x0,
        float $y0,
        float $ancho,
        float $logoAncho,
    ): float {
        $colDer = $x0 + ($ancho * 0.55);
        $textX = $x0 + 2 + $logoAncho + 2;
        $textW = max(20.0, $colDer - $textX - 2);

        $yTexto = $y0 + 2.0;
        $yTexto += self::lineasTextoEstimadas((string) ($header['nombre'] ?? ''), $textW, 10) * 4.0;

        foreach (['direccion', 'localidad', 'telefono', 'cuit', 'repace'] as $campo) {
            if (trim((string) ($header[$campo] ?? '')) !== '') {
                $yTexto += 4.0;
            }
        }

        return max($yTexto + 2.0, $y0 + 30.0);
    }

    private static function lineasTextoEstimadas(string $texto, float $anchoMm, int $fontSize): int
    {
        $texto = trim($texto);
        if ($texto === '') {
            return 0;
        }

        $charsPorLinea = max(12, (int) floor($anchoMm / ($fontSize * 0.18)));

        return max(1, (int) ceil(mb_strlen($texto) / $charsPorLinea));
    }
}
