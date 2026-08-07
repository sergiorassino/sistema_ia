<?php

namespace App\Support\BoletinesSecundario;

use App\Support\ConsultaCalificacionesAlumno;
use App\Support\Examenes\TercerMateriaGestor;
use App\Support\Pdf\TcpdfImagenPng;
use TCPDF;

/**
 * Informe de progreso escolar y consulta de calificaciones (secundario, A4 apaisado).
 * Usado por boletín oficial y por consulta (marca de agua / firmas según el controlador).
 */
final class BoletinConsultaCalificacionesTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 7.0;

    private const MARGEN_DER = 7.0;

    private const MARGEN_SUP = 15.0;

    private const ANCHO_UTIL = 283.0;

    private const FUENTE = 'dejavusans';

    private const BLANK = "\u{00A0}";

    /** Separación horizontal entre bloques (border-spacing ~3px). */
    private const GAP_COL = 1.05;

    private const RADIO_BAY = 0.53;

    private const ALTURA_ENC = 7.8;

    private const ALTURA_FILA = 4.2;

    private const ALTURA_ENCABEZADO_INST = 22.0;

    /** Línea meta (alumno / DNI / curso): separadores vs. datos destacados. */
    private const META_FUENTE_SEP = 6.5;

    private const META_FUENTE_DATO = 7.5;

    private const ANCHO_LOGO = 17.0;

    /** Pie con firmas: columna texto 38 % (como pie-texto en DomPDF). */
    private const PIE_FRACC_TEXTO = 0.38;

    private const PIE_TEXTO_PAD_R = 3.0;

    private const PIE_FIRMAS_MARGEN_DER = 40.0;

    private const PIE_FIRMA_W_LINEA = 65.0;

    private const PIE_FIRMA_SEP = 20.0;

    private const PIE_FIRMA_PADRE_IZQ = 20.0;

    private const PIE_FIRMA_PADDING_SUP = 10.0;

    private const PIE_FIRMA_ALTURA_BLOQUE = 21.0;

    /** Anchos de columna (mm), proporciones legacy del Blade. */
    private const ANCHOS_COL = [
        52.4, // Espacio curricular 19.54%
        18.9, 18.9, 18.9, 18.9, 18.9, 18.9, 18.9, 18.9, // Eval 1-8
        15.5, 15.5, // JIS
        10.6, 10.6, 14.3, // Coloq Dic, Feb, Prom
    ];

    /** @var array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string} */
    private array $header;

    private string $tituloDocumento;

    private bool $mostrarMarcaAgua;

    private bool $mostrarFirmas;

    /**
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public function __construct(array $header, string $tituloDocumento, bool $mostrarMarcaAgua, bool $mostrarFirmas)
    {
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8', false);
        $this->header = $header;
        $this->tituloDocumento = $tituloDocumento;
        $this->mostrarMarcaAgua = $mostrarMarcaAgua;
        $this->mostrarFirmas = $mostrarFirmas;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle($tituloDocumento);
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
    }

    /**
     * @param  array<string, mixed>  $consulta
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generarHoja(
        array $consulta,
        array $header,
        string $tituloDocumento,
        bool $mostrarMarcaAgua,
        bool $mostrarFirmas,
    ): self {
        $pdf = new self($header, $tituloDocumento, $mostrarMarcaAgua, $mostrarFirmas);
        $pdf->AddPage();
        $pdf->dibujarHoja($consulta);

        return $pdf;
    }

    /**
     * @param  list<array<string, mixed>>  $consultas
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generarLote(
        array $consultas,
        array $header,
        string $tituloDocumento,
    ): self {
        $pdf = new self($header, $tituloDocumento, false, true);
        foreach ($consultas as $consulta) {
            $pdf->AddPage();
            $pdf->dibujarHoja($consulta);
        }

        return $pdf;
    }

    /**
     * @param  array<string, mixed>  $consulta
     */
    private function dibujarHoja(array $consulta): void
    {
        $y = self::MARGEN_SUP;
        $y = $this->dibujarEncabezadoInstitucional($y);
        $y = $this->dibujarTituloYMeta($y, $consulta);
        $yGrilla = $y;
        if ($this->mostrarMarcaAgua) {
            $yFinGrillaEst = $yGrilla + $this->alturaGrillaMm($consulta);
            $this->dibujarMarcaAgua($yGrilla, $yFinGrillaEst);
        }

        $yFinGrilla = $this->dibujarGrilla($yGrilla, $consulta);

        $this->dibujarPie($yFinGrilla + 1.5, $consulta);
    }

    private function dibujarEncabezadoInstitucional(float $y): float
    {
        $x0 = self::MARGEN_IZQ;
        $h = self::ALTURA_ENCABEZADO_INST;
        $this->SetDrawColor(17, 17, 17);
        $this->SetLineWidth(0.26);
        $this->RoundedRect($x0, $y, self::ANCHO_UTIL, $h, 2.0, '1111', 'D');

        $logo = $this->header['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), $x0 + 3, $y + 2, self::ANCHO_LOGO, self::ANCHO_LOGO, '', '', '', false, 300);
        }

        $insti = trim((string) ($this->header['insti'] ?? ''));
        if ($insti === '') {
            $insti = 'Institución';
        }
        $direccion = trim((string) ($this->header['direccion'] ?? ''));
        $localidad = trim((string) ($this->header['localidad'] ?? ''));
        $lineaDir = trim($direccion.($direccion !== '' && $localidad !== '' ? ' — ' : '').$localidad);
        $cue = trim((string) ($this->header['cue'] ?? ''));
        $ee = trim((string) ($this->header['ee'] ?? ''));
        $lineaIds = trim(($cue !== '' ? 'CUE: '.$cue : '').(($cue !== '' && $ee !== '') ? '   ' : '').($ee !== '' ? 'EE: '.$ee : ''));

        $xTexto = $x0 + self::ANCHO_LOGO + 6;
        $wTexto = self::ANCHO_UTIL - (self::ANCHO_LOGO + 12) * 2;
        $this->SetTextColor(17, 17, 17);
        $this->SetXY($xTexto, $y + 3);
        $this->SetFont(self::FUENTE, 'B', 12);
        $this->Cell($wTexto, 5, $insti, 0, 2, 'C');
        if ($lineaDir !== '') {
            $this->SetFont(self::FUENTE, '', 9);
            $this->Cell($wTexto, 4, $lineaDir, 0, 2, 'C');
        }
        if ($lineaIds !== '') {
            $this->SetFont(self::FUENTE, '', 6.5);
            $this->Cell($wTexto, 3.5, $lineaIds, 0, 2, 'C');
        }

        return $y + $h + 2.5;
    }

    /**
     * @param  array<string, mixed>  $consulta
     */
    private function dibujarTituloYMeta(float $y, array $consulta): float
    {
        $this->SetTextColor(0, 0, 0);
        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->SetFont(self::FUENTE, 'B', 10);
        $this->Cell(self::ANCHO_UTIL, 4, mb_strtoupper($this->tituloDocumento, 'UTF-8'), 0, 2, 'C');

        $ano = $consulta['anoLectivo'] ?? null;
        if ($ano !== null && $ano !== '') {
            $this->SetFont(self::FUENTE, '', 6.5);
            $this->Cell(self::ANCHO_UTIL, 3.5, 'Ciclo lectivo '.$ano, 0, 2, 'C');
        }

        $this->SetXY(self::MARGEN_IZQ, $this->GetY() + 1);
        $this->dibujarLineaMetaAlumno($consulta);

        return $this->GetY() + 1.5;
    }

    /**
     * Apellido, nombre, DNI y curso en negrita y 1 pt más grande que los separadores.
     *
     * @param  array<string, mixed>  $consulta
     */
    private function dibujarLineaMetaAlumno(array $consulta): void
    {
        $alumno = trim((string) ($consulta['alumnoLinea'] ?? ''));
        $dni = trim((string) ($consulta['dni'] ?? ''));
        $curso = trim((string) ($consulta['cursoLabel'] ?? ''));

        if ($alumno === '' && $dni === '' && $curso === '') {
            return;
        }

        $this->SetTextColor(0, 0, 0);
        $lineHeight = 4.0;

        if ($alumno !== '') {
            $this->SetFont(self::FUENTE, 'B', self::META_FUENTE_DATO);
            $this->Write($lineHeight, $alumno, '', false, 'L', false);
        }

        if ($dni !== '') {
            $this->SetFont(self::FUENTE, '', self::META_FUENTE_SEP);
            $this->Write($lineHeight, ' · D.N.I. ', '', false, 'L', false);
            $this->SetFont(self::FUENTE, 'B', self::META_FUENTE_DATO);
            $this->Write($lineHeight, $dni, '', false, 'L', false);
        }

        if ($curso !== '') {
            $this->SetFont(self::FUENTE, '', self::META_FUENTE_SEP);
            $this->Write($lineHeight, ' · ', '', false, 'L', false);
            $this->SetFont(self::FUENTE, 'B', self::META_FUENTE_DATO);
            $this->Write($lineHeight, $curso, '', false, 'L', true);

            return;
        }

        $this->Ln(0);
    }

    /**
     * @param  array<string, mixed>  $consulta
     */
    private function alturaGrillaMm(array $consulta): float
    {
        /** @var list<object> $rows */
        $rows = is_array($consulta['rows'] ?? null) ? $consulta['rows'] : [];
        $filas = $rows === [] ? 1 : count($rows);

        return self::ALTURA_ENC + 0.7 + $filas * (self::ALTURA_FILA + 0.7);
    }

    /**
     * @param  array<string, mixed>  $consulta
     */
    private function dibujarGrilla(float $yInicio, array $consulta): float
    {
        $xs = $this->posicionesXColumnas();
        $this->dibujarEncabezadoGrilla($yInicio, $xs);
        $y = $yInicio + self::ALTURA_ENC + 0.7;

        /** @var list<object> $rows */
        $rows = is_array($consulta['rows'] ?? null) ? $consulta['rows'] : [];
        if ($rows === []) {
            $this->dibujarCeldaBay($xs[0], $y, self::ANCHOS_COL[0], self::ALTURA_FILA, 'D');
            $this->SetFont(self::FUENTE, '', 5.8);
            $this->SetXY($xs[0], $y + 0.8);
            $wTotal = end($xs) + self::ANCHOS_COL[13] - $xs[0];
            $this->Cell($wTotal, 2.5, 'Sin calificaciones registradas para esta matrícula.', 0, 0, 'C');

            return $y + self::ALTURA_FILA;
        }

        foreach ($rows as $row) {
            $this->dibujarFilaGrilla($y, $xs, $row);
            $y += self::ALTURA_FILA + 0.7;
        }

        return $y;
    }

    /**
     * @param  list<float>  $xs
     */
    private function dibujarEncabezadoGrilla(float $y, array $xs): void
    {
        $h1 = 4.0;
        $h2 = self::ALTURA_ENC - $h1;

        $this->dibujarCeldaBay($xs[0], $y, self::ANCHOS_COL[0], self::ALTURA_ENC, 'D');
        $this->SetFont(self::FUENTE, 'B', 5.5);
        $this->SetXY($xs[0], $y + 1.5);
        $this->MultiCell(self::ANCHOS_COL[0], 2.5, "Espacio\nCurricular", 0, 'C', false, 0);

        for ($e = 1; $e <= 8; $e++) {
            $i = $e;
            $x = $xs[$i];
            $w = self::ANCHOS_COL[$i];
            $this->dibujarCeldaBay($x, $y, $w, self::ALTURA_ENC, 'D');
            $this->lineaHorizontal($x, $y + $h1, $w);
            $this->lineaVertical($x + $w / 3, $y + $h1, $h2);
            $this->lineaVertical($x + 2 * $w / 3, $y + $h1, $h2);
            $this->SetFont(self::FUENTE, 'B', 5.2);
            $this->SetXY($x, $y + 0.6);
            $this->Cell($w, 2.5, 'Eval. '.$e, 0, 0, 'C');
            $this->SetFont(self::FUENTE, 'B', 5.1);
            $tw = $w / 3;
            $this->SetXY($x, $y + $h1 + 0.4);
            $this->Cell($tw, 2.5, 'N', 0, 0, 'C');
            $this->Cell($tw, 2.5, 'R1', 0, 0, 'C');
            $this->Cell($tw, 2.5, 'R2', 0, 1, 'C');
        }

        foreach ([9 => 'JIS 1', 10 => 'JIS 2'] as $idx => $titulo) {
            $x = $xs[$idx];
            $w = self::ANCHOS_COL[$idx];
            $this->dibujarCeldaBay($x, $y, $w, self::ALTURA_ENC, 'D');
            $this->lineaHorizontal($x, $y + $h1, $w);
            $this->lineaVertical($x + $w / 2, $y + $h1, $h2);
            $this->SetFont(self::FUENTE, 'B', 5.2);
            $this->SetXY($x, $y + 0.6);
            $this->Cell($w, 2.5, $titulo, 0, 0, 'C');
            $this->SetFont(self::FUENTE, 'B', 5.1);
            $tw = $w / 2;
            $this->SetXY($x, $y + $h1 + 0.4);
            $this->Cell($tw, 2.5, 'N', 0, 0, 'C');
            $this->Cell($tw, 2.5, 'R', 0, 1, 'C');
        }

        $titulosCol = [11 => "Coloq.\nDic", 12 => "Coloq.\nFeb", 13 => "Prom.\nFinal"];
        foreach ($titulosCol as $idx => $txt) {
            $this->dibujarCeldaBay($xs[$idx], $y, self::ANCHOS_COL[$idx], self::ALTURA_ENC, 'D');
            $this->SetFont(self::FUENTE, 'B', 5.1);
            $this->SetXY($xs[$idx], $y + 1.2);
            $this->MultiCell(self::ANCHOS_COL[$idx], 2.5, $txt, 0, 'C', false, 0);
        }
    }

    /**
     * @param  list<float>  $xs
     */
    private function dibujarFilaGrilla(float $y, array $xs, object $row): void
    {
        $this->dibujarCeldaBay($xs[0], $y, self::ANCHOS_COL[0], self::ALTURA_FILA, 'D');
        $this->SetFont(self::FUENTE, '', 5.8);
        $this->SetXY($xs[0] + 1.5, $y + 0.9);
        $ec = trim((string) ($row->espacio_curricular ?? ''));
        $this->Cell(self::ANCHOS_COL[0] - 3, 2.5, $this->truncar($ec, self::ANCHOS_COL[0] - 3), 0, 0, 'L');

        for ($e = 1; $e <= 8; $e++) {
            $i = $e;
            $b = ($e - 1) * 3 + 1;
            $vals = [
                $this->valorIc($row, 'ic'.str_pad((string) $b, 2, '0', STR_PAD_LEFT)),
                $this->valorIc($row, 'ic'.str_pad((string) ($b + 1), 2, '0', STR_PAD_LEFT)),
                $this->valorIc($row, 'ic'.str_pad((string) ($b + 2), 2, '0', STR_PAD_LEFT)),
            ];
            $this->dibujarBayEvalDatos($xs[$i], $y, self::ANCHOS_COL[$i], $vals);
        }

        $this->dibujarBayJisDatos($xs[9], $y, self::ANCHOS_COL[9], [
            $this->valorIc($row, 'ic25'),
            $this->valorIc($row, 'ic26'),
        ]);
        $this->dibujarBayJisDatos($xs[10], $y, self::ANCHOS_COL[10], [
            $this->valorIc($row, 'ic27'),
            $this->valorIc($row, 'ic28'),
        ]);

        $this->dibujarCeldaBay($xs[11], $y, self::ANCHOS_COL[11], self::ALTURA_FILA, 'D');
        $this->dibujarCeldaBay($xs[12], $y, self::ANCHOS_COL[12], self::ALTURA_FILA, 'D');
        $this->dibujarCeldaBay($xs[13], $y, self::ANCHOS_COL[13], self::ALTURA_FILA, 'D');
        $this->SetFont(self::FUENTE, '', 5.5);
        $this->SetXY($xs[11], $y + 1);
        $this->Cell(self::ANCHOS_COL[11], 2.5, $this->valorIc($row, 'dic'), 0, 0, 'C');
        $this->SetXY($xs[12], $y + 1);
        $this->Cell(self::ANCHOS_COL[12], 2.5, $this->valorIc($row, 'feb'), 0, 0, 'C');
        $this->SetFont(self::FUENTE, 'B', 5.5);
        $this->SetXY($xs[13], $y + 1);
        $this->Cell(self::ANCHOS_COL[13], 2.5, $this->valorPromedio($row), 0, 0, 'C');
    }

    /**
     * @param  list<string>  $vals
     */
    private function dibujarBayEvalDatos(float $x, float $y, float $w, array $vals): void
    {
        $this->dibujarCeldaBay($x, $y, $w, self::ALTURA_FILA, 'D');
        $this->lineaVertical($x + $w / 3, $y, self::ALTURA_FILA);
        $this->lineaVertical($x + 2 * $w / 3, $y, self::ALTURA_FILA);
        $this->SetFont(self::FUENTE, '', 6);
        $tw = $w / 3;
        $this->SetXY($x, $y + 1);
        foreach ($vals as $i => $v) {
            $this->Cell($tw, 2.5, $v, 0, 0, 'C');
        }
    }

    /**
     * @param  list<string>  $vals
     */
    private function dibujarBayJisDatos(float $x, float $y, float $w, array $vals): void
    {
        $this->dibujarCeldaBay($x, $y, $w, self::ALTURA_FILA, 'D');
        $this->lineaVertical($x + $w / 2, $y, self::ALTURA_FILA);
        $this->SetFont(self::FUENTE, '', 6);
        $tw = $w / 2;
        $this->SetXY($x, $y + 1);
        $this->Cell($tw, 2.5, $vals[0] ?? self::BLANK, 0, 0, 'C');
        $this->Cell($tw, 2.5, $vals[1] ?? self::BLANK, 0, 0, 'C');
    }

    /**
     * Marca centrada en la grilla (posición original). Se invoca antes de dibujar la grilla para quedar detrás de los datos.
     */
    private function dibujarMarcaAgua(float $yTop, float $yBottom): void
    {
        $cx = self::MARGEN_IZQ + self::ANCHO_UTIL / 2;
        $cy = $yTop + ($yBottom - $yTop) * 0.54;
        $this->SetAlpha(0.52);
        $this->SetTextColor(168, 168, 168);
        $this->SetFont(self::FUENTE, 'B', 22);
        $this->StartTransform();
        $this->Rotate(-29, $cx, $cy);
        $this->Text($cx - 38, $cy - 2, 'SIN VALOR LEGAL');
        $this->StopTransform();
        $this->SetAlpha(1);
        $this->SetTextColor(0, 0, 0);
    }

    /**
     * @param  array<string, mixed>  $consulta
     */
    private function dibujarPie(float $y, array $consulta): void
    {
        /** @var list<object> $adeudadas */
        $adeudadas = is_array($consulta['materias_adeudadas'] ?? null) ? $consulta['materias_adeudadas'] : [];
        /** @var list<array<string, mixed>> $tercerMateria */
        $tercerMateria = is_array($consulta['tercer_materia'] ?? null) ? $consulta['tercer_materia'] : [];
        /** @var list<object> $itemsBoletin */
        $itemsBoletin = is_array($consulta['items_boletin'] ?? null) ? $consulta['items_boletin'] : [];

        $hayPieTexto = $adeudadas !== [] || $tercerMateria !== [] || $itemsBoletin !== [];

        if ($this->mostrarFirmas) {
            $this->dibujarPieConFirmas($y, $adeudadas, $tercerMateria, $itemsBoletin, $hayPieTexto);

            return;
        }

        $xPie = self::MARGEN_IZQ;
        $wPie = self::ANCHO_UTIL;
        $yPie = $y + 2.45;
        if ($adeudadas !== []) {
            $yPie = $this->dibujarMateriasPrevias($yPie, $adeudadas, $xPie, $wPie);
        }
        if (! $this->mostrarFirmas) {
            /** @var list<object{linea: string}> $proximasEvaluaciones */
            $proximasEvaluaciones = is_array($consulta['proximas_evaluaciones'] ?? null)
                ? $consulta['proximas_evaluaciones']
                : [];
            if ($proximasEvaluaciones !== []) {
                $yPie = $this->dibujarProximasEvaluaciones($yPie, $proximasEvaluaciones, $xPie, $wPie);
            }
        }
        foreach ($tercerMateria as $tm) {
            $yPie = $this->dibujarTercerMateria($yPie, $tm, $xPie, $wPie);
        }
        if ($itemsBoletin !== []) {
            $this->dibujarItemsBoletin($yPie + 5, $itemsBoletin, $xPie, $wPie);
        }
    }

    /**
     * @param  list<object>  $adeudadas
     * @param  list<array<string, mixed>>  $tercerMateria
     * @param  list<object>  $itemsBoletin
     */
    private function dibujarPieConFirmas(
        float $y,
        array $adeudadas,
        array $tercerMateria,
        array $itemsBoletin,
        bool $hayPieTexto,
    ): void {
        $xPag = self::MARGEN_IZQ;
        $xPagDer = $xPag + self::ANCHO_UTIL;
        $wTexto = self::ANCHO_UTIL * self::PIE_FRACC_TEXTO;
        $xColFirmas = $xPag + $wTexto + self::PIE_TEXTO_PAD_R;

        $yRowTop = $y + 1.5;
        $yLeftEnd = $yRowTop;

        if ($hayPieTexto) {
            $yCursor = $yRowTop;
            if ($adeudadas !== []) {
                $yCursor = $this->dibujarMateriasPrevias($yCursor, $adeudadas, $xPag, $wTexto);
            }
            foreach ($tercerMateria as $tm) {
                $yCursor = $this->dibujarTercerMateria($yCursor, $tm, $xPag, $wTexto);
            }
            if ($itemsBoletin !== []) {
                $yCursor = $this->dibujarItemsBoletin($yCursor + 1.5, $itemsBoletin, $xPag, $wTexto) ?? $yCursor;
            }
            $yLeftEnd = $yCursor;
        }

        $yRowBottom = max($yLeftEnd, $yRowTop + self::PIE_FIRMA_ALTURA_BLOQUE);
        $yFirmaCelTop = $yRowBottom - self::PIE_FIRMA_ALTURA_BLOQUE;
        $yLineaBase = $yFirmaCelTop + self::PIE_FIRMA_PADDING_SUP;

        $xTablaIzq = $hayPieTexto ? $xColFirmas : $xPag + 50.0;
        $xPadreLinea = $xTablaIzq + self::PIE_FIRMA_PADRE_IZQ;
        $xDirectivo = $xPadreLinea + self::PIE_FIRMA_W_LINEA + self::PIE_FIRMA_SEP;

        $this->dibujarLineaFirma($xPadreLinea, $yLineaBase, self::PIE_FIRMA_W_LINEA);
        $this->dibujarLineaFirma($xDirectivo, $yLineaBase, self::PIE_FIRMA_W_LINEA);
        $this->SetFont(self::FUENTE, '', 6);
        $yEtiqueta = $yLineaBase + 2.0;
        $this->SetXY($xPadreLinea, $yEtiqueta);
        $this->Cell(self::PIE_FIRMA_W_LINEA, 3, 'Firma Padre / Madre / Tutor', 0, 0, 'C');
        $this->SetXY($xDirectivo, $yEtiqueta);
        $this->Cell(self::PIE_FIRMA_W_LINEA, 3, 'Firma Directivo', 0, 0, 'C');
    }

    /**
     * @param  list<object{linea: string}>  $proximas
     */
    private function dibujarProximasEvaluaciones(float $y, array $proximas, float $x, float $w): float
    {
        $this->SetFont(self::FUENTE, 'B', 6.9);
        $this->SetXY($x, $y);
        $this->Cell(50, 3, 'PRÓXIMAS EVALUACIONES:', 0, 1, 'L');
        $this->SetFont(self::FUENTE, '', 6.8);
        foreach ($proximas as $ev) {
            $linea = trim((string) ($ev->linea ?? ''));
            if ($linea === '') {
                continue;
            }
            $this->SetXY($x, $this->GetY());
            $this->MultiCell($w, 3, $linea, 0, 'L');
        }

        return $this->GetY() + 1;
    }

    /**
     * @param  list<object>  $adeudadas
     */
    private function dibujarMateriasPrevias(float $y, array $adeudadas, float $x, float $w): float
    {
        $this->SetFont(self::FUENTE, 'B', 6.9);
        $this->SetXY($x, $y);
        $this->Cell(40, 3, 'MATERIAS PREVIAS:', 0, 1, 'L');
        $lineas = [];
        foreach ($adeudadas as $a) {
            $lineas[] = trim((string) ($a->linea ?? ''));
        }
        $this->SetFont(self::FUENTE, '', 6.8);
        $this->SetXY($x, $this->GetY());
        $this->MultiCell($w, 3, implode(' - ', array_filter($lineas)), 0, 'L');

        return $this->GetY() + 1;
    }

    /**
     * @param  array<string, mixed>  $tm
     */
    private function dibujarTercerMateria(float $y, array $tm, float $x, float $wMax): float
    {
        $gapTm = 3.0;
        /** Anchos alineados al partial DomPDF (17pt / 34pt). */
        $wCelTm = 6.0;
        $wCelNota = 12.0;
        $hCel = 3.5;
        $campos = ['tm1', 'tm2', 'tm3', 'tm4', 'tm5', 'tm6', 'tmNota'];
        $xMax = $x + $wMax;

        $anchoGrilla = 0.0;
        foreach ($campos as $campo) {
            $anchoGrilla += $campo === 'tmNota' ? $wCelNota : $wCelTm;
        }

        $this->SetFont(self::FUENTE, 'B', 6.8);
        $lbl = 'Tercer Materia:';
        $nombre = trim((string) ($tm['nombre_boletin'] ?? TercerMateriaGestor::nombreMateriaBoletin($tm)));
        $texto = $lbl.' '.$nombre;
        $textoAncho = $this->GetStringWidth($texto);
        $yTexto = $y + 0.5;
        $yCeldas = $y;

        $xCeldas = $x + $textoAncho + $gapTm;
        if ($xCeldas + $anchoGrilla > $xMax + 0.5) {
            $this->SetXY($x, $yTexto);
            $this->Cell(min($textoAncho, $wMax), 3, $texto, 0, 1, 'L');
            $yCeldas = $this->GetY();
            $xCeldas = $x;
        } else {
            $this->SetXY($x, $yTexto);
            $this->Cell($textoAncho, 3, $texto, 0, 0, 'L');
        }

        foreach ($campos as $campo) {
            $wCel = $campo === 'tmNota' ? $wCelNota : $wCelTm;
            if ($xCeldas + $wCel > $xMax + 0.5) {
                break;
            }
            $v = trim((string) ($tm[$campo] ?? ''));
            $this->Rect($xCeldas, $yCeldas, $wCel, $hCel, 'D');
            $this->SetFont(self::FUENTE, '', 6);
            $this->SetXY($xCeldas, $yCeldas + 0.6);
            $this->Cell($wCel, 2.5, $v !== '' ? $v : self::BLANK, 0, 0, 'C');
            $xCeldas += $wCel;
        }

        return $yCeldas + $hCel + 1;
    }

    /**
     * @param  list<object>  $items
     */
    private function dibujarItemsBoletin(float $y, array $items, float $x, float $w): float
    {
        $this->SetFont(self::FUENTE, '', 7);
        foreach ($items as $it) {
            $pres = ConsultaCalificacionesAlumno::presentacionItemBoletin($it);
            $txt = $pres['mostrar'] ? $pres['texto'] : self::BLANK;
            $etiqueta = trim((string) ($it->etiqueta ?? ''));
            $this->SetXY($x, $y);
            $this->Cell($w, 3.2, $etiqueta.': '.$txt, 0, 1, 'L');
            $y = $this->GetY() + ($pres['tight'] ? 0.2 : 0.5);
        }

        return $y;
    }

    private function dibujarLineaFirma(float $x, float $y, float $w): void
    {
        $this->SetDrawColor(51, 51, 51);
        $this->SetLineWidth(0.2);
        $this->SetLineStyle(['width' => 0.2, 'dash' => '1,1', 'color' => [51, 51, 51]]);
        $this->Line($x, $y, $x + $w, $y);
        $this->SetLineStyle(['width' => 0.26, 'dash' => 0, 'color' => [51, 51, 51]]);
    }

    private function dibujarCeldaBay(float $x, float $y, float $w, float $h, string $estilo): void
    {
        $this->SetDrawColor(51, 51, 51);
        $this->SetLineWidth(0.26);
        $this->RoundedRect($x, $y, $w, $h, self::RADIO_BAY, '1111', $estilo);
    }

    private function lineaHorizontal(float $x, float $y, float $w): void
    {
        $this->SetDrawColor(51, 51, 51);
        $this->Line($x, $y, $x + $w, $y);
    }

    private function lineaVertical(float $x, float $y, float $h): void
    {
        $this->SetDrawColor(136, 136, 136);
        $this->SetLineWidth(0.14);
        $this->Line($x, $y, $x, $y + $h);
        $this->SetLineWidth(0.26);
        $this->SetDrawColor(51, 51, 51);
    }

    /** @return list<float> */
    private function posicionesXColumnas(): array
    {
        $xs = [];
        $x = self::MARGEN_IZQ;
        foreach (self::ANCHOS_COL as $w) {
            $xs[] = $x;
            $x += $w + self::GAP_COL;
        }

        return $xs;
    }

    private function valorIc(object $row, string $col): string
    {
        $s = trim((string) ($row->{$col} ?? ''));
        return $s === '' ? self::BLANK : $s;
    }

    private function valorPromedio(object $row): string
    {
        $s = trim((string) ($row->calif ?? ''));
        if ($s === '') {
            return self::BLANK;
        }
        $n = str_replace(',', '.', $s);
        if (is_numeric($n)) {
            return number_format((float) $n, 2, ',', '');
        }

        return $s;
    }

    private function truncar(string $texto, float $anchoMm): string
    {
        if ($texto === '') {
            return '';
        }
        $this->SetFont(self::FUENTE, '', 5.8);
        $max = max(6, (int) floor($anchoMm / 1.4));
        if (mb_strlen($texto) <= $max) {
            return $texto;
        }

        return mb_substr($texto, 0, $max - 1).'…';
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
}
