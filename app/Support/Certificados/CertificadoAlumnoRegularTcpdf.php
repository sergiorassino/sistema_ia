<?php

namespace App\Support\Certificados;

use TCPDF;

/**
 * Certificado escolar de alumno/a regular (A4, formato legacy FPDF → TCPDF).
 */
final class CertificadoAlumnoRegularTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const ANCHO_UTIL = 180.0;

    private const FUENTE = 'dejavusans';

    /** Separación entre el último párrafo del certificado y el bloque de firmas (3 cm). */
    private const ESPACIO_TEXTO_FIRMAS_MM = 30.0;

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
        $this->SetTitle('Constancia de Alumno Regular');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(true, 15);
        $this->SetMargins(self::MARGEN_IZQ, 10, 15);
        $this->SetFillColor(255, 255, 255);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage();
        $pdf->dibujarEncabezado();
        $pdf->dibujarCuerpo();
        $pdf->dibujarFirmas();

        return $pdf;
    }

    private function dibujarEncabezado(): void
    {
        $inst = $this->datos['institucion'] ?? [];
        $insti = trim((string) ($inst['insti'] ?? ''));
        $cue = trim((string) ($inst['cue'] ?? ''));
        $logo = $inst['logo_abs'] ?? null;

        $y0 = 10.0;
        $this->Rect(self::MARGEN_IZQ, $y0, self::ANCHO_UTIL, 22.0, 'D');

        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $this->Image($logo, self::MARGEN_IZQ + 5, $y0 + 1, 15, 20, '', '', '', false, 300);
        }

        $this->SetXY(self::MARGEN_IZQ, $y0 + 5);
        $this->SetFont(self::FUENTE, 'B', 12);
        $this->Cell(self::ANCHO_UTIL, 7, $insti, 0, 2, 'C');

        $this->SetFont(self::FUENTE, '', 10);
        $this->Cell(self::ANCHO_UTIL, 5, 'CERTIFICADO ESCOLAR', 0, 2, 'C');

        $this->SetFont(self::FUENTE, '', 7);
        $cueLinea = $cue !== '' ? 'C.U.E.:  '.$cue : '';
        $this->Cell(self::ANCHO_UTIL, 5, $cueLinea, 0, 2, 'C');
    }

    private function dibujarCuerpo(): void
    {
        $leg = $this->datos['legajo'] ?? [];
        $cert = $this->datos['certificado'] ?? [];

        $apellido = trim((string) ($leg['apellido'] ?? ''));
        $nombre = trim((string) ($leg['nombre'] ?? ''));
        $dni = trim((string) ($leg['dni'] ?? ''));
        $verbo = trim((string) ($cert['verboIniFin'] ?? 'ha iniciado'));
        $fechIniFin = trim((string) ($cert['fechIniFin'] ?? ''));
        $ano = (int) ($cert['anoLectivo'] ?? 0);
        $curso = trim((string) ($cert['curso'] ?? ''));
        $localidad = trim((string) (($this->datos['institucion'] ?? [])['localidad'] ?? ''));
        $prePor = trim((string) ($cert['prePor'] ?? ''));
        $prePorDni = trim((string) ($cert['prePorDni'] ?? ''));
        $preAnte = trim((string) ($cert['preAnte'] ?? ''));
        $diaEm = trim((string) ($cert['diaEmision'] ?? ''));
        $mesEm = trim((string) ($cert['mesEmision'] ?? ''));
        $anioEm = trim((string) ($cert['anioEmision'] ?? ''));

        $this->SetXY(self::MARGEN_IZQ, 41);
        $this->SetFont(self::FUENTE, '', 10);

        $html1 = 'Certifica que el/la estudiante <b>'.$this->escapeHtml($apellido.' '.$nombre).'</b>'
            .', D.N.I. Nº '.$this->escapeHtml($dni).' '.$verbo.' el '
            .$this->escapeHtml($fechIniFin)
            .' como alumno/a regular del ciclo lectivo normal, correspondiente al año '
            .$ano.' en '.$this->escapeHtml($curso).' en este establecimiento educativo.';

        $this->writeHTMLCell(self::ANCHO_UTIL, 0, self::MARGEN_IZQ, 41, $html1, 0, 1, false, true, 'J', true);

        $this->Ln(4);

        $html2 = 'Este certificado se extiende en '.$this->escapeHtml($localidad).', a los '
            .$this->escapeHtml($diaEm).' días del mes de '.$this->escapeHtml($mesEm)
            .' del año '.$this->escapeHtml($anioEm).', para ser presentado por el/la Sr/a '
            .$this->escapeHtml($prePor).' D.N.I. Nº '.$this->escapeHtml($prePorDni)
            .' ante '.$this->escapeHtml($preAnte).'.';

        $this->writeHTMLCell(self::ANCHO_UTIL, 0, self::MARGEN_IZQ, $this->GetY(), $html2, 0, 1, false, true, 'J', true);
    }

    private function dibujarFirmas(): void
    {
        $y = $this->GetY() + self::ESPACIO_TEXTO_FIRMAS_MM;

        $this->SetFont(self::FUENTE, '', 8);
        $this->SetXY(50, $y);
        $this->Cell(20, 5, 'Sello', 0, 0, 'C');

        $this->SetXY(120, $y);
        $this->Cell(50, 5, '..........................................................', 0, 0, 'C');

        $this->SetXY(120, $y + 3);
        $this->Cell(50, 5, 'Firma Autorizada', 0, 0, 'C');
    }

    private function escapeHtml(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
