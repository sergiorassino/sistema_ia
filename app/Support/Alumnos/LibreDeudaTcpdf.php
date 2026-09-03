<?php

namespace App\Support\Alumnos;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use TCPDF;

/**
 * Constancia de libre deuda (A4 vertical, legacy FPDF Montecristo).
 */
final class LibreDeudaTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const ANCHO_BLOQUE = 180.0;

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
        $this->SetAutoPageBreak(true, 18);
        $this->SetLeftMargin(self::MARGEN_IZQ);
        $this->SetMargins(self::MARGEN_IZQ, 10, 10);
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
        $x = self::MARGEN_IZQ;
        $y = 10.0;

        $this->SetDrawColor(0, 0, 0);
        $this->Rect($x, $y, self::ANCHO_BLOQUE, 22);

        $logo = pdfHeaderLogoAbsolutePath($header);
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), 25, 11, 15, 20, '', '', '', false, 300);
        }

        $insti = trim((string) ($header['insti'] ?? ''));
        $cue = trim((string) ($header['cue'] ?? ''));
        $ee = trim((string) ($header['ee'] ?? ''));

        $this->SetXY($x, 14);
        TcpdfFuenteArial::aplicar($this, 'B', 12);
        $this->Cell(170, 5, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(170, 5, 'CUE: '.$cue.'    EE: '.$ee, 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->Cell(170, 5, 'CONSTANCIA DE LIBRE DEUDA', 0, 2, 'C');
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

        $html = 'Por la presente certifico que, al día de la fecha, el/la estudiante '
            .'<span style="font-family:'.$faceBold.'; font-weight:bold;">'.$apenom.'</span>';
        if ($dni !== '') {
            $html .= '   DNI: '.$dni;
        }
        if ($cursec !== '') {
            $html .= '  de  '.$cursec;
            if ($nivel !== '') {
                $html .= ',  ('.$nivel.')';
            }
        } elseif ($nivel !== '') {
            $html .= '  ('.$nivel.')';
        }
        $html .= '  no registra deuda en este establecimiento';

        $this->writeHTMLCell(170, 0, self::MARGEN_IZQ, 41, $html, 0, 1, false, true, 'L', true);

        $this->Ln(6);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $idLegajo = (int) ($this->datos['id_legajo'] ?? 0);
        $this->Write(5, 'ALUMNO Nº '.($idLegajo > 0 ? (string) $idLegajo : ''));

        $this->Ln(10);
        TcpdfFuenteArial::aplicar($this, '', 10);
        $lugar = trim((string) ($this->datos['lugar'] ?? 'Monte Cristo'));
        $this->Write(5, $lugar.', ');
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Write(5, (string) ($this->datos['fecha'] ?? ''));
    }

    private function dibujarPie(): void
    {
        $this->Ln(12);
        $y = max($this->GetY(), 78.0);
        $sello = $this->datos['sello_file'] ?? null;
        $firma = $this->datos['firma_file'] ?? null;

        if (is_string($sello) && $sello !== '' && is_file($sello)) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($sello), 60, $y, 40, 0, '', '', '', false, 300);
        }

        if (is_string($firma) && $firma !== '' && is_file($firma)) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($firma), 128, $y, 42, 0, '', '', '', false, 300);
        }
    }

    private function escapeHtml(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
