<?php

namespace App\Support\Mora;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfLogoInstitucional;
use TCPDF;

/**
 * PDF «Estado de deuda» por familia — TCPDF (adaptado del legacy FPDF).
 */
final class EstadoDeudaFamiliarTcpdf extends TCPDF
{
    private const ORIGEN_X = 20.0;

    private const ANCHO_BLOQUE = 180.0;

    private const MARGEN_DER = 10.0;

    private const MARGEN_SUP = 10.0;

    private const ALTO_ENCABEZADO = 23.0;

    private const LOGO_ANCHO = 21.0;

    private const LOGO_ALTO = 21.0;

    private const ALTO_FILA = 4.0;

    private const FILAS_POR_PAGINA = 45;

    /** @var array<int, float> */
    private const ANCHOS = [
        3.0,   // Nº
        42.0,  // Estudiante
        25.0,  // Cuota
        15.0,  // Sala/Grado/Curso
        10.0,  // Nivel
        9.0,   // Año
        16.0,  // Beca
        15.0,  // 1ºVenc
        15.0,  // Importe
        15.0,  // Interés
        15.0,  // A pagar
    ];

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
        $this->SetTitle(trim((string) ($datos['tituloDocumento'] ?? 'Estado de deuda familiar')));
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false, 10);
        $this->SetMargins(self::ORIGEN_X, self::MARGEN_SUP, self::MARGEN_DER);
        $this->SetDrawColor(0, 0, 0);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage();
        $pdf->dibujarDocumento();

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

    private function dibujarDocumento(): void
    {
        $y = $this->dibujarEncabezadoInstitucional(10.0);
        $y += 5.0;
        $this->dibujarEncabezadoTabla($y);
        $y += self::ALTO_FILA;

        $filas = (array) ($this->datos['filas'] ?? []);
        $nro = 0;

        foreach ($filas as $fila) {
            $nro++;
            if ($nro > 1 && ($nro - 1) % self::FILAS_POR_PAGINA === 0) {
                $this->AddPage();
                $y = $this->dibujarEncabezadoInstitucional(10.0);
                $y += 5.0;
                $this->dibujarEncabezadoTabla($y);
                $y += self::ALTO_FILA;
            }

            $this->dibujarFilaDatos($y, (array) $fila);
            $y += self::ALTO_FILA;
        }

        $y += 1.0;
        $this->dibujarTotales($y);
    }

    private function dibujarEncabezadoInstitucional(float $y): float
    {
        $header = (array) ($this->datos['pdfHeader'] ?? []);
        $insti = trim((string) ($header['insti'] ?? config('tenant.nombre', '')));
        $fecha = (string) ($this->datos['fechaInforme'] ?? '');
        $familiaLinea = trim((string) ($this->datos['familiaLinea'] ?? ''));

        $this->Rect(self::ORIGEN_X, $y, self::ANCHO_BLOQUE, self::ALTO_ENCABEZADO);

        $logoFile = $header['logo_file'] ?? null;
        TcpdfLogoInstitucional::dibujar(
            $this,
            self::ORIGEN_X + 5,
            $y + 1,
            self::LOGO_ANCHO,
            self::LOGO_ALTO,
            is_string($logoFile) ? $logoFile : null,
        );

        $this->SetXY(self::ORIGEN_X, $y + 3);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(self::ANCHO_BLOQUE, 7, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(self::ANCHO_BLOQUE, 5, 'ESTADO DE DEUDA - '.$fecha, 0, 2, 'C');
        $this->Cell(self::ANCHO_BLOQUE, 5, $familiaLinea !== '' ? $familiaLinea : '—', 0, 2, 'C');

        return $y + self::ALTO_ENCABEZADO;
    }

    private function dibujarEncabezadoTabla(float $y): void
    {
        $this->SetXY(self::ORIGEN_X, $y);
        TcpdfFuenteArial::aplicar($this, '', 5);

        $etiquetas = [
            'Nº',
            'Estudiante',
            'Cuota',
            'Sala/Grado/Curso',
            'Nivel',
            'Año',
            'Beca',
            '1ºVenc',
            'Importe',
            'Interés',
            'A pagar',
        ];

        foreach ($etiquetas as $i => $texto) {
            $align = match ($i) {
                8, 9, 10 => 'R',
                default => 'C',
            };
            $this->Cell(self::ANCHOS[$i], self::ALTO_FILA, $texto, 1, $i === count($etiquetas) - 1 ? 1 : 0, $align);
        }
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function dibujarFilaDatos(float $y, array $fila): void
    {
        $this->SetXY(self::ORIGEN_X, $y);
        TcpdfFuenteArial::aplicar($this, '', 6);

        $valores = [
            (string) ($fila['nro'] ?? ''),
            (string) ($fila['estudiante'] ?? ''),
            (string) ($fila['cuota'] ?? ''),
            (string) ($fila['curso'] ?? ''),
            (string) ($fila['nivel'] ?? ''),
            (string) ($fila['ano'] ?? ''),
            (string) ($fila['beca'] ?? ''),
            (string) ($fila['venc1'] ?? ''),
            (string) ($fila['importe'] ?? ''),
            (string) ($fila['interes'] ?? ''),
            (string) ($fila['aPagar'] ?? ''),
        ];

        foreach ($valores as $i => $texto) {
            $align = match ($i) {
                1 => 'L',
                8, 9, 10 => 'R',
                default => 'C',
            };
            $this->Cell(self::ANCHOS[$i], self::ALTO_FILA, $texto, 1, $i === count($valores) - 1 ? 1 : 0, $align);
        }
    }

    private function dibujarTotales(float $y): void
    {
        $totales = (array) ($this->datos['totales'] ?? []);

        $this->SetXY(self::ORIGEN_X, $y);
        TcpdfFuenteArial::aplicar($this, '', 6);

        $anchoEtiqueta = array_sum(array_slice(self::ANCHOS, 0, 8));
        $this->Cell($anchoEtiqueta, self::ALTO_FILA, 'TOTALES', 0, 0, 'C');
        $this->Cell(self::ANCHOS[8], self::ALTO_FILA, (string) ($totales['importe'] ?? '0,00'), 1, 0, 'R');
        $this->Cell(self::ANCHOS[9], self::ALTO_FILA, (string) ($totales['interes'] ?? '0,00'), 1, 0, 'R');
        $this->Cell(self::ANCHOS[10], self::ALTO_FILA, (string) ($totales['aPagar'] ?? '0,00'), 1, 1, 'R');
    }
}
