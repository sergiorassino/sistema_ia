<?php

namespace App\Support\Cooperadora;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Recibo de ingreso cooperadora — TCPDF (formato legacy institucional).
 */
final class ReciboTcpdf extends TCPDF
{
    private const ANCHO_HOJA = 210.0;

    private const ALTO_HOJA_A4 = 297.0;

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
        $this->altoHoja = min(self::altoParaDatos($datos), self::ALTO_HOJA_A4);

        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
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
        $yHeaderFin = CooperadoraComprobanteEncabezadoTcpdf::estimarYHeaderFin(
            $header,
            self::MARGEN,
            self::MARGEN,
            self::ANCHO_HOJA - (self::MARGEN * 2),
            self::LOGO_ANCHO,
        );
        $altoDetalle = self::altoZonaDetalle($lineas);

        return max(
            self::ALTO_BASE,
            $yHeaderFin + self::ALTO_BLOQUE_PRE_DETALLE + $altoDetalle + self::ALTO_PIE + self::MARGEN,
        );
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
        $yHeaderFin = CooperadoraComprobanteEncabezadoTcpdf::dibujar(
            $this,
            $header,
            $x0,
            $y0,
            $ancho,
            self::LOGO_ANCHO,
            self::LOGO_ALTO,
            [
                'titulo' => 'Recibo',
                'numero_texto' => (string) ($this->datos['recibo_numero_texto'] ?? ''),
                'fecha_texto' => (string) ($this->datos['fecha_texto'] ?? ''),
                'mostrar_aviso_no_factura' => true,
            ],
        );
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
