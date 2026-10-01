<?php

namespace App\Support\Estadistica;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use TCPDF;

/**
 * Estadística por edad — TCPDF A4 apaisado, una columna por curso.
 */
final class EstadisticaPorEdadTcpdf extends TCPDF
{
    private const MARGEN = 6.0;

    private const ANCHO_EDAD = 42.0;

    private const ANCHO_TOTAL = 12.0;

    private const ANCHO_MIN_CURSO = 7.5;

    private const ALTO_FILA = 5.0;

    private const ALTO_ENCABEZADO_TABLA = 7.0;

    /** @var array<string, mixed> */
    private array $datos;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8', false);
        $this->datos = $datos;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Estadísticas por edad');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false, 6);
        $this->SetMargins(self::MARGEN, 6, self::MARGEN);
        $this->SetDrawColor(0, 0, 0);
        $this->SetTextColor(0, 0, 0);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);

        /** @var list<array{id: int, etiqueta: string, total: int}> $cursos */
        $cursos = $datos['cursos'] ?? [];
        if ($cursos === []) {
            $pdf->AddPage();
            $pdf->dibujarEncabezado(null);
            $pdf->SetXY(self::MARGEN, 32);
            TcpdfFuenteArial::aplicar($pdf, '', 10);
            $pdf->Cell(0, 8, 'No hay cursos en este nivel para el ciclo lectivo.', 0, 1, 'L');

            return $pdf;
        }

        $porPagina = $pdf->cursosPorPagina();
        $trozos = array_chunk($cursos, $porPagina);
        $paginas = count($trozos);
        foreach ($trozos as $indice => $trozo) {
            $pdf->AddPage();
            $pdf->dibujarEncabezado($paginas > 1 ? ($indice + 1).' / '.$paginas : null);
            $pdf->dibujarTabla($trozo);
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

    private function cursosPorPagina(): int
    {
        $disponible = $this->getPageWidth() - (self::MARGEN * 2) - self::ANCHO_EDAD - self::ANCHO_TOTAL;

        return max(1, (int) floor($disponible / self::ANCHO_MIN_CURSO));
    }

    private function dibujarEncabezado(?string $pagina): void
    {
        /** @var array<string, mixed> $header */
        $header = $this->datos['header'] ?? [];
        $ano = (int) ($this->datos['ano'] ?? now()->year);
        $fecha = (string) ($this->datos['fechaTexto'] ?? '');
        $ancho = $this->getPageWidth() - (self::MARGEN * 2);
        $x = self::MARGEN;
        $y = 6.0;

        $this->SetLineWidth(0.2);
        $this->Rect($x, $y, $ancho, 20);

        $logo = $header['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), $x + 2, $y + 2, 16, 16, '', '', '', false, 300);
        }

        $insti = trim((string) ($header['insti'] ?? ''));
        if ($insti === '') {
            $insti = (string) ($this->datos['nivel'] ?? 'Institución');
        }

        $this->SetXY($x, $y + 2.2);
        TcpdfFuenteArial::aplicar($this, 'B', 11);
        $this->Cell($ancho, 4.5, mb_strtoupper($insti, 'UTF-8'), 0, 2, 'C');
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell($ancho, 4.2, 'ESTADÍSTICAS POR EDAD - '.$ano, 0, 2, 'C');
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell($ancho, 3.8, '(Solo regulares)', 0, 2, 'C');
        $lineaFecha = 'Fecha de cálculo: '.$fecha;
        if ($pagina !== null) {
            $lineaFecha .= '   ·   Hoja '.$pagina;
        }
        $this->Cell($ancho, 3.6, $lineaFecha, 0, 2, 'C');
    }

    /**
     * @param  list<array{id: int, etiqueta: string, total: int}>  $cursos
     */
    private function dibujarTabla(array $cursos): void
    {
        $x0 = self::MARGEN;
        $y = 28.0;
        $disponible = $this->getPageWidth() - (self::MARGEN * 2) - self::ANCHO_EDAD - self::ANCHO_TOTAL;
        $anchoCurso = $disponible / max(1, count($cursos));
        $this->SetLineWidth(0.15);

        $this->SetXY($x0, $y);
        $this->SetFillColor(244, 248, 249);
        $this->celda($x0, $y, self::ANCHO_EDAD, self::ALTO_ENCABEZADO_TABLA, '', true, true, 7);
        $x = $x0 + self::ANCHO_EDAD;
        foreach ($cursos as $curso) {
            $this->celdaCurso($x, $y, $anchoCurso, (string) ($curso['etiqueta'] ?? ''));
            $x += $anchoCurso;
        }
        $this->celda($x, $y, self::ANCHO_TOTAL, self::ALTO_ENCABEZADO_TABLA, 'Total', true, true, 7);

        $y += self::ALTO_ENCABEZADO_TABLA;

        /** @var list<array{clave: string, etiqueta: string, celdas: array<int, int>, total: int}> $filas */
        $filas = $this->datos['filas'] ?? [];
        foreach ($filas as $fila) {
            $esTotal = ($fila['clave'] ?? '') === EstadisticaPorEdadDatos::TOTAL;
            $this->SetFillColor($esTotal ? 193 : 255, $esTotal ? 215 : 255, $esTotal ? 218 : 255);
            $this->celda($x0, $y, self::ANCHO_EDAD, self::ALTO_FILA, ' '.($fila['etiqueta'] ?? ''), $esTotal, true, 6.5, 'L');
            $x = $x0 + self::ANCHO_EDAD;
            foreach ($cursos as $curso) {
                $valor = (int) ($fila['celdas'][$curso['id']] ?? 0);
                $this->celda(
                    $x,
                    $y,
                    $anchoCurso,
                    self::ALTO_FILA,
                    (string) $valor,
                    $esTotal,
                    $esTotal || $valor > 0,
                    7,
                );
                $x += $anchoCurso;
            }
            $totalFila = (int) ($fila['total'] ?? 0);
            $this->SetFillColor(193, 215, 218);
            $this->celda(
                $x,
                $y,
                self::ANCHO_TOTAL,
                self::ALTO_FILA,
                (string) $totalFila,
                true,
                true,
                7,
            );
            $y += self::ALTO_FILA;
        }
    }

    private function celdaCurso(float $x, float $y, float $w, string $etiqueta): void
    {
        $size = 7.0;
        TcpdfFuenteArial::aplicar($this, 'B', $size);
        while ($size > 4.0 && $this->GetStringWidth($etiqueta) > ($w - 0.6)) {
            $size -= 0.5;
            TcpdfFuenteArial::aplicar($this, 'B', $size);
        }
        $this->celda($x, $y, $w, self::ALTO_ENCABEZADO_TABLA, $etiqueta, true, true, $size);
    }

    private function celda(
        float $x,
        float $y,
        float $w,
        float $h,
        string $texto,
        bool $fill,
        bool $bold,
        float $size,
        string $align = 'C',
    ): void {
        TcpdfFuenteArial::aplicar($this, $bold ? 'B' : '', $size);
        $this->SetXY($x, $y);
        $this->Cell($w, $h, $texto, 1, 0, $align, $fill);
    }
}
