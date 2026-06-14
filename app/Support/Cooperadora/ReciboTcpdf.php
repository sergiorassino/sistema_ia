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

    private const ALTO_HOJA = 99.0;

    private const MARGEN = 8.0;

    private const LOGO_ANCHO = 16.0;

    private const LOGO_ALTO = 16.0;

    /** @var array<string, mixed> */
    private array $datos;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('L', 'mm', [self::ANCHO_HOJA, self::ALTO_HOJA], true, 'UTF-8', false);
        $this->datos = $datos;
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

    private function dibujar(): void
    {
        $header = (array) ($this->datos['header'] ?? []);
        $ancho = self::ANCHO_HOJA - (self::MARGEN * 2);
        $x0 = self::MARGEN;
        $y0 = self::MARGEN;

        $this->Rect($x0, $y0, $ancho, self::ALTO_HOJA - (self::MARGEN * 2));

        $colDer = $x0 + ($ancho * 0.55);
        $this->Line($colDer, $y0, $colDer, $y0 + 28);

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

        TcpdfFuenteArial::aplicar($this, 'B', 11);
        $this->SetXY($textX, $y0 + 2);
        $this->Cell($textW, 5, (string) ($header['nombre'] ?? ''), 0, 2, 'L');
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell($textW, 4, (string) ($header['direccion'] ?? ''), 0, 2, 'L');
        $this->Cell($textW, 4, (string) ($header['localidad'] ?? ''), 0, 2, 'L');
        $tel = trim((string) ($header['telefono'] ?? ''));
        if ($tel !== '') {
            $this->Cell($textW, 4, 'TELÉFONO '.$tel, 0, 2, 'L');
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

        $y = $y0 + 32;
        $this->Line($x0, $y, $x0 + $ancho, $y);

        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->SetXY($x0 + 3, $y + 3);
        $this->Cell(18, 5, 'Señor:', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell($ancho - 24, 5, (string) ($this->datos['pagador_nombre'] ?? ''), 'B', 1, 'L');

        $y = $this->GetY() + 4;
        $this->SetXY($x0 + 3, $y);
        $this->Cell(52, 5, 'Recibimos la suma de:', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, 'I', 9);
        $this->Cell($ancho - 58, 5, (string) ($this->datos['importe_letras'] ?? ''), 'B', 1, 'L');

        $y = $this->GetY() + 4;
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->SetXY($x0 + 3, $y);
        $this->Cell(38, 5, 'En concepto de:', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->SetXY($x0 + 41, $y);
        $this->MultiCell($ancho - 44, 4, (string) ($this->datos['concepto'] ?? ''), 0, 'L');

        $altoInner = self::ALTO_HOJA - (self::MARGEN * 2);
        $yBottom = $y0 + $altoInner;
        $yPie = $yBottom - 20;
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
        $this->Cell(50, $hFila, number_format((float) ($this->datos['importe'] ?? 0), 1, '.', ''), 'B', 0, 'L');

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
}
