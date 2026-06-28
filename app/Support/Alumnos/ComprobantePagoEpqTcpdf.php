<?php

namespace App\Support\Alumnos;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfLogoInstitucional;
use TCPDF;

/**
 * Cupón de pago EPQ — maquetación legacy FPDF (dos talonarios por hoja A4).
 */
final class ComprobantePagoEpqTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 25.0;

    private const MARGEN_DER = 15.0;

    private const MARGEN_SUP = 10.0;

    private const ORIGEN_X = 20.0;

    private const ANCHO_BLOQUE = 170.0;

    private const LOGO_ANCHO = 15.0;

    private const LOGO_ALTO = 15.0;

    private const ALTO_CUPON = 110.0;

    private const ALTO_ENCAB = 22.0;

    private const ALTO_ALUMNO = 48.0;

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

    private function dibujarDocumento(): void
    {
        $this->dibujarCupon(10.0);
        $this->dibujarCupon(148.0);
    }

    private function dibujarCupon(float $yInicio): void
    {
        $header = (array) ($this->datos['pdfHeader'] ?? []);
        $x = self::ORIGEN_X;
        $w = self::ANCHO_BLOQUE;

        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->SetXY(self::MARGEN_IZQ, $yInicio);
        $this->Cell(160, 3, (string) ($this->datos['fechaImpresion'] ?? ''), 0, 0, 'R');

        $yTop = $yInicio + 6.0;
        $yEncFin = $yTop + self::ALTO_ENCAB;
        $yAluFin = $yEncFin + self::ALTO_ALUMNO;

        $this->Rect($x, $yTop, $w, self::ALTO_CUPON, 'D');
        $this->Line($x, $yEncFin, $x + $w, $yEncFin);
        $this->Line($x, $yAluFin, $x + $w, $yAluFin);

        $this->dibujarLogo($header, $yTop + 3.5);

        $insti = trim((string) ($header['insti'] ?? ''));
        $this->SetXY($x, $yTop + 6.0);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell($w, 6, $insti !== '' ? $insti : 'Institución', 0, 0, 'C');

        $this->SetXY($x, $yEncFin - 5.0);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(
            $w - 2,
            5,
            'Comprobante Nº:  '.(string) ($this->datos['nroComprobanteTexto'] ?? ''),
            0,
            0,
            'R',
        );

        $yTexto = $yEncFin + 6.0;
        $lineas = [
            ['label' => 'APELLIDO Y NOMBRE: ', 'valor' => (string) ($this->datos['apellidoNombre'] ?? ''), 'estiloValor' => 'BI', 'sizeLabel' => 7.0, 'sizeValor' => 8.0],
            ['label' => 'SALA / GRADO / CURSO: ', 'valor' => (string) ($this->datos['cursec'] ?? ''), 'estiloValor' => 'B', 'sizeLabel' => 7.0, 'sizeValor' => 8.0],
            ['label' => '', 'valor' => (string) ($this->datos['nivel'] ?? ''), 'estiloValor' => '', 'sizeLabel' => 8.0, 'sizeValor' => 8.0],
            ['label' => '', 'valor' => (string) ($this->datos['cuotaNombre'] ?? ''), 'estiloValor' => '', 'sizeLabel' => 8.0, 'sizeValor' => 8.0],
        ];

        foreach ($lineas as $linea) {
            $this->SetXY(self::MARGEN_IZQ, $yTexto);
            if ($linea['label'] !== '') {
                TcpdfFuenteArial::aplicar($this, '', (float) $linea['sizeLabel']);
                $this->Cell(90, 5, (string) $linea['label'], 0, 0, 'L');
            }
            TcpdfFuenteArial::aplicar($this, (string) $linea['estiloValor'], (float) $linea['sizeValor']);
            $this->Cell(90, 5, (string) $linea['valor'], 0, 1, 'L');
            $yTexto += 5.0;
        }

        $codigoPago = trim((string) ($this->datos['codigoPagoElectronico'] ?? ''));
        if ($codigoPago !== '') {
            $this->SetXY($x, $yAluFin - 6.0);
            TcpdfFuenteArial::aplicar($this, 'B', 8);
            $this->Cell(
                $w - 2,
                5,
                'Código de Pago Electrónico:    '.$codigoPago,
                0,
                0,
                'R',
            );
        }

        $this->dibujarVencimientos($yAluFin + 4.0);

        $barra = trim((string) ($this->datos['barra'] ?? ''));
        if ($barra === '') {
            return;
        }

        $yBarraNum = $yAluFin + 18.0;
        $this->SetXY($x, $yBarraNum);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell($w, 4, $barra, 0, 1, 'C');

        $anchoBarcode = 140.0;
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

        $this->write1DBarcode($barra, 'C128', $xBarcode, $yBarraNum + 5.0, $anchoBarcode, 12.0, 0.4, $style, 'N');
    }

    private function dibujarVencimientos(float $y): void
    {
        $this->SetXY(30.0, $y);

        if (! empty($this->datos['cuponVencido'])) {
            TcpdfFuenteArial::aplicar($this, '', 8);
            $this->Cell(
                80,
                5,
                'Nuevo Vencimiento: '.(string) ($this->datos['nuevoVencEsp'] ?? ''),
                0,
                1,
                'L',
            );
            $this->SetX(30.0);
            $this->Cell(
                80,
                5,
                'Importe Actualizado: '.(string) ($this->datos['nuevoImporteFmt'] ?? ''),
                0,
                1,
                'L',
            );

            return;
        }

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(
            50,
            5,
            '1º Venc: '.(string) ($this->datos['venc1Esp'] ?? ''),
            0,
            0,
            'L',
        );
        $this->Cell(
            30,
            5,
            '$ '.(string) ($this->datos['importeVenc1Fmt'] ?? ''),
            0,
            1,
            'L',
        );

        $this->SetX(30.0);
        $this->Cell(
            50,
            5,
            '2º Venc: '.(string) ($this->datos['venc2Esp'] ?? ''),
            0,
            0,
            'L',
        );
        $this->Cell(
            30,
            5,
            '$ '.(string) ($this->datos['importeVenc2Fmt'] ?? ''),
            0,
            1,
            'L',
        );
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function dibujarLogo(array $header, float $y): void
    {
        TcpdfLogoInstitucional::dibujarAjustado(
            $this,
            self::ORIGEN_X + 5.0,
            $y,
            self::LOGO_ANCHO,
            self::LOGO_ALTO,
            pdfHeaderLogoAbsolutePath($header),
        );
    }
}
