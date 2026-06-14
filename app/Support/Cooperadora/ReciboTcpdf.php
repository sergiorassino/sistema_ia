<?php

namespace App\Support\Cooperadora;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfLogoInstitucional;
use TCPDF;

/**
 * Recibo de ingreso cooperadora — TCPDF (formato legacy institucional).
 */
final class ReciboTcpdf extends TCPDF
{
    private const ANCHO_HOJA = 210.0;

    private const ALTO_BASE = 99.0;

    private const MARGEN = 8.0;

    private const LOGO_ANCHO = 16.0;

    private const LOGO_ALTO = 16.0;

    private const ALTO_PIE = 20.0;

    /** Desde fin de encabezado hasta inicio del bloque Detalle (línea + Señor + suma). */
    private const ALTO_BLOQUE_PRE_DETALLE = 21.0;

    private const SEPARACION_FILA_DETALLE = 1.5;

    private const ALTO_LINEA_CONCEPTO = 4.0;

    /** @var array<string, mixed> */
    private array $datos;

    private float $altoHoja;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        $this->datos = $datos;
        $this->altoHoja = self::altoParaDatos($datos);

        parent::__construct('L', 'mm', [self::ANCHO_HOJA, $this->altoHoja], true, 'UTF-8', false);
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Recibo cooperadora');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN, self::MARGEN, self::MARGEN);
        $this->SetDrawColor(0, 0, 0);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage();
        $pdf->dibujar();

        return $pdf;
    }

    public static function respuestaHttp(self $pdf, string $nombreArchivo): \Illuminate\Http\Response
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $binario = $pdf->Output($nombreArchivo, 'S');

        return response($binario, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombreArchivo.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private static function altoParaDatos(array $datos): float
    {
        $header = (array) ($datos['header'] ?? []);
        $lineas = (array) ($datos['lineas'] ?? []);
        $yHeaderFin = self::estimarYHeaderFin($header);
        $altoDetalle = self::altoZonaDetalle($lineas);

        return max(
            self::ALTO_BASE,
            $yHeaderFin + self::ALTO_BLOQUE_PRE_DETALLE + $altoDetalle + self::ALTO_PIE + self::MARGEN,
        );
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private static function estimarYHeaderFin(array $header): float
    {
        $y0 = self::MARGEN;
        $ancho = self::ANCHO_HOJA - (self::MARGEN * 2);
        $colDer = self::MARGEN + ($ancho * 0.55);
        $textX = self::MARGEN + 2 + self::LOGO_ANCHO + 2;
        $textW = max(20.0, $colDer - $textX - 2);

        $yTexto = $y0 + 2.0;
        $yTexto += self::lineasTextoEstimadas((string) ($header['nombre'] ?? ''), $textW, 10) * 4.0;

        foreach (['direccion', 'localidad'] as $campo) {
            if (trim((string) ($header[$campo] ?? '')) !== '') {
                $yTexto += 4.0;
            }
        }

        foreach (['telefono', 'cuit', 'repace'] as $campo) {
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

    /**
     * @param  list<array{concepto?: string, importe?: float}>|list<mixed>  $lineas
     */
    private static function altoZonaDetalle(array $lineas): float
    {
        if ($lineas === []) {
            return 11.0 + self::ALTO_LINEA_CONCEPTO;
        }

        $alto = 11.0;
        $total = count($lineas);

        foreach ($lineas as $index => $linea) {
            $filasTexto = self::filasTextoConcepto((string) ($linea['concepto'] ?? ''));
            $alto += $filasTexto * self::ALTO_LINEA_CONCEPTO;
            if ($index < $total - 1) {
                $alto += self::SEPARACION_FILA_DETALLE;
            }
        }

        return $alto;
    }

    private static function filasTextoConcepto(string $concepto): int
    {
        $concepto = trim($concepto);
        if ($concepto === '') {
            return 1;
        }

        $wConcepto = self::ANCHO_HOJA - (self::MARGEN * 2) - 6 - 28.0;
        $charsPorLinea = max(28, (int) floor($wConcepto / 2.1));

        return max(1, (int) ceil(mb_strlen($concepto) / $charsPorLinea));
    }

    private function dibujar(): void
    {
        $header = (array) ($this->datos['header'] ?? []);
        $ancho = self::ANCHO_HOJA - (self::MARGEN * 2);
        $x0 = self::MARGEN;
        $y0 = self::MARGEN;
        $altoInner = $this->altoHoja - (self::MARGEN * 2);

        $this->Rect($x0, $y0, $ancho, $altoInner);

        $colDer = $x0 + ($ancho * 0.55);
        $yHeaderFin = $this->dibujarEncabezadoInstitucional($header, $x0, $y0, $colDer, $ancho);
        $this->Line($colDer, $y0, $colDer, $yHeaderFin);

        $y = $yHeaderFin + 2;
        $this->Line($x0, $y, $x0 + $ancho, $y);

        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->SetXY($x0 + 3, $y + 3);
        $this->Cell(18, 5, 'Señor:', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell($ancho - 24, 5, (string) ($this->datos['pagador_nombre'] ?? ''), 'B', 1, 'L');

        $y = $this->GetY() + 3;
        $this->SetXY($x0 + 3, $y);
        $this->Cell(52, 5, 'Recibimos la suma de:', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, 'I', 9);
        $this->Cell($ancho - 58, 5, (string) ($this->datos['importe_letras'] ?? ''), 'B', 1, 'L');

        $y = $this->GetY() + 3;
        $this->dibujarDetalleLineas($x0, $y, $ancho);

        $yBottom = $y0 + $altoInner;
        $yPie = $yBottom - self::ALTO_PIE;
        $mitad = $x0 + ($ancho / 2);
        $pad = 3.0;
        $xFin = $x0 + $ancho - $pad;

        $this->Line($x0, $yPie, $x0 + $ancho, $yPie);
        $this->Line($mitad, $yPie, $mitad, $yBottom);

        $yTexto = $yPie + 4.0;
        $hFila = 6.0;
        $yLineaFirma = $yTexto + $hFila;

        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->SetXY($x0 + $pad, $yTexto);
        $this->Cell(20, $hFila, 'Total $', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', 11);
        $this->Cell(50, $hFila, number_format((float) ($this->datos['importe'] ?? 0), 2, '.', ''), 'B', 0, 'L');

        $xDer = $mitad + $pad;
        $wLabelFirma = 14.0;
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->SetXY($xDer, $yTexto);
        $this->Cell($wLabelFirma, $hFila, 'Firma:', 0, 0, 'L');
        $this->Line($xDer + $wLabelFirma, $yLineaFirma, $xFin, $yLineaFirma);

        $yAclTexto = $yLineaFirma + 3.0;
        $hAcl = 5.0;
        $wLabelAcl = 26.0;
        $yLineaAcl = $yAclTexto + $hAcl;
        $this->SetXY($xDer, $yAclTexto);
        $this->Cell($wLabelAcl, $hAcl, 'Aclaración:', 0, 0, 'L');
        $this->Line($xDer + $wLabelAcl, $yLineaAcl, $xFin, $yLineaAcl);
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function dibujarEncabezadoInstitucional(array $header, float $x0, float $y0, float $colDer, float $ancho): float
    {
        $textX = $x0 + 2;
        TcpdfLogoInstitucional::dibujar(
            $this,
            $textX,
            $y0 + 3,
            self::LOGO_ANCHO,
            self::LOGO_ALTO,
            $header['logo_file'] ?? null,
        );
        $textX += self::LOGO_ANCHO + 2;
        $textW = $colDer - $textX - 2;

        $yTexto = $y0 + 2;
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->SetXY($textX, $yTexto);
        $this->MultiCell($textW, 4, (string) ($header['nombre'] ?? ''), 0, 'L', false, 1);
        $yTexto = $this->GetY();

        TcpdfFuenteArial::aplicar($this, '', 8);
        foreach (['direccion', 'localidad'] as $campo) {
            $valor = trim((string) ($header[$campo] ?? ''));
            if ($valor === '') {
                continue;
            }
            $this->SetXY($textX, $yTexto);
            $this->Cell($textW, 4, $valor, 0, 2, 'L');
            $yTexto = $this->GetY();
        }

        $tel = trim((string) ($header['telefono'] ?? ''));
        if ($tel !== '') {
            $this->SetXY($textX, $yTexto);
            $this->Cell($textW, 4, 'TELÉFONO '.$tel, 0, 2, 'L');
            $yTexto = $this->GetY();
        }

        $cuit = trim((string) ($header['cuit'] ?? ''));
        if ($cuit !== '') {
            $this->SetXY($textX, $yTexto);
            $this->Cell($textW, 4, 'CUIT '.$cuit, 0, 2, 'L');
            $yTexto = $this->GetY();
        }

        $repace = trim((string) ($header['repace'] ?? ''));
        if ($repace !== '') {
            $this->SetXY($textX, $yTexto);
            $this->Cell($textW, 4, 'REPACE '.$repace, 0, 2, 'L');
            $yTexto = $this->GetY();
        }

        TcpdfFuenteArial::aplicar($this, 'B', 16);
        $this->SetXY($colDer + 4, $y0 + 4);
        $this->Cell($ancho - ($colDer - $x0) - 8, 8, 'Recibo', 0, 2, 'L');
        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell($ancho - ($colDer - $x0) - 8, 5, 'Nº: '.(string) ($this->datos['recibo_numero_texto'] ?? ''), 0, 2, 'L');
        $this->Cell($ancho - ($colDer - $x0) - 8, 5, 'Fecha: '.(string) ($this->datos['fecha_texto'] ?? ''), 0, 2, 'L');

        $wColDer = $x0 + $ancho - $colDer;
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

        $this->Rect($boxX, $boxY, $boxW, $boxH);
        TcpdfFuenteArial::aplicar($this, 'B', 14);
        $this->SetXY($boxX, $boxY + (($boxH - 8) / 2));
        $this->Cell($boxW, 8, 'X', 0, 0, 'C');
        TcpdfFuenteArial::aplicar($this, '', 5);
        $this->SetXY($xCelda, $boxY + $boxH + 1);
        $this->MultiCell($wCelda, $lineH, 'DOCUMENTO NO VALIDO COMO FACTURA', 0, 'C');

        return max($yTexto + 2, $y0 + 30);
    }

    private function dibujarDetalleLineas(float $x0, float $y, float $ancho): void
    {
        /** @var list<array{concepto?: string, importe?: float}> $lineas */
        $lineas = (array) ($this->datos['lineas'] ?? []);
        if ($lineas === []) {
            $lineas = [[
                'concepto' => (string) ($this->datos['concepto'] ?? ''),
                'importe' => (float) ($this->datos['importe'] ?? 0),
            ]];
        }

        $xDet = $x0 + 3;
        $wDet = $ancho - 6;
        $wImporte = 28.0;
        $wConcepto = $wDet - $wImporte;

        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->SetXY($xDet, $y);
        $this->Cell($wConcepto, 5, 'Detalle', 0, 0, 'L');
        $this->Cell($wImporte, 5, 'Importe', 0, 1, 'R');

        $this->Line($xDet, $y + 5, $xDet + $wDet, $y + 5);

        TcpdfFuenteArial::aplicar($this, '', 8);
        $yFila = $y + 6;

        foreach ($lineas as $linea) {
            $concepto = trim((string) ($linea['concepto'] ?? ''));
            $importe = (float) ($linea['importe'] ?? 0);
            $yInicio = $yFila;

            $this->SetXY($xDet, $yInicio);
            $this->MultiCell($wConcepto, self::ALTO_LINEA_CONCEPTO, $concepto, 0, 'L', false, 1);
            $yFin = $this->GetY();
            $hFila = max(self::ALTO_LINEA_CONCEPTO, $yFin - $yInicio);

            $this->SetXY($xDet + $wConcepto, $yInicio);
            $this->Cell(
                $wImporte,
                $hFila,
                '$ '.number_format($importe, 2, ',', '.'),
                0,
                0,
                'R',
                false,
                '',
                0,
                false,
                'T',
                'T',
            );

            $yFila = $yFin + self::SEPARACION_FILA_DETALLE;
        }
    }
}
