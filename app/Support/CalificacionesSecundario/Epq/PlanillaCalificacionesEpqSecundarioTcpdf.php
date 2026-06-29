<?php

namespace App\Support\CalificacionesSecundario\Epq;

use App\Support\Pdf\TcpdfFuenteArial;
use Illuminate\Http\Response;
use TCPDF;

/**
 * Planilla de calificaciones secundario EPQ — A4 vertical, layout legacy FPDF → TCPDF.
 */
final class PlanillaCalificacionesEpqSecundarioTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const ANCHO_UTIL = 179.0;

    private const FILL_GRIS = [232, 232, 232];

    private const ANCHO_NRO = 7.0;

    private const ANCHO_NOMBRE = 60.0;

    private const ANCHO_INF = 10.0;

    private const ANCHO_CUAT = 15.0;

    private const ANCHO_EV_INT = 12.0;

    private const ANCHO_NOTA_FINAL = 18.0;

    private const ANCHO_COLOQ = 6.0;

    private const ALTURA_FILA = 5.0;

    /** @var array<string, mixed> */
    private array $contexto;

    /** @var list<array<string, mixed>> */
    private array $hojas;

    /**
     * @param  array<string, mixed>  $contexto
     * @param  list<array<string, mixed>>  $hojas
     */
    private function __construct(array $contexto, array $hojas)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->contexto = $contexto;
        $this->hojas = $hojas;
        $this->SetCreator('Sistema Escolar');
        $this->SetTitle('Planilla de Calificaciones');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetLeftMargin(self::MARGEN_IZQ);
        $this->SetLineWidth(0.2);
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @param  list<array<string, mixed>>  $hojas
     */
    public static function generar(array $contexto, array $hojas): self
    {
        $pdf = new self($contexto, $hojas);
        foreach ($hojas as $hoja) {
            $pdf->dibujarHoja($hoja);
        }

        return $pdf;
    }

    public static function respuestaHttp(self $pdf, string $nombreArchivo): Response
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
     * @param  array<string, mixed>  $hoja
     */
    private function dibujarHoja(array $hoja): void
    {
        $this->AddPage();
        $this->dibujarEncabezado($hoja);
        $this->dibujarEncabezadoTabla();
        $this->dibujarFilasAlumnos($hoja);
        $this->dibujarPieFirmas();
    }

    /**
     * @param  array<string, mixed>  $hoja
     */
    private function dibujarEncabezado(array $hoja): void
    {
        $insti = (string) ($this->contexto['insti'] ?? '');
        $ano = (int) ($this->contexto['ano'] ?? 0);
        $materia = (string) ($hoja['materia'] ?? '');
        $curso = (string) ($hoja['curso'] ?? '');
        $profesores = (string) ($hoja['profesores'] ?? 'Prof: ');

        $this->SetXY(self::MARGEN_IZQ, 20);
        $this->Rect(self::MARGEN_IZQ, 20, self::ANCHO_UTIL, 22);

        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(self::ANCHO_UTIL - 2, 7, $insti, 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(self::ANCHO_UTIL - 2, 5, 'PLANILLA DE CALIFICACIONES '.$ano, 0, 2, 'C');
        $this->Cell(self::ANCHO_UTIL - 2, 5, $materia.' - '.$curso, 0, 2, 'C');

        $this->SetX(40);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(50, 5, $profesores, 0, 0, 'L');
        $this->Ln(7);
    }

    private function dibujarEncabezadoTabla(): void
    {
        $this->SetFillColor(...self::FILL_GRIS);
        $this->SetX(self::MARGEN_IZQ);
        TcpdfFuenteArial::aplicar($this, 'I', 7);
        $this->Cell(self::ANCHO_NRO, self::ALTURA_FILA, 'Nº', 1, 0, 'C');
        $this->Cell(self::ANCHO_NOMBRE, self::ALTURA_FILA, 'Estudiantes', 1, 0, 'C');
        $this->Cell(self::ANCHO_INF, self::ALTURA_FILA, '1º Inf', 1, 0, 'C');
        $this->Cell(self::ANCHO_INF, self::ALTURA_FILA, '2º Inf', 1, 0, 'C');
        $this->Cell(self::ANCHO_CUAT, self::ALTURA_FILA, '1º Cuat.', 1, 0, 'C', true);
        $this->Cell(self::ANCHO_INF, self::ALTURA_FILA, '3º Inf', 1, 0, 'C');
        $this->Cell(self::ANCHO_INF, self::ALTURA_FILA, '4º Inf', 1, 0, 'C');
        $this->Cell(self::ANCHO_CUAT, self::ALTURA_FILA, '2º Cuat.', 1, 0, 'C', true);
        $this->Cell(self::ANCHO_EV_INT, self::ALTURA_FILA, 'Ev.Int.', 1, 0, 'C');
        $this->Cell(self::ANCHO_NOTA_FINAL, self::ALTURA_FILA, 'NOTA FINAL', 1, 0, 'C', true);
        $this->Cell(self::ANCHO_COLOQ, self::ALTURA_FILA, 'Dic', 1, 0, 'C');
        $this->Cell(self::ANCHO_COLOQ, self::ALTURA_FILA, 'Feb', 1, 0, 'C');
        $this->Ln(self::ALTURA_FILA);
    }

    /**
     * @param  array<string, mixed>  $hoja
     */
    private function dibujarFilasAlumnos(array $hoja): void
    {
        /** @var list<array<string, mixed>> $alumnos */
        $alumnos = $hoja['alumnos'] ?? [];

        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->SetFillColor(...self::FILL_GRIS);

        foreach ($alumnos as $alumno) {
            $this->SetX(self::MARGEN_IZQ);
            $this->Cell(self::ANCHO_NRO, self::ALTURA_FILA, (string) ($alumno['nro'] ?? ''), 1, 0, 'C');
            $this->Cell(self::ANCHO_NOMBRE, self::ALTURA_FILA, ' '.(string) ($alumno['nombre'] ?? ''), 1, 0, 'L');
            $this->Cell(self::ANCHO_INF, self::ALTURA_FILA, (string) ($alumno['ic07'] ?? ''), 1, 0, 'C');
            $this->Cell(self::ANCHO_INF, self::ALTURA_FILA, (string) ($alumno['ic14'] ?? ''), 1, 0, 'C');
            $this->Cell(self::ANCHO_CUAT, self::ALTURA_FILA, (string) ($alumno['ic31'] ?? ''), 1, 0, 'C', true);
            $this->Cell(self::ANCHO_INF, self::ALTURA_FILA, (string) ($alumno['ic21'] ?? ''), 1, 0, 'C');
            $this->Cell(self::ANCHO_INF, self::ALTURA_FILA, (string) ($alumno['ic28'] ?? ''), 1, 0, 'C');
            $this->Cell(self::ANCHO_CUAT, self::ALTURA_FILA, (string) ($alumno['ic32'] ?? ''), 1, 0, 'C', true);
            $this->Cell(self::ANCHO_EV_INT, self::ALTURA_FILA, (string) ($alumno['ic33'] ?? ''), 1, 0, 'C');
            $this->Cell(self::ANCHO_NOTA_FINAL, self::ALTURA_FILA, (string) ($alumno['ic34'] ?? ''), 1, 0, 'C', true);
            $this->Cell(self::ANCHO_COLOQ, self::ALTURA_FILA, (string) ($alumno['dic'] ?? ''), 1, 0, 'C');
            $this->Cell(self::ANCHO_COLOQ, self::ALTURA_FILA, (string) ($alumno['feb'] ?? ''), 1, 0, 'C');
            $this->Ln(self::ALTURA_FILA);
        }
    }

    private function dibujarPieFirmas(): void
    {
        $y = $this->GetY();
        $this->Rect(self::MARGEN_IZQ, $y + 6, self::ANCHO_UTIL, 20);

        $this->SetXY(self::MARGEN_IZQ, $y + 15);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(85, 5, '...........................................................', 0, 0, 'C');
        $this->Cell(85, 5, '...........................................................', 0, 0, 'C');

        $this->SetXY(self::MARGEN_IZQ, $y + 20);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(85, 5, 'Firma Preceptor/a', 0, 0, 'C');
        $this->Cell(85, 5, 'Firma Director/a', 0, 0, 'C');
    }
}
