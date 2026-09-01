<?php

namespace App\Support\Cuotas;

use App\Support\Pdf\TcpdfFuenteArial;
use Illuminate\Support\Facades\Storage;
use TCPDF;

/**
 * Comprobante de pago tras imputación manual — TCPDF (legacy, sin cupón ni código de barras).
 *
 * En SFQ/EPQ (`tenant.cuotas.comprobante_imputacion.dos_copias_por_hoja`) imprime dos
 * talonarios idénticos en la misma hoja A4, compactando espacios para el corte.
 */
final class ComprobantePagoImputacionTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 25.0;

    private const MARGEN_DER = 15.0;

    private const MARGEN_SUP = 10.0;

    private const ANCHO_BLOQUE = 170.0;

    private const ORIGEN_X = 20.0;

    private const LOGO_ANCHO = 17.0;

    private const LOGO_ALTO = 17.0;

    private const LOGO_ANCHO_COMPACTO = 15.0;

    private const LOGO_ALTO_COMPACTO = 15.0;

    private const ALTO_SECCION_ALUMNO = 52.0;

    private const ALTO_SECCION_ALUMNO_COMPACTO = 38.0;

    private const ALTO_SECCION_PAGO = 58.0;

    private const ALTO_SECCION_PAGO_COMPACTO = 46.0;

    /** Alto fijo del bloque de título + medio de pago + márgenes internos (múltiples cuotas). */
    private const ALTO_PAGO_MULTIPLE_BASE = 28.0;

    private const ALTO_PAGO_MULTIPLE_BASE_COMPACTO = 22.0;

    /** Alto de cada fila de la grilla (encabezado, ítems y totales). */
    private const ALTO_FILA_TABLA = 5.0;

    /** Anchos de columna de la grilla multi-cuota (suma = ANCHO_BLOQUE − 2×pad). */
    private const COL_CUOTA = 54.0;

    private const COL_IMPORTE = 26.0;

    private const COL_INTERES = 26.0;

    private const COL_BONIF = 28.0;

    private const COL_ABONADO = 24.0;

    private const MEDIA_HOJA = 148.5;

    /** Margen mínimo dentro de cada mitad si el talonario es más bajo que 148,5 mm. */
    private const MARGEN_MIN_MITAD = 4.0;

    /** @var array<string, mixed> */
    private array $datos;

    private bool $compacto = false;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->datos = $datos;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Comprobante de pago');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false, 10);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
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
        $lineas = (array) ($this->datos['lineas'] ?? []);
        $esMultiple = (bool) ($this->datos['esMultiple'] ?? false) || count($lineas) > 1;
        $duplicar = $this->debeDuplicarEnHoja($esMultiple, count($lineas));

        if ($duplicar) {
            $this->compacto = true;
            $altura = $this->estimarAlturaCupon($esMultiple, count($lineas), compacto: true);
            $margen = (self::MEDIA_HOJA - $altura) / 2;
            if ($margen < self::MARGEN_MIN_MITAD) {
                $margen = self::MARGEN_MIN_MITAD;
            }
            $this->dibujarCupon($margen);
            $this->dibujarLineaCorte();
            $this->dibujarCupon(self::MEDIA_HOJA + $margen);

            return;
        }

        $this->compacto = false;
        $this->dibujarCupon(self::MARGEN_SUP);
    }

    private function debeDuplicarEnHoja(bool $esMultiple, int $nLineas): bool
    {
        if (! tenantCuotasComprobanteImputacionDosCopiasPorHoja()) {
            return false;
        }

        return $this->estimarAlturaCupon($esMultiple, $nLineas, compacto: true) <= (self::MEDIA_HOJA - 6.0);
    }

    private function dibujarCupon(float $yInicio): void
    {
        $header = (array) ($this->datos['pdfHeader'] ?? []);
        $lineas = (array) ($this->datos['lineas'] ?? []);
        $esMultiple = (bool) ($this->datos['esMultiple'] ?? false) || count($lineas) > 1;

        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->SetXY(self::MARGEN_IZQ, $yInicio);
        $this->Cell(160, 3, (string) ($this->datos['fechaImpresion'] ?? ''), 0, 0, 'R');

        $y = $yInicio + 3 + ($this->compacto ? 1.0 : 2.0);
        $y = $this->dibujarHeaderInstitucional($y, $header);
        $y0 = $y + ($this->compacto ? 2.0 : 4.0);

        $altoAlumno = $this->altoSeccionAlumno();
        $altoPago = $this->altoSeccionPago($esMultiple, count($lineas));
        $altoTotal = $altoAlumno + $altoPago;

        $this->Rect(self::ORIGEN_X, $y0, self::ANCHO_BLOQUE, $altoTotal, 'D');
        $this->Line(
            self::ORIGEN_X,
            $y0 + $altoAlumno,
            self::ORIGEN_X + self::ANCHO_BLOQUE,
            $y0 + $altoAlumno,
        );

        $this->dibujarSeccionAlumno($y0, $esMultiple, $altoAlumno);
        $this->dibujarSeccionPago($y0, $esMultiple, $altoAlumno);
    }

    private function altoSeccionAlumno(): float
    {
        return $this->compacto ? self::ALTO_SECCION_ALUMNO_COMPACTO : self::ALTO_SECCION_ALUMNO;
    }

    private function altoSeccionPago(bool $esMultiple, int $nLineas): float
    {
        $base = $this->compacto ? self::ALTO_SECCION_PAGO_COMPACTO : self::ALTO_SECCION_PAGO;
        if (! $esMultiple) {
            return $base;
        }

        $multipleBase = $this->compacto ? self::ALTO_PAGO_MULTIPLE_BASE_COMPACTO : self::ALTO_PAGO_MULTIPLE_BASE;
        $extraFecha = $this->compacto ? 5.0 : 7.0;

        return max(
            $base,
            $multipleBase + (($nLineas + 2) * self::ALTO_FILA_TABLA) + $extraFecha,
        );
    }

    private function estimarAlturaCupon(bool $esMultiple, int $nLineas, bool $compacto): float
    {
        $prev = $this->compacto;
        $this->compacto = $compacto;
        $altoFecha = 3.0 + ($compacto ? 1.0 : 2.0);
        $altoHeader = $compacto ? 26.0 : 30.0;
        $gap = $compacto ? 2.0 : 4.0;
        $altura = $altoFecha + $altoHeader + $gap + $this->altoSeccionAlumno() + $this->altoSeccionPago($esMultiple, $nLineas);
        $this->compacto = $prev;

        return $altura;
    }

    private function dibujarLineaCorte(): void
    {
        $this->SetLineStyle(['width' => 0.15, 'dash' => '1,2', 'color' => [150, 150, 150]]);
        $this->Line(self::ORIGEN_X, self::MEDIA_HOJA, self::ORIGEN_X + self::ANCHO_BLOQUE, self::MEDIA_HOJA);
        $this->SetLineStyle(['width' => 0.2, 'dash' => 0, 'color' => [0, 0, 0]]);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);
    }

    private function dibujarSeccionAlumno(float $y0, bool $esMultiple, float $altoAlumno): void
    {
        $offsetNro = $this->compacto ? 5.0 : 8.0;
        $this->SetXY(self::MARGEN_IZQ, $y0 + $offsetNro);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(150, $this->compacto ? 4.0 : 5.0, 'Comprobante Nº: '.(string) ($this->datos['nroComprobanteTexto'] ?? ''), 0, 0, 'R');

        $x = self::MARGEN_IZQ;
        $w = 90.0;
        $altoLinea = $this->compacto ? 4.5 : 5.0;
        $lineas = [
            ['texto' => 'APELLIDO Y NOMBRE: ', 'estilo' => '', 'size' => 7.0, 'alto' => $altoLinea],
            ['texto' => (string) ($this->datos['apellidoNombre'] ?? ''), 'estilo' => 'BI', 'size' => 8.0, 'alto' => $altoLinea],
            ['texto' => 'SALA / GRADO / CURSO: ', 'estilo' => '', 'size' => 7.0, 'alto' => $altoLinea],
            ['texto' => (string) ($this->datos['cursec'] ?? ''), 'estilo' => 'B', 'size' => 8.0, 'alto' => $altoLinea],
            ['texto' => (string) ($this->datos['nivel'] ?? ''), 'estilo' => '', 'size' => 8.0, 'alto' => $altoLinea],
        ];

        if (! $esMultiple) {
            $lineas[] = ['texto' => (string) ($this->datos['cuotaNombre'] ?? ''), 'estilo' => 'B', 'size' => 8.0, 'alto' => $altoLinea];
        } else {
            $lineas[] = ['texto' => 'CUOTAS ABONADAS: VARIAS', 'estilo' => 'B', 'size' => 8.0, 'alto' => $altoLinea];
        }

        $hContenido = array_sum(array_column($lineas, 'alto'));
        $padMin = $this->compacto ? 4.0 : 8.0;
        $y = $y0 + (($hContenido < $altoAlumno ? ($altoAlumno - $hContenido) / 2 : $padMin));

        foreach ($lineas as $linea) {
            $this->SetXY($x, $y);
            TcpdfFuenteArial::aplicar($this, (string) $linea['estilo'], (float) $linea['size']);
            $this->Cell($w, (float) $linea['alto'], (string) $linea['texto'], 0, 0, 'L');
            $y += (float) $linea['alto'];
        }
    }

    private function dibujarSeccionPago(float $y0, bool $esMultiple, float $altoAlumno): void
    {
        $yTop = $y0 + $altoAlumno;
        $x = self::ORIGEN_X;
        $w = self::ANCHO_BLOQUE;
        $padX = $this->compacto ? 5.0 : 6.0;
        $lineas = (array) ($this->datos['lineas'] ?? []);
        $offsetTop = $this->compacto ? 3.0 : 6.0;
        $gapTrasOriginal = $this->compacto ? 2.0 : 4.0;
        $gapTrasTitulo = $this->compacto ? 1.0 : 2.0;

        $importeOriginal = trim((string) ($this->datos['importeOriginalFmt'] ?? ''));
        if (! $esMultiple && $importeOriginal !== '') {
            $this->SetXY($x, $yTop + $offsetTop);
            TcpdfFuenteArial::aplicar($this, '', 8);
            $this->Cell($w, 5, '(Importe Original: $ '.$importeOriginal.')', 0, 1, 'C');
            $y = $this->GetY() + $gapTrasOriginal;
        } else {
            $y = $yTop + $offsetTop;
        }

        $this->SetXY($x + $padX, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(90, 5, 'DETALLE DEL PAGO REALIZADO', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, 'I', 8);
        $this->Cell($w - ($padX * 2) - 90, 5, (string) ($this->datos['medioPago'] ?? ''), 0, 1, 'R');

        $yDetalle = $this->GetY() + $gapTrasTitulo;

        if ($esMultiple && $lineas !== []) {
            $this->dibujarTablaDetalleMultiple($x + $padX, $yDetalle, $lineas);

            return;
        }

        $this->dibujarFilaDetalle($x + $padX, $yDetalle, 'Importe:', (string) ($this->datos['importeFmt'] ?? ''));
        $this->dibujarFilaDetalle($x + $padX, $this->GetY(), 'Bonificación:', (string) ($this->datos['bonificacionFmt'] ?? ''));
        $this->dibujarFilaDetalle($x + $padX, $this->GetY(), 'Intereses:', (string) ($this->datos['interesFmt'] ?? ''));
        $this->dibujarFilaDetalle($x + $padX, $this->GetY(), 'Importe Abonado:', (string) ($this->datos['abonadoFmt'] ?? ''), true);
        $this->dibujarFilaDetalle($x + $padX, $this->GetY(), 'Fecha de Pago:', (string) ($this->datos['fechaPagoEsp'] ?? ''));
    }

    private function dibujarFilaDetalle(float $x, float $y, string $label, string $valor, bool $resaltar = false): void
    {
        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(55, 5, $label, 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, $resaltar ? 'B' : '', 8);
        $this->Cell(40, 5, $valor, 0, 1, 'R');
    }

    /**
     * Grilla de cuotas cobradas (una fila por cuota + totales por columna).
     *
     * @param  list<array<string, mixed>>  $lineas
     */
    private function dibujarTablaDetalleMultiple(float $x, float $y, array $lineas): void
    {
        $h = self::ALTO_FILA_TABLA;
        $anchoTabla = self::COL_CUOTA + self::COL_IMPORTE + self::COL_INTERES + self::COL_BONIF + self::COL_ABONADO;

        $this->dibujarFilaTablaMultiple(
            $x,
            $y,
            'Cuota',
            'Importe',
            'Intereses',
            'Bonificación',
            'Abonado',
            encabezado: true,
            resaltarAbonado: false,
        );
        $y += $h;
        $this->Line($x, $y, $x + $anchoTabla, $y);
        $y += 0.8;

        foreach ($lineas as $linea) {
            $this->dibujarFilaTablaMultiple(
                $x,
                $y,
                (string) ($linea['cuotaNombre'] ?? ''),
                (string) ($linea['importeFmt'] ?? ''),
                (string) ($linea['interesFmt'] ?? ''),
                (string) ($linea['bonificacionFmt'] ?? ''),
                (string) ($linea['abonadoFmt'] ?? ''),
                encabezado: false,
                resaltarAbonado: true,
            );
            $y += $h;
        }

        $this->Line($x, $y + 0.5, $x + $anchoTabla, $y + 0.5);
        $y += 1.5;

        $this->dibujarFilaTablaMultiple(
            $x,
            $y,
            'TOTALES',
            (string) ($this->datos['importeFmt'] ?? ''),
            (string) ($this->datos['interesFmt'] ?? ''),
            (string) ($this->datos['bonificacionFmt'] ?? ''),
            (string) ($this->datos['abonadoFmt'] ?? ''),
            encabezado: false,
            resaltarAbonado: true,
            negritaFila: true,
        );
        $y += $h + 2.0;

        $this->dibujarFilaDetalle($x, $y, 'Fecha de Pago:', (string) ($this->datos['fechaPagoEsp'] ?? ''));
    }

    private function dibujarFilaTablaMultiple(
        float $x,
        float $y,
        string $cuota,
        string $importe,
        string $interes,
        string $bonificacion,
        string $abonado,
        bool $encabezado,
        bool $resaltarAbonado,
        bool $negritaFila = false,
    ): void {
        $h = self::ALTO_FILA_TABLA;
        $estilo = ($encabezado || $negritaFila) ? 'B' : '';
        $size = $encabezado ? 7.0 : 7.5;

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, $estilo, $size);
        $this->Cell(self::COL_CUOTA, $h, $this->recortarTextoCelda($cuota, self::COL_CUOTA - 1.0), 0, 0, 'L');

        TcpdfFuenteArial::aplicar($this, $estilo, $size);
        $this->Cell(self::COL_IMPORTE, $h, $importe, 0, 0, $encabezado ? 'C' : 'R');
        $this->Cell(self::COL_INTERES, $h, $interes, 0, 0, $encabezado ? 'C' : 'R');
        $this->Cell(self::COL_BONIF, $h, $bonificacion, 0, 0, $encabezado ? 'C' : 'R');

        TcpdfFuenteArial::aplicar($this, ($resaltarAbonado && ! $encabezado) || $negritaFila || $encabezado ? 'B' : '', $size);
        $this->Cell(self::COL_ABONADO, $h, $abonado, 0, 1, $encabezado ? 'C' : 'R');
    }

    private function recortarTextoCelda(string $texto, float $anchoMaxMm): string
    {
        $texto = trim($texto);
        if ($texto === '' || $this->GetStringWidth($texto) <= $anchoMaxMm) {
            return $texto;
        }

        $ellipsis = '…';
        $candidato = $texto;
        while ($candidato !== '' && $this->GetStringWidth($candidato.$ellipsis) > $anchoMaxMm) {
            $candidato = mb_substr($candidato, 0, mb_strlen($candidato) - 1);
        }

        return $candidato === '' ? $ellipsis : $candidato.$ellipsis;
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function dibujarHeaderInstitucional(float $y, array $header): float
    {
        $x = self::ORIGEN_X;
        $w = self::ANCHO_BLOQUE;
        $yTexto = $y + ($this->compacto ? 2.0 : 3.0);
        $logoAncho = $this->compacto ? self::LOGO_ANCHO_COMPACTO : self::LOGO_ANCHO;
        $logoAlto = $this->compacto ? self::LOGO_ALTO_COMPACTO : self::LOGO_ALTO;

        $this->SetXY($x, $yTexto);

        $insti = trim((string) ($header['insti'] ?? ''));
        $direccion = trim((string) ($header['direccion'] ?? ''));
        $localidad = trim((string) ($header['localidad'] ?? ''));
        $lineaDir = trim($direccion.($direccion !== '' && $localidad !== '' ? ' — ' : '').$localidad);
        $cue = trim((string) ($header['cue'] ?? ''));
        $ee = trim((string) ($header['ee'] ?? ''));
        $lineaIds = trim(($cue !== '' ? 'CUE: '.$cue : '').(($cue !== '' && $ee !== '') ? '   ' : '').($ee !== '' ? 'EE: '.$ee : ''));

        TcpdfFuenteArial::aplicar($this, 'B', $this->compacto ? 9 : 10);
        $this->Cell($w, $this->compacto ? 4.0 : 4.5, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        if ($lineaDir !== '') {
            TcpdfFuenteArial::aplicar($this, '', 7);
            $this->Cell($w, $this->compacto ? 3.0 : 3.5, $lineaDir, 0, 2, 'C');
        }

        if ($lineaIds !== '') {
            TcpdfFuenteArial::aplicar($this, '', 5.5);
            $this->Cell($w, $this->compacto ? 2.6 : 3, $lineaIds, 0, 2, 'C');
        }

        TcpdfFuenteArial::aplicar($this, 'B', 6);
        $this->Cell($w, $this->compacto ? 3.0 : 3.5, 'IVA: Exento - Ing. Brutos: Exento', 0, 2, 'C');
        TcpdfFuenteArial::aplicar($this, '', $this->compacto ? 4.5 : 5);
        $this->MultiCell(
            $w,
            $this->compacto ? 2.8 : 3.2,
            'Entidad Exenta al cumplimiento de la RG (AFIP) 1415 y modificatorias, por aplicación del artículo 5 y del Anexo 1, apartado K de la citada norma',
            0,
            'C',
        );

        $yFin = $this->GetY();
        $h = max($logoAlto + 4, $yFin - $y + ($this->compacto ? 1.0 : 2.0));

        $this->RoundedRect($x, $y, $w, $h, 2.0, '1111', 'D');

        $logo = $this->resolverLogoArchivo($header);
        if ($logo !== null) {
            $this->Image(
                $logo,
                $x + 3,
                $y + (($h - $logoAlto) / 2),
                $logoAncho,
                $logoAlto,
                '',
                '',
                '',
                false,
                300,
            );
        }

        return $y + $h;
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function resolverLogoArchivo(array $header): ?string
    {
        $logo = $header['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            return $logo;
        }

        $path = entoInstitutionalLogoStoragePath();
        if (is_string($path) && $path !== '') {
            $abs = Storage::disk('public')->path($path);
            if (is_file($abs)) {
                return $abs;
            }
        }

        $fallback = public_path('img/3.png');

        return is_file($fallback) ? $fallback : null;
    }
}
