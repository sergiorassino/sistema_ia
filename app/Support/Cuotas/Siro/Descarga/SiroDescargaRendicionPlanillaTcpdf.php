<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Planilla de descarga SIRO — TCPDF A4 apaisado.
 */
final class SiroDescargaRendicionPlanillaTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 10.0;

    private const MARGEN_DER = 10.0;

    private const MARGEN_SUP = 10.0;

    private const ANCHO_TABLA = 277.0;

    private const ALTURA_ENCABEZADO = 28.0;

    private const ALTURA_FILA = 5.0;

    private const Y_ENCABEZADO_TABLA = 40.0;

    private const Y_PRIMERA_FILA = 46.0;

    private const Y_MAX_FILA = 190.0;

    private const TAMANO_FUENTE = 6;

    /** @var list<float> */
    private const ANCHOS = [
        8, 18, 12, 55, 42, 32, 18, 12, 18, 14, 14, 20, 14,
    ];

    /** @var list<string> */
    private const TITULOS = [
        '#', 'F. pago', 'Canal', 'Estudiante', 'Curso', 'Cuota',
        '1º vto', 'Beca', 'Importe', 'Int.', 'Bonif.', 'Pagado', 'Impto.',
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
        $this->SetTitle('Planilla SIRO Nº '.($datos['nroPlanillaEtiqueta'] ?? ''));
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
        $yFin = $pdf->dibujarFilas();
        $pdf->dibujarTotales($yFin);

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
        $y = self::MARGEN_SUP;

        $this->Rect($x, $y, self::ANCHO_TABLA, self::ALTURA_ENCABEZADO);

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(self::ANCHO_TABLA, 6, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(
            self::ANCHO_TABLA,
            5,
            'PLANILLA DE DESCARGA SIRO Nº '.($this->datos['nroPlanillaEtiqueta'] ?? ''),
            0,
            2,
            'C',
        );

        TcpdfFuenteArial::aplicar($this, '', 6.5);
        $this->Cell(
            self::ANCHO_TABLA,
            4,
            'Fecha de carga: '.($this->datos['fechaCarga'] ?? '—')
                .' · Canal: '.($this->datos['canalPago'] ?? '—')
                .' · Pagos: '.(string) ($this->datos['cantidad'] ?? 0)
                .((! empty($this->datos['impactada'])) ? ' · Impactada' : ''),
            0,
            2,
            'C',
        );

        $archivo = trim((string) ($this->datos['nombreArchivo'] ?? ''));
        if ($archivo !== '') {
            $this->Cell(
                self::ANCHO_TABLA,
                4,
                'Archivo de origen: '.$archivo,
                0,
                2,
                'C',
            );
        }

        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(
            self::ANCHO_TABLA,
            4,
            'Impreso: '.now()->format('d/m/Y H:i'),
            0,
            2,
            'C',
        );
    }

    private function dibujarEncabezadoTabla(): void
    {
        $this->dibujarFilaTabla(self::Y_ENCABEZADO_TABLA, self::TITULOS, true, true);
    }

    private function dibujarFilas(): float
    {
        /** @var list<array<string, mixed>> $filas */
        $filas = $this->datos['filas'] ?? [];
        $y = self::Y_PRIMERA_FILA;

        foreach ($filas as $fila) {
            if ($y + self::ALTURA_FILA > self::Y_MAX_FILA) {
                $this->AddPage('L', 'A4');
                $this->dibujarEncabezadoTabla();
                $y = self::Y_PRIMERA_FILA;
            }

            $this->dibujarFilaTabla($y, [
                (string) ($fila['item'] ?? ''),
                (string) ($fila['fechaPago'] ?? ''),
                (string) ($fila['canal'] ?? ''),
                (string) ($fila['estudiante'] ?? ''),
                (string) ($fila['curso'] ?? ''),
                (string) ($fila['cuota'] ?? ''),
                (string) ($fila['venc1'] ?? ''),
                (string) ($fila['beca'] ?? ''),
                (string) ($fila['importe'] ?? ''),
                (string) ($fila['interes'] ?? ''),
                (string) ($fila['bonificacion'] ?? ''),
                (string) ($fila['pagado'] ?? ''),
                (string) ($fila['impactado'] ?? ''),
            ], false, false);

            $y += self::ALTURA_FILA;
        }

        return $y;
    }

    private function dibujarTotales(float $yFinFilas): void
    {
        /** @var array<string, string> $totales */
        $totales = $this->datos['totales'] ?? [];

        $y = $yFinFilas + 0.5;
        if ($y + self::ALTURA_FILA > self::Y_MAX_FILA) {
            $this->AddPage('L', 'A4');
            $this->dibujarEncabezadoTabla();
            $y = self::Y_PRIMERA_FILA;
        }

        $valores = array_fill(0, count(self::ANCHOS), '');
        $valores[0] = 'Totales';
        $valores[8] = (string) ($totales['importe'] ?? '');
        $valores[9] = (string) ($totales['interes'] ?? '');
        $valores[10] = (string) ($totales['bonificacion'] ?? '');
        $valores[11] = (string) ($totales['pagado'] ?? '');

        $this->dibujarFilaTabla($y, $valores, true, false, true);
    }

    /**
     * @param  list<string>  $valores
     */
    private function dibujarFilaTabla(
        float $y,
        array $valores,
        bool $negrita,
        bool $esEncabezado,
        bool $esTotales = false,
    ): void {
        $x = self::MARGEN_IZQ;
        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, $negrita ? 'B' : '', self::TAMANO_FUENTE);

        if ($esEncabezado) {
            $this->SetFillColor(220, 220, 220);
        } elseif ($esTotales) {
            $this->SetFillColor(232, 240, 242);
        } else {
            $this->SetFillColor(255, 255, 255);
        }

        $indicesCentrados = [0, 1, 2, 6, 7, 12];
        $indicesDerecha = [8, 9, 10, 11];

        foreach (self::ANCHOS as $i => $ancho) {
            $texto = self::recortarTexto($valores[$i] ?? '', $ancho);
            $align = match (true) {
                in_array($i, $indicesDerecha, true) => 'R',
                in_array($i, $indicesCentrados, true) => 'C',
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
