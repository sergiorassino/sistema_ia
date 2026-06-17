<?php

namespace App\Support\Mora;

use App\Support\Pdf\TcpdfFuenteArial;
use Illuminate\Support\Facades\Storage;
use TCPDF;

/**
 * PDF «Listado de deuda» (morosos) agrupado por familia — TCPDF A4 vertical.
 */
final class ListadoMorososTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 8.0;

    private const MARGEN_DER = 8.0;

    private const MARGEN_SUP = 8.0;

    /** Ancho útil A4 vertical (210 mm) menos márgenes laterales. */
    private const ANCHO_BLOQUE = 194.0;

    private const ALTO_ENC_INST = 20.0;

    private const ALTO_FILA = 4.0;

    private const ALTO_TITULO_FAMILIA = 4.5;

    private const Y_MAX = 277.0;

    /** @var array<int, float> Suma = 194 mm */
    private const ANCHOS = [
        34.0,  // Estudiante
        20.0,  // Sala/Grado/Curso
        20.0,  // Cuota
        8.0,   // Año
        8.0,   // Beca
        14.0,  // 1º Venc
        14.0,  // Importe
        12.0,  // Bonif.
        12.0,  // Inter.
        12.0,  // Pagado
        12.0,  // Saldo
        14.0,  // Intereses
        14.0,  // A pagar
    ];

    private const ETIQUETAS = [
        'Estudiante',
        'Sala/Grado/Curso',
        'Cuota',
        'Año',
        'Beca',
        '1º Venc',
        'Importe',
        'Bonif.',
        'Inter.',
        'Pagado',
        'Saldo',
        'Intereses',
        'A pagar',
    ];

    /** @var array<string, mixed> */
    private array $datos;

    private int $numeroPagina = 0;

    private float $yActual = 0.0;

    private ?string $logoArchivo = null;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->datos = $datos;
        $this->logoArchivo = $this->resolverLogoArchivo((array) ($datos['pdfHeader'] ?? []));
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Listado de deuda');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
        $this->SetDrawColor(0, 0, 0);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);

        /** @var list<array<string, mixed>> $secciones */
        $secciones = $datos['secciones'] ?? [];
        foreach ($secciones as $seccion) {
            $pdf->renderSeccionFamilia($seccion);
        }

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

    /**
     * @param  array<string, mixed>  $seccion
     */
    private function renderSeccionFamilia(array $seccion): void
    {
        $titulo = trim((string) ($seccion['tituloFamilia'] ?? ''));
        $filas = (array) ($seccion['filas'] ?? []);
        $totales = (array) ($seccion['totales'] ?? []);

        $this->asegurarEspacio(self::ALTO_TITULO_FAMILIA + self::ALTO_FILA);
        $this->dibujarTituloFamilia($titulo);
        $this->dibujarEncabezadoColumnas();

        foreach ($filas as $fila) {
            $this->asegurarEspacio(self::ALTO_FILA);
            $this->dibujarFila((array) $fila);
        }

        $this->asegurarEspacio(self::ALTO_FILA);
        $this->dibujarTotales($totales);
        $this->yActual += 2.0;
    }

    private function asegurarEspacio(float $alto): void
    {
        if ($this->numeroPagina === 0 || $this->yActual + $alto > self::Y_MAX) {
            $this->nuevaPagina();
        }
    }

    private function nuevaPagina(): void
    {
        $this->AddPage('P', 'A4');
        $this->numeroPagina++;
        $this->yActual = $this->dibujarEncabezadoInstitucional(self::MARGEN_SUP);
        $this->yActual += 2.0;
    }

    private function dibujarEncabezadoInstitucional(float $y): float
    {
        $header = (array) ($this->datos['pdfHeader'] ?? []);
        $insti = trim((string) ($header['insti'] ?? config('tenant.nombre', '')));
        $fechaCalculo = (string) ($this->datos['fechaCalculo'] ?? '');
        $fechaInforme = (string) ($this->datos['fechaInforme'] ?? '');

        $this->Rect(self::MARGEN_IZQ, $y, self::ANCHO_BLOQUE, self::ALTO_ENC_INST);

        if ($this->logoArchivo !== null) {
            $this->Image($this->logoArchivo, self::MARGEN_IZQ + 4, $y + 1, 16, 16, '', '', '', false, 300);
        }

        $this->SetXY(self::MARGEN_IZQ, $y + 2);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(self::ANCHO_BLOQUE, 5, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(self::ANCHO_BLOQUE, 4, 'LISTADO DE DEUDA', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 7);
        $sub = 'Fecha de cálculo: '.$fechaCalculo;
        if ($fechaInforme !== '' && $fechaInforme !== $fechaCalculo) {
            $sub .= ' · Emitido: '.$fechaInforme;
        }
        $this->Cell(self::ANCHO_BLOQUE, 4, $sub, 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->SetXY(self::MARGEN_IZQ, $y + self::ALTO_ENC_INST - 3);
        $this->Cell(self::ANCHO_BLOQUE - 6, 3, 'Pág. '.$this->numeroPagina, 0, 0, 'R');

        return $y + self::ALTO_ENC_INST;
    }

    private function dibujarTituloFamilia(string $titulo): void
    {
        $this->SetXY(self::MARGEN_IZQ, $this->yActual);
        TcpdfFuenteArial::aplicar($this, 'B', 6);
        $this->Cell(self::ANCHO_BLOQUE, self::ALTO_TITULO_FAMILIA, $titulo !== '' ? $titulo : 'Familia / Responsable: —', 1, 1, 'L');
        $this->yActual += self::ALTO_TITULO_FAMILIA;
    }

    private function dibujarEncabezadoColumnas(): void
    {
        $this->SetXY(self::MARGEN_IZQ, $this->yActual);
        TcpdfFuenteArial::aplicar($this, 'B', 5);

        foreach (self::ETIQUETAS as $i => $texto) {
            $align = in_array($i, [6, 7, 8, 9, 10, 11, 12], true) ? 'R' : 'C';
            $this->Cell(self::ANCHOS[$i], self::ALTO_FILA, $texto, 1, $i === count(self::ETIQUETAS) - 1 ? 1 : 0, $align);
        }

        $this->yActual += self::ALTO_FILA;
    }

    /**
     * @param  array<string, string>  $fila
     */
    private function dibujarFila(array $fila): void
    {
        $this->SetXY(self::MARGEN_IZQ, $this->yActual);
        TcpdfFuenteArial::aplicar($this, '', 5);

        $valores = [
            (string) ($fila['estudiante'] ?? ''),
            (string) ($fila['curso'] ?? ''),
            (string) ($fila['cuota'] ?? ''),
            (string) ($fila['ano'] ?? ''),
            (string) ($fila['beca'] ?? ''),
            (string) ($fila['venc1'] ?? ''),
            (string) ($fila['importe'] ?? ''),
            (string) ($fila['bonificacion'] ?? ''),
            (string) ($fila['interes'] ?? ''),
            (string) ($fila['pagado'] ?? ''),
            (string) ($fila['saldo'] ?? ''),
            (string) ($fila['intereses'] ?? ''),
            (string) ($fila['aPagar'] ?? ''),
        ];

        foreach ($valores as $i => $texto) {
            $align = match ($i) {
                0, 1, 2 => 'L',
                6, 7, 8, 9, 10, 11, 12 => 'R',
                default => 'C',
            };
            $this->Cell(self::ANCHOS[$i], self::ALTO_FILA, $texto, 1, $i === count($valores) - 1 ? 1 : 0, $align);
        }

        $this->yActual += self::ALTO_FILA;
    }

    /**
     * @param  array<string, string>  $totales
     */
    private function dibujarTotales(array $totales): void
    {
        $this->SetXY(self::MARGEN_IZQ, $this->yActual);
        TcpdfFuenteArial::aplicar($this, 'B', 5);

        $anchoEtiqueta = array_sum(array_slice(self::ANCHOS, 0, 6));
        $this->Cell($anchoEtiqueta, self::ALTO_FILA, 'Totales', 1, 0, 'R');

        $cols = ['importe', 'bonificacion', 'interes', 'pagado', 'saldo', 'intereses', 'aPagar'];
        foreach ($cols as $idx => $key) {
            $i = 6 + $idx;
            $this->Cell(self::ANCHOS[$i], self::ALTO_FILA, (string) ($totales[$key] ?? '0,00'), 1, $idx === count($cols) - 1 ? 1 : 0, 'R');
        }

        $this->yActual += self::ALTO_FILA;
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
            if (is_string($abs) && $abs !== '' && is_file($abs)) {
                return $abs;
            }
        }

        $fallback = public_path('img/3.png');
        if (is_file($fallback)) {
            return $fallback;
        }

        return null;
    }
}
