<?php

namespace App\Support\CalificacionesSecundario;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use App\Support\Pdf\TcpdfMultiCellJustificado;
use App\Support\PlanillaResumenCalificacionesSecundario;
use TCPDF;

/**
 * Planilla resumen de calificaciones (secundario) — TCPDF.
 * Réplica visual de `pdf/planilla-resumen-calificaciones*.blade.php` (A4 landscape).
 */
final class PlanillaResumenCalificacionesTcpdf extends TCPDF
{
    /** @page DomPDF: margin 8mm 5mm 6mm 5mm */
    private const MARGEN_SUP = 8.0;

    private const MARGEN_DER = 5.0;

    private const MARGEN_INF = 6.0;

    private const MARGEN_IZQ = 5.0;

    private const PCT_ORD = 2.2;

    private const PCT_NOM = 17.5;

    /** 0,4 pt compartido entre celdas adyacentes ≈ 0,08 mm por lado */
    private const GROSOR_BORDE = 0.08;

    /** 1,2 pt → mm (separador entre alumnos) */
    private const GROSOR_SEP = 0.42;

    private const COLOR_BORDE = [51, 51, 51];

    private const COLOR_GRIS = [184, 184, 184];

    private const COLOR_ROJO = [204, 0, 0];

    private const ALTURA_ENCABEZADO_INST = 18.0;

    private const ANCHO_LOGO = 14.0;

    private const LEYENDA = 'Líneas 1 y 2: módulos 1 a 8. Línea 3: JIS 1, JIS 2 y promedio anual. Línea 4: coloquios dic. y feb. Fondo gris: módulo aprobado con recuperatorio. Texto rojo: mejor nota inferior a 7. Línea 5: Nº Rep. (materias con al menos un módulo 1-8 con mejor nota inferior a 7), Inas., Amon., Ed.Fi. (inas. a educación física), Prom.Gral. (solo si hay promedio en todas las materias), Previas.';

    /** @var array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string} */
    private array $pdfHeader;

    private ?int $ano;

    /**
     * @var list<array{
     *     cursoLabel: string,
     *     materias: list<array{id: int, abrev: string}>,
     *     estudiantes: list<array<string, mixed>>,
     *     layout: array{cantidad: int, fontPt: float, paddingPx: float, lineHeight: float}
     * }>
     */
    private array $secciones;

    /**
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $pdfHeader
     * @param  list<array{
     *     cursoLabel: string,
     *     materias: list<array{id: int, abrev: string}>,
     *     estudiantes: list<array<string, mixed>>,
     *     layout?: array{cantidad: int, fontPt: float, paddingPx: float, lineHeight: float}
     * }>  $secciones
     */
    private function __construct(array $pdfHeader, ?int $ano, array $secciones)
    {
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdfHeader = $pdfHeader;
        $this->ano = $ano;
        $this->secciones = $secciones;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Planilla resumen de calificaciones');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
    }

    /**
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $pdfHeader
     * @param  list<array{
     *     cursoLabel: string,
     *     ano?: int|null,
     *     materias: list<array{id: int, abrev: string}>,
     *     estudiantes: list<array<string, mixed>>,
     *     layout?: array{cantidad: int, fontPt: float, paddingPx: float, lineHeight: float}
     * }>  $secciones
     */
    public static function generar(array $pdfHeader, ?int $ano, array $secciones): self
    {
        $pdf = new self($pdfHeader, $ano, $secciones);

        foreach ($pdf->secciones as $sec) {
            $pdf->AddPage();
            $pdf->dibujarCurso($sec);
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

    private function anchoUtil(): float
    {
        return $this->getPageWidth() - self::MARGEN_IZQ - self::MARGEN_DER;
    }

    /**
     * @param  array{
     *     cursoLabel: string,
     *     materias: list<array{id: int, abrev: string}>,
     *     estudiantes: list<array<string, mixed>>,
     *     layout?: array{cantidad: int, fontPt: float, paddingPx: float, lineHeight: float}
     * }  $sec
     */
    private function dibujarCurso(array $sec): void
    {
        $materias = array_values($sec['materias'] ?? []);
        $estudiantes = array_values($sec['estudiantes'] ?? []);
        $layout = $sec['layout'] ?? PlanillaResumenCalificacionesSecundario::metricasLayout(count($estudiantes));
        $fontPt = (float) ($layout['fontPt'] ?? 4.5);
        $cursoLabel = trim((string) ($sec['cursoLabel'] ?? ''));

        $anchos = $this->anchosColumnasMm(count($materias));
        $hFila = $this->alturaFilaMm($fontPt, (float) ($layout['lineHeight'] ?? 1.2));

        $y = self::MARGEN_SUP;
        $y = $this->dibujarEncabezadoInstitucional($y);
        $y = $this->dibujarTitulosYLeyenda($y, $cursoLabel);
        $y = $this->dibujarEncabezadoTabla($y, $materias, $anchos, $fontPt);

        if ($estudiantes === []) {
            $this->celdaBorde(
                self::MARGEN_IZQ,
                $y,
                $this->anchoUtil(),
                max(6.0, $hFila * 2),
                'Sin estudiantes regulares en este curso.',
                'C',
                $fontPt + 0.5,
                false,
                false,
                false,
                'LTRB'
            );

            return;
        }

        $primeroEnPagina = true;
        $yLimite = $this->getPageHeight() - self::MARGEN_INF;

        foreach ($estudiantes as $est) {
            $hBloque = 5 * $hFila;
            $hSep = $primeroEnPagina ? 0.0 : (self::GROSOR_SEP + 0.3);

            if ($y + $hSep + $hBloque > $yLimite) {
                $this->AddPage();
                $y = $this->dibujarContinuacionCurso(self::MARGEN_SUP, $cursoLabel);
                $y = $this->dibujarEncabezadoTabla($y, $materias, $anchos, $fontPt);
                $primeroEnPagina = true;
                $hSep = 0.0;
            }

            if (! $primeroEnPagina) {
                $y = $this->dibujarSeparadorAlumno($y, $anchos);
            }

            $y = $this->dibujarBloqueAlumno($y, $est, $materias, $anchos, $fontPt, $hFila);
            $primeroEnPagina = false;
        }
    }

    /**
     * @return array{ord: float, nom: float, sub: float, mat: float, par: float, pie: float, colsMateria: int}
     */
    private function anchosColumnasMm(int $nMat): array
    {
        $total = $this->anchoUtil();
        $wOrd = $total * self::PCT_ORD / 100;
        $wNom = $total * self::PCT_NOM / 100;
        $colsMateria = max(1, $nMat) * 4;
        $resto = $total - $wOrd - $wNom;
        $wSub = $resto / max(1, $colsMateria);

        return [
            'ord' => $wOrd,
            'nom' => $wNom,
            'sub' => $wSub,
            'mat' => $wSub * 4,
            'par' => $wSub * 2,
            'pie' => $resto,
            'colsMateria' => $colsMateria,
        ];
    }

    private function alturaFilaMm(float $fontPt, float $lineHeight): float
    {
        // Equivale al padding + line-height del Blade DomPDF.
        return max(2.55, $fontPt * 0.42 * max(1.0, $lineHeight) + 0.55);
    }

    private function dibujarEncabezadoInstitucional(float $y): float
    {
        $x = self::MARGEN_IZQ;
        $w = $this->anchoUtil();
        $h = self::ALTURA_ENCABEZADO_INST;

        $this->SetDrawColor(17, 17, 17);
        $this->SetLineWidth(0.26);
        $this->RoundedRect($x, $y, $w, $h, 2.2, '1111', 'D');

        $logo = $this->pdfHeader['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), $x + 2.5, $y + 2, self::ANCHO_LOGO, self::ANCHO_LOGO, '', '', '', false, 300);
        }

        $insti = trim((string) ($this->pdfHeader['insti'] ?? ''));
        if ($insti === '') {
            $insti = 'Institución';
        }
        $direccion = trim((string) ($this->pdfHeader['direccion'] ?? ''));
        $localidad = trim((string) ($this->pdfHeader['localidad'] ?? ''));
        $lineaDir = trim($direccion.($direccion !== '' && $localidad !== '' ? ' — ' : '').$localidad);
        $cue = trim((string) ($this->pdfHeader['cue'] ?? ''));
        $ee = trim((string) ($this->pdfHeader['ee'] ?? ''));
        $lineaIds = trim(($cue !== '' ? 'CUE: '.$cue : '').(($cue !== '' && $ee !== '') ? '   ' : '').($ee !== '' ? 'EE: '.$ee : ''));

        $this->SetTextColor(17, 17, 17);
        $this->SetXY($x, $y + 3);
        TcpdfFuenteArial::aplicar($this, 'B', 11);
        $this->Cell($w, 4.5, $insti, 0, 2, 'C');
        if ($lineaDir !== '') {
            TcpdfFuenteArial::aplicar($this, '', 8);
            $this->Cell($w, 3.5, $lineaDir, 0, 2, 'C');
        }
        if ($lineaIds !== '') {
            TcpdfFuenteArial::aplicar($this, '', 6);
            $this->Cell($w, 3, $lineaIds, 0, 2, 'C');
        }

        return $y + $h + 2.5;
    }

    private function dibujarTitulosYLeyenda(float $y, string $cursoLabel): float
    {
        $x = self::MARGEN_IZQ;
        $w = $this->anchoUtil();
        $this->SetTextColor(0, 0, 0);

        $titulo = 'PLANILLA RESUMEN';
        if ($this->ano !== null && $this->ano > 0) {
            $titulo .= ' — '.$this->ano;
        }
        $titulo .= ' — CALIFICACIONES PARCIALES';

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell($w, 4, $titulo, 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell($w, 3.2, 'Ambas etapas · Mejor nota por módulo (incluye recuperatorios)', 0, 2, 'C');

        if ($cursoLabel !== '') {
            TcpdfFuenteArial::aplicar($this, 'B', 7);
            $this->Cell($w, 3.5, mb_strtoupper($cursoLabel, 'UTF-8'), 0, 2, 'C');
        }

        $yLey = $this->GetY() + 0.8;
        $this->SetXY($x, $yLey);
        TcpdfFuenteArial::aplicar($this, '', 4.6);
        TcpdfMultiCellJustificado::escribir($this, $w, 2.4, self::LEYENDA);

        return $this->GetY() + 1.5;
    }

    private function dibujarContinuacionCurso(float $y, string $cursoLabel): float
    {
        $x = self::MARGEN_IZQ;
        $w = $this->anchoUtil();
        $this->SetTextColor(0, 0, 0);
        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 7);
        $txt = 'PLANILLA RESUMEN';
        if ($cursoLabel !== '') {
            $txt .= ' — '.mb_strtoupper($cursoLabel, 'UTF-8');
        }
        if ($this->ano !== null && $this->ano > 0) {
            $txt .= ' — '.$this->ano;
        }
        $this->Cell($w, 3.5, $txt, 0, 2, 'C');

        return $this->GetY() + 1.0;
    }

    /**
     * @param  list<array{id: int, abrev: string}>  $materias
     * @param  array{ord: float, nom: float, sub: float, mat: float, par: float, pie: float, colsMateria: int}  $anchos
     */
    private function dibujarEncabezadoTabla(float $y, array $materias, array $anchos, float $fontPt): float
    {
        $h = max(4.2, $fontPt + 1.2);
        $x = self::MARGEN_IZQ;
        $fontEnc = max(4.2, min(5.0, $fontPt + 0.3));

        $this->celdaBorde($x, $y, $anchos['ord'], $h, 'Nº', 'C', $fontEnc, true, false, false, 'LTRB');
        $x += $anchos['ord'];
        $this->celdaBorde($x, $y, $anchos['nom'], $h, 'Estudiante', 'C', $fontEnc, true, false, false, 'LTRB');
        $x += $anchos['nom'];

        foreach ($materias as $m) {
            $abrev = trim((string) ($m['abrev'] ?? ''));
            if ($abrev === '') {
                $abrev = '—';
            }
            $this->celdaBorde($x, $y, $anchos['mat'], $h, $abrev, 'C', max(3.8, $fontEnc - 0.3), true, false, false, 'LTRB');
            $x += $anchos['mat'];
        }

        if ($materias === []) {
            $this->celdaBorde($x, $y, $anchos['pie'], $h, '—', 'C', $fontEnc, true, false, false, 'LTRB');
        }

        return $y + $h;
    }

    /**
     * @param  array{ord: float, nom: float, sub: float, mat: float, par: float, pie: float, colsMateria: int}  $anchos
     */
    private function dibujarSeparadorAlumno(float $y, array $anchos): float
    {
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(self::GROSOR_SEP);
        $x1 = self::MARGEN_IZQ;
        $x2 = $x1 + $anchos['ord'] + $anchos['nom'] + $anchos['pie'];
        $this->Line($x1, $y + self::GROSOR_SEP / 2, $x2, $y + self::GROSOR_SEP / 2);

        return $y + self::GROSOR_SEP + 0.15;
    }

    /**
     * @param  array<string, mixed>  $est
     * @param  list<array{id: int, abrev: string}>  $materias
     * @param  array{ord: float, nom: float, sub: float, mat: float, par: float, pie: float, colsMateria: int}  $anchos
     */
    private function dibujarBloqueAlumno(
        float $y,
        array $est,
        array $materias,
        array $anchos,
        float $fontPt,
        float $hFila,
    ): float {
        $matCells = is_array($est['materias'] ?? null) ? $est['materias'] : [];
        $res = is_array($est['resumen'] ?? null) ? $est['resumen'] : [];
        $ord = (string) ($est['ord'] ?? '');
        $alumno = mb_strtoupper(trim((string) ($est['alumno'] ?? '')), 'UTF-8');

        // Fila 1: módulos 1–4
        $x = self::MARGEN_IZQ;
        $this->celdaBorde($x, $y, $anchos['ord'], $hFila, $ord, 'C', $fontPt, true, false, false, 'LTRB');
        $x += $anchos['ord'];
        $this->celdaBorde($x, $y, $anchos['nom'], $hFila, $alumno, 'L', $fontPt, true, false, false, 'LTRB', true);
        $x += $anchos['nom'];
        foreach ($materias as $m) {
            $c = $matCells[(int) $m['id']] ?? [];
            $mods = is_array($c['modulos'] ?? null) ? $c['modulos'] : [];
            for ($i = 0; $i < 4; $i++) {
                $this->celdaNota($x, $y, $anchos['sub'], $hFila, $mods[$i] ?? null, $fontPt);
                $x += $anchos['sub'];
            }
        }
        $y += $hFila;

        // Fila 2: módulos 5–8 (ord/nom sin borde H)
        $x = self::MARGEN_IZQ;
        $this->celdaBorde($x, $y, $anchos['ord'], $hFila, '', 'C', $fontPt, false, false, false, 'LR');
        $x += $anchos['ord'];
        $this->celdaBorde($x, $y, $anchos['nom'], $hFila, '', 'L', $fontPt, false, false, false, 'LR');
        $x += $anchos['nom'];
        foreach ($materias as $m) {
            $c = $matCells[(int) $m['id']] ?? [];
            $mods = is_array($c['modulos'] ?? null) ? $c['modulos'] : [];
            for ($i = 4; $i < 8; $i++) {
                $this->celdaNota($x, $y, $anchos['sub'], $hFila, $mods[$i] ?? null, $fontPt);
                $x += $anchos['sub'];
            }
        }
        $y += $hFila;

        // Fila 3: JIS1, JIS2, prom anual (colspan 2)
        $x = self::MARGEN_IZQ;
        $this->celdaBorde($x, $y, $anchos['ord'], $hFila, '', 'C', $fontPt, false, false, false, 'LR');
        $x += $anchos['ord'];
        $this->celdaBorde($x, $y, $anchos['nom'], $hFila, '', 'L', $fontPt, false, false, false, 'LR');
        $x += $anchos['nom'];
        foreach ($materias as $m) {
            $c = $matCells[(int) $m['id']] ?? [];
            $this->celdaNota($x, $y, $anchos['sub'], $hFila, is_array($c['jis1'] ?? null) ? $c['jis1'] : null, $fontPt);
            $x += $anchos['sub'];
            $this->celdaNota($x, $y, $anchos['sub'], $hFila, is_array($c['jis2'] ?? null) ? $c['jis2'] : null, $fontPt);
            $x += $anchos['sub'];
            $pa = trim((string) ($c['promAnual'] ?? ''));
            $this->celdaBorde($x, $y, $anchos['par'], $hFila, $pa, 'C', $fontPt, true, false, false, 'LTRB');
            $x += $anchos['par'];
        }
        $y += $hFila;

        // Fila 4: dic / feb
        $x = self::MARGEN_IZQ;
        $this->celdaBorde($x, $y, $anchos['ord'], $hFila, '', 'C', $fontPt, false, false, false, 'LR');
        $x += $anchos['ord'];
        $this->celdaBorde($x, $y, $anchos['nom'], $hFila, '', 'L', $fontPt, false, false, false, 'LR');
        $x += $anchos['nom'];
        foreach ($materias as $m) {
            $c = $matCells[(int) $m['id']] ?? [];
            $dic = trim((string) ($c['dic'] ?? ''));
            $feb = trim((string) ($c['feb'] ?? ''));
            $this->celdaBorde($x, $y, $anchos['par'], $hFila, $dic, 'C', $fontPt, false, false, false, 'LTRB');
            $x += $anchos['par'];
            $this->celdaBorde($x, $y, $anchos['par'], $hFila, $feb, 'C', $fontPt, false, false, false, 'LTRB');
            $x += $anchos['par'];
        }
        $y += $hFila;

        // Fila 5: pie resumen
        $x = self::MARGEN_IZQ;
        $this->celdaBorde($x, $y, $anchos['ord'], $hFila, '', 'C', $fontPt, false, false, false, 'LRB');
        $x += $anchos['ord'];
        $this->celdaBorde($x, $y, $anchos['nom'], $hFila, '', 'L', $fontPt, false, false, false, 'LRB');
        $x += $anchos['nom'];
        $this->dibujarCeldaPie($x, $y, $anchos['pie'], $hFila, $res, max(3.8, $fontPt - 0.2));

        return $y + $hFila;
    }

    /**
     * @param  array{texto?: string, rojo?: bool, gris?: bool}|null  $cel
     */
    private function celdaNota(float $x, float $y, float $w, float $h, ?array $cel, float $fontPt): void
    {
        $txt = trim((string) ($cel['texto'] ?? ''));
        $rojo = ! empty($cel['rojo']);
        $gris = ! empty($cel['gris']);
        $this->celdaBorde($x, $y, $w, $h, $txt, 'C', $fontPt, false, $gris, $rojo, 'LTRB');
    }

    /**
     * @param  array<string, mixed>  $res
     */
    private function dibujarCeldaPie(float $x, float $y, float $w, float $h, array $res, float $fontPt): void
    {
        $this->SetDrawColor(...self::COLOR_BORDE);
        $this->SetLineWidth(self::GROSOR_BORDE);
        $this->SetFillColor(255, 255, 255);
        $this->Rect($x, $y, $w, $h, 'D');

        $partes = [];
        $numRep = (int) ($res['numRep'] ?? 0);
        $partes[] = ['Nº Rep:', (string) $numRep, $numRep > 0];
        foreach (['inas' => 'Inas:', 'amon' => 'Amon:', 'edFi' => 'Ed.Fi:', 'promGral' => 'Prom.Gral:', 'previas' => 'Previas:'] as $key => $lbl) {
            $val = trim((string) ($res[$key] ?? ''));
            if ($val !== '') {
                $partes[] = [$lbl, $val, false];
            }
        }

        $cursorX = $x + 0.8;
        $maxX = $x + $w - 0.5;
        $textY = $y + max(0.15, ($h - ($fontPt * 0.35)) / 2);

        foreach ($partes as $i => [$lbl, $val, $rojo]) {
            $color = $rojo ? self::COLOR_ROJO : [0, 0, 0];
            if ($i > 0) {
                $sep = ' — ';
                TcpdfFuenteArial::aplicar($this, '', $fontPt);
                $this->SetTextColor(0, 0, 0);
                $sw = $this->GetStringWidth($sep);
                if ($cursorX + $sw > $maxX) {
                    break;
                }
                $this->SetXY($cursorX, $textY);
                $this->Cell($sw, $fontPt * 0.4, $sep, 0, 0, 'L');
                $cursorX += $sw;
            }

            TcpdfFuenteArial::aplicar($this, 'B', $fontPt);
            $this->SetTextColor(...$color);
            $swL = $this->GetStringWidth($lbl.' ');
            if ($cursorX + $swL > $maxX) {
                break;
            }
            $this->SetXY($cursorX, $textY);
            $this->Cell($swL, $fontPt * 0.4, $lbl.' ', 0, 0, 'L');
            $cursorX += $swL;

            TcpdfFuenteArial::aplicar($this, '', $fontPt);
            $this->SetTextColor(...$color);
            $restante = $maxX - $cursorX;
            if ($restante < 1.5) {
                break;
            }
            $textoVal = $val;
            while ($textoVal !== '' && $this->GetStringWidth($textoVal) > $restante) {
                $textoVal = mb_substr($textoVal, 0, -1, 'UTF-8');
            }
            $swV = $this->GetStringWidth($textoVal);
            $this->SetXY($cursorX, $textY);
            $this->Cell($swV, $fontPt * 0.4, $textoVal, 0, 0, 'L');
            $cursorX += $swV;
        }
    }

    private function celdaBorde(
        float $x,
        float $y,
        float $w,
        float $h,
        string $texto,
        string $align,
        float $fontPt,
        bool $bold,
        bool $fillGris,
        bool $textoRojo,
        string $border,
        bool $recortar = false,
    ): void {
        $this->SetDrawColor(...self::COLOR_BORDE);
        $this->SetLineWidth(self::GROSOR_BORDE);

        if ($fillGris) {
            $this->SetFillColor(...self::COLOR_GRIS);
            $fill = true;
        } else {
            $this->SetFillColor(255, 255, 255);
            $fill = false;
        }

        if ($textoRojo) {
            $this->SetTextColor(...self::COLOR_ROJO);
        } else {
            $this->SetTextColor(0, 0, 0);
        }

        TcpdfFuenteArial::aplicar($this, $bold ? 'B' : '', $fontPt);

        if ($recortar && $texto !== '') {
            $pad = 1.0;
            while ($texto !== '' && $this->GetStringWidth($texto) > $w - $pad) {
                $texto = mb_substr($texto, 0, -1, 'UTF-8');
            }
        }

        $this->SetXY($x, $y);
        // Padding izquierdo visual en nombres
        if ($align === 'L' && $texto !== '') {
            $this->Cell($w, $h, ' '.$texto, $border, 0, $align, $fill);
        } else {
            $this->Cell($w, $h, $texto, $border, 0, $align, $fill);
        }

        $this->SetTextColor(0, 0, 0);
    }
}
