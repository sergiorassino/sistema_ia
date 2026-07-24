<?php

namespace App\Support\CalificacionesSecundario;

use App\Support\Pdf\TcpdfImagenPng;
use App\Support\PlanillaCalificacionesSecundario;
use App\Support\PromedioAnualCalificacionesSecundario;
use TCPDF;

/**
 * Planilla de calificaciones (secundario) — TCPDF, réplica de `pdf/planilla-calificaciones*.blade.php`.
 */
final class PlanillaCalificacionesTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 7.0;

    private const MARGEN_DER = 7.0;

    private const MARGEN_SUP = 10.0;

    private const MARGEN_INF = 8.0;

    private const FUENTE = 'dejavusans';

    private const BLANK = "\u{00A0}";

    /** 0,75 pt → mm */
    private const GROSOR_BORDE = 0.27;

    /** 1,5 pt → mm */
    private const RADIO_CELDA = 0.53;

    /** border-spacing horizontal 2px */
    private const SEP_COL_MM = 0.53;

    private const COLOR_BORDE = [51, 51, 51];

    private const COLOR_GRIS_DESAP = [184, 184, 184];

    private const ALTURA_ENCABEZADO_INST = 22.0;

    private const ALTURA_TABLA_ENC = 7.2;

    private const ALTURA_PIE_FIRMAS = 18.0;

    private const MARGEN_PIE_DESDE_TABLA = 12.0;

    /** @var array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string} */
    private array $pdfHeader;

    private string $cursoLabel;

    private ?int $ano;

    /** @var list<array{materiaLabel: string, profesoresLinea: string, filas: list<array<string, mixed>>, layoutFilas: array<string, float|int>}> */
    private array $secciones;

    /**
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $pdfHeader
     * @param  list<array{materiaLabel: string, profesoresLinea: string, filas: list<array<string, mixed>>, layoutFilas: array<string, float|int>}>  $secciones
     */
    private function __construct(array $pdfHeader, string $cursoLabel, ?int $ano, array $secciones)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdfHeader = $pdfHeader;
        $this->cursoLabel = $cursoLabel;
        $this->ano = $ano;
        $this->secciones = $secciones;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Planilla de calificaciones');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
    }

    /**
     * @param  array{
     *     pdfHeader: array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string},
     *     ano: int|null,
     *     cursoLabel: string,
     *     secciones: list<array{
     *         materiaLabel: string,
     *         profesoresLinea: string,
     *         filas: list<array<string, mixed>>,
     *         layoutFilas: array<string, float|int>
     *     }>
     * }  $payload
     */
    public static function generar(array $payload): self
    {
        $pdf = new self(
            $payload['pdfHeader'],
            (string) ($payload['cursoLabel'] ?? ''),
            isset($payload['ano']) ? (int) $payload['ano'] : null,
            $payload['secciones'] ?? [],
        );

        foreach ($pdf->secciones as $idx => $sec) {
            $pdf->AddPage();
            $pdf->dibujarSeccion($sec, $idx === 0);
        }

        return $pdf;
    }

    private function anchoUtil(): float
    {
        return $this->getPageWidth() - self::MARGEN_IZQ - self::MARGEN_DER;
    }

    /**
     * @return array{
     *     ord: float, ec: float, eval: list<float>, jis: list<float>, dic: float, feb: float, prom: float
     * }
     */
    /** Ensanche fijo en mm aplicado a la columna del estudiante (aprovecha el
     * margen libre que dejó la reducción del 10 % en las celdas de notas). */
    private const ENSANCHE_EC_MM = 5.0;

    private function anchosColumnasMm(): array
    {
        $pct = PlanillaCalificacionesSecundario::anchosColumnasPorcentaje();
        $total = $this->anchoUtil();

        return [
            'ord' => $total * $pct['ord'] / 100,
            'ec' => ($total * $pct['ec'] / 100) + self::ENSANCHE_EC_MM,
            'eval' => array_map(fn (float $p) => $total * $p / 100, $pct['eval']),
            'jis' => array_map(fn (float $p) => $total * $p / 100, $pct['jis']),
            'dic' => $total * $pct['dic'] / 100,
            'feb' => $total * $pct['feb'] / 100,
            'prom' => $total * $pct['prom'] / 100,
        ];
    }

    /**
     * @param  array{materiaLabel: string, profesoresLinea: string, filas: list<array<string, mixed>>, layoutFilas: array<string, float|int>}  $sec
     */
    private function dibujarSeccion(array $sec, bool $mostrarEncabezadoInst): void
    {
        $y = self::MARGEN_SUP;

        if ($mostrarEncabezadoInst) {
            $y = $this->dibujarEncabezadoInstitucional($y);
            $y = $this->dibujarTituloDocumento($y);
        }

        $y = $this->dibujarMetaMateria($y, $sec);
        $y = $this->dibujarGrilla($y, $sec);
        $this->dibujarPieFirmas($y);
    }

    private function dibujarEncabezadoInstitucional(float $y): float
    {
        $x = self::MARGEN_IZQ;
        $w = $this->anchoUtil();
        $h = self::ALTURA_ENCABEZADO_INST;

        $this->SetDrawColor(...self::COLOR_BORDE);
        $this->SetLineWidth(self::GROSOR_BORDE);
        $this->RoundedRect($x, $y, $w, $h, 2.8, '1111', 'D');

        $logo = $this->pdfHeader['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), $x + 3, $y + 2, 17, 17, '', '', '', false, 300);
        }

        $insti = trim($this->pdfHeader['insti'] ?? '');
        if ($insti === '') {
            $insti = 'Institución';
        }

        $direccion = trim($this->pdfHeader['direccion'] ?? '');
        $localidad = trim($this->pdfHeader['localidad'] ?? '');
        $lineaDir = trim($direccion.($direccion !== '' && $localidad !== '' ? ' — ' : '').$localidad);

        $cue = trim($this->pdfHeader['cue'] ?? '');
        $ee = trim($this->pdfHeader['ee'] ?? '');
        $lineaIds = trim(($cue !== '' ? "CUE: {$cue}" : '').(($cue !== '' && $ee !== '') ? '   ' : '').($ee !== '' ? "EE: {$ee}" : ''));

        $this->SetXY($x, $y + 4);
        $this->SetFont(self::FUENTE, 'B', 12);
        $this->Cell($w, 5, $insti, 0, 2, 'C');

        if ($lineaDir !== '') {
            $this->SetFont(self::FUENTE, '', 9);
            $this->Cell($w, 4, $lineaDir, 0, 2, 'C');
        }

        if ($lineaIds !== '') {
            $this->SetFont(self::FUENTE, '', 6.5);
            $this->Cell($w, 3.5, $lineaIds, 0, 2, 'C');
        }

        return $y + $h + 3.5;
    }

    private function dibujarTituloDocumento(float $y): float
    {
        $x = self::MARGEN_IZQ;
        $w = $this->anchoUtil();

        $this->SetXY($x, $y);
        $this->SetFont(self::FUENTE, 'B', 10);
        $this->Cell($w, 5, 'PLANILLA DE CALIFICACIONES', 0, 2, 'C');

        if ($this->ano !== null && $this->ano > 0) {
            $this->SetFont(self::FUENTE, '', 6.5);
            $this->Cell($w, 4, 'Ciclo lectivo '.$this->ano, 0, 2, 'C');
        }

        return $this->GetY() + 1.5;
    }

    /**
     * @param  array{materiaLabel: string, profesoresLinea: string}  $sec
     */
    private function dibujarMetaMateria(float $y, array $sec): float
    {
        $x = self::MARGEN_IZQ;
        $w = $this->anchoUtil();

        $materia = mb_strtoupper(trim((string) ($sec['materiaLabel'] ?? '')));
        $this->SetXY($x, $y);
        $this->SetFont(self::FUENTE, 'B', 8);
        $linea = $materia;
        if (trim($this->cursoLabel) !== '') {
            $this->SetFont(self::FUENTE, 'B', 6.5);
            $linea .= '   ·   '.$this->cursoLabel;
        }
        $this->SetFont(self::FUENTE, 'B', 8);
        $this->Cell($w, 4.5, $linea, 0, 2, 'L');

        $prof = trim((string) ($sec['profesoresLinea'] ?? ''));
        if ($prof !== '' && $prof !== '—') {
            $this->SetFont(self::FUENTE, '', 6.5);
            $this->Cell($w, 4, 'Prof: '.$prof, 0, 2, 'L');
        }

        return $this->GetY() + 1.5;
    }

    /**
     * @param  array{materiaLabel: string, filas: list<array<string, mixed>>, layoutFilas: array<string, float|int>}  $sec
     */
    private function dibujarGrilla(float $y, array $sec): float
    {
        $filas = array_values($sec['filas'] ?? []);
        $layout = $sec['layoutFilas'] ?? PlanillaCalificacionesSecundario::metricasLayoutFilas(count($filas));
        $anchos = $this->anchosColumnasMm();

        $yPie = $this->getPageHeight() - self::MARGEN_INF - self::ALTURA_PIE_FIRMAS - self::MARGEN_PIE_DESDE_TABLA;
        $gapV = ((float) ($layout['espacioFilasPx'] ?? 0.94)) * 0.352778;
        // Se usa un target mínimo de filas por hoja: si el curso tiene menos
        // alumnos, las filas se calculan como si hubiese FILAS_OBJETIVO_PDF
        // (filas compactas y aire al pie); si tiene más, se sigue compactando
        // dinámicamente para que entren todos en una sola hoja.
        $numFilas = max(PlanillaCalificacionesSecundario::FILAS_OBJETIVO_PDF, count($filas));
        $gapsTotal = max(0, $numFilas - 1) * $gapV;
        $disponible = $yPie - $y - self::ALTURA_TABLA_ENC;
        $hFila = count($filas) > 0
            ? max(3.4, ($disponible - $gapsTotal) / $numFilas)
            : 4.5;

        $y = $this->dibujarEncabezadoGrilla($y, $anchos, $layout);
        $x0 = self::MARGEN_IZQ;

        if ($filas === []) {
            $this->dibujarCeldaBay($x0, $y, $this->anchoUtil(), $hFila, 'Sin estudiantes con calificaciones para esta materia.', 'C', 6.5, false, false, 6.5);

            return $y + $hFila;
        }

        foreach ($filas as $idx => $fila) {
            $x = $x0;
            $this->dibujarCeldaOrd($x, $y, $anchos['ord'], $hFila, (string) ($idx + 1), $layout);
            $x += $anchos['ord'] + self::SEP_COL_MM;

            $this->dibujarCeldaNombre($x, $y, $anchos['ec'], $hFila, (string) ($fila['alumno'] ?? ''), $layout);
            $x += $anchos['ec'] + self::SEP_COL_MM;

            for ($e = 1; $e <= 8; $e++) {
                $b = ($e - 1) * 3 + 1;
                $campos = [
                    sprintf('ic%02d', $b),
                    sprintf('ic%02d', $b + 1),
                    sprintf('ic%02d', $b + 2),
                ];
                $desap = PromedioAnualCalificacionesSecundario::bloqueDesaprobado($campos, $fila);
                $this->dibujarCeldaEval($x, $y, $anchos['eval'][$e - 1], $hFila, $fila, $campos, $layout, $desap);
                $x += $anchos['eval'][$e - 1] + self::SEP_COL_MM;
            }

            $desapJ1 = PromedioAnualCalificacionesSecundario::bloqueDesaprobado(['ic25', 'ic26'], $fila);
            $this->dibujarCeldaJis($x, $y, $anchos['jis'][0], $hFila, $fila, ['ic25', 'ic26'], $layout, $desapJ1);
            $x += $anchos['jis'][0] + self::SEP_COL_MM;

            $desapJ2 = PromedioAnualCalificacionesSecundario::bloqueDesaprobado(['ic27', 'ic28'], $fila);
            $this->dibujarCeldaJis($x, $y, $anchos['jis'][1], $hFila, $fila, ['ic27', 'ic28'], $layout, $desapJ2);
            $x += $anchos['jis'][1] + self::SEP_COL_MM;

            $this->dibujarCeldaNotaSimple($x, $y, $anchos['dic'], $hFila, $this->valorCelda($fila, 'dic'), $layout, false);
            $x += $anchos['dic'] + self::SEP_COL_MM;
            $this->dibujarCeldaNotaSimple($x, $y, $anchos['feb'], $hFila, $this->valorCelda($fila, 'feb'), $layout, false);
            $x += $anchos['feb'] + self::SEP_COL_MM;
            $this->dibujarCeldaNotaSimple($x, $y, $anchos['prom'], $hFila, $this->valorProm($fila), $layout, true);

            $y += $hFila + $gapV;
        }

        return $y - $gapV;
    }

    /**
     * @param  array{ord: float, ec: float, eval: list<float>, jis: list<float>, dic: float, feb: float, prom: float}  $anchos
     * @param  array<string, float|int>  $layout
     */
    private function dibujarEncabezadoGrilla(float $y, array $anchos, array $layout): float
    {
        $h = self::ALTURA_TABLA_ENC;
        $h1 = $h * 0.48;
        $h2 = $h - $h1;
        $x0 = self::MARGEN_IZQ;
        $x = $x0;

        $fontEnc = 5.0;
        $fontSub = 4.8;

        $this->dibujarCeldaBay($x, $y, $anchos['ord'], $h, 'Nº', 'C', $fontEnc, true, false, $fontEnc);
        $x += $anchos['ord'] + self::SEP_COL_MM;
        $this->dibujarCeldaBay($x, $y, $anchos['ec'], $h, 'Estudiante', 'C', 5.5, true, false, 5.5);
        $x += $anchos['ec'] + self::SEP_COL_MM;

        for ($e = 1; $e <= 8; $e++) {
            $w = $anchos['eval'][$e - 1];
            $this->dibujarEncabezadoColumnaEval($x, $y, $w, $h, $h1, $h2, $e, $fontEnc, $fontSub);
            $x += $w + self::SEP_COL_MM;
        }

        $this->dibujarEncabezadoColumnaJis($x, $y, $anchos['jis'][0], $h, $h1, $h2, 'JIS 1', $fontEnc, $fontSub);
        $x += $anchos['jis'][0] + self::SEP_COL_MM;

        $this->dibujarEncabezadoColumnaJis($x, $y, $anchos['jis'][1], $h, $h1, $h2, 'JIS 2', $fontEnc, $fontSub);
        $x += $anchos['jis'][1] + self::SEP_COL_MM;

        $this->dibujarCeldaBay($x, $y, $anchos['dic'], $h, "Coloq.\nDic", 'C', $fontEnc, true, false, $fontEnc);
        $x += $anchos['dic'] + self::SEP_COL_MM;
        $this->dibujarCeldaBay($x, $y, $anchos['feb'], $h, "Coloq.\nFeb", 'C', $fontEnc, true, false, $fontEnc);
        $x += $anchos['feb'] + self::SEP_COL_MM;
        $this->dibujarCeldaBay($x, $y, $anchos['prom'], $h, "Prom.\nFinal", 'C', $fontEnc, true, false, $fontEnc);

        return $y + $h;
    }

    private function dibujarEncabezadoColumnaEval(
        float $x,
        float $y,
        float $w,
        float $h,
        float $h1,
        float $h2,
        int $e,
        float $fontEnc,
        float $fontSub,
    ): void {
        $this->dibujarCeldaBay($x, $y, $w, $h, '', 'C', $fontEnc, true, false, $fontEnc);
        $this->lineaHorizontal($x, $y + $h1, $w);
        $this->textoEnCelda($x, $y, $w, $h1, 'Eval. '.$e, 'C', $fontEnc, true);
        $part = $w / 3;
        foreach (['N', 'R1', 'R2'] as $i => $label) {
            $sx = $x + $i * $part;
            if ($i > 0) {
                $this->lineaVertical($sx, $y + $h1, $h2);
            }
            $this->textoEnCelda($sx, $y + $h1, $part, $h2, $label, 'C', $fontSub, true);
        }
    }

    private function dibujarEncabezadoColumnaJis(
        float $x,
        float $y,
        float $w,
        float $h,
        float $h1,
        float $h2,
        string $titulo,
        float $fontEnc,
        float $fontSub,
    ): void {
        $this->dibujarCeldaBay($x, $y, $w, $h, '', 'C', $fontEnc, true, false, $fontEnc);
        $this->lineaHorizontal($x, $y + $h1, $w);
        $this->textoEnCelda($x, $y, $w, $h1, $titulo, 'C', $fontEnc, true);
        $part = $w / 2;
        foreach (['N', 'R'] as $i => $label) {
            $sx = $x + $i * $part;
            if ($i > 0) {
                $this->lineaVertical($sx, $y + $h1, $h2);
            }
            $this->textoEnCelda($sx, $y + $h1, $part, $h2, $label, 'C', $fontSub, true);
        }
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  list<string>  $campos
     * @param  array<string, float|int>  $layout
     */
    private function dibujarCeldaEval(float $x, float $y, float $w, float $h, array $fila, array $campos, array $layout, bool $desap): void
    {
        $this->dibujarCeldaBay($x, $y, $w, $h, '', 'C', (float) $layout['fontDataPt'], false, $desap, (float) $layout['fontDataPt']);
        $part = $w / 3;
        foreach ($campos as $i => $campo) {
            $sx = $x + $i * $part;
            if ($i > 0) {
                $this->lineaVertical($sx, $y, $h);
            }
            $this->textoEnCelda(
                $sx,
                $y,
                $part,
                $h,
                $this->valorCelda($fila, $campo),
                'C',
                (float) $layout['fontDataPt'],
                false,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  list<string>  $campos
     * @param  array<string, float|int>  $layout
     */
    private function dibujarCeldaJis(float $x, float $y, float $w, float $h, array $fila, array $campos, array $layout, bool $desap): void
    {
        $this->dibujarCeldaBay($x, $y, $w, $h, '', 'C', (float) $layout['fontDataPt'], false, $desap, (float) $layout['fontDataPt']);
        $part = $w / 2;
        foreach ($campos as $i => $campo) {
            $sx = $x + $i * $part;
            if ($i > 0) {
                $this->lineaVertical($sx, $y, $h);
            }
            $this->textoEnCelda(
                $sx,
                $y,
                $part,
                $h,
                $this->valorCelda($fila, $campo),
                'C',
                (float) $layout['fontDataPt'],
                false,
            );
        }
    }

    /**
     * @param  array<string, float|int>  $layout
     */
    private function dibujarCeldaNotaSimple(
        float $x,
        float $y,
        float $w,
        float $h,
        string $texto,
        array $layout,
        bool $bold,
    ): void {
        $this->dibujarCeldaBay(
            $x,
            $y,
            $w,
            $h,
            $texto,
            'C',
            (float) $layout['fontColPt'],
            $bold,
            false,
            (float) $layout['fontColPt'],
        );
    }

    /**
     * @param  array<string, float|int>  $layout
     */
    private function dibujarCeldaOrd(float $x, float $y, float $w, float $h, string $nro, array $layout): void
    {
        $this->dibujarCeldaBay($x, $y, $w, $h, $nro, 'C', (float) $layout['fontDataPt'], false, false, (float) $layout['fontDataPt']);
    }

    /**
     * @param  array<string, float|int>  $layout
     */
    private function dibujarCeldaNombre(float $x, float $y, float $w, float $h, string $nombre, array $layout): void
    {
        $texto = mb_strtoupper(trim($nombre));
        $this->dibujarCeldaBay($x, $y, $w, $h, '', 'L', (float) $layout['fontEcPt'], false, false, (float) $layout['fontEcPt']);
        $this->textoEnCelda($x, $y, $w, $h, $texto, 'L', (float) $layout['fontEcPt'], false, true);
    }

    private function dibujarCeldaBay(
        float $x,
        float $y,
        float $w,
        float $h,
        string $texto,
        string $align,
        float $fontPt,
        bool $bold,
        bool $fillGris,
        float $fontDataPt,
    ): void {
        $this->SetDrawColor(...self::COLOR_BORDE);
        $this->SetLineWidth(self::GROSOR_BORDE);
        if ($fillGris) {
            $this->SetFillColor(...self::COLOR_GRIS_DESAP);
            $this->RoundedRect($x, $y, $w, $h, self::RADIO_CELDA, '1111', 'DF');
        } else {
            $this->SetFillColor(255, 255, 255);
            $this->RoundedRect($x, $y, $w, $h, self::RADIO_CELDA, '1111', 'D');
        }

        if ($texto !== '') {
            $this->textoEnCelda($x, $y, $w, $h, $texto, $align, $fontDataPt, $bold, $align === 'L');
        }
    }

    private function textoEnCelda(
        float $x,
        float $y,
        float $w,
        float $h,
        string $texto,
        string $align,
        float $fontPt,
        bool $bold,
        bool $recortar = false,
    ): void {
        $style = $bold ? 'B' : '';
        $this->SetFont(self::FUENTE, $style, $fontPt);
        $this->SetTextColor(0, 0, 0);

        if ($recortar && $texto !== '') {
            while ($texto !== '' && $this->GetStringWidth($texto, self::FUENTE, $style, $fontPt) > $w - 1.2) {
                $texto = mb_substr($texto, 0, -1);
            }
        }

        if (str_contains($texto, "\n")) {
            $this->SetXY($x, $y + 0.4);
            $this->MultiCell($w, max(2.5, $h / 2 - 0.2), $texto, 0, $align, false, 1, '', '', true, 0, false, true, $h, 'M');
        } else {
            $this->SetXY($x, $y);
            $this->Cell($w, $h, $texto, 0, 0, $align, false, '', 0, false, 'T', 'M');
        }
    }

    private function lineaVertical(float $x, float $y, float $h): void
    {
        $this->SetDrawColor(...self::COLOR_BORDE);
        $this->SetLineWidth(0.15);
        $this->Line($x, $y, $x, $y + $h);
    }

    private function lineaHorizontal(float $x, float $y, float $w): void
    {
        $this->SetDrawColor(...self::COLOR_BORDE);
        $this->SetLineWidth(0.19);
        $this->Line($x, $y, $x + $w, $y);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function valorCelda(array $fila, string $col): string
    {
        $s = trim((string) ($fila[$col] ?? ''));

        return $s === '' ? self::BLANK : $s;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function valorProm(array $fila): string
    {
        $s = trim((string) ($fila['prom'] ?? ''));
        if ($s === '') {
            return self::BLANK;
        }
        $n = str_replace(',', '.', $s);
        if (is_numeric($n)) {
            return number_format((float) $n, 2, ',', '');
        }

        return $s;
    }

    private function dibujarPieFirmas(float $yTablaFin): void
    {
        $y = max($yTablaFin + self::MARGEN_PIE_DESDE_TABLA, $this->getPageHeight() - self::MARGEN_INF - self::ALTURA_PIE_FIRMAS);
        $wFirma = 65.0;
        $gutter = 20.0;
        $ancho = $this->anchoUtil();
        $espacio = $ancho - 2 * $gutter - 2 * $wFirma;
        $xIzq = self::MARGEN_IZQ + $gutter;
        $xDer = self::MARGEN_IZQ + $gutter + $wFirma + max(5.0, $espacio);

        $this->dibujarBloqueFirma($xIzq, $y, $wFirma, 'Firma Preceptor/a');
        $this->dibujarBloqueFirma($xDer, $y, $wFirma, 'Firma Director/a');
    }

    private function dibujarBloqueFirma(float $x, float $y, float $w, string $label): void
    {
        $this->SetDrawColor(...self::COLOR_BORDE);
        $this->SetLineWidth(0.19);
        $this->SetLineStyle(['dash' => '1,1']);
        $this->Line($x, $y + 6, $x + $w, $y + 6);
        $this->SetLineStyle(['dash' => 0]);

        $this->SetFont(self::FUENTE, '', 6);
        $this->SetXY($x, $y + 6.5);
        $this->Cell($w, 4, $label, 0, 0, 'C');
    }
}
