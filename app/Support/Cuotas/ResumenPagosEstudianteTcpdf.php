<?php

namespace App\Support\Cuotas;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Cuotas\ResumenPagosEstudianteDatos;
use App\Support\Cuotas\ResumenPagosEstudianteTcpdf;
use App\Support\Pdf\TcpdfImagenPng;
use Illuminate\Support\Facades\Storage;
use TCPDF;

/**
 * Resumen de pagos del estudiante — TCPDF (estética legacy cuotas / comprobante).
 */
final class ResumenPagosEstudianteTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 12.0;

    private const MARGEN_DER = 12.0;

    private const MARGEN_SUP = 10.0;

    private const MARGEN_INF = 12.0;

    private const ANCHO_UTIL = 186.0;

    private const ORIGEN_X = 12.0;

    private const LOGO_ANCHO = 17.0;

    private const LOGO_ALTO = 17.0;

    private const ALTURA_FILA = 4.0;

    private const ALTURA_ENC_TABLA = 4.5;

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
        $this->SetTitle('Resumen de pagos');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(true, self::MARGEN_INF);
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
        $this->Cell(self::ANCHO_UTIL, 3, (string) ($this->datos['fechaImpresion'] ?? ''), 0, 1, 'R');

        $y = $this->GetY() + 1;
        $y = $this->dibujarHeaderInstitucional($y, $header);
        $y = $this->dibujarTituloEstudiante($y + 3);
        $this->dibujarTablaPagos($y + 1);
    }

    private function dibujarTituloEstudiante(float $y): float
    {
        $x = self::MARGEN_IZQ;
        $w = self::ANCHO_UTIL;

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell($w, 4.5, 'RESUMEN DE PAGOS REALIZADOS', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $apellidoNombre = trim((string) ($this->datos['apellidoNombre'] ?? ''));
        $dni = trim((string) ($this->datos['dni'] ?? ''));
        $lineaAlumno = $apellidoNombre.($dni !== '' ? ' — DNI: '.$dni : '');
        $this->Cell($w, 4, $lineaAlumno, 0, 2, 'C');

        $curso = trim((string) ($this->datos['curso'] ?? ''));
        $terlecAno = trim((string) ($this->datos['terlecAno'] ?? ''));
        if ($curso !== '') {
            TcpdfFuenteArial::aplicar($this, '', 7.5);
            $lineaCurso = $curso.($terlecAno !== '' ? ' — Ciclo lectivo activo: '.$terlecAno : '');
            $this->Cell($w, 3.5, $lineaCurso, 0, 2, 'C');
        }

        return $this->GetY();
    }

    private function dibujarTablaPagos(float $y): void
    {
        $x = self::MARGEN_IZQ;
        $wAno = 11.0;
        $wCuota = 40.0;
        $wFecha = 30.0;
        $wMp = 12.0;
        $wImp = 23.0;
        $wBon = 23.0;
        $wInt = 23.0;
        $wAbo = 24.0;

        $this->SetXY($x, $y);
        $this->dibujarEncabezadoTabla($x, $wAno, $wCuota, $wFecha, $wMp, $wImp, $wBon, $wInt, $wAbo);

        /** @var list<array<string, string>> $filas */
        $filas = $this->datos['filas'] ?? [];

        TcpdfFuenteArial::aplicar($this, '', 6.5);
        if ($filas === []) {
            $this->Cell(self::ANCHO_UTIL, self::ALTURA_FILA, 'Sin pagos registrados para este estudiante.', 1, 1, 'C');

            return;
        }

        foreach ($filas as $fila) {
            if ($this->GetY() + self::ALTURA_FILA > $this->getPageHeight() - self::MARGEN_INF - 14) {
                $this->AddPage();
                $this->dibujarEncabezadoTabla($x, $wAno, $wCuota, $wFecha, $wMp, $wImp, $wBon, $wInt, $wAbo);
            }

            $this->SetX($x);
            $this->Cell($wAno, self::ALTURA_FILA, $this->truncar((string) ($fila['ano'] ?? ''), 4), 1, 0, 'C');
            $this->Cell($wCuota, self::ALTURA_FILA, $this->truncar((string) ($fila['cuota'] ?? ''), 24), 1, 0, 'L');
            $this->Cell($wFecha, self::ALTURA_FILA, (string) ($fila['fechaHora'] ?? ''), 1, 0, 'C');
            $this->Cell($wMp, self::ALTURA_FILA, $this->truncar((string) ($fila['medioPago'] ?? ''), 6), 1, 0, 'C');
            $this->Cell($wImp, self::ALTURA_FILA, (string) ($fila['importe'] ?? ''), 1, 0, 'R');
            $this->Cell($wBon, self::ALTURA_FILA, (string) ($fila['bonificacion'] ?? ''), 1, 0, 'R');
            $this->Cell($wInt, self::ALTURA_FILA, (string) ($fila['interes'] ?? ''), 1, 0, 'R');
            $this->Cell($wAbo, self::ALTURA_FILA, (string) ($fila['abonado'] ?? ''), 1, 1, 'R');
        }

        $this->dibujarFilaTotales($x, $wAno, $wCuota, $wFecha, $wMp, $wImp, $wBon, $wInt, $wAbo);
    }

    private function dibujarEncabezadoTabla(
        float $x,
        float $wAno,
        float $wCuota,
        float $wFecha,
        float $wMp,
        float $wImp,
        float $wBon,
        float $wInt,
        float $wAbo,
    ): void {
        $this->SetX($x);
        $this->SetFillColor(220, 220, 220);
        TcpdfFuenteArial::aplicar($this, 'B', 6.5);
        $this->Cell($wAno, self::ALTURA_ENC_TABLA, 'Año', 1, 0, 'C', true);
        $this->Cell($wCuota, self::ALTURA_ENC_TABLA, 'Cuota', 1, 0, 'C', true);
        $this->Cell($wFecha, self::ALTURA_ENC_TABLA, 'Fecha y hora del Pago', 1, 0, 'C', true);
        $this->Cell($wMp, self::ALTURA_ENC_TABLA, 'M.P.', 1, 0, 'C', true);
        $this->Cell($wImp, self::ALTURA_ENC_TABLA, 'Importe', 1, 0, 'C', true);
        $this->Cell($wBon, self::ALTURA_ENC_TABLA, 'Bonificación', 1, 0, 'C', true);
        $this->Cell($wInt, self::ALTURA_ENC_TABLA, 'Interés', 1, 0, 'C', true);
        $this->Cell($wAbo, self::ALTURA_ENC_TABLA, 'Abonado', 1, 1, 'C', true);
        TcpdfFuenteArial::aplicar($this, '', 6.5);
    }

    private function dibujarFilaTotales(
        float $x,
        float $wAno,
        float $wCuota,
        float $wFecha,
        float $wMp,
        float $wImp,
        float $wBon,
        float $wInt,
        float $wAbo,
    ): void {
        if ($this->GetY() + self::ALTURA_FILA > $this->getPageHeight() - self::MARGEN_INF) {
            $this->AddPage();
        }

        /** @var array{importe?: string, bonificacion?: string, interes?: string, abonado?: string} $totales */
        $totales = $this->datos['totales'] ?? [];
        $wEtiqueta = $wAno + $wCuota + $wFecha + $wMp;

        $this->SetX($x);
        $this->SetFillColor(235, 235, 235);
        TcpdfFuenteArial::aplicar($this, 'B', 6.5);
        $this->Cell($wEtiqueta, self::ALTURA_FILA, 'TOTALES', 1, 0, 'R', true);
        $this->Cell($wImp, self::ALTURA_FILA, (string) ($totales['importe'] ?? ''), 1, 0, 'R', true);
        $this->Cell($wBon, self::ALTURA_FILA, (string) ($totales['bonificacion'] ?? ''), 1, 0, 'R', true);
        $this->Cell($wInt, self::ALTURA_FILA, (string) ($totales['interes'] ?? ''), 1, 0, 'R', true);
        $this->Cell($wAbo, self::ALTURA_FILA, (string) ($totales['abonado'] ?? ''), 1, 1, 'R', true);
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function dibujarHeaderInstitucional(float $y, array $header): float
    {
        $x = self::ORIGEN_X;
        $w = self::ANCHO_UTIL;
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

    private function truncar(string $texto, int $max): string
    {
        $texto = trim($texto);
        if (mb_strlen($texto) <= $max) {
            return $texto;
        }

        return mb_substr($texto, 0, max(1, $max - 1)).'…';
    }
}
