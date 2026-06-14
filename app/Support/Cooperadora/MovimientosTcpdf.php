<?php

namespace App\Support\Cooperadora;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfLogoInstitucional;
use TCPDF;

/**
 * Listado de movimientos cooperadora con saldo — TCPDF vertical A4.
 */
final class MovimientosTcpdf extends TCPDF
{
    private const MARGEN = 12.0;

    private const LOGO_ANCHO = 16.0;

    private const LOGO_ALTO = 16.0;

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
        $this->SetTitle('Movimientos cooperadora');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(true, 12);
        $this->SetMargins(self::MARGEN, self::MARGEN, self::MARGEN);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage();
        $pdf->dibujar();

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

    private function dibujar(): void
    {
        $header = (array) ($this->datos['header'] ?? []);
        $yEnc = self::MARGEN;
        TcpdfLogoInstitucional::dibujar(
            $this,
            self::MARGEN,
            $yEnc,
            self::LOGO_ANCHO,
            self::LOGO_ALTO,
            $header['logo_file'] ?? null,
        );
        TcpdfFuenteArial::aplicar($this, 'B', 12);
        $this->SetY($yEnc + self::LOGO_ALTO + 2);
        $this->Cell(0, 6, (string) ($header['nombre'] ?? 'Cooperadora').' — Movimientos', 0, 1, 'C');
        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell(0, 5, 'Período: '.(string) ($this->datos['fecha_desde_texto'] ?? '').' al '.(string) ($this->datos['fecha_hasta_texto'] ?? ''), 0, 1, 'C');
        $this->Ln(2);

        $w = $this->anchosColumnas();
        $titulos = ['Fecha', 'Tipo', 'Nº', 'Detalle', 'Ingreso', 'Egreso', 'Saldo'];
        TcpdfFuenteArial::aplicar($this, 'B', 7);
        $this->SetFillColor(193, 215, 218);
        $this->SetTextColor(51, 51, 51);
        foreach ($titulos as $i => $titulo) {
            $this->Cell($w[$i], 6, $titulo, 1, 0, 'C', true);
        }
        $this->SetFillColor(255, 255, 255);
        $this->SetTextColor(0, 0, 0);
        $this->Ln();

        TcpdfFuenteArial::aplicar($this, '', 7);
        /** @var list<object> $filas */
        $filas = (array) ($this->datos['filas'] ?? []);
        $maxDetalle = max(28, (int) floor($w[3] / 1.9));
        foreach ($filas as $fila) {
            $fecha = isset($fila->fecha) ? \Carbon\Carbon::parse($fila->fecha)->format('d/m/Y') : '';
            $tipo = ($fila->tipo_mov ?? '') === 'egreso' ? 'Egreso' : 'Ingreso';
            $this->Cell($w[0], 5, $fecha, 1, 0, 'C');
            $this->Cell($w[1], 5, $tipo, 1, 0, 'C');
            $this->Cell($w[2], 5, (string) ($fila->numero ?? ''), 1, 0, 'R');
            $this->Cell($w[3], 5, mb_substr((string) ($fila->detalle ?? ''), 0, $maxDetalle), 1, 0, 'L');
            $this->Cell($w[4], 5, $fila->ingreso > 0 ? number_format((float) $fila->ingreso, 2, ',', '.') : '', 1, 0, 'R');
            $this->Cell($w[5], 5, $fila->egreso > 0 ? number_format((float) $fila->egreso, 2, ',', '.') : '', 1, 0, 'R');
            $this->Cell($w[6], 5, number_format((float) ($fila->saldo ?? 0), 2, ',', '.'), 1, 1, 'R');
        }

        $this->Ln(3);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $anchoEtiqueta = $w[0] + $w[1] + $w[2] + $w[3];
        $this->Cell($anchoEtiqueta, 5, 'Totales del período', 0, 0, 'R');
        $this->Cell($w[4], 5, number_format((float) ($this->datos['total_ingresos'] ?? 0), 2, ',', '.'), 0, 0, 'R');
        $this->Cell($w[5], 5, number_format((float) ($this->datos['total_egresos'] ?? 0), 2, ',', '.'), 0, 0, 'R');
        $this->Cell($w[6], 5, number_format((float) ($this->datos['saldo'] ?? 0), 2, ',', '.'), 0, 1, 'R');
    }

    /**
     * @return list<float>
     */
    private function anchosColumnas(): array
    {
        $anchoUtil = $this->getPageWidth() - (self::MARGEN * 2);
        $wFecha = 20.0;
        $wTipo = 14.0;
        $wNumero = 16.0;
        $wImporte = 22.0;
        $wDetalle = $anchoUtil - $wFecha - $wTipo - $wNumero - (3 * $wImporte);

        return [$wFecha, $wTipo, $wNumero, $wDetalle, $wImporte, $wImporte, $wImporte];
    }
}
