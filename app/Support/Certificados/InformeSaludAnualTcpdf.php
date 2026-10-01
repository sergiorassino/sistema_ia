<?php

namespace App\Support\Certificados;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Informe de Salud Anual (I.S.A.) — A4 vertical, plantilla de fondo.
 */
final class InformeSaludAnualTcpdf extends TCPDF
{
    private const FILL_GRIS = [232, 232, 232];

    private ?string $plantilla;

    private string $insti;

    private function __construct(?string $plantilla, string $insti)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->plantilla = $plantilla;
        $this->insti = $insti;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Informe de Salud Anual');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(0, 0, 0);
        $this->SetFillColor(...self::FILL_GRIS);
    }

    /**
     * @param  list<array<string, mixed>>  $alumnos
     */
    public static function generarLote(array $alumnos, string $insti): self
    {
        $pdf = new self(CusIsaVozImagenDatos::rutaPlantilla('isa.jpg'), $insti);

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

    public static function aplicarMargenes(TCPDF $pdf): void
    {
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
    }

    /**
     * @param  array<string, mixed>  $alumno
     */
    public static function dibujarPagina(TCPDF $pdf, array $alumno, ?string $plantilla, string $insti): void
    {
        self::aplicarMargenes($pdf);

        if ($plantilla !== null && is_file($plantilla)) {
            $pdf->Image($plantilla, 0, 3, 210, 0, '', '', '', false, 300);
        }

        $cursec = (string) ($alumno['cursec'] ?? '');
        $pdf->SetXY(15, 10);
        TcpdfFuenteArial::aplicar($pdf, 'B', 14);
        $pdf->Cell(180, 8, $insti.'   *   '.$cursec, 1, 0, 'C');

        $y = 67.0;
        TcpdfFuenteArial::aplicar($pdf, '', 8);
        $pdf->SetXY(15, $y);
        $pdf->Cell(30, 6, 'FECHA: ............. / .............. / ............', 0, 0, 'L');

        $y += 6;
        $pdf->SetXY(15, $y);
        $pdf->Cell(50, 6, 'Apellido y Nombre del Estudiante: ', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($pdf, 'BI', 9);
        $pdf->Cell(100, 6, trim((string) ($alumno['apellido'] ?? '')).', '.trim((string) ($alumno['nombre'] ?? '')), 0, 0, 'L');

        $y += 6;
        TcpdfFuenteArial::aplicar($pdf, '', 8);
        $pdf->SetXY(15, $y);
        $pdf->Cell(30, 6, 'D.N.I.: '.(string) ($alumno['dni'] ?? ''), 0, 0, 'L');

        $y += 6;
        $pdf->SetXY(15, $y);
        $pdf->Cell(80, 6, 'Escuela: '.$insti, 0, 0, 'L');
        $pdf->Cell(50, 6, 'Curso: '.$cursec, 0, 0, 'L');
        $pdf->Cell(50, 6, 'Turno: .......................', 0, 0, 'L');

        $y += 6;
        $pdf->SetXY(15, $y);
        $pdf->Cell(20, 6, 'Edad: .......', 0, 0, 'L');
        $pdf->Cell(30, 6, 'Sexo: '.(string) ($alumno['sexo_etiqueta'] ?? ''), 0, 0, 'L');
        $pdf->Cell(50, 6, 'Fecha de Nacimiento: '.(string) ($alumno['fechnaci'] ?? ''), 0, 0, 'L');

        $y += 6;
        $pdf->SetXY(15, $y);
        $pdf->Cell(90, 6, 'Domicilio: '.(string) ($alumno['callenum'] ?? ''), 0, 0, 'L');
        $pdf->Cell(80, 6, 'Localidad: '.(string) ($alumno['localidad'] ?? ''), 0, 0, 'L');

        $y += 6;
        $pdf->SetXY(15, $y);
        $pdf->Cell(70, 6, 'Teléfonos: Madre: '.(string) ($alumno['telemad'] ?? '').'   Padre: '.(string) ($alumno['telepad'] ?? ''), 0, 0, 'L');
        $pdf->Cell(17, 6, 'Obra Social: ', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($pdf, 'B', 8);
        $pdf->Cell(15, 6, 'SI  /  NO', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($pdf, '', 8);
        $pdf->Cell(30, 6, 'Cuál: ..........................................       Grupo Sanguíneo: ...........', 0, 0, 'L');

        $pdf->Rect(13, 64, 185, 47);
    }

    /**
     * @param  array<string, mixed>  $alumno
     */
    private function dibujarHoja(array $alumno): void
    {
        self::dibujarPagina($this, $alumno, $this->plantilla, $this->insti);
    }
}
