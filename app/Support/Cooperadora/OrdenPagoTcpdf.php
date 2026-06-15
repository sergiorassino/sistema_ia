<?php

namespace App\Support\Cooperadora;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfLogoInstitucional;
use TCPDF;

/**
 * Orden de pago cooperadora — TCPDF.
 */
final class OrdenPagoTcpdf extends TCPDF
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
        $this->SetTitle('Orden de pago cooperadora');
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
        $alto = self::ALTO_HOJA - (self::MARGEN * 2);

        $this->Rect($x0, $y0, $ancho, $alto);

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

        TcpdfFuenteArial::aplicar($this, 'B', 14);
        $this->SetXY($colDer + 4, $y0 + 4);
        $this->Cell($ancho - ($colDer - $x0) - 8, 7, 'Orden de pago', 0, 2, 'L');
        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell($ancho - ($colDer - $x0) - 8, 5, 'N° '.(string) ($this->datos['orden_numero_texto'] ?? ''), 0, 2, 'L');
        $this->Cell($ancho - ($colDer - $x0) - 8, 5, 'Fecha '.(string) ($this->datos['fecha_texto'] ?? ''), 0, 2, 'L');
        if (! empty($this->datos['anulado'])) {
            TcpdfFuenteArial::aplicar($this, 'B', 10);
            $this->SetTextColor(180, 0, 0);
            $this->Cell($ancho - ($colDer - $x0) - 8, 6, 'ANULADO', 0, 2, 'L');
            $this->SetTextColor(0, 0, 0);
        }

        $y = $y0 + 30;
        $this->Line($x0, $y, $x0 + $ancho, $y);

        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->SetXY($x0 + 3, $y + 3);
        $this->Cell(24, 5, 'Proveedor:', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell($ancho - 30, 5, (string) ($this->datos['proveedor_nombre'] ?? ''), 'B', 1, 'L');

        $cuerpoY = $y + 12;
        $cuerpoH = $alto - ($cuerpoY - $y0) - 2;
        $this->RoundedRect($x0 + 2, $cuerpoY, $ancho - 4, $cuerpoH, 2);

        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->SetXY($x0 + 5, $cuerpoY + 4);
        $this->Cell(48, 5, 'Páguese la suma de', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, 'I', 9);
        $this->Cell($ancho - 58, 5, (string) ($this->datos['importe_letras'] ?? ''), 'B', 1, 'L');

        $this->SetXY($x0 + 5, $cuerpoY + 14);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(36, 5, 'En concepto de:', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->SetXY($x0 + 41, $cuerpoY + 14);
        $this->MultiCell($ancho - 48, 4, (string) ($this->datos['concepto'] ?? ''), 0, 'L');

        $pieY = $cuerpoY + $cuerpoH - 16;
        $yBottom = $y0 + $alto;
        $this->Line($x0 + 2, $pieY, $x0 + $ancho - 2, $pieY);
        $mitad = $x0 + ($ancho / 2);
        $this->Line($mitad, $pieY, $mitad, $yBottom);

        $pad = 4.0;
        $xFin = $x0 + $ancho - $pad;
        $yTexto = $pieY + 2.0;
        $hFila = 6.0;
        $yLineaFirma = $yTexto + $hFila;

        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->SetXY($x0 + $pad, $yTexto);
        $this->Cell(20, $hFila, 'Total $', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', 11);
        $this->Cell(45, $hFila, number_format((float) ($this->datos['importe'] ?? 0), 1, '.', ''), 'B', 0, 'L');

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
        $firmante = trim((string) ($this->datos['firmante'] ?? ''));
        if ($firmante !== '') {
            TcpdfFuenteArial::aplicar($this, 'I', 8);
            $this->SetXY($xDer + $wLabelAcl, $yAclTexto);
            $this->Cell($xFin - $xDer - $wLabelAcl, $hAcl, $firmante, 'B', 0, 'L');
        } else {
            $this->Line($xDer + $wLabelAcl, $yLineaAcl, $xFin, $yLineaAcl);
        }
    }
}
