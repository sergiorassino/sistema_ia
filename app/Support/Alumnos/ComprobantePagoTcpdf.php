<?php

namespace App\Support\Alumnos;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use Illuminate\Support\Facades\Storage;
use TCPDF;

/**
 * Cupón de pago — maquetación legacy FPDF (ScriptCase) convertida a TCPDF.
 */
final class ComprobantePagoTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 25.0;

    private const MARGEN_DER = 15.0;

    private const MARGEN_SUP = 10.0;

    private const ANCHO_BLOQUE = 170.0;

    private const ORIGEN_X = 20.0;

    private const LOGO_ANCHO = 17.0;

    private const LOGO_ALTO = 17.0;

    private const ALTURA_CUPON = 235.0;

    /** Alto del recuadro superior (datos del alumno). */
    private const ALTO_SECCION_ALUMNO = 80.0;

    /** Alto del recuadro medio (código electrónico y vencimientos). */
    private const ALTO_SECCION_PAGO = 60.0;

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
        $this->SetTitle('Cupón de pago');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false, 10);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
        $this->SetFillColor(220, 220, 220);
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

        $this->Rect(self::ORIGEN_X, $y0, self::ANCHO_BLOQUE, self::ALTURA_CUPON, 'D');
        $this->Rect(self::ORIGEN_X, $y0 + self::ALTO_SECCION_ALUMNO, self::ANCHO_BLOQUE, self::ALTO_SECCION_PAGO, 'D');

        $this->dibujarSeccionAlumno($y0);
        $this->dibujarSeccionCodigoYVencimientos($y0);
        $this->dibujarSeccionPieRecaudacion($y0);
    }

    private function dibujarSeccionAlumno(float $y0): void
    {
        $this->SetXY(self::MARGEN_IZQ, $y0 + 12);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(150, 5, 'Comprobante Nº: '.(string) ($this->datos['nroComprobanteTexto'] ?? ''), 0, 0, 'R');

        $x = self::MARGEN_IZQ;
        $w = 90.0;
        $lineas = [
            [
                'texto' => 'APELLIDO Y NOMBRE: ',
                'estilo' => '',
                'size' => 7.0,
                'alto' => 5.0,
            ],
            [
                'texto' => (string) ($this->datos['apellidoNombre'] ?? ''),
                'estilo' => 'BI',
                'size' => 8.0,
                'alto' => 5.0,
            ],
            [
                'texto' => 'SALA / GRADO / CURSO: ',
                'estilo' => '',
                'size' => 7.0,
                'alto' => 5.0,
            ],
            [
                'texto' => (string) ($this->datos['cursec'] ?? ''),
                'estilo' => 'B',
                'size' => 8.0,
                'alto' => 5.0,
            ],
            [
                'texto' => (string) ($this->datos['nivel'] ?? ''),
                'estilo' => '',
                'size' => 8.0,
                'alto' => 5.0,
            ],
            [
                'texto' => (string) ($this->datos['cuotaNombre'] ?? ''),
                'estilo' => '',
                'size' => 8.0,
                'alto' => 5.0,
            ],
        ];

        $leyendaBeca = trim((string) ($this->datos['leyendaBeca'] ?? ''));
        if ($leyendaBeca !== '') {
            $lineas[] = [
                'texto' => $leyendaBeca,
                'estilo' => '',
                'size' => 8.0,
                'alto' => 5.0,
            ];
        }

        $hBox = self::ALTO_SECCION_ALUMNO;
        $hContenido = array_sum(array_column($lineas, 'alto'));
        $y = $y0 + (($hBox - $hContenido) / 2);

        foreach ($lineas as $linea) {
            $this->SetXY($x, $y);
            TcpdfFuenteArial::aplicar($this, (string) $linea['estilo'], (float) $linea['size']);
            $this->Cell($w, (float) $linea['alto'], (string) $linea['texto'], 0, 0, 'L');
            $y += (float) $linea['alto'];
        }
    }

    /**
     * Recuadro medio: código de pago electrónico y vencimientos (centrados).
     */
    private function dibujarSeccionCodigoYVencimientos(float $y0): void
    {
        $yTop = $y0 + self::ALTO_SECCION_ALUMNO;
        $hBox = self::ALTO_SECCION_PAGO;
        $x = self::ORIGEN_X;
        $w = self::ANCHO_BLOQUE;

        $leyendaBonificada = (string) ($this->datos['leyendaBonificada'] ?? '');
        $codigoPago = trim((string) ($this->datos['codigoPagoElectronico'] ?? ''));
        $lineasCodigo = [];
        if ($codigoPago !== '') {
            $lineasCodigo = [
                [
                    'texto' => 'Código de Pago Electrónico:   '.$codigoPago,
                    'estilo' => 'B',
                    'size' => 8.0,
                    'alto' => 4.5,
                ],
                [
                    'texto' => '(Link Pagos y Pago Mis Cuentas): Rubro: INSTITUCIONES EDUCATIVAS',
                    'estilo' => '',
                    'size' => 6.0,
                    'alto' => 4.0,
                ],
            ];
        }
        $lineasVencimientos = [
            [
                'texto' => '1º Venc '.$leyendaBonificada.': '.(string) ($this->datos['venc1Esp'] ?? '')
                    .'    $ '.(string) ($this->datos['importeVenc1Fmt'] ?? ''),
                'estilo' => '',
                'size' => 8.0,
                'alto' => 5.0,
            ],
            [
                'texto' => '2º Venc: '.(string) ($this->datos['venc2Esp'] ?? '')
                    .'    $ '.(string) ($this->datos['importeVenc2Fmt'] ?? ''),
                'estilo' => '',
                'size' => 8.0,
                'alto' => 5.0,
            ],
        ];

        if (! empty($this->datos['cuponVencido'])) {
            $lineasVencimientos[] = [
                'texto' => 'Actualización Cupon Vencido: '.(string) ($this->datos['nuevoVencEsp'] ?? '')
                    .'    '.(string) ($this->datos['nuevoImporteFmt'] ?? ''),
                'estilo' => '',
                'size' => 8.0,
                'alto' => 5.0,
            ];
        }

        $espacioCodigoVencimientos = $lineasCodigo !== [] ? 10.0 : 0.0;
        $hContenido = array_sum(array_column($lineasCodigo, 'alto'))
            + $espacioCodigoVencimientos
            + array_sum(array_column($lineasVencimientos, 'alto'));
        $y = $yTop + (($hBox - $hContenido) / 2);

        foreach ($lineasCodigo as $linea) {
            $this->SetXY($x, $y);
            TcpdfFuenteArial::aplicar($this, (string) $linea['estilo'], (float) $linea['size']);
            $this->Cell($w, (float) $linea['alto'], (string) $linea['texto'], 0, 1, 'C');
            $y += (float) $linea['alto'];
        }

        $y += $espacioCodigoVencimientos;

        $xVencimientos = self::ORIGEN_X + 4 + 28 + 4;
        $wVencimientos = self::ANCHO_BLOQUE - ($xVencimientos - self::ORIGEN_X);

        foreach ($lineasVencimientos as $linea) {
            $this->SetXY($xVencimientos, $y);
            TcpdfFuenteArial::aplicar($this, (string) $linea['estilo'], (float) $linea['size']);
            $this->Cell($wVencimientos, (float) $linea['alto'], (string) $linea['texto'], 0, 1, 'L');
            $y += (float) $linea['alto'];
        }

        $this->dibujarQrEnSeccionPago($yTop, $hBox);
    }

    private function dibujarQrEnSeccionPago(float $yTop, float $hBox): void
    {
        $cadena = trim((string) ($this->datos['cadenaQr'] ?? ''));
        if ($cadena === '') {
            return;
        }

        $qrSize = 28.0;
        $style = [
            'border' => false,
            'vpadding' => 'auto',
            'hpadding' => 'auto',
            'fgcolor' => [0, 0, 0],
            'bgcolor' => false,
        ];

        $this->write2DBarcode(
            $cadena,
            'QRCODE,H',
            self::ORIGEN_X + 4,
            $yTop + (($hBox - $qrSize) / 2),
            $qrSize,
            $qrSize,
            $style,
        );
    }

    /**
     * Recuadro inferior: agentes recaudadores y código de barras (centrados).
     */
    private function dibujarSeccionPieRecaudacion(float $y0): void
    {
        $yTop = $y0 + self::ALTO_SECCION_ALUMNO + self::ALTO_SECCION_PAGO;
        $hBox = self::ALTURA_CUPON - self::ALTO_SECCION_ALUMNO - self::ALTO_SECCION_PAGO;
        $x = self::ORIGEN_X;
        $w = self::ANCHO_BLOQUE;
        $barra = trim((string) ($this->datos['barra'] ?? ''));

        if ($barra === '') {
            return;
        }

        $altoAgentes1 = 5.0;
        $altoAgentes2 = 5.0;
        $altoNumero = 5.0;
        $altoBarcode = 10.0;
        $espacioAgentesBarra = 10.0;

        $hContenido = $altoAgentes1 + $altoAgentes2;
        if ($barra !== '') {
            $hContenido += $espacioAgentesBarra + $altoNumero + 2 + $altoBarcode;
        }

        $y = $yTop + (($hBox - $hContenido) / 2);

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell($w, $altoAgentes1, 'Agentes recaudadores: BANCO ROELA, PAGO FÁCIL, RAPIPAGO', 0, 1, 'C');
        $y += $altoAgentes1;

        $this->SetXY($x, $y);
        $this->Cell(
            $w,
            $altoAgentes2,
            'Red Link: Colegio San Francisco Asís Córdoba  -  Red Banelco: Col SF de Asís Cba',
            0,
            1,
            'C',
        );
        $y += $altoAgentes2;

        if ($barra === '') {
            return;
        }

        $y += $espacioAgentesBarra;

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell($w, $altoNumero, $barra, 0, 1, 'C');
        $y += $altoNumero + 2;

        $anchoBarcode = 100.0;
        $xBarcode = $x + (($w - $anchoBarcode) / 2);
        $style = [
            'position' => '',
            'align' => 'C',
            'stretch' => false,
            'fitwidth' => true,
            'cellfitalign' => '',
            'border' => false,
            'hpadding' => 'auto',
            'vpadding' => 'auto',
            'fgcolor' => [0, 0, 0],
            'bgcolor' => false,
            'text' => false,
            'font' => 'helvetica',
            'fontsize' => 8,
            'stretchtext' => 4,
        ];

        $this->write1DBarcode($barra, 'C128', $xBarcode, $y, $anchoBarcode, $altoBarcode, 0.4, $style, 'N');
    }

    /**
     * Encabezado institucional SE (mismo criterio que `pdf/partials/header.blade.php`).
     *
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

        $this->SetDrawColor(17, 17, 17);
        $this->RoundedRect($x, $y, $w, $h, 2.0, '1111', 'D');

        $logo = $this->resolverLogoArchivo($header);
        if ($logo !== null) {
            $this->Image(
                TcpdfImagenPng::fuenteTcpdf($logo),
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
