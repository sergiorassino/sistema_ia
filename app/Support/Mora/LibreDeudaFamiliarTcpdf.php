<?php

namespace App\Support\Mora;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use TCPDF;

/**
 * Constancia de libre deuda (A4 vertical).
 *
 * Maquetación del FPDF legacy: recuadro de membrete, párrafo del estudiante,
 * lugar y fecha, y firma del representante legal.
 */
final class LibreDeudaFamiliarTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    /** @var array<string, mixed> */
    private array $datos;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->datos = $datos;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Constancia de libre deuda');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_IZQ, 10, 10);
        $this->SetLeftMargin(self::MARGEN_IZQ);
        $this->SetFillColor(255, 255, 255);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage();
        $pdf->dibujarDocumento();

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

    private function dibujarDocumento(): void
    {
        $this->dibujarEncabezado();
        $this->dibujarCuerpo();
        $this->dibujarPie();
    }

    private function dibujarEncabezado(): void
    {
        $header = is_array($this->datos['header'] ?? null) ? $this->datos['header'] : [];

        $this->SetDrawColor(0, 0, 0);
        $this->Rect(self::MARGEN_IZQ, 10, 180, 22);

        $logo = $header['logo_file'] ?? null;
        $hayLogo = is_string($logo) && $logo !== '' && is_file($logo);
        if ($hayLogo) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), 22, 11, 16, 20, '', '', '', false, 300, '', false, false, 0, 'CM');
        }

        $xTexto = $hayLogo ? 40.0 : self::MARGEN_IZQ;
        $anchoTexto = $hayLogo ? 156.0 : 170.0;

        TcpdfFuenteArial::aplicar($this, 'B', 12);
        $this->SetXY($xTexto, 14);
        $this->Cell($anchoTexto, 5, (string) ($header['insti'] ?? ''), 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->SetX($xTexto);
        $this->Cell($anchoTexto, 3, $this->lineaDomicilio($header), 0, 2, 'C');
        $this->SetX($xTexto);
        $this->Cell($anchoTexto, 3, $this->lineaIva($header), 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->SetX($xTexto);
        $this->Cell($anchoTexto, 5, 'CONSTANCIA DE LIBRE DEUDA', 0, 2, 'C');
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function lineaDomicilio(array $header): string
    {
        $partes = [];
        $direccion = trim((string) ($header['direccion'] ?? ''));
        $cuit = trim((string) ($header['cuit'] ?? ''));
        if ($direccion !== '') {
            $partes[] = $direccion;
        }
        if ($cuit !== '') {
            $partes[] = 'CUIT Nº '.$cuit;
        }

        return implode(' - ', $partes);
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function lineaIva(array $header): string
    {
        $iva = trim((string) ($header['condicion_iva'] ?? ''));
        $brutos = trim((string) ($header['ingresos_brutos'] ?? ''));
        if ($iva === '' && $brutos === '') {
            return '';
        }

        return 'IVA: '.$iva.' - Ing.Brutos: '.$brutos;
    }

    private function dibujarCuerpo(): void
    {
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $faceBold = $this->getFontFamily();
        TcpdfFuenteArial::aplicar($this, '', 10);

        $apenom = $this->escapeHtml((string) ($this->datos['apenom'] ?? ''));
        $dni = $this->escapeHtml(trim((string) ($this->datos['dni'] ?? '')));
        $cursec = $this->escapeHtml(trim((string) ($this->datos['cursec'] ?? '')));
        $nivel = $this->escapeHtml(trim((string) ($this->datos['nivel'] ?? '')));

        $html = 'Por la presente certifico que, al día de la fecha, el/la estudiante  '
            .'<span style="font-family:'.$faceBold.';font-weight:bold;">'.$apenom.'</span>'
            .'   DNI: '.$dni;
        if ($cursec !== '' && $cursec !== '0') {
            $html .= '  de  '.$cursec;
            if ($nivel !== '') {
                $html .= ',  ('.$nivel.')';
            }
        }
        $html .= '  no registra deuda en este establecimiento';

        $this->writeHTMLCell(170, 0, self::MARGEN_IZQ, 41, $html, 0, 1, false, true, 'L', true);

        $this->Ln(10);
        $lugar = trim((string) ($this->datos['lugar'] ?? ''));
        TcpdfFuenteArial::aplicar($this, '', 10);
        if ($lugar !== '') {
            $this->Write(5, $lugar.', ');
        }
        TcpdfFuenteArial::aplicar($this, 'BI', 10);
        $this->Write(5, (string) ($this->datos['fecha'] ?? ''));
    }

    private function escapeHtml(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function dibujarPie(): void
    {
        $replegal = trim((string) ($this->datos['replegal'] ?? ''));
        $firma = $this->datos['firma_file'] ?? null;
        $hayFirma = is_string($firma) && $firma !== '' && is_file($firma);

        if (! $hayFirma && $replegal === '') {
            return;
        }

        $y = max(70.0, $this->GetY() + 6);
        if ($hayFirma) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($firma), 130, $y, 27, 27, '', '', '', false, 300);
            $yTexto = $y + 28;
        } else {
            $yTexto = $y;
        }

        $this->SetTextColor(0, 0, 0);
        TcpdfFuenteArial::aplicar($this, '', 8);
        if ($replegal !== '') {
            $this->SetXY(100, $yTexto);
            $this->Cell(80, 4, $replegal, 0, 0, 'C');
            $yTexto += 4.5;
        }
        $this->SetXY(100, $yTexto);
        $this->Cell(80, 4, 'Representante Legal', 0, 0, 'C');
    }
}
