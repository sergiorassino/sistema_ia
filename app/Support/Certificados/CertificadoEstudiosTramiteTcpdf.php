<?php

namespace App\Support\Certificados;

use TCPDF;

/**
 * Constancia de certificado de estudios en trámite (A4, formato legacy FPDF → TCPDF).
 */
final class CertificadoEstudiosTramiteTcpdf extends TCPDF
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
        $this->SetTitle('Constancia de Certificado en Trámite');
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
        $this->Cell(self::ANCHO_UTIL, 5, $insti, 0, 2, 'C');

        $this->SetFont(self::FUENTE, '', 8);
        $cueLinea = $cue !== '' ? 'CUE: '.$cue : '';
        $this->Cell(self::ANCHO_UTIL, 5, $cueLinea, 0, 2, 'C');

        $this->SetFont(self::FUENTE, '', 10);
        $this->Cell(self::ANCHO_UTIL, 5, 'CONSTANCIA DE CERTIFICADO DE ESTUDIOS EN TRÁMITE', 0, 2, 'C');
    }

    private function dibujarCuerpo(): void
    {
        $leg = $this->datos['legajo'] ?? [];
        $cert = $this->datos['constancia'] ?? [];
        $inst = $this->datos['institucion'] ?? [];

        $apellido = trim((string) ($leg['apellido'] ?? ''));
        $nombre = trim((string) ($leg['nombre'] ?? ''));
        $dni = trim((string) ($leg['dni'] ?? ''));
        $curso = trim((string) ($cert['curso'] ?? ''));
        $plan = trim((string) ($cert['plan'] ?? ''));
        $mateAdeud = trim((string) ($cert['mateAdeud'] ?? ''));
        $idioma = trim((string) ($cert['idiomaCursado'] ?? ''));
        $preAnte = trim((string) ($cert['preAnte'] ?? ''));
        $localidad = trim((string) ($inst['localidad'] ?? ''));
        $diaEm = trim((string) ($cert['diaEmision'] ?? ''));
        $mesEm = trim((string) ($cert['mesEmision'] ?? ''));
        $anioEm = trim((string) ($cert['anioEmision'] ?? ''));
        $insti = trim((string) ($inst['insti'] ?? ''));

        $this->SetXY(self::MARGEN_IZQ, 37);
        $this->SetFont(self::FUENTE, '', 10);

        $htmlIntro = 'El director de '.$this->escapeHtml($insti !== '' ? $insti : 'este establecimiento')
            .' hace constar que <b>'.$this->escapeHtml($apellido.' '.$nombre).'</b>'
            .' de '.$this->escapeHtml($curso)
            .', tiene en trámite su Certificado de Estudios de '.$this->escapeHtml($plan).'.';

        $this->writeHTMLCell(self::ANCHO_UTIL, 0, self::MARGEN_IZQ, 37, $htmlIntro, 0, 1, false, true, 'J', true);

        $this->Ln(4);
        $this->SetFont(self::FUENTE, '', 10);
        $this->Write(5, 'DATOS COMPLEMENTARIOS:', '', false, '', true);
        $this->Ln(5);
        $this->Write(5, 'D.N.I.: '.$dni, '', false, '', true);
        $this->Ln(5);
        $this->Write(5, 'Materias Adeudadas: '.$mateAdeud, '', false, '', true);
        $this->Ln(5);
        $this->Write(5, 'Idioma Cursado: '.$idioma, '', false, '', true);
        $this->Ln(7);

        $htmlPie = 'A pedido del/de la interesado/a se extiende la presente constancia, sin enmiendas ni raspaduras, en '
            .$this->escapeHtml($localidad).' a los '.$this->escapeHtml($diaEm)
            .' días del mes de '.$this->escapeHtml($mesEm)
            .' del año '.$this->escapeHtml($anioEm)
            .' para ser presentado ante las autoridades de '.$this->escapeHtml($preAnte).'.';

        $this->writeHTMLCell(self::ANCHO_UTIL, 0, self::MARGEN_IZQ, $this->GetY(), $htmlPie, 0, 1, false, true, 'J', true);

        $this->Ln(5);
        $this->SetFont(self::FUENTE, 'B', 7);
        $nota = 'NOTA: LA PRESENTE CONSTANCIA TENDRÁ UNA VIGENCIA DE 60 (SESENTA) DÍAS CORRIDOS CONTADOS A PARTIR DE LA FECHA DE EMISIÓN.';
        $this->writeHTMLCell(self::ANCHO_UTIL, 0, self::MARGEN_IZQ, $this->GetY(), $nota, 0, 1, false, true, 'J', true);
    }

    private function escapeHtml(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
