<?php

namespace App\Support\Certificados;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfLogoInstitucional;
use TCPDF;

/**
 * Certificado Único de Salud (CUS) — A4 vertical, plantilla de fondo.
 */
final class CertificadoUnicoSaludTcpdf extends TCPDF
{
    private const FILL_GRIS = [232, 232, 232];

    /**
     * Tapa completa del logo/texto legacy incrustado en cus.jpg (escudo + leyenda arqueada).
     * Debe cubrir todo el bloque superior izquierdo, no solo el escudo.
     */
    private const LEGACY_LOGO_TAPA_X = 2.0;

    private const LEGACY_LOGO_TAPA_Y = 3.0;

    private const LEGACY_LOGO_TAPA_ANCHO = 61.0;

    private const LEGACY_LOGO_TAPA_ALTO = 38.0;

    /** Área útil para dibujar el logo institucional del colegio activo. */
    private const LOGO_INST_X = 15.0;

    private const LOGO_INST_Y = 6.0;

    private const LOGO_INST_ANCHO = 32.0;

    private const LOGO_INST_ALTO = 28.0;

    private ?string $plantilla;

    /** @var array{insti?: string, logo_file?: ?string}|null */
    private ?array $header;

    private function __construct(?string $plantilla, ?array $header = null)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->plantilla = $plantilla;
        $this->header = $header;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Certificado Único de Salud');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(0, 0, 0);
        $this->SetFillColor(...self::FILL_GRIS);
    }

    /**
     * @param  list<array<string, mixed>>  $alumnos
     * @param  array{insti?: string, logo_file?: ?string}|null  $header  Encabezado institucional (autogestión: studentPdfHeaderData).
     */
    public static function generarLote(array $alumnos, ?array $header = null): self
    {
        $pdf = new self(CusIsaVozImagenDatos::rutaPlantilla('cus.jpg'), $header);

        foreach ($alumnos as $alumno) {
            $pdf->AddPage('P', 'A4');
            $pdf->dibujarHoja($alumno);
        }

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
     * @param  array<string, mixed>  $alumno
     */
    private function dibujarHoja(array $alumno): void
    {
        if ($this->plantilla !== null && is_file($this->plantilla)) {
            $this->Image($this->plantilla, 0, 0, 210, 0, '', '', '', false, 300);
        }

        $this->dibujarLogoInstitucional();

        $cursec = (string) ($alumno['cursec'] ?? '');
        $this->SetXY(155, 10);
        TcpdfFuenteArial::aplicar($this, 'BI', 9);
        $this->Cell(40, 6, 'CURSO:  '.$cursec, 1, 0, 'C');

        $y = 40.0;
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->SetXY(15, $y);
        $this->Cell(30, 6, 'FECHA: ............. / .............. / ............', 0, 0, 'L');
        $this->SetXY(150, $y);
        $this->Cell(30, 6, 'D.N.I.: '.(string) ($alumno['dni'] ?? ''), 0, 0, 'L');

        $y += 6;
        $this->SetXY(15, $y);
        $this->Cell(50, 6, 'Apellidos y Nombres (según DNI): ', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, 'BI', 9);
        $this->Cell(30, 6, trim((string) ($alumno['apellido'] ?? '')).', '.trim((string) ($alumno['nombre'] ?? '')), 0, 0, 'L');

        TcpdfFuenteArial::aplicar($this, '', 8);
        $y += 6;
        $this->SetXY(15, $y);
        $this->Cell(50, 6, 'Fecha de Nacimiento: '.(string) ($alumno['fechnaci'] ?? ''), 0, 0, 'L');
        $this->Cell(20, 6, 'Edad: .......', 0, 0, 'L');
        $this->Cell(30, 6, 'Sexo: '.(string) ($alumno['sexo_etiqueta'] ?? ''), 0, 0, 'L');
        $lnCiudad = trim((string) ($alumno['ln_ciudad'] ?? ''));
        $lnProvincia = trim((string) ($alumno['ln_provincia'] ?? ''));
        $lugarNac = $lnCiudad.($lnProvincia !== '' ? ', '.$lnProvincia : '');
        $this->Cell(30, 6, 'Lugar de Nacimiento: '.$lugarNac, 0, 0, 'L');

        $y += 6;
        $this->SetXY(15, $y);
        $this->Cell(90, 6, 'Domicilio: '.(string) ($alumno['callenum'] ?? ''), 0, 0, 'L');
        $this->Cell(80, 6, 'Barrio: '.(string) ($alumno['barrio'] ?? ''), 0, 0, 'L');

        $y += 6;
        $this->SetXY(15, $y);
        $this->Cell(80, 6, 'Localidad: '.(string) ($alumno['localidad'] ?? ''), 0, 0, 'L');
        $this->Cell(50, 6, 'Cel. de la madre: '.(string) ($alumno['telemad'] ?? ''), 0, 0, 'L');
        $this->Cell(50, 6, 'Cel. del padre: '.(string) ($alumno['telepad'] ?? ''), 0, 0, 'L');

        $this->Rect(13, 39, 185, 32);
    }

    private function dibujarLogoInstitucional(): void
    {
        $this->SetFillColor(255, 255, 255);
        $this->Rect(
            self::LEGACY_LOGO_TAPA_X,
            self::LEGACY_LOGO_TAPA_Y,
            self::LEGACY_LOGO_TAPA_ANCHO,
            self::LEGACY_LOGO_TAPA_ALTO,
            'F',
        );

        $logoFile = pdfHeaderLogoAbsolutePath($this->header ?? schoolPdfHeaderData());
        TcpdfLogoInstitucional::dibujarAjustado(
            $this,
            self::LOGO_INST_X,
            self::LOGO_INST_Y,
            self::LOGO_INST_ANCHO,
            self::LOGO_INST_ALTO,
            $logoFile,
        );
    }
}
