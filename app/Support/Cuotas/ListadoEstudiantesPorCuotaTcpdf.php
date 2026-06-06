<?php

namespace App\Support\Cuotas;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Listado de estudiantes por cuota — TCPDF A4 apaisado.
 */
final class ListadoEstudiantesPorCuotaTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 15.0;

    private const MARGEN_DER = 15.0;

    private const MARGEN_SUP = 12.0;

    /** Ancho útil A4 apaisado (297 mm) menos márgenes laterales. */
    private const ANCHO_TABLA = 267.0;

    private const ALTURA_ENCABEZADO = 26.0;

    private const ALTURA_FILA = 5.0;

    private const Y_ENCABEZADO_TABLA = 40.0;

    private const Y_PRIMERA_FILA = 46.0;

    private const Y_MAX_FILA = 190.0;

    private const TAMANO_FUENTE = 6;

    /** @var list<float> Suma = 267 mm */
    private const ANCHOS_COL = [
        7, 52, 24, 12, 20, 32, 14, 14, 14, 16, 13, 13, 13, 13,
    ];

    /** @var list<string> */
    private const TITULOS_COL = [
        '#', 'Estudiante', 'Curso', 'Año', 'Nivel', 'Cuota',
        'Venc 1', 'Venc 2', 'Venc 3', 'Importe', 'Bonif.', 'Interés', 'Pagado', 'Saldo',
    ];

    /** @var array<string, mixed> */
    private array $datos;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8', false);
        $this->datos = $datos;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Listado de estudiantes por cuota');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetLeftMargin(self::MARGEN_IZQ);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
        $this->SetDrawColor(0, 0, 0);
        $this->setCellHeightRatio(1.05);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage('L', 'A4');
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

        /** @var array<string, mixed> $filtros */
        $filtros = $this->datos['filtros'] ?? [];

        $x = self::MARGEN_IZQ;
        $y = self::MARGEN_SUP;

        $this->Rect($x, $y, self::ANCHO_TABLA, self::ALTURA_ENCABEZADO);

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(self::ANCHO_TABLA, 6, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(self::ANCHO_TABLA, 5, 'LISTADO DE CUOTAS POR ALUMNO', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 6);
        $lineaFiltros = self::lineaFiltros($filtros);
        $this->Cell(self::ANCHO_TABLA, 4, $lineaFiltros, 0, 2, 'C');
        $this->Cell(
            self::ANCHO_TABLA,
            4,
            'Ciclo de trabajo: '.(string) ($this->datos['anoContexto'] ?? ''),
            0,
            2,
            'C',
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private static function lineaFiltros(array $filtros): string
    {
        $partes = [];
        $partes[] = 'Año cuota: '.(string) ($filtros['titAno'] ?? 'TODOS');
        $partes[] = 'Curso: '.(string) ($filtros['titCurso'] ?? 'TODOS');
        $partes[] = 'Cuota: '.(string) ($filtros['titCuota'] ?? 'TODAS');

        $importe = trim((string) ($filtros['titImporte'] ?? ''));
        if ($importe !== '') {
            $partes[] = $importe;
        }

        $pagado = trim((string) ($filtros['titPagado'] ?? ''));
        if ($pagado !== '') {
            $partes[] = $pagado;
        }

        return implode(' · ', $partes);
    }

    private function dibujarEncabezadoTabla(): void
    {
        $this->dibujarFilaTabla(self::Y_ENCABEZADO_TABLA, self::TITULOS_COL, true, false);
    }

    private function dibujarFilas(): void
    {
        /** @var list<array<string, mixed>> $filas */
        $filas = $this->datos['filas'] ?? [];
        $y = self::Y_PRIMERA_FILA;
        $indice = 0;

        foreach ($filas as $fila) {
            if ($y + self::ALTURA_FILA > self::Y_MAX_FILA) {
                $this->AddPage('L', 'A4');
                $this->dibujarEncabezadoTabla();
                $y = self::Y_PRIMERA_FILA;
                $indice = 0;
            }

            $valores = [
                (string) ($fila['numero'] ?? ''),
                (string) ($fila['estudiante'] ?? ''),
                (string) ($fila['cursec'] ?? ''),
                (string) ($fila['ano'] ?? ''),
                (string) ($fila['nivel'] ?? ''),
                (string) ($fila['cuota'] ?? ''),
                (string) ($fila['venc1'] ?? ''),
                (string) ($fila['venc2'] ?? ''),
                (string) ($fila['venc3'] ?? ''),
                (string) ($fila['importe'] ?? ''),
                (string) ($fila['bonificacion'] ?? ''),
                (string) ($fila['interes'] ?? ''),
                (string) ($fila['pagado'] ?? ''),
                (string) ($fila['saldo'] ?? ''),
            ];

            $this->dibujarFilaTabla($y, $valores, false, $indice % 2 === 1);
            $y += self::ALTURA_FILA;
            $indice++;
        }

        if ($filas !== []) {
            if ($y + self::ALTURA_FILA > self::Y_MAX_FILA) {
                $this->AddPage('L', 'A4');
                $this->dibujarEncabezadoTabla();
                $y = self::Y_PRIMERA_FILA;
            } else {
                $y += 0.5;
            }

            /** @var array<string, string> $totales */
            $totales = $this->datos['totales'] ?? [];
            $valoresTotales = [
                '', '', '', '', '', 'TOTALES',
                '', '', '',
                (string) ($totales['importe'] ?? ''),
                (string) ($totales['bonificacion'] ?? ''),
                (string) ($totales['interes'] ?? ''),
                (string) ($totales['pagado'] ?? ''),
                (string) ($totales['saldo'] ?? ''),
            ];
            $this->dibujarFilaTabla($y, $valoresTotales, true, false);
        }
    }

    /**
     * @param  list<string>  $valores
     */
    private function dibujarFilaTabla(float $y, array $valores, bool $negrita, bool $fondoSuave): void
    {
        $x = self::MARGEN_IZQ;
        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, $negrita ? 'B' : '', self::TAMANO_FUENTE);

        if ($fondoSuave) {
            $this->SetFillColor(225, 237, 240);
        } else {
            $this->SetFillColor(255, 255, 255);
        }

        foreach (self::ANCHOS_COL as $i => $ancho) {
            $texto = self::recortarTexto($valores[$i] ?? '', $ancho);
            $align = match (true) {
                $i === 0 => 'C',
                $i >= 9 => 'R',
                $i >= 6 && $i <= 8 => 'C',
                default => 'L',
            };
            $this->Cell($ancho, self::ALTURA_FILA, $texto, 1, 0, $align, true);
        }
    }

    private static function recortarTexto(string $texto, float $anchoMm): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }

        $maxChars = max(4, (int) floor($anchoMm * 1.35));

        if (mb_strlen($texto) <= $maxChars) {
            return $texto;
        }

        return mb_substr($texto, 0, max(1, $maxChars - 1)).'…';
    }
}
