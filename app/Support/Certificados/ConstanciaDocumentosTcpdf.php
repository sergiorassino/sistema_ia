<?php

namespace App\Support\Certificados;

use App\Support\Pdf\TcpdfImagenPng;
use TCPDF;

/**
 * Constancia de documentos (A4, formato legacy FPDF → TCPDF).
 */
final class ConstanciaDocumentosTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const ANCHO_UTIL = 180.0;

    private const FUENTE = 'dejavusans';

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
        $this->SetTitle('Constancia de Documentos');
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
        $pdf->dibujarMetadatosLegajo();
        $pdf->dibujarCuerpo();

        return $pdf;
    }

    private function dibujarEncabezado(): void
    {
        $inst = $this->datos['institucion'] ?? [];
        $insti = trim((string) ($inst['insti'] ?? ''));
        $logo = $inst['logo_abs'] ?? null;

        $y0 = 10.0;
        $this->Rect(self::MARGEN_IZQ, $y0, self::ANCHO_UTIL, 22.0, 'D');

        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), self::MARGEN_IZQ + 5, $y0 + 1, 15, 20, '', '', '', false, 300);
        }

        $this->SetXY(self::MARGEN_IZQ, $y0 + 5);
        $this->SetFont(self::FUENTE, 'B', 12);
        $this->Cell(self::ANCHO_UTIL, 7, 'CONSTANCIA DE DOCUMENTOS', 0, 2, 'C');

        $this->SetFont(self::FUENTE, '', 10);
        $this->Cell(self::ANCHO_UTIL, 5, $insti, 0, 2, 'C');
    }

    private function dibujarMetadatosLegajo(): void
    {
        $leg = $this->datos['legajo'] ?? [];

        $libro = trim((string) ($leg['libro'] ?? ''));
        $folio = trim((string) ($leg['folio'] ?? ''));
        $matricula = trim((string) ($leg['matricula'] ?? ''));
        $legajo = trim((string) ($leg['legajo'] ?? ''));

        $this->SetXY(60, 41);
        $this->SetFont(self::FUENTE, '', 7);
        $this->Cell(60, 5, 'Libro: '.$libro, 0, 0, 'L');
        $this->Cell(60, 5, 'Folio: '.$folio, 0, 1, 'L');

        $this->SetXY(60, 47);
        $this->Cell(60, 5, 'Nº Matrícula: '.$matricula, 0, 0, 'L');
        $this->Cell(60, 5, 'Legajo: '.$legajo, 0, 1, 'L');
    }

    private function dibujarCuerpo(): void
    {
        $leg = $this->datos['legajo'] ?? [];
        $cert = $this->datos['constancia'] ?? [];

        $apellido = trim((string) ($leg['apellido'] ?? ''));
        $nombre = trim((string) ($leg['nombre'] ?? ''));
        $dni = trim((string) ($leg['dni'] ?? ''));
        $certifde = trim((string) ($cert['certifde'] ?? ''));
        $otorpor = trim((string) ($cert['otorpor'] ?? ''));
        $fechotor = trim((string) ($cert['fechotor'] ?? ''));
        $parnacop = trim((string) ($cert['parnacop'] ?? ''));
        $nombrepad = trim((string) ($leg['nombrepad'] ?? ''));
        $nombremad = trim((string) ($leg['nombremad'] ?? ''));
        $diaNac = trim((string) ($leg['diaNac'] ?? ''));
        $mesNac = trim((string) ($leg['mesNac'] ?? ''));
        $anioNac = trim((string) ($leg['anioNac'] ?? ''));
        $lnCiudad = trim((string) ($leg['ln_ciudad'] ?? ''));
        $parapre = trim((string) ($cert['parapre'] ?? ''));
        $diaEm = trim((string) ($cert['diaEmision'] ?? ''));
        $mesEm = trim((string) ($cert['mesEmision'] ?? ''));
        $anioEm = trim((string) ($cert['anioEmision'] ?? ''));

        $this->SetXY(self::MARGEN_IZQ, 61);
        $this->SetFont(self::FUENTE, '', 10);

        $htmlIntro = 'El Director del I.E.S. hace constar que el/la alumno/a '
            .'<b>'.$this->escapeHtml($apellido.' '.$nombre).'</b>'
            .', D.N.I. Nº '.$this->escapeHtml($dni)
            .' tiene archivados en la Secretaría de este Establecimiento los siguientes documentos:';

        $this->writeHTMLCell(self::ANCHO_UTIL, 0, self::MARGEN_IZQ, 61, $htmlIntro, 0, 1, false, true, 'J', true);

        $this->Ln(2);
        $this->SetFont(self::FUENTE, '', 10);

        $lineaCert = 'CERTIFICADO DE '.$this->escapeHtml($certifde).' GRADO del/de la alumno/a: '
            .$this->escapeHtml($apellido.' '.$nombre)
            .' otorgado por '.$this->escapeHtml($otorpor).' el día '.$this->escapeHtml($fechotor).'.';

        $this->writeHTMLCell(self::ANCHO_UTIL, 0, self::MARGEN_IZQ, $this->GetY(), $lineaCert, 0, 1, false, true, 'J', true);

        $this->Ln(2);

        $lineaPartida = 'PARTIDA DE NACIMIENTO otorgada por '.$this->escapeHtml($parnacop)
            .' donde consta que la recurrente es hijo/a de '.$this->escapeHtml($nombrepad)
            .' y de '.$this->escapeHtml($nombremad)
            .' y que nació el día '.$this->escapeHtml($diaNac)
            .' del mes de '.$this->escapeHtml($mesNac)
            .' de '.$this->escapeHtml($anioNac)
            .' en '.$this->escapeHtml($lnCiudad).'.';

        $this->writeHTMLCell(self::ANCHO_UTIL, 0, self::MARGEN_IZQ, $this->GetY(), $lineaPartida, 0, 1, false, true, 'J', true);

        $this->Ln(2);

        $lineaPie = 'A pedido del/de la interesado/a y para ser presentado a '.$this->escapeHtml($parapre)
            .' se expide la presente, sin enmiendas ni raspaduras, refrendado por el/la secretario/a a los '
            .$this->escapeHtml($diaEm).' días del mes de '.$this->escapeHtml($mesEm)
            .' del año '.$this->escapeHtml($anioEm).'.';

        $this->writeHTMLCell(self::ANCHO_UTIL, 0, self::MARGEN_IZQ, $this->GetY(), $lineaPie, 0, 1, false, true, 'J', true);
    }

    private function escapeHtml(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
