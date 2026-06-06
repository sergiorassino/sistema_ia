<?php

namespace App\Support\Cuotas;

use App\Support\Pdf\TcpdfFuenteArial;
use Illuminate\Support\Facades\Storage;
use TCPDF;

/**
 * Comprobante de pago tras imputación manual — TCPDF (legacy, sin cupón ni código de barras).
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

    private const ALTO_SECCION_ALUMNO = 52.0;

    private const ALTO_SECCION_PAGO = 58.0;

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
        $header = (array) ($this->datos['pdfHeader'] ?? []);

        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(160, 3, (string) ($this->datos['fechaImpresion'] ?? ''), 0, 1, 'R');

        $y = $this->GetY() + 2;
        $y = $this->dibujarHeaderInstitucional($y, $header);
        $y0 = $y + 4;

        $altoTotal = self::ALTO_SECCION_ALUMNO + self::ALTO_SECCION_PAGO;
        $this->Rect(self::ORIGEN_X, $y0, self::ANCHO_BLOQUE, $altoTotal, 'D');
        $this->Line(
            self::ORIGEN_X,
            $y0 + self::ALTO_SECCION_ALUMNO,
            self::ORIGEN_X + self::ANCHO_BLOQUE,
            $y0 + self::ALTO_SECCION_ALUMNO,
        );

        $this->dibujarSeccionAlumno($y0);
        $this->dibujarSeccionPago($y0);
    }

    private function dibujarSeccionAlumno(float $y0): void
    {
        $this->SetXY(self::MARGEN_IZQ, $y0 + 8);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(150, 5, 'Comprobante Nº: '.(string) ($this->datos['nroComprobanteTexto'] ?? ''), 0, 0, 'R');

        $x = self::MARGEN_IZQ;
        $w = 90.0;
        $lineas = [
            ['texto' => 'APELLIDO Y NOMBRE: ', 'estilo' => '', 'size' => 7.0, 'alto' => 5.0],
            ['texto' => (string) ($this->datos['apellidoNombre'] ?? ''), 'estilo' => 'BI', 'size' => 8.0, 'alto' => 5.0],
            ['texto' => 'SALA / GRADO / CURSO: ', 'estilo' => '', 'size' => 7.0, 'alto' => 5.0],
            ['texto' => (string) ($this->datos['cursec'] ?? ''), 'estilo' => 'B', 'size' => 8.0, 'alto' => 5.0],
            ['texto' => (string) ($this->datos['nivel'] ?? ''), 'estilo' => '', 'size' => 8.0, 'alto' => 5.0],
            ['texto' => (string) ($this->datos['cuotaNombre'] ?? ''), 'estilo' => 'B', 'size' => 8.0, 'alto' => 5.0],
        ];

        $hContenido = array_sum(array_column($lineas, 'alto'));
        $y = $y0 + (($hContenido < self::ALTO_SECCION_ALUMNO ? (self::ALTO_SECCION_ALUMNO - $hContenido) / 2 : 8));

        foreach ($lineas as $linea) {
            $this->SetXY($x, $y);
            TcpdfFuenteArial::aplicar($this, (string) $linea['estilo'], (float) $linea['size']);
            $this->Cell($w, (float) $linea['alto'], (string) $linea['texto'], 0, 0, 'L');
            $y += (float) $linea['alto'];
        }
    }

    private function dibujarSeccionPago(float $y0): void
    {
        $yTop = $y0 + self::ALTO_SECCION_ALUMNO;
        $x = self::ORIGEN_X;
        $w = self::ANCHO_BLOQUE;
        $padX = 6.0;

        $importeOriginal = trim((string) ($this->datos['importeOriginalFmt'] ?? ''));
        if ($importeOriginal !== '') {
            $this->SetXY($x, $yTop + 6);
            TcpdfFuenteArial::aplicar($this, '', 8);
            $this->Cell($w, 5, '(Importe Original: $ '.$importeOriginal.')', 0, 1, 'C');
        }

        $y = $this->GetY() + 4;
        $this->SetXY($x + $padX, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(90, 5, 'DETALLE DEL PAGO REALIZADO', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, 'I', 8);
        $this->Cell($w - ($padX * 2) - 90, 5, (string) ($this->datos['medioPago'] ?? ''), 0, 1, 'R');

        $yDetalle = $this->GetY() + 2;
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
     * @param  array<string, mixed>  $header
     */
    private function dibujarHeaderInstitucional(float $y, array $header): float
    {
        $x = self::ORIGEN_X;
        $w = self::ANCHO_BLOQUE;
        $yTexto = $y + 3;

        $this->SetXY($x, $yTexto);

        $insti = trim((string) ($header['insti'] ?? ''));
        $direccion = trim((string) ($header['direccion'] ?? ''));
        $localidad = trim((string) ($header['localidad'] ?? ''));
        $lineaDir = trim($direccion.($direccion !== '' && $localidad !== '' ? ' — ' : '').$localidad);
        $cue = trim((string) ($header['cue'] ?? ''));
        $ee = trim((string) ($header['ee'] ?? ''));
        $lineaIds = trim(($cue !== '' ? 'CUE: '.$cue : '').(($cue !== '' && $ee !== '') ? '   ' : '').($ee !== '' ? 'EE: '.$ee : ''));

        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell($w, 4.5, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        if ($lineaDir !== '') {
            TcpdfFuenteArial::aplicar($this, '', 7);
            $this->Cell($w, 3.5, $lineaDir, 0, 2, 'C');
        }

        if ($lineaIds !== '') {
            TcpdfFuenteArial::aplicar($this, '', 5.5);
            $this->Cell($w, 3, $lineaIds, 0, 2, 'C');
        }

        TcpdfFuenteArial::aplicar($this, 'B', 6);
        $this->Cell($w, 3.5, 'IVA: Exento - Ing. Brutos: Exento', 0, 2, 'C');
        TcpdfFuenteArial::aplicar($this, '', 5);
        $this->MultiCell(
            $w,
            3.2,
            'Entidad Exenta al cumplimiento de la RG (AFIP) 1415 y modificatorias, por aplicación del artículo 5 y del Anexo 1, apartado K de la citada norma',
            0,
            'C',
        );

        $yFin = $this->GetY();
        $h = max(self::LOGO_ALTO + 4, $yFin - $y + 2);

        $this->RoundedRect($x, $y, $w, $h, 2.0, '1111', 'D');

        $logo = $this->resolverLogoArchivo($header);
        if ($logo !== null) {
            $this->Image(
                $logo,
                $x + 3,
                $y + (($h - self::LOGO_ALTO) / 2),
                self::LOGO_ANCHO,
                self::LOGO_ALTO,
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
