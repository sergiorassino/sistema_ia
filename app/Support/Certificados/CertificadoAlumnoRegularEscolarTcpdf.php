<?php

namespace App\Support\Certificados;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use App\Support\Pdf\TcpdfMultiCellJustificado;
use TCPDF;

/**
 * Constancia de alumno/a regular — modelo escolar (A4, TCPDF + Arial).
 */
final class CertificadoAlumnoRegularEscolarTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const ANCHO_UTIL = 180.0;

    /** Separación entre el último párrafo del certificado y el bloque de firmas (3 cm). */
    private const ESPACIO_TEXTO_FIRMAS_MM = 30.0;

    private const ALTO_LINEA_MM = 5.0;

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
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), self::MARGEN_IZQ + 5, $y0 + 1, 15, 20, '', '', '', false, 300);
        }

        $this->SetXY(self::MARGEN_IZQ, $y0 + 5);
        TcpdfFuenteArial::aplicar($this, 'B', 12);
        $this->Cell(self::ANCHO_UTIL, 5, $insti, 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(self::ANCHO_UTIL, 5, 'CONSTANCIA DE ALUMNO/A REGULAR', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 7);
        $cueLinea = $cue !== '' ? 'C.U.E.:  '.$cue : '';
        $this->Cell(self::ANCHO_UTIL, 5, $cueLinea, 0, 2, 'C');
    }

    private function dibujarCuerpo(): void
    {
        $leg = $this->datos['legajo'] ?? [];
        $cert = $this->datos['certificado'] ?? [];

        $apellido = trim((string) ($leg['apellido'] ?? ''));
        $nombre = trim((string) ($leg['nombre'] ?? ''));
        $estudiante = trim($apellido.' '.$nombre);
        $dni = trim((string) ($leg['dni'] ?? ''));
        $ano = (int) ($cert['anoLectivo'] ?? 0);
        $curso = trim((string) ($cert['curso'] ?? ''));
        $localidad = trim((string) (($this->datos['institucion'] ?? [])['localidad'] ?? ''));
        $preAnte = trim((string) ($cert['preAnte'] ?? ''));
        $diaEm = trim((string) ($cert['diaEmision'] ?? ''));
        $mesEm = trim((string) ($cert['mesEmision'] ?? ''));
        $anioEm = trim((string) ($cert['anioEmision'] ?? ''));

        $this->SetXY(self::MARGEN_IZQ, 41);
        TcpdfFuenteArial::aplicar($this, '', 10);

        $parrafo1 = 'Se hace constar que el/la estudiante '.$estudiante
            .', DNI Nº '.$dni.', es alumno/a regular de '.$curso
            .' en este establecimiento, en el presente ciclo lectivo normal, correspondiente al año '
            .$ano.'.';

        TcpdfMultiCellJustificado::escribir($this, self::ANCHO_UTIL, self::ALTO_LINEA_MM, $parrafo1);

        $this->Ln(4);
        $this->SetX(self::MARGEN_IZQ);

        $parrafo2 = 'Este certificado se extiende en '.$localidad.', a los '
            .$diaEm.' días del mes de '.$mesEm
            .' del año '.$anioEm.', para ser presentado ante '.$preAnte.'.';

        TcpdfMultiCellJustificado::escribir($this, self::ANCHO_UTIL, self::ALTO_LINEA_MM, $parrafo2);
    }

    private function dibujarFirmas(): void
    {
        $y = $this->GetY() + self::ESPACIO_TEXTO_FIRMAS_MM;

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->SetXY(50, $y);
        $this->Cell(20, 5, 'Sello', 0, 0, 'C');

        $this->SetXY(120, $y);
        $this->Cell(50, 5, '..........................................................', 0, 0, 'C');

        $this->SetXY(120, $y + 3);
        $this->Cell(50, 5, 'Firma Autorizada', 0, 0, 'C');
    }
}
