<?php

namespace App\Support\Cuotas;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Comprobante electrónico AFIP (recibo / factura C) — TCPDF.
 *
 * Encabezado tipo factura C AFIP: emisor (izq.) | recuadro C (centro) | datos del comprobante (der.).
 */
final class ComprobanteAfipTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 30.0;

    private const MARGEN_DER = 30.0;

    private const ANCHO_UTIL = 150.0;

    /** Columna izquierda — emisor. */
    private const X_COL_IZQ = 30.0;

    private const ANCHO_COL_IZQ = 60.0;

    /** Columna central — letra y código AFIP. */
    private const X_COL_CENTRO = 93.0;

    private const ANCHO_CAJA_C = 20.0;

    private const ALTO_CAJA_C = 15.0;

    /** Columna derecha — número, CUIT, fechas. */
    private const X_COL_DER = 118.0;

    private const ANCHO_COL_DER = 62.0;

    private const Y_HEADER_TOP = 30.0;

    private const ALTO_FILA = 5.0;

    /** @var array<string, mixed> */
    private array $datos;

    private float $yCursor = 30.0;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->datos = $datos;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Comprobante AFIP');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false, 10);
        $this->SetMargins(self::MARGEN_IZQ, 10, self::MARGEN_DER);
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
        $this->dibujarEncabezado();
        $this->dibujarBloqueCliente();
        $this->dibujarDetalleConceptos();
        $this->dibujarTotal();
        $this->dibujarCaeYQr();
    }

    private function dibujarEncabezado(): void
    {
        $yTop = self::Y_HEADER_TOP;
        $xFin = self::MARGEN_IZQ + self::ANCHO_UTIL;
        $yContent = $yTop + 2.0;

        $this->Line(self::MARGEN_IZQ, $yTop, $xFin, $yTop);

        $yCentroFin = $this->dibujarColumnaCentral($yContent);
        $yIzqFin = $this->dibujarColumnaEmisor($yContent);
        $yDerFin = $this->dibujarColumnaComprobante($yContent);

        $yBottom = max($yIzqFin, $yCentroFin, $yDerFin) + 4.0;
        $this->Line(self::MARGEN_IZQ, $yBottom, $xFin, $yBottom);

        $this->yCursor = $yBottom + 5.0;
    }

    private function dibujarColumnaCentral(float $y): float
    {
        $tipo = (int) ($this->datos['tipoComprobante'] ?? 15);

        $this->SetXY(self::X_COL_CENTRO, $y);
        TcpdfFuenteArial::aplicar($this, '', 20);
        $this->Cell(self::ANCHO_CAJA_C, self::ALTO_CAJA_C, 'C', 1, 0, 'C');

        $this->SetXY(self::X_COL_CENTRO, $y + 11.0);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(self::ANCHO_CAJA_C, 3, 'Cod. '.$tipo, 0, 0, 'C');

        return $y + self::ALTO_CAJA_C + 5.0;
    }

    private function dibujarColumnaEmisor(float $y): float
    {
        $this->SetXY(self::X_COL_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 11);
        $this->MultiCell(
            self::ANCHO_COL_IZQ,
            self::ALTO_FILA,
            (string) ($this->datos['razonSocial'] ?? ''),
            0,
            'L',
        );

        $this->SetXY(self::X_COL_IZQ, $this->GetY() + 0.5);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->MultiCell(
            self::ANCHO_COL_IZQ,
            self::ALTO_FILA,
            (string) ($this->datos['domicilioComercial'] ?? ''),
            0,
            'L',
        );

        $this->SetXY(self::X_COL_IZQ, $this->GetY() + 1.0);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->MultiCell(
            self::ANCHO_COL_IZQ,
            self::ALTO_FILA,
            (string) ($this->datos['condicionIvaInstitucion'] ?? ''),
            0,
            'L',
        );

        return $this->GetY();
    }

    private function dibujarColumnaComprobante(float $y): float
    {
        $tipo = (int) ($this->datos['tipoComprobante'] ?? 15);
        $numero = (string) ($this->datos['numeroComprobanteTexto'] ?? '');

        $titulo = match ($tipo) {
            12 => 'NOTA DE CRÉDITO:',
            default => 'FACTURA:',
        };

        $y = $this->filaColumnaDerecha($y, $titulo, $numero, true, $tipo === 11 ? 10 : 8);
        $y = $this->filaColumnaDerecha($y, 'CUIT:', (string) ($this->datos['cuitInstitucion'] ?? ''));
        $y = $this->filaColumnaDerecha($y, 'Ingresos Brutos:', (string) ($this->datos['ingresosBrutos'] ?? ''));
        $y = $this->filaColumnaDerecha(
            $y,
            'Inicio de Actividades:',
            (string) ($this->datos['fechaInicioActividades'] ?? ''),
        );

        $y += 2.0;
        $this->SetXY(self::X_COL_DER, $y);
        $this->SetFillColor(220, 220, 220);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(
            self::ANCHO_COL_DER,
            self::ALTO_FILA,
            'Fecha  '.(string) ($this->datos['fechaEmision'] ?? ''),
            1,
            0,
            'C',
            true,
        );
        $this->SetFillColor(255, 255, 255);

        return $y + self::ALTO_FILA;
    }

    private function dibujarBloqueCliente(): void
    {
        $y = $this->yCursor;

        $y = $this->filaEtiquetaValor($y, self::MARGEN_IZQ, 'DNI / CUIT:', (string) ($this->datos['docNro'] ?? ''), 22.0, 100.0);
        $y = $this->filaEtiquetaValor(
            $y,
            self::MARGEN_IZQ,
            'Apellido y Nombre / Razón Social:',
            (string) ($this->datos['nombreCliente'] ?? ''),
            50.0,
            100.0,
        );
        $y = $this->filaEtiquetaValor(
            $y,
            self::MARGEN_IZQ,
            'Condición frente a IVA:',
            (string) ($this->datos['condicionIvaReceptorTexto'] ?? ''),
        );
        $y = $this->filaEtiquetaValor(
            $y,
            self::MARGEN_IZQ,
            'Condición de venta:',
            (string) ($this->datos['condicionVenta'] ?? ''),
            35.0,
            60.0,
        );

        $this->yCursor = $y + 2.0;
    }

    private function dibujarDetalleConceptos(): void
    {
        $y = $this->yCursor;
        $this->SetFillColor(0, 0, 0);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(255, 255, 255);
        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(110, self::ALTO_FILA, 'Descripción', 1, 0, 'L', true);
        $this->Cell(20, self::ALTO_FILA, 'Precio', 1, 0, 'R', true);
        $this->Cell(20, self::ALTO_FILA, 'Total', 1, 0, 'R', true);

        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(0, 0, 0);
        $y += self::ALTO_FILA + 2.0;

        /** @var list<array{concepto?: string, importeFmt?: string}> $lineas */
        $lineas = (array) ($this->datos['lineas'] ?? []);
        if ($lineas === []) {
            $lineas = [[
                'concepto' => (string) ($this->datos['concepto'] ?? ''),
                'importeFmt' => (string) ($this->datos['importeFmt'] ?? '0,00'),
            ]];
        }

        foreach ($lineas as $linea) {
            $importeFmt = (string) ($linea['importeFmt'] ?? '0,00');
            $this->SetXY(self::MARGEN_IZQ, $y);
            TcpdfFuenteArial::aplicar($this, '', 8);
            $this->Cell(110, self::ALTO_FILA, (string) ($linea['concepto'] ?? ''), 0, 0, 'L');
            $this->Cell(20, self::ALTO_FILA, $importeFmt, 0, 0, 'R');
            $this->Cell(20, self::ALTO_FILA, $importeFmt, 0, 0, 'R');
            $y += self::ALTO_FILA;
        }

        $this->yCursor = $y;
    }

    private function dibujarTotal(): void
    {
        $y = max(200.0, $this->yCursor + 12.0);
        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->SetFillColor(0, 0, 0);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(255, 255, 255);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(150, self::ALTO_FILA, 'TOTAL', 1, 0, 'C', true);

        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(0, 0, 0);
        $y += self::ALTO_FILA;
        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->Cell(150, self::ALTO_FILA, '$ '.(string) ($this->datos['importeFmt'] ?? '0,00'), 1, 0, 'C');

        $this->yCursor = $y + self::ALTO_FILA;
    }

    private function dibujarCaeYQr(): void
    {
        $yBase = $this->yCursor + 10.0;

        $this->SetXY(self::X_COL_DER, $yBase);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(self::ANCHO_COL_DER, self::ALTO_FILA, 'CAE: '.(string) ($this->datos['cae'] ?? ''), 0, 1, 'L');
        $this->SetX(self::X_COL_DER);
        $this->Cell(self::ANCHO_COL_DER, self::ALTO_FILA, 'VTO. CAE: '.(string) ($this->datos['vtoCae'] ?? ''), 0, 0, 'L');

        $urlQr = trim((string) ($this->datos['urlQr'] ?? ''));
        if ($urlQr === '') {
            return;
        }

        $style = [
            'border' => false,
            'vpadding' => 'auto',
            'hpadding' => 'auto',
            'fgcolor' => [0, 0, 0],
            'bgcolor' => false,
        ];

        $this->write2DBarcode(
            $urlQr,
            'QRCODE,H',
            self::MARGEN_IZQ,
            $yBase,
            50,
            50,
            $style,
        );
    }

    private function filaColumnaDerecha(
        float $y,
        string $etiqueta,
        string $valor,
        bool $etiquetaNegrita = true,
        int $size = 8,
    ): float {
        $this->SetXY(self::X_COL_DER, $y);
        TcpdfFuenteArial::aplicar($this, $etiquetaNegrita ? 'B' : '', $size);
        $anchoEtiqueta = $this->GetStringWidth($etiqueta) + 1.0;
        $this->Cell($anchoEtiqueta, self::ALTO_FILA, $etiqueta, 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', $size);
        $this->Cell(self::ANCHO_COL_DER - $anchoEtiqueta, self::ALTO_FILA, $valor, 0, 0, 'L');

        return $y + self::ALTO_FILA;
    }

    private function filaEtiquetaValor(
        float $y,
        float $x,
        string $etiqueta,
        string $valor,
        ?float $anchoEtiqueta = null,
        ?float $anchoValor = null,
    ): float {
        $anchoDisponible = self::ANCHO_UTIL - ($x - self::MARGEN_IZQ);

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $anchoLabel = $anchoEtiqueta ?? ($this->GetStringWidth($etiqueta) + 2.0);
        $anchoLabel = min(max($anchoLabel, 18.0), $anchoDisponible - 25.0);
        $this->Cell($anchoLabel, self::ALTO_FILA, $etiqueta, 0, 0, 'L');

        TcpdfFuenteArial::aplicar($this, '', 8);
        $anchoVal = $anchoValor ?? ($anchoDisponible - $anchoLabel);
        $this->Cell($anchoVal, self::ALTO_FILA, $valor, 0, 0, 'L');

        return $y + self::ALTO_FILA;
    }
}
