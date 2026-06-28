<?php

namespace App\Support\CalificacionesPrimario\Epq;

use App\Support\Pdf\TcpdfFuenteArial;
use Illuminate\Http\Response;
use TCPDF;

/**
 * Planilla de calificaciones primario EPQ — A4 vertical, una hoja por materia (layout legacy FPDF).
 */
final class PlanillaCalificacionesEpqTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const ANCHO_UTIL = 179.0;

    private const ANCHO_NRO = 7.0;

    private const ANCHO_NOMBRE = 60.0;

    private const ANCHO_DIAG = 10.0;

    private const ANCHO_TRIM = 14.0;

    private const ANCHO_PROM = 20.0;

    private const ANCHO_RECUP = 10.0;

    private const ANCHO_DEF = 20.0;

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
        $xBase = self::MARGEN_IZQ;
        $y = $this->GetY();

        $this->SetXY($xBase, $y);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(self::ANCHO_NRO, 21, 'Nº', 1, 0, 'C');
        $this->Cell(self::ANCHO_NOMBRE, 21, 'Estudiantes', 1, 0, 'C');

        $this->SetXY($xBase, $y);
        TcpdfFuenteArial::aplicar($this, 'I', 7);
        $this->Cell(self::ANCHO_NRO, 15, '', 0, 0, 'C');
        $this->Cell(self::ANCHO_NOMBRE, 15, '', 0, 0, 'C');
        $this->Cell(112, 4, 'Seguimiento Pedagógico', 1, 0, 'C');
        $this->Ln(4);

        $this->SetX($xBase);
        $this->Cell(self::ANCHO_NRO, 4, '', 0, 0, 'C');
        $this->Cell(self::ANCHO_NOMBRE, 4, '', 0, 0, 'C');
        $this->Cell(self::ANCHO_DIAG, 4, '', 'LTR', 0, 'C');
        $this->Cell(self::ANCHO_TRIM, 4, '', 'LTR', 0, 'C');
        $this->Cell(self::ANCHO_TRIM, 4, '', 'LTR', 0, 'C');
        $this->Cell(self::ANCHO_TRIM, 4, '', 'LTR', 0, 'C');
        $this->Cell(self::ANCHO_PROM, 4, '', 'LTR', 0, 'C');
        $this->Cell(40, 4, 'Compensac. Aprend.', 1, 0, 'C');
        $this->Ln(4);

        $xNotas = $xBase + self::ANCHO_NRO + self::ANCHO_NOMBRE;
        $this->SetX($xNotas);
        $this->Cell(self::ANCHO_DIAG, 7, 'Período', 'LR', 0, 'C');
        $this->Cell(self::ANCHO_TRIM, 7, '1º', 'LR', 0, 'C');
        $this->Cell(self::ANCHO_TRIM, 7, '2º', 'LR', 0, 'C');
        $this->Cell(self::ANCHO_TRIM, 7, '3º', 'LR', 0, 'C');
        $this->Cell(self::ANCHO_PROM, 7, 'Prom.', 'LR', 0, 'C');
        $this->Cell(self::ANCHO_RECUP, 7, 'Recup.', 'LTR', 0, 'C');
        $this->Cell(self::ANCHO_RECUP, 7, 'Recup.', 'LTR', 0, 'C');
        $this->Cell(self::ANCHO_DEF, 7, 'Calif.', 'LTR', 0, 'C');
        $this->Ln(6);

        $this->SetX($xNotas);
        $this->Cell(self::ANCHO_DIAG, 7, 'Diag.', 'LBR', 0, 'C');
        $this->Cell(self::ANCHO_TRIM, 7, 'Trim', 'LBR', 0, 'C');
        $this->Cell(self::ANCHO_TRIM, 7, 'Trim', 'LBR', 0, 'C');
        $this->Cell(self::ANCHO_TRIM, 7, 'Trim', 'LBR', 0, 'C');
        $this->Cell(self::ANCHO_PROM, 7, 'Final', 'LBR', 0, 'C');
        $this->Cell(self::ANCHO_RECUP, 7, 'Dic', 'LBR', 0, 'C');
        $this->Cell(self::ANCHO_RECUP, 7, 'Feb', 'LBR', 0, 'C');
        $this->Cell(self::ANCHO_DEF, 7, 'Def.', 'LBR', 0, 'C');
        $this->Ln(8);
    }

    /**
     * @param  array<string, mixed>  $hoja
     */
    private function dibujarFilasAlumnos(array $hoja): void
    {
        /** @var list<array<string, mixed>> $alumnos */
        $alumnos = $hoja['alumnos'] ?? [];

        TcpdfFuenteArial::aplicar($this, '', 7);
        foreach ($alumnos as $alumno) {
            $this->SetX(self::MARGEN_IZQ);
            $this->Cell(self::ANCHO_NRO, self::ALTURA_FILA, (string) ($alumno['nro'] ?? ''), 1, 0, 'C');
            $this->Cell(self::ANCHO_NOMBRE, self::ALTURA_FILA, ' '.(string) ($alumno['nombre'] ?? ''), 1, 0, 'L');
            $this->Cell(self::ANCHO_DIAG, self::ALTURA_FILA, '', 1, 0, 'C');
            $this->Cell(self::ANCHO_TRIM, self::ALTURA_FILA, (string) ($alumno['ic01'] ?? ''), 1, 0, 'C');
            $this->Cell(self::ANCHO_TRIM, self::ALTURA_FILA, (string) ($alumno['ic02'] ?? ''), 1, 0, 'C');
            $this->Cell(self::ANCHO_TRIM, self::ALTURA_FILA, (string) ($alumno['ic03'] ?? ''), 1, 0, 'C');
            $this->Cell(self::ANCHO_PROM, self::ALTURA_FILA, (string) ($alumno['ic04'] ?? ''), 1, 0, 'C');
            $this->Cell(self::ANCHO_RECUP, self::ALTURA_FILA, (string) ($alumno['ic05'] ?? ''), 1, 0, 'C');
            $this->Cell(self::ANCHO_RECUP, self::ALTURA_FILA, (string) ($alumno['ic06'] ?? ''), 1, 0, 'C');
            $this->Cell(self::ANCHO_DEF, self::ALTURA_FILA, (string) ($alumno['ic07'] ?? ''), 1, 0, 'C');
            $this->Ln(self::ALTURA_FILA);
        }
    }
}
