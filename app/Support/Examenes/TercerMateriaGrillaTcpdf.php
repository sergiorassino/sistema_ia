<?php

namespace App\Support\Examenes;

use App\Support\Pdf\TcpdfImagenPng;
use TCPDF;

/**
 * Grilla de gestión de tercer materia (listado TM) — TCPDF apaisado, bloque centrado.
 */
final class TercerMateriaGrillaTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 10.0;

    private const MARGEN_DER = 10.0;

    private const MARGEN_SUP = 8.0;

    private const FUENTE = 'dejavusans';

    private const ALTURA_FILA = 5.0;

    private const ALTURA_ENC = 12.0;

    private const ALTURA_CAJA_ENCABEZADO = 22.0;

    /** Ancho total de columnas (mm). */
    private const ANCHO_GRILLA = 227.0;

    /** @var array{
     *     instiNombre: string,
     *     nivelNombre: string,
     *     cicloAno: string,
     *     logo_abs: ?string,
     *     fechaImpresion: string
     * } */
    private array $meta;

    /** @var list<array<string, mixed>> */
    private array $filas;

    /**
     * @param  array{
     *     instiNombre: string,
     *     nivelNombre: string,
     *     cicloAno: string,
     *     logo_abs: ?string,
     *     fechaImpresion: string
     * }  $meta
     * @param  list<array<string, mixed>>  $filas
     */
    private function __construct(array $meta, array $filas)
    {
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8', false);
        $this->meta = $meta;
        $this->filas = $filas;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Gestión de tercer materia');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
    }

    /**
     * @param  array{
     *     instiNombre: string,
     *     nivelNombre: string,
     *     cicloAno: string,
     *     logo_abs: ?string,
     *     fechaImpresion: string
     * }  $meta
     * @param  list<array<string, mixed>>  $filas
     */
    public static function generar(array $meta, array $filas): self
    {
        $pdf = new self($meta, $filas);
        $pdf->iniciarPagina();
        $pdf->dibujarGrilla();

        return $pdf;
    }

    private function anchoPaginaUtil(): float
    {
        return $this->getPageWidth() - self::MARGEN_IZQ - self::MARGEN_DER;
    }

    private function offsetXCentrado(): float
    {
        return self::MARGEN_IZQ + max(0.0, ($this->anchoPaginaUtil() - self::ANCHO_GRILLA) / 2);
    }

    private function iniciarPagina(): void
    {
        $this->AddPage();
        $this->dibujarFechaImpresion();
        $this->dibujarEncabezadoInstitucional();
        $this->Ln(3);
    }

    private function dibujarFechaImpresion(): void
    {
        $fecha = trim($this->meta['fechaImpresion']);
        if ($fecha === '') {
            return;
        }

        $x0 = $this->offsetXCentrado();
        $this->SetXY($x0, self::MARGEN_SUP);
        $this->SetFont(self::FUENTE, '', 5);
        $this->Cell(self::ANCHO_GRILLA, 3, 'Impreso: '.$fecha, 0, 0, 'R');
    }

    private function dibujarEncabezadoInstitucional(): void
    {
        $x0 = $this->offsetXCentrado();
        $y0 = self::MARGEN_SUP + 4;
        $logo = $this->meta['logo_abs'] ?? null;

        $this->Rect($x0, $y0, self::ANCHO_GRILLA, self::ALTURA_CAJA_ENCABEZADO, 'D');

        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), $x0 + 4, $y0 + 1, 18, 20, '', '', '', false, 300);
        }

        $this->SetXY($x0, $y0 + 3);
        $this->SetFont(self::FUENTE, 'B', 11);
        $this->Cell(self::ANCHO_GRILLA, 6, $this->meta['instiNombre'], 0, 2, 'C');

        $this->SetFont(self::FUENTE, 'B', 9);
        $this->Cell(self::ANCHO_GRILLA, 5, 'Gestión de Tercer Materia', 0, 2, 'C');

        $sub = trim($this->meta['nivelNombre']);
        if ($this->meta['cicloAno'] !== '') {
            $sub .= ($sub !== '' ? ' · ' : '').'Ciclo lectivo actual '.$this->meta['cicloAno'];
        }
        if ($sub !== '') {
            $this->SetFont(self::FUENTE, '', 7);
            $this->Cell(self::ANCHO_GRILLA, 4, $sub, 0, 2, 'C');
        }

        $this->SetY($y0 + self::ALTURA_CAJA_ENCABEZADO);
    }

    private function dibujarGrilla(): void
    {
        $cols = $this->columnas();
        $x0 = $this->offsetXCentrado();
        $y = $this->GetY();
        $limiteY = $this->getPageHeight() - 12;

        $this->dibujarFilaEncabezadoGrilla($x0, $y, $cols);
        $y += self::ALTURA_ENC;

        $this->SetFont(self::FUENTE, '', 6);
        $this->SetFillColor(255, 255, 255);

        $n = 0;
        foreach ($this->filas as $fila) {
            if ($y + self::ALTURA_FILA > $limiteY) {
                $this->iniciarPagina();
                $x0 = $this->offsetXCentrado();
                $y = $this->GetY();
                $this->dibujarFilaEncabezadoGrilla($x0, $y, $cols);
                $y += self::ALTURA_ENC;
                $this->SetFont(self::FUENTE, '', 6);
            }

            $n++;
            $fill = $n % 2 === 0;
            $valores = [
                (string) ($fila['estudiante'] ?? ''),
                (string) ($fila['ano_lectivo'] ?? ''),
                (string) ($fila['curso'] ?? ''),
                (string) ($fila['materia'] ?? ''),
                (string) ($fila['tm1'] ?? ''),
                (string) ($fila['tm2'] ?? ''),
                (string) ($fila['tm3'] ?? ''),
                (string) ($fila['tm4'] ?? ''),
                (string) ($fila['tm5'] ?? ''),
                (string) ($fila['tm6'] ?? ''),
                (string) ($fila['tmNota'] ?? ''),
                (string) ($fila['curso_actual'] ?? ''),
                (string) ($fila['profesor_actual'] ?? ''),
            ];

            $x = $x0;
            foreach ($cols as $i => $col) {
                $this->SetXY($x, $y);
                $align = $i === 0 || $i >= 12 ? 'L' : 'C';
                $this->Cell($col['w'], self::ALTURA_FILA, $this->truncar($valores[$i], $col['w']), 1, 0, $align, $fill);
                $x += $col['w'];
            }
            $y += self::ALTURA_FILA;
        }

        $this->SetXY($x0, $y + 2);
        $this->SetFont(self::FUENTE, '', 7);
        $this->Cell(self::ANCHO_GRILLA, 4, count($this->filas).' registro(s) · Condición TM · apro = 1', 0, 1, 'C');
    }

    /**
     * @param  list<array{w: float, h: string}>  $cols
     */
    private function dibujarFilaEncabezadoGrilla(float $x0, float $y, array $cols): void
    {
        $this->SetFillColor(241, 245, 246);
        $this->SetFont(self::FUENTE, 'B', 6);
        $x = $x0;
        foreach ($cols as $col) {
            $this->SetXY($x, $y);
            $this->Cell($col['w'], self::ALTURA_ENC, $col['h'], 1, 0, 'C', true);
            $x += $col['w'];
        }
    }

    /**
     * @return list<array{w: float, h: string}>
     */
    private function columnas(): array
    {
        return [
            ['w' => 38.0, 'h' => 'Estudiante'],
            ['w' => 10.0, 'h' => 'Año'],
            ['w' => 22.0, 'h' => 'Curso'],
            ['w' => 32.0, 'h' => 'Materia'],
            ['w' => 9.0, 'h' => 'TM1'],
            ['w' => 9.0, 'h' => 'TM2'],
            ['w' => 9.0, 'h' => 'TM3'],
            ['w' => 9.0, 'h' => 'TM4'],
            ['w' => 9.0, 'h' => 'TM5'],
            ['w' => 9.0, 'h' => 'TM6'],
            ['w' => 11.0, 'h' => 'Nota'],
            ['w' => 22.0, 'h' => 'Curso actual'],
            ['w' => 38.0, 'h' => 'Profesor'],
        ];
    }

    private function truncar(string $texto, float $anchoMm): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }
        $max = max(4, (int) floor($anchoMm / 1.6));
        if (mb_strlen($texto) <= $max) {
            return $texto;
        }

        return mb_substr($texto, 0, $max - 1).'…';
    }
}
