<?php

namespace App\Support\Cuotas;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Comprobante electrónico AFIP escolar — TCPDF (encabezado y pie modelo ARCA).
 */
final class ComprobanteAfipTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 15.0;

    private const MARGEN_DER = 15.0;

    private const ANCHO_UTIL = 180.0;

    private const ANCHO_CAJA_C = 16.0;

    private const ALTO_CAJA_C = 16.0;

    private const FUENTE_LETRA_TIPO = 16;

    private const FUENTE_COD_TIPO = 6;

    private const ALTO_TEXTO_ORIGINAL = 3.5;

    private const ESPACIO_ORIGINAL_CAJA = 0.3;

    /** Espacio entre el texto lateral y la caja del tipo de comprobante (franja superior). */
    private const MARGEN_RESERVA_CAJA = 2.0;

    private const Y_HEADER_TOP = 12.0;

    private const ALTO_HEADER = 38.0;

    /** Aire vertical mínimo arriba y abajo de cada bloque dentro del recuadro. */
    private const PADDING_VERTICAL_ENCABEZADO = 3.0;

    private const ALTO_FILA = 4.5;

    private const FUENTE_TITULO_COMPROBANTE = 11;

    /** Un punto menor que {@see self::FUENTE_TITULO_COMPROBANTE}. */
    private const FUENTE_NOMBRE_INSTITUCION = 10;

    private const ALTO_FILA_NOMBRE_INSTITUCION = 4.8;

    /** Sangría interna de las columnas emisor y comprobante (separación del borde). */
    private const MARGEN_INTERNO_COL_IZQ = 1.5;

    private const ESPACIO_ANTES_SEPARADOR_NOMBRE = 0.6;

    private const ESPACIO_DESPUES_SEPARADOR_NOMBRE = 0.8;

    /** @var array<string, mixed> */
    private array $datos;

    private float $yCursor = 12.0;

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
        $this->dibujarPieArca();
    }

    private function dibujarEncabezado(): void
    {
        $yContent = self::Y_HEADER_TOP;
        $yFinCaja = $this->yFinCajaTipoComprobante($yContent);

        $alturaIzq = $this->alturaTituloEmisor() + $this->alturaDatosEmisor();
        $alturaDer = $this->alturaTituloComprobante() + $this->alturaDatosComprobante();
        $alturaCols = max($alturaIzq, $alturaDer);

        $altoHeader = max(
            self::ALTO_HEADER,
            $alturaCols + (2 * self::PADDING_VERTICAL_ENCABEZADO),
            ($yFinCaja - $yContent) + self::PADDING_VERTICAL_ENCABEZADO,
        );

        $yStart = $yContent + (($altoHeader - $alturaCols) / 2.0);

        $this->Rect(self::MARGEN_IZQ, $yContent, self::ANCHO_UTIL, $altoHeader);
        $this->dibujarColumnaCentral($yContent, $altoHeader);

        $yDatosIzq = $this->dibujarTituloEmisor($yStart);
        $this->dibujarDatosEmisor($yDatosIzq);

        $yDatosDer = $this->dibujarTituloComprobante($yStart);
        $this->dibujarDatosComprobante($yDatosDer);

        $this->yCursor = $yContent + $altoHeader + 4.0;
    }

    private function yFinCajaTipoComprobante(float $yContent): float
    {
        return $yContent + 0.5 + self::ALTO_TEXTO_ORIGINAL + self::ESPACIO_ORIGINAL_CAJA + self::ALTO_CAJA_C;
    }

    private function tituloComprobante(): string
    {
        $tipo = (int) ($this->datos['tipoComprobante'] ?? 15);

        return match ($tipo) {
            12 => 'NOTA DE CRÉDITO',
            default => 'FACTURA',
        };
    }

    /** @return list<array{0: string, 1: string}> */
    private function filasColumnaComprobante(): array
    {
        return [
            ['Punto de Venta:', (string) ($this->datos['puntoVentaTexto'] ?? '')],
            ['Comp. Nro.:', (string) ($this->datos['numeroComprobanteSolo'] ?? '')],
            ['Fecha de Emisión:', (string) ($this->datos['fechaEmision'] ?? '')],
            ['CUIT:', (string) ($this->datos['cuitInstitucion'] ?? '')],
            ['Ingresos Brutos:', (string) ($this->datos['ingresosBrutos'] ?? '')],
            ['Fecha de Inicio de Actividades:', (string) ($this->datos['fechaInicioActividades'] ?? '')],
            ['Aporte Estatal:', (string) ($this->datos['aporteEstatal'] ?? '')],
        ];
    }

    private function alturaFilaColumnaDerecha(string $etiqueta, string $valor, float $anchoCol): float
    {
        TcpdfFuenteArial::aplicar($this, 'B', 7);
        $anchoEtiqueta = min(52.0, $this->GetStringWidth($etiqueta) + 1.0);
        $anchoValor = max(10.0, $anchoCol - $anchoEtiqueta);
        TcpdfFuenteArial::aplicar($this, '', 7);

        return max(
            self::ALTO_FILA,
            $this->getStringHeight($anchoValor, $valor),
        );
    }

    private function xCentroEncabezado(): float
    {
        return self::MARGEN_IZQ + (self::ANCHO_UTIL / 2.0);
    }

    private function xCajaTipoComprobante(): float
    {
        return $this->xCentroEncabezado() - (self::ANCHO_CAJA_C / 2.0);
    }

    private function xTextoColIzq(): float
    {
        return self::MARGEN_IZQ + self::MARGEN_INTERNO_COL_IZQ;
    }

    private function xFinTextoColIzq(): float
    {
        return $this->xCajaTipoComprobante() - self::MARGEN_RESERVA_CAJA;
    }

    private function anchoTextoColIzq(): float
    {
        return max(10.0, $this->xFinTextoColIzq() - $this->xTextoColIzq());
    }

    private function xFinTextoColIzqSuperior(): float
    {
        return $this->xFinTextoColIzq();
    }

    private function anchoTextoColIzqSuperior(): float
    {
        return $this->anchoTextoColIzq();
    }

    private function xFinTextoColIzqInferior(): float
    {
        return $this->xFinTextoColIzq();
    }

    private function anchoTextoColIzqInferior(): float
    {
        return $this->anchoTextoColIzq();
    }

    /** Inicio del texto en la columna derecha (después de la caja del tipo de comprobante). */
    private function xTextoColDer(): float
    {
        return $this->xCajaTipoComprobante() + self::ANCHO_CAJA_C + self::MARGEN_RESERVA_CAJA;
    }

    private function xTextoColDerSuperior(): float
    {
        return $this->xTextoColDer();
    }

    private function anchoTextoColDer(): float
    {
        $xFin = self::MARGEN_IZQ + self::ANCHO_UTIL - self::MARGEN_INTERNO_COL_IZQ;

        return max(10.0, $xFin - $this->xTextoColDer());
    }

    private function anchoTextoColDerSuperior(): float
    {
        return $this->anchoTextoColDer();
    }

    private function xTextoColDerInferior(): float
    {
        return $this->xTextoColDer();
    }

    private function anchoTextoColDerInferior(): float
    {
        return $this->anchoTextoColDer();
    }

    private function alturaTituloEmisor(): float
    {
        $nombreInstitucion = trim((string) ($this->datos['nombreInstitucion'] ?? ''));
        if ($nombreInstitucion === '') {
            return 0.0;
        }

        return $this->alturaFilaNombreInstitucion($nombreInstitucion, $this->anchoTextoColIzqSuperior())
            + $this->alturaSeparacionNombreInstitucion();
    }

    private function alturaDatosEmisor(): float
    {
        $ancho = $this->anchoTextoColIzqInferior();
        $altura = $this->alturaFilaEmisorValor(22, (string) ($this->datos['razonSocial'] ?? ''), true, $ancho);
        $altura += $this->alturaFilaEmisorValor(28, (string) ($this->datos['domicilioComercial'] ?? ''), false, $ancho);
        $altura += self::ALTO_FILA;
        $altura += $this->alturaFilaEmisorValor(32, (string) ($this->datos['condicionIvaInstitucion'] ?? ''), false, $ancho);

        return $altura;
    }

    private function alturaTituloComprobante(): float
    {
        TcpdfFuenteArial::aplicar($this, 'B', self::FUENTE_TITULO_COMPROBANTE);

        return max(6.0, $this->getStringHeight($this->anchoTextoColDerSuperior(), $this->tituloComprobante()));
    }

    private function alturaDatosComprobante(): float
    {
        $anchoCol = $this->anchoTextoColDerInferior();
        $altura = 0.0;
        foreach ($this->filasColumnaComprobante() as [$etiqueta, $valor]) {
            $altura += $this->alturaFilaColumnaDerecha($etiqueta, $valor, $anchoCol);
        }

        return $altura;
    }

    private function alturaFilaNombreInstitucion(string $nombre, float $ancho): float
    {
        TcpdfFuenteArial::aplicar($this, 'B', self::FUENTE_NOMBRE_INSTITUCION);

        return $this->getStringHeight($ancho, mb_strtoupper($nombre));
    }

    private function alturaSeparacionNombreInstitucion(): float
    {
        return self::ESPACIO_ANTES_SEPARADOR_NOMBRE + self::ESPACIO_DESPUES_SEPARADOR_NOMBRE;
    }

    private function alturaFilaEmisorValor(float $anchoEtiqueta, string $valor, bool $forzarMinimoFila, float $anchoCol): float
    {
        TcpdfFuenteArial::aplicar($this, '', 7);
        $anchoValor = $anchoCol - $anchoEtiqueta;
        $altura = $this->getStringHeight($anchoValor, $valor);

        if ($forzarMinimoFila) {
            $altura = max(self::ALTO_FILA, $altura);
        }

        return $altura;
    }

    private function letraTipoComprobante(int $tipo): string
    {
        return match (true) {
            in_array($tipo, [1, 2, 3, 51, 52, 53], true) => 'A',
            in_array($tipo, [6, 7, 8, 56, 57, 58], true) => 'B',
            default => 'C',
        };
    }

    private function xCentroDivisorEncabezado(): float
    {
        return $this->xCentroEncabezado();
    }

    private function dibujarColumnaCentral(float $yContent, float $altoHeader): void
    {
        $tipo = (int) ($this->datos['tipoComprobante'] ?? 15);
        $letra = $this->letraTipoComprobante($tipo);
        $x = $this->xCajaTipoComprobante();
        $y = $yContent + 0.5;

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 7);
        $this->Cell(self::ANCHO_CAJA_C, self::ALTO_TEXTO_ORIGINAL, 'ORIGINAL', 0, 0, 'C');

        $yCaja = $y + self::ALTO_TEXTO_ORIGINAL + self::ESPACIO_ORIGINAL_CAJA;
        $this->Rect($x, $yCaja, self::ANCHO_CAJA_C, self::ALTO_CAJA_C);

        $this->SetXY($x, $yCaja + 0.8);
        TcpdfFuenteArial::aplicar($this, 'B', self::FUENTE_LETRA_TIPO);
        $this->Cell(self::ANCHO_CAJA_C, 7.5, $letra, 0, 0, 'C');

        $this->SetXY($x, $yCaja + self::ALTO_CAJA_C - 4.2);
        TcpdfFuenteArial::aplicar($this, 'B', self::FUENTE_COD_TIPO);
        $this->Cell(self::ANCHO_CAJA_C, 3.0, 'COD. '.$tipo, 0, 0, 'C');

        $xLinea = $this->xCentroDivisorEncabezado();
        $yFinCaja = $yCaja + self::ALTO_CAJA_C;
        $this->SetLineWidth(0.35);
        $this->Line($xLinea, $yFinCaja, $xLinea, $yContent + $altoHeader);
        $this->SetLineWidth(0.2);
    }

    private function dibujarSeparacionNombreInstitucion(float $yFinNombre, float $ancho): float
    {
        $x = $this->xTextoColIzq();
        $yLine = $yFinNombre + self::ESPACIO_ANTES_SEPARADOR_NOMBRE;

        $this->SetDrawColor(175, 175, 175);
        $this->SetLineWidth(0.12);
        $this->Line($x, $yLine, $x + $ancho, $yLine);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);

        return $yLine + self::ESPACIO_DESPUES_SEPARADOR_NOMBRE;
    }

    private function dibujarTituloEmisor(float $y): float
    {
        $nombreInstitucion = trim((string) ($this->datos['nombreInstitucion'] ?? ''));
        if ($nombreInstitucion === '') {
            return $y;
        }

        $x = $this->xTextoColIzq();
        $ancho = $this->anchoTextoColIzqSuperior();

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', self::FUENTE_NOMBRE_INSTITUCION);
        $this->MultiCell(
            $ancho,
            self::ALTO_FILA_NOMBRE_INSTITUCION,
            mb_strtoupper($nombreInstitucion),
            0,
            'L',
        );

        return $this->dibujarSeparacionNombreInstitucion($this->GetY(), $ancho);
    }

    private function dibujarDatosEmisor(float $y): float
    {
        $x = $this->xTextoColIzq();
        $ancho = $this->anchoTextoColIzqInferior();

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 7);
        $this->Cell(22, self::ALTO_FILA, 'Razón Social:', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->MultiCell($ancho - 22, self::ALTO_FILA, (string) ($this->datos['razonSocial'] ?? ''), 0, 'L');

        $y = $this->GetY();
        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 7);
        $this->Cell(28, self::ALTO_FILA, 'Domicilio Comercial:', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->MultiCell($ancho - 28, self::ALTO_FILA, (string) ($this->datos['domicilioComercial'] ?? ''), 0, 'L');

        $y = $this->GetY();
        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 7);
        $this->Cell(14, self::ALTO_FILA, 'Teléfono:', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell($ancho - 14, self::ALTO_FILA, (string) ($this->datos['telefonoInstitucion'] ?? ''), 0, 1, 'L');

        $y = $this->GetY();
        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 7);
        $this->Cell(32, self::ALTO_FILA, 'Condición frente al IVA:', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->MultiCell($ancho - 32, self::ALTO_FILA, (string) ($this->datos['condicionIvaInstitucion'] ?? ''), 0, 'L');

        return $this->GetY();
    }

    private function dibujarTituloComprobante(float $y): float
    {
        $this->SetXY($this->xTextoColDerSuperior(), $y);
        TcpdfFuenteArial::aplicar($this, 'B', self::FUENTE_TITULO_COMPROBANTE);
        $this->MultiCell($this->anchoTextoColDerSuperior(), 5.5, $this->tituloComprobante(), 0, 'L');

        return $this->GetY();
    }

    private function dibujarDatosComprobante(float $y): float
    {
        foreach ($this->filasColumnaComprobante() as [$etiqueta, $valor]) {
            $y = $this->filaColumnaDerecha($y, $etiqueta, $valor, $this->anchoTextoColDerInferior(), $this->xTextoColDerInferior());
        }

        return $y;
    }

    private function dibujarBloqueCliente(): void
    {
        $y = $this->yCursor;
        $xFin = self::MARGEN_IZQ + self::ANCHO_UTIL;
        $xMedio = self::MARGEN_IZQ + (self::ANCHO_UTIL / 2);

        $this->Line(self::MARGEN_IZQ, $y, $xFin, $y);
        $y += 2.0;

        $y = $this->filaEtiquetaValor($y, self::MARGEN_IZQ, 'Responsable:', (string) ($this->datos['nombreResp'] ?? ''));
        $yResp = $y - self::ALTO_FILA;
        $this->filaEtiquetaValor($yResp, $xMedio, 'DNI:', (string) ($this->datos['dniResp'] ?? ''));

        $y = $this->filaEtiquetaValor($y, self::MARGEN_IZQ, 'Condición frente al IVA:', (string) ($this->datos['condicionIvaReceptorTexto'] ?? ''));

        $y = $this->filaEtiquetaValor($y, self::MARGEN_IZQ, 'Alumno:', (string) ($this->datos['nombreCliente'] ?? ''));
        $yAlu = $y - self::ALTO_FILA;
        $this->filaEtiquetaValor($yAlu, $xMedio, 'Curso:', (string) ($this->datos['cursoTexto'] ?? ''));

        $y = $this->filaEtiquetaValor($y, self::MARGEN_IZQ, 'Cuota:', (string) ($this->datos['cuotaTexto'] ?? ''));
        $yCuota = $y - self::ALTO_FILA;
        $this->filaEtiquetaValor($yCuota, $xMedio, 'DNI:', (string) ($this->datos['docNro'] ?? ''));

        if (! empty($this->datos['muestraCondicionVenta'])) {
            $y = $this->filaEtiquetaValor($y, self::MARGEN_IZQ, 'Condición de venta:', (string) ($this->datos['condicionVenta'] ?? ''));
        }

        $this->Line(self::MARGEN_IZQ, $y + 1.0, $xFin, $y + 1.0);
        $this->yCursor = $y + 4.0;
    }

    private function dibujarDetalleConceptos(): void
    {
        $y = $this->yCursor;
        $this->SetFillColor(230, 230, 230);
        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(130, self::ALTO_FILA + 1, 'Descripción', 1, 0, 'L', true);
        $this->Cell(25, self::ALTO_FILA + 1, 'Precio', 1, 0, 'R', true);
        $this->Cell(25, self::ALTO_FILA + 1, 'Total', 1, 0, 'R', true);

        $y += self::ALTO_FILA + 3.0;

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
            $concepto = (string) ($linea['concepto'] ?? '');
            $becaPorcentaje = (int) ($this->datos['becaPorcentaje'] ?? 0);
            $becaImporteOriginalFmt = (string) ($this->datos['becaImporteOriginalFmt'] ?? '');

            $descripcion = $concepto;
            if ($becaPorcentaje > 0 && $becaImporteOriginalFmt !== '') {
                $descripcion .= "\n(Beca {$becaPorcentaje} % - Importe Original de la cuota: {$becaImporteOriginalFmt})";
            }

            TcpdfFuenteArial::aplicar($this, '', 8);
            $altoFila = max(self::ALTO_FILA, $this->getStringHeight(130, $descripcion));

            $x = self::MARGEN_IZQ;
            $this->SetXY($x, $y);
            $this->MultiCell(130, self::ALTO_FILA, $descripcion, 0, 'L', false, 0);

            $this->SetXY($x + 130, $y);
            $this->MultiCell(25, $altoFila, $importeFmt, 0, 'R', false, 0);

            $this->SetXY($x + 130 + 25, $y);
            $this->MultiCell(25, $altoFila, $importeFmt, 0, 'R', false, 0);

            $y += $altoFila;
        }

        $this->yCursor = $y;
    }

    private function dibujarTotal(): void
    {
        $y = max(175.0, $this->yCursor + 8.0);
        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->SetFillColor(230, 230, 230);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(self::ANCHO_UTIL, self::ALTO_FILA + 1, 'TOTAL', 1, 0, 'C', true);
        $y += self::ALTO_FILA + 1.0;
        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(self::ANCHO_UTIL, self::ALTO_FILA + 2, '$ '.(string) ($this->datos['importeFmt'] ?? '0,00'), 1, 0, 'C');

        $this->yCursor = $y + self::ALTO_FILA + 2.0;
    }

    private function dibujarPieArca(): void
    {
        $yBase = max(230.0, $this->yCursor + 8.0);
        $xFin = self::MARGEN_IZQ + self::ANCHO_UTIL;

        $urlQr = trim((string) ($this->datos['urlQr'] ?? ''));
        if ($urlQr !== '') {
            $style = [
                'border' => false,
                'vpadding' => 'auto',
                'hpadding' => 'auto',
                'fgcolor' => [0, 0, 0],
                'bgcolor' => false,
            ];
            $this->write2DBarcode($urlQr, 'QRCODE,H', self::MARGEN_IZQ, $yBase, 32, 32, $style);
        }

        $xTexto = self::MARGEN_IZQ + 36.0;
        $this->SetXY($xTexto, $yBase + 2.0);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(50, 5, 'ARCA', 0, 1, 'L');
        $this->SetX($xTexto);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->MultiCell(70, 3, 'AGENCIA DE RECAUDACIÓN Y CONTROL ADUANERO', 0, 'L');
        $this->SetX($xTexto);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(70, 4, 'Comprobante Autorizado', 0, 1, 'L');
        $this->SetX($xTexto);
        TcpdfFuenteArial::aplicar($this, 'I', 6);
        $this->MultiCell(
            70,
            3,
            'Esta Administración Federal no se responsabiliza por los datos ingresados en el detalle de la operación',
            0,
            'L',
        );

        $this->SetXY(self::MARGEN_IZQ, $yBase + 34.0);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(self::ANCHO_UTIL, 4, 'Pág. '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, 0, 'C');

        $this->SetXY($xFin - 55.0, $yBase + 24.0);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(55, 4, 'CAE N°: '.(string) ($this->datos['cae'] ?? ''), 0, 2, 'R');
        $this->Cell(55, 4, 'Fecha de Vto. de CAE: '.(string) ($this->datos['vtoCae'] ?? ''), 0, 0, 'R');
    }

    private function filaColumnaDerecha(float $y, string $etiqueta, string $valor, float $anchoCol, float $xCol): float
    {
        $alturaFila = $this->alturaFilaColumnaDerecha($etiqueta, $valor, $anchoCol);

        $this->SetXY($xCol, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 7);
        $anchoEtiqueta = min(52.0, $this->GetStringWidth($etiqueta) + 1.0);
        $this->Cell($anchoEtiqueta, $alturaFila, $etiqueta, 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell($anchoCol - $anchoEtiqueta, $alturaFila, $valor, 0, 0, 'L');

        return $y + $alturaFila;
    }

    private function filaEtiquetaValor(
        float $y,
        float $x,
        string $etiqueta,
        string $valor,
        ?float $anchoEtiqueta = null,
        ?float $anchoValor = null,
    ): float {
        $anchoDisponible = (self::ANCHO_UTIL / 2) - 2.0;

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 7);
        $anchoLabel = $anchoEtiqueta ?? ($this->GetStringWidth($etiqueta) + 1.0);
        $anchoLabel = min(max($anchoLabel, 16.0), $anchoDisponible - 10.0);
        $this->Cell($anchoLabel, self::ALTO_FILA, $etiqueta, 0, 0, 'L');

        TcpdfFuenteArial::aplicar($this, '', 7);
        $anchoVal = $anchoValor ?? ($anchoDisponible - $anchoLabel);
        $this->Cell($anchoVal, self::ALTO_FILA, $valor, 0, 0, 'L');

        return $y + self::ALTO_FILA;
    }
}
