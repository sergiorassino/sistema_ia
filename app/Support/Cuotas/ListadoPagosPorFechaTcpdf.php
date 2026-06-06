<?php

namespace App\Support\Cuotas;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Listado de pagos por fecha — TCPDF A4 vertical (réplica legacy FPDF).
 */
final class ListadoPagosPorFechaTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const ANCHO_BLOQUE = 180.0;

    private const ALTURA_ENCABEZADO = 22.0;

    private const ALTURA_FILA = 5.0;

    private const Y_ENCABEZADO_TABLA = 35.0;

    private const Y_PRIMERA_FILA = 41.0;

    private const Y_MAX_FILA = 270.0;

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
        $this->SetTitle('Listado de pagos por fecha');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetLeftMargin(self::MARGEN_IZQ);
        $this->SetMargins(self::MARGEN_IZQ, 10.0, 10.0);
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(0, 0, 0);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage('P', 'A4');
        $pdf->dibujarEncabezadoInstitucional();
        $pdf->dibujarEncabezadoTabla();
        $pdf->dibujarFilas();

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

    private function dibujarEncabezadoInstitucional(): void
    {
        /** @var array<string, mixed> $header */
        $header = $this->datos['pdfHeader'] ?? [];
        $insti = trim((string) ($header['insti'] ?? ''));

        $x = self::MARGEN_IZQ;
        $y = 10.0;

        $this->Rect($x, $y, self::ANCHO_BLOQUE, self::ALTURA_ENCABEZADO);

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(self::ANCHO_BLOQUE, 7, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(
            self::ANCHO_BLOQUE,
            5,
            'PAGOS RECIBIDOS ENTRE EL '.($this->datos['fechaDesdeEtiqueta'] ?? '').' y el '.($this->datos['fechaHastaEtiqueta'] ?? ''),
            0,
            2,
            'C',
        );
        $this->Cell(
            self::ANCHO_BLOQUE,
            5,
            'MEDIOS DE PAGO: '.($this->datos['titMedioPago'] ?? 'TODOS'),
            0,
            2,
            'C',
        );
        $this->Cell(
            self::ANCHO_BLOQUE,
            5,
            'FILTRO POR CUOTA: '.($this->datos['titFiltroCuota'] ?? 'TODAS'),
            0,
            2,
            'C',
        );
    }

    private function dibujarEncabezadoTabla(): void
    {
        $this->SetXY(self::MARGEN_IZQ, self::Y_ENCABEZADO_TABLA);
        TcpdfFuenteArial::aplicar($this, '', 6);

        $this->Cell(50, self::ALTURA_FILA, 'Estudiante', 1, 0, 'C');
        $this->Cell(20, self::ALTURA_FILA, 'Sala/Grado/Curso', 1, 0, 'C');
        $this->Cell(25, self::ALTURA_FILA, 'Fecha Pago', 1, 0, 'C');
        $this->Cell(15, self::ALTURA_FILA, 'Medio Pago', 1, 0, 'C');
        $this->Cell(25, self::ALTURA_FILA, 'Cuota', 1, 0, 'C');
        $this->Cell(15, self::ALTURA_FILA, 'Importe', 1, 0, 'C');
        $this->Cell(15, self::ALTURA_FILA, 'Bonificación', 1, 0, 'C');
        $this->Cell(15, self::ALTURA_FILA, 'Interés', 1, 0, 'C');
    }

    private function dibujarFilas(): void
    {
        /** @var list<array<string, mixed>> $filas */
        $filas = $this->datos['filas'] ?? [];
        $y = self::Y_PRIMERA_FILA;

        foreach ($filas as $fila) {
            if ($y + self::ALTURA_FILA > self::Y_MAX_FILA) {
                $this->AddPage('P', 'A4');
                $this->dibujarEncabezadoTabla();
                $y = self::Y_PRIMERA_FILA;
            }

            $this->SetXY(self::MARGEN_IZQ, $y);
            TcpdfFuenteArial::aplicar($this, '', 6);

            $this->Cell(50, self::ALTURA_FILA, (string) ($fila['estudiante'] ?? ''), 1, 0, 'L', true);
            $this->Cell(20, self::ALTURA_FILA, (string) ($fila['curso'] ?? ''), 1, 0, 'L', true);
            $this->Cell(25, self::ALTURA_FILA, (string) ($fila['fechaPago'] ?? ''), 1, 0, 'C', true);
            $this->Cell(15, self::ALTURA_FILA, (string) ($fila['medioPago'] ?? ''), 1, 0, 'C', true);
            $this->Cell(25, self::ALTURA_FILA, (string) ($fila['cuota'] ?? ''), 1, 0, 'L', true);
            $this->Cell(15, self::ALTURA_FILA, (string) ($fila['importe'] ?? ''), 1, 0, 'C', true);
            $this->Cell(15, self::ALTURA_FILA, (string) ($fila['bonificacion'] ?? ''), 1, 0, 'C', true);
            $this->Cell(15, self::ALTURA_FILA, (string) ($fila['interes'] ?? ''), 1, 0, 'C', true);

            $y += self::ALTURA_FILA;
        }

        if ($y + self::ALTURA_FILA > self::Y_MAX_FILA) {
            $this->AddPage('P', 'A4');
            $y = self::Y_PRIMERA_FILA;
        } else {
            $y += 1;
        }

        /** @var array<string, string> $totales */
        $totales = $this->datos['totales'] ?? [];

        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(135, self::ALTURA_FILA, 'Totales:', 1, 0, 'L', true);
        $this->Cell(15, self::ALTURA_FILA, (string) ($totales['importe'] ?? ''), 1, 0, 'C', true);
        $this->Cell(15, self::ALTURA_FILA, (string) ($totales['bonificacion'] ?? ''), 1, 0, 'C', true);
        $this->Cell(15, self::ALTURA_FILA, (string) ($totales['interes'] ?? ''), 1, 0, 'C', true);
    }
}
