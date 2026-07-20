<?php

namespace App\Support\Examenes;

use TCPDF;

/**
 * Acta volante de exámenes (previas) — generación incremental (TCPDF), una página por acta.
 * Misma maquetación que pdf/acta-volante-coloquios.blade.php (proporciones legacy suma 170 mm).
 */
final class ActaVolantePreviosTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 25.0;

    private const MARGEN_DER = 15.0;

    private const MARGEN_SUP = 10.0;

    private const ANCHO_UTIL = 170.0;

    private const FUENTE = 'dejavusans';

    private const BLANK = "\u{00A0}";

    /** Proporciones legacy (mm): 7+15+78+20+20+20+10 */
    private const ANCHO_NRO = 7.0;

    private const ANCHO_DNI = 15.0;

    private const ANCHO_NOM = 78.0;

    private const ANCHO_NOTA = 20.0;

    private const ANCHO_PERM = 10.0;

    /** Mínimo histórico (legacy −10 %); el alto real se calcula para llenar la hoja. */
    private const ALTURA_FILA_MIN = 4.05;

    private const ALTURA_ENC_FILA1 = 4.5;

    private const ALTURA_ENC_SUB = 3.15;

    private const ALTURA_ENC_ROWSPAN = 7.65;

    private const MARGEN_INF = 10.0;

    /**
     * Desde el fin de la grilla hasta el fin del pie (nota + firmas + totales), en mm.
     * Debe coincidir con los offsets de dibujarActa() tras la tabla.
     */
    private const ALTURA_BLOQUE_POST_TABLA = 46.0;

    /** Espacio entre el bloque de encabezado (meta) y la grilla de alumnos. */
    private const SEP_ENCABEZADO_GRILLA = 3.0;

    /** @var array{instiNombre: string, tituloCajaActa: string} */
    private array $meta;

    private int $filasPorActa;

    private int $paginasGeneradas = 0;

    /**
     * @param  array{instiNombre: string, tituloCajaActa: string}  $meta
     * @param  list<array{
     *     cursoLabel: string,
     *     materiaLabel: string,
     *     condicionLabel: string,
     *     filas: list<array{nro: int, dni: string, nombre: string}>
     * }>  $actas
     */
    public function __construct(array $meta, int $filasPorActa)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->meta = $meta;
        $this->filasPorActa = max(1, $filasPorActa);
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Acta volante de exámenes');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
    }

    /**
     * @param  array{instiNombre: string, tituloCajaActa: string}  $meta
     * @param  list<array{
     *     cursoLabel: string,
     *     materiaLabel: string,
     *     condicionLabel: string,
     *     filas: list<array{nro: int, dni: string, nombre: string}>
     * }>  $actas
     */
    public static function generar(array $actas, array $meta, int $filasPorActa): self
    {
        $pdf = new self($meta, $filasPorActa);

        foreach ($actas as $acta) {
            $pdf->AddPage();
            $pdf->dibujarActa($acta);
            $pdf->paginasGeneradas++;
        }

        return $pdf;
    }

    public function paginasGeneradas(): int
    {
        return $this->paginasGeneradas;
    }

    /**
     * @param  array{
     *     cursoLabel: string,
     *     materiaLabel: string,
     *     condicionLabel: string,
     *     filas: list<array{nro: int, dni: string, nombre: string}>
     * }  $acta
     */
    private function dibujarActa(array $acta): void
    {
        $x0 = self::MARGEN_IZQ;
        $y = self::MARGEN_SUP;

        if (trim($this->meta['instiNombre']) !== '') {
            $this->SetXY($x0, $y);
            $this->SetFont(self::FUENTE, 'B', 12);
            $this->Cell(self::ANCHO_UTIL, 6, $this->meta['instiNombre'], 0, 1, 'C');
            $y = $this->GetY() + 2;
        }

        $anchoIzq = self::ANCHO_UTIL * 0.65;
        $anchoDer = self::ANCHO_UTIL * 0.35;
        $xDer = $x0 + $anchoIzq;

        $this->SetFont(self::FUENTE, '', 10);
        $this->SetXY($x0, $y);
        $this->Cell($anchoIzq, 4.5, 'Alumnos condición: '.($acta['condicionLabel'] ?? ''), 0, 2, 'L');
        $this->SetFont(self::FUENTE, 'B', 10);
        $this->Cell($anchoIzq, 4.5, 'Materia: '.($acta['materiaLabel'] ?? ''), 0, 2, 'L');
        $this->Cell($anchoIzq, 4.5, 'Curso: '.($acta['cursoLabel'] ?? ''), 0, 0, 'L');

        $tituloCaja = $this->meta['tituloCajaActa'] ?? 'Acta Volante de Exámenes';
        $this->SetFont(self::FUENTE, 'I', 10);
        $tw = $this->GetStringWidth($tituloCaja, self::FUENTE, 'I', 10) + 5;
        $th = 7.0;
        $rx = $xDer + $anchoDer - $tw;
        $ry = $y;
        $this->RoundedRect($rx, $ry, $tw, $th, 1.5, '1111', 'D');
        $this->SetXY($rx + 2.5, $ry + 1.8);
        $this->Cell($tw - 5, 3.5, $tituloCaja, 0, 0, 'C');

        $this->SetFont(self::FUENTE, '', 9);
        $this->SetXY($xDer, $ry + $th + 2);
        $this->Cell($anchoDer, 4, 'Fecha: ........../.........../..........', 0, 2, 'L');
        $this->Cell($anchoDer, 4, 'Tomo: ...........   Folio: ...........', 0, 0, 'L');

        $yFinEncabezado = max(
            (float) $this->GetY(),
            $ry + $th + 2 + 8.0,
            $y + 13.5,
        );
        $y = $yFinEncabezado + self::SEP_ENCABEZADO_GRILLA;

        $filasAlumnos = $acta['filas'] ?? [];
        $y = $this->dibujarTablaActa($x0, $y, $filasAlumnos);

        $this->SetFont(self::FUENTE, '', 6);
        $this->SetXY($x0, $y + 2);
        $this->Cell(self::ANCHO_UTIL, 3, 'A continuación del último alumno deberá firmar el secretario', 0, 1, 'C');

        $yFirmas = $this->GetY() + 15;
        $this->SetFont(self::FUENTE, '', 8);
        $this->SetXY($x0, $yFirmas);
        $this->Cell(self::ANCHO_UTIL * 0.36, 5, 'Presidente: .......................................', 0, 0, 'L');
        $this->Cell(self::ANCHO_UTIL * 0.32, 5, 'Vocal: .......................................', 0, 0, 'L');
        $this->Cell(self::ANCHO_UTIL * 0.32, 5, 'Vocal: .......................................', 0, 1, 'L');

        $yPie = $yFirmas + 10;
        $this->SetFont(self::FUENTE, '', 8);
        $this->SetXY($x0, $yPie);
        $this->Cell(self::ANCHO_UTIL * 0.58, 4, '......................   de   ................................   de   20...................', 0, 0, 'L');
        $this->MultiCell(
            self::ANCHO_UTIL * 0.42,
            4,
            "Total de Alumnos: ......................\n"
            ."Aprobados: ......................\n"
            ."Aplazados: ......................\n"
            .'Ausentes: .......................',
            0,
            'L',
            false,
            1,
            $x0 + self::ANCHO_UTIL * 0.58,
            $yPie,
            true,
        );
    }

    /**
     * @param  list<array{nro: int, dni: string, nombre: string}>  $filasAlumnos
     */
    private function dibujarTablaActa(float $x, float $y, array $filasAlumnos): float
    {
        $yEnc = $this->dibujarEncabezadoTabla($x, $y);
        $alturaFila = $this->alturaFilaCuerpo($yEnc);
        $y = $yEnc;

        for ($n = 1; $n <= $this->filasPorActa; $n++) {
            $fila = $filasAlumnos[$n - 1] ?? null;
            $dni = isset($fila) && ($fila['dni'] ?? '') !== '' ? (string) $fila['dni'] : self::BLANK;
            $nombre = isset($fila) ? (string) ($fila['nombre'] ?? '') : self::BLANK;

            $this->filaDatos($x, $y, $alturaFila, (string) $n, $dni, $nombre);
            $y += $alturaFila;
        }

        return $y;
    }

    /** Reparte el espacio libre de la hoja A4 entre las filas de alumnos. */
    private function alturaFilaCuerpo(float $yInicioFilas): float
    {
        $yFinTablaMax = $this->getPageHeight() - self::MARGEN_INF - self::ALTURA_BLOQUE_POST_TABLA;
        $disponible = $yFinTablaMax - $yInicioFilas;
        if ($disponible <= 0) {
            return self::ALTURA_FILA_MIN;
        }

        return max(self::ALTURA_FILA_MIN, $disponible / $this->filasPorActa);
    }

    private function dibujarEncabezadoTabla(float $x, float $y): float
    {
        $wNro = self::ANCHO_NRO;
        $wDni = self::ANCHO_DNI;
        $wNom = self::ANCHO_NOM;
        $wNota = self::ANCHO_NOTA;
        $wPerm = self::ANCHO_PERM;
        $wCalif = $wNota * 3;

        $xNro = $x;
        $xDni = $xNro + $wNro;
        $xNom = $xDni + $wDni;
        $xCalif = $xNom + $wNom;
        $xPerm = $xCalif + $wCalif;

        $this->celdaRect($xNro, $y, $wNro, self::ALTURA_ENC_ROWSPAN, 'Nº', 'C', 6, '');
        $this->celdaRect($xDni, $y, $wDni, self::ALTURA_ENC_ROWSPAN, 'D.N.I.', 'C', 6, '');
        $this->celdaRect($xNom, $y, $wNom, self::ALTURA_ENC_ROWSPAN, 'Apellido y Nombres', 'C', 6, '');
        $this->celdaRect($xCalif, $y, $wCalif, self::ALTURA_ENC_FILA1, 'Calificaciones', 'C', 6, '');
        $this->celdaRect($xPerm, $y, $wPerm, self::ALTURA_ENC_ROWSPAN, 'Nº Perm.', 'C', 6, '');

        $ySub = $y + self::ALTURA_ENC_FILA1;
        $this->celdaRect($xCalif, $ySub, $wNota, self::ALTURA_ENC_SUB, 'Escrito', 'C', 6, '');
        $this->celdaRect($xCalif + $wNota, $ySub, $wNota, self::ALTURA_ENC_SUB, 'Oral', 'C', 6, '');
        $this->celdaRect($xCalif + ($wNota * 2), $ySub, $wNota, self::ALTURA_ENC_SUB, 'Prom', 'C', 6, '');

        return $y + self::ALTURA_ENC_ROWSPAN;
    }

    private function filaDatos(float $x, float $y, float $alturaFila, string $nro, string $dni, string $nombre): void
    {
        $xCur = $x;
        $this->celdaRect($xCur, $y, self::ANCHO_NRO, $alturaFila, $nro, 'C', 6, '');
        $xCur += self::ANCHO_NRO;
        $this->celdaRect($xCur, $y, self::ANCHO_DNI, $alturaFila, $dni, 'C', 7, '');
        $xCur += self::ANCHO_DNI;
        $this->celdaRect($xCur, $y, self::ANCHO_NOM, $alturaFila, $this->truncarNombre($nombre), 'L', 8, '');
        $xCur += self::ANCHO_NOM;
        $this->celdaRect($xCur, $y, self::ANCHO_NOTA, $alturaFila, self::BLANK, 'C', 7, '');
        $xCur += self::ANCHO_NOTA;
        $this->celdaRect($xCur, $y, self::ANCHO_NOTA, $alturaFila, self::BLANK, 'C', 7, '');
        $xCur += self::ANCHO_NOTA;
        $this->celdaRect($xCur, $y, self::ANCHO_NOTA, $alturaFila, self::BLANK, 'C', 7, '');
        $xCur += self::ANCHO_NOTA;
        $this->celdaRect($xCur, $y, self::ANCHO_PERM, $alturaFila, self::BLANK, 'C', 7, '');
    }

    private function celdaRect(
        float $x,
        float $y,
        float $w,
        float $h,
        string $texto,
        string $align,
        float $fontSize,
        string $fontStyle,
    ): void {
        $this->Rect($x, $y, $w, $h);
        $this->SetFont(self::FUENTE, $fontStyle, $fontSize);
        $padX = $align === 'L' ? 2.0 : 0.5;
        $this->SetXY($x + $padX, $y + ($h - 3.2) / 2);
        $this->Cell($w - ($padX * 2), 3.2, $texto, 0, 0, $align, false, '', 0, false, 'T', 'M');
    }

    private function truncarNombre(string $nombre): string
    {
        if ($nombre === self::BLANK || $nombre === '') {
            return self::BLANK;
        }

        $this->SetFont(self::FUENTE, '', 8);
        $max = self::ANCHO_NOM - 4;
        if ($this->GetStringWidth($nombre, self::FUENTE, '', 8) <= $max) {
            return $nombre;
        }

        $len = mb_strlen($nombre);
        for ($i = $len; $i > 0; $i--) {
            $candidato = mb_substr($nombre, 0, $i).'…';
            if ($this->GetStringWidth($candidato, self::FUENTE, '', 8) <= $max) {
                return $candidato;
            }
        }

        return mb_substr($nombre, 0, 1).'…';
    }
}
