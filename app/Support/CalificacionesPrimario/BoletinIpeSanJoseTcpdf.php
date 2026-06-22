<?php

namespace App\Support\CalificacionesPrimario;

use App\Support\Pdf\PdfMateriaEncabezadoLineas;
use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfMultiCellJustificado;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use TCPDF;

/**
 * Informe de Progreso Escolar — variante San José (A4 apaisado, matriz con encabezados rotados).
 */
final class BoletinIpeSanJoseTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const ANCHO_ENCABEZADO = 254.0;

    private const ANCHO_ETIQUETA_FILA = 46.0;

    private const ALTO_ENCABEZADO_GRUPOS = 8.0;

    private const FUENTE_ENCABEZADO_GRUPO_MULTILINEA = 7;

    /** Alto de cada renglón en etiquetas multilínea del encabezado de grupos (mm). */
    private const INTERLINEADO_ENCABEZADO_GRUPO_MULTILINEA = 3.0;

    private const ALTO_ENCABEZADO_MATERIAS = 26.0;

    private const FUENTE_ENCABEZADO_MATERIAS = 7;

    /** Distancia entre centros de dos renglones verticales en el encabezado de materia (mm). */
    private const SEPARACION_ENCABEZADO_2_LINEAS = 3.1;

    private const SEPARACION_ENCABEZADO_3_LINEAS = 2.8;

    private const ALTO_FILA = 7.0;

    private const ALTO_ENCABEZADO_INST = 22.0;

    private const ANCHO_LOGO = 18.0;

    private const ALTO_LOGO = 18.0;

    private const FILL_GRIS = [232, 232, 232];

    /** @var array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string} */
    private array $header;

    public function __construct(array $header)
    {
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8', false);
        $this->header = $header;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Informe de Progreso Escolar');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetLeftMargin(self::MARGEN_IZQ);
        $this->SetFillColor(...self::FILL_GRIS);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generarHoja(array $datos, array $header): self
    {
        $pdf = new self($header);
        $pdf->AddPage();
        $pdf->dibujarHoja($datos);

        return $pdf;
    }

    /**
     * @param  list<array<string, mixed>>  $hojas
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generarLote(array $hojas, array $header): self
    {
        $pdf = new self($header);
        foreach ($hojas as $datos) {
            $pdf->AddPage();
            $pdf->dibujarHoja($datos);
        }

        return $pdf;
    }

    public static function respuestaHttp(self $pdf, string $nombreArchivo): Response
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
     * @param  array<string, mixed>  $datos
     */
    private function dibujarHoja(array $datos): void
    {
        $cicloEscolar = BoletinIpeSanJoseLayout::normalizarCicloEscolar((int) ($datos['cicloEscolar'] ?? 1));
        /** @var list<array{ord: int, materia: string, ic01: string, ic02: string, ic03: string}> $columnas */
        $columnas = $datos['columnas'] ?? [];

        $this->dibujarEncabezado($datos);
        $this->dibujarEncabezadoGrupos($cicloEscolar);
        $this->dibujarEncabezadosMaterias($columnas, $cicloEscolar);

        $y = $this->GetY();
        $y = $this->dibujarFilaEtapas($y, '1º Etapa', 'ic01', $columnas, $cicloEscolar);
        $y = $this->dibujarFilaEtapas($y, '2º Etapa', 'ic02', $columnas, $cicloEscolar);
        $y = $this->dibujarFilaApreciacionFinal($y, $columnas, $cicloEscolar);

        $y = $this->dibujarObservaciones($y, $datos);
        $this->dibujarBloquesInferiores($y, $datos);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarEncabezado(array $datos): float
    {
        $xy1 = 10.0;
        $insti = trim((string) ($this->header['insti'] ?? ''));
        if ($insti === '') {
            $insti = 'Institución';
        }

        $this->SetDrawColor(0, 0, 0);
        $this->Rect(self::MARGEN_IZQ, $xy1, self::ANCHO_ENCABEZADO, self::ALTO_ENCABEZADO_INST);

        $logo = $this->resolverLogoArchivo();
        if ($logo !== null) {
            $this->Image(
                $logo,
                self::MARGEN_IZQ + 2,
                $xy1 + ((self::ALTO_ENCABEZADO_INST - self::ALTO_LOGO) / 2),
                self::ANCHO_LOGO,
                self::ALTO_LOGO,
                '',
                '',
                '',
                false,
                300,
            );
        }

        $this->SetXY(self::MARGEN_IZQ, $xy1);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(self::ANCHO_ENCABEZADO, 7, $insti, 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 8);
        $titulo = trim((string) ($datos['titulo'] ?? 'INFORME DE PROGRESO ESCOLAR'));
        $ano = (int) ($datos['ano'] ?? now()->year);
        $this->Cell(self::ANCHO_ENCABEZADO, 5, $titulo.' - '.$ano, 0, 2, 'C');

        $alumno = trim((string) ($datos['alumnoLinea'] ?? ''));
        $dni = trim((string) ($datos['dni'] ?? ''));
        $lineaAlumno = $alumno.($dni !== '' ? ' - '.$dni : '');
        $this->Cell(self::ANCHO_ENCABEZADO, 5, $lineaAlumno, 0, 2, 'C');

        $curso = trim((string) ($datos['cursoLabel'] ?? ''));
        $this->Cell(self::ANCHO_ENCABEZADO, 5, $curso, 0, 2, 'C');

        return $xy1 + self::ALTO_ENCABEZADO_INST + 2;
    }

    private function resolverLogoArchivo(): ?string
    {
        $logo = $this->header['logo_file'] ?? null;
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

        return is_file($fallback) ? $fallback : null;
    }

    private function dibujarEncabezadoGrupos(int $cicloEscolar): void
    {
        $slots = BoletinIpeSanJoseLayout::slots();
        $anchoCelda = BoletinIpeSanJoseLayout::anchoCeldaMm();
        $y = $this->GetY();
        $x = self::MARGEN_IZQ;

        TcpdfFuenteArial::aplicar($this, '', 8);

        $this->SetXY($x, $y);
        $this->Cell(self::ANCHO_ETIQUETA_FILA, self::ALTO_ENCABEZADO_GRUPOS, '', 1, 0, 'C');
        $x += self::ANCHO_ETIQUETA_FILA;

        $wOficial = $anchoCelda * $slots['oficial'];
        $this->SetXY($x, $y);
        $this->Cell($wOficial, self::ALTO_ENCABEZADO_GRUPOS, 'Espacios Curriculares Oficiales', 1, 0, 'C');
        $x += $wOficial;

        $wExtraoficial = $anchoCelda * $slots['instit'];
        $this->dibujarEtiquetaGrupoMultilinea($x, $y, $wExtraoficial, self::ALTO_ENCABEZADO_GRUPOS, [
            'Espacios Curric.',
            'Extraoficiales',
        ]);
        $x += $wExtraoficial;

        $wInasist = $anchoCelda * $slots['inasist'];
        $this->SetXY($x, $y);
        $this->Cell($wInasist, self::ALTO_ENCABEZADO_GRUPOS, 'Inasistencias', 1, 1, 'C');
    }

    /**
     * Etiqueta de grupo en varias líneas (celda angosta del encabezado de columnas).
     *
     * @param  list<string>  $lineas
     */
    private function dibujarEtiquetaGrupoMultilinea(float $x, float $y, float $ancho, float $alto, array $lineas): void
    {
        $this->Rect($x, $y, $ancho, $alto);

        if ($lineas === []) {
            return;
        }

        $cantidad = count($lineas);
        $altoRenglon = self::INTERLINEADO_ENCABEZADO_GRUPO_MULTILINEA;
        $bloqueAlto = $altoRenglon * $cantidad;
        $yInicio = $y + max(0.0, ($alto - $bloqueAlto) / 2);

        TcpdfFuenteArial::aplicar($this, '', self::FUENTE_ENCABEZADO_GRUPO_MULTILINEA);

        foreach ($lineas as $i => $texto) {
            $this->SetXY($x, $yInicio + ($i * $altoRenglon));
            $this->Cell($ancho, $altoRenglon, $texto, 0, 0, 'C');
        }
    }

    private function cantidadColumnasMaterias(int $cicloEscolar): int
    {
        return BoletinIpeSanJoseLayout::slots()['total'];
    }

    /**
     * @param  list<array{ord: int, materia: string, ic01: string, ic02: string, ic03: string}>  $columnas
     */
    private function dibujarEncabezadosMaterias(array $columnas, int $cicloEscolar): void
    {
        $maxColumnas = $this->cantidadColumnasMaterias($cicloEscolar);
        $anchoCelda = BoletinIpeSanJoseLayout::anchoCeldaMm();
        $xInicio = self::MARGEN_IZQ + self::ANCHO_ETIQUETA_FILA;
        $y = $this->GetY();

        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->Cell(self::ANCHO_ETIQUETA_FILA, self::ALTO_ENCABEZADO_MATERIAS, '', 1, 0, 'C');

        $x = $xInicio;
        for ($i = 0; $i < $maxColumnas; $i++) {
            $this->SetXY($x, $y);
            $this->Cell($anchoCelda, self::ALTO_ENCABEZADO_MATERIAS, '', 1, 0, 'C');

            $materia = trim((string) ($columnas[$i]['materia'] ?? ''));
            if ($materia !== '') {
                $this->dibujarMateriaVerticalEnCelda($x, $y, $anchoCelda, self::ALTO_ENCABEZADO_MATERIAS, $materia);
            }

            $x += $anchoCelda;
        }

        $this->SetXY(self::MARGEN_IZQ, $y + self::ALTO_ENCABEZADO_MATERIAS);
    }

    private function dibujarMateriaVerticalEnCelda(float $xCelda, float $yCelda, float $anchoCelda, float $altoCelda, string $materia): void
    {
        $fuente = (float) self::FUENTE_ENCABEZADO_MATERIAS;
        $lineas = $this->armarLineasEncabezadoMateria($materia, $altoCelda, $fuente);
        if ($lineas === []) {
            return;
        }

        TcpdfFuenteArial::aplicar($this, 'I', $fuente);
        while ($this->maxAnchoTrazos($lineas) > ($altoCelda - 1.2) && $fuente >= 4.5) {
            $fuente -= 0.5;
            TcpdfFuenteArial::aplicar($this, 'I', $fuente);
        }

        $separacion = match (count($lineas)) {
            2 => min(self::SEPARACION_ENCABEZADO_2_LINEAS, $anchoCelda * 0.28),
            3 => min(self::SEPARACION_ENCABEZADO_3_LINEAS, $anchoCelda * 0.24),
            default => 0.0,
        };

        $offsets = $this->offsetsXBloqueCentrado(count($lineas), $anchoCelda, $separacion, $fuente);

        foreach ($lineas as $i => $texto) {
            $this->dibujarTextoVerticalCentradoEnCelda(
                $xCelda + $offsets[$i],
                $yCelda,
                $altoCelda,
                $texto,
                $fuente,
            );
        }
    }

    private function maxCaracteresColumnaVertical(float $altoCelda, float $fuentePt): int
    {
        $mmPorCaracter = ($fuentePt / $this->getScaleFactor()) * 0.42;

        return max(12, (int) floor(($altoCelda - 1.5) / $mmPorCaracter));
    }

    /**
     * Parte el nombre en varias columnas verticales antes de achicar la fuente.
     *
     * @return list<string>
     */
    private function armarLineasEncabezadoMateria(string $materia, float $altoCelda, float $fuentePt): array
    {
        TcpdfFuenteArial::aplicar($this, 'I', $fuentePt);

        $maxPorLinea = $this->maxCaracteresColumnaVertical($altoCelda, $fuentePt);
        $lineas = PdfMateriaEncabezadoLineas::partir($materia, $maxPorLinea, true);

        if ($lineas === []) {
            return [];
        }

        $limiteAlto = $altoCelda - 1.2;

        while (count($lineas) < 3 && $this->maxAnchoTrazos($lineas) > $limiteAlto) {
            $maxForzado = max(8, (int) floor($maxPorLinea / (count($lineas) + 1)));
            $repartidas = PdfMateriaEncabezadoLineas::partir($materia, $maxForzado, true);

            if ($repartidas === [] || count($repartidas) <= count($lineas)) {
                break;
            }

            $lineas = $repartidas;
        }

        return $lineas;
    }

    /**
     * @param  list<string>  $lineas
     */
    private function maxAnchoTrazos(array $lineas): float
    {
        $max = 0.0;
        foreach ($lineas as $texto) {
            $max = max($max, $this->GetStringWidth($texto));
        }

        return $max;
    }

    /**
     * Centra el bloque de columnas verticales en el ancho de la celda (márgenes izq./der. iguales al imprimir).
     *
     * @return list<float>
     */
    private function offsetsXBloqueCentrado(int $cantidad, float $anchoCelda, float $separacionCentros, float $fuentePt = self::FUENTE_ENCABEZADO_MATERIAS): array
    {
        if ($cantidad <= 0) {
            return [];
        }

        if ($cantidad === 1) {
            return [$anchoCelda / 2];
        }

        $centro = $anchoCelda / 2;
        $semi = (($cantidad - 1) * $separacionCentros) / 2;
        $centros = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $centros[] = $centro - $semi + ($i * $separacionCentros);
        }

        $halfIzq = $this->medioAnchoTrazoVertical(true, $fuentePt);
        $halfDer = $this->medioAnchoTrazoVertical(false, $fuentePt);
        $bloqueIzq = $centros[0] - $halfIzq;
        $bloqueDer = $centros[$cantidad - 1] + $halfDer;
        $desplazamiento = (($anchoCelda - ($bloqueDer - $bloqueIzq)) / 2) - $bloqueIzq;

        return array_map(static fn (float $x): float => $x + $desplazamiento, $centros);
    }

    /**
     * Mitad del ancho visible del trazo vertical (mm). Itálica: más protrusión hacia la izquierda de la celda.
     */
    private function medioAnchoTrazoVertical(bool $ladoIzquierdo, float $fuentePt = self::FUENTE_ENCABEZADO_MATERIAS): float
    {
        $mm = $fuentePt / $this->getScaleFactor();

        return $ladoIzquierdo ? $mm * 0.48 : $mm * 0.30;
    }

    /**
     * @param  list<array{ord: int, materia: string, ic01: string, ic02: string, ic03: string}>  $columnas
     */
    private function dibujarFilaEtapas(float $y, string $etiqueta, string $campo, array $columnas, int $cicloEscolar): float
    {
        $maxColumnas = $this->cantidadColumnasMaterias($cicloEscolar);
        $anchoCelda = BoletinIpeSanJoseLayout::anchoCeldaMm();

        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(self::ANCHO_ETIQUETA_FILA, self::ALTO_FILA, $etiqueta, 1, 0, 'C');
        TcpdfFuenteArial::aplicar($this, '', 8);

        for ($i = 0; $i < $maxColumnas; $i++) {
            $valor = trim((string) ($columnas[$i][$campo] ?? ''));
            $this->Cell($anchoCelda, self::ALTO_FILA, $valor, 1, 0, 'C');
        }

        $this->Ln();

        return $y + self::ALTO_FILA;
    }

    /**
     * @param  list<array{ord: int, materia: string, ic01: string, ic02: string, ic03: string}>  $columnas
     */
    private function dibujarFilaApreciacionFinal(float $y, array $columnas, int $cicloEscolar): float
    {
        $slots = BoletinIpeSanJoseLayout::slots();
        $anchoCelda = BoletinIpeSanJoseLayout::anchoCeldaMm();

        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(self::ANCHO_ETIQUETA_FILA, self::ALTO_FILA, 'Aprec.Final', 1, 0, 'C');
        TcpdfFuenteArial::aplicar($this, '', 8);

        // Primeras 3 columnas (ord 1–3): una sola nota — legacy tomaba la de la 2.ª columna.
        $afLengua = trim((string) ($columnas[1]['ic03'] ?? ''));
        $this->Cell($anchoCelda * 3, self::ALTO_FILA, $afLengua, 1, 0, 'C');

        for ($i = 3; $i < $slots['total']; $i++) {
            $valor = trim((string) ($columnas[$i]['ic03'] ?? ''));
            $this->Cell($anchoCelda, self::ALTO_FILA, $valor, 1, 0, 'C');
        }

        $this->Ln();

        return $y + self::ALTO_FILA;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarObservaciones(float $y, array $datos): float
    {
        $y += 10;
        $anchoObs = 239.0;
        $xObs = self::MARGEN_IZQ + 16;

        $y = $this->dibujarBloqueObservacion($y, '1º Etapa;', $xObs, $anchoObs, (string) ($datos['obs1'] ?? ''));
        $y = $this->dibujarBloqueObservacion($y + 2, '2º Etapa:', $xObs, $anchoObs, (string) ($datos['obs2'] ?? ''));

        return $y + 4;
    }

    private function dibujarBloqueObservacion(float $y, string $etiqueta, float $xObs, float $anchoObs, string $texto): float
    {
        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, 'I', 9);
        $this->Cell(16, 4, $etiqueta, 0, 0, 'L');

        $altoMin = 10.0;
        $this->SetDrawColor(0, 0, 0);
        $this->Rect($xObs, $y, $anchoObs, $altoMin);

        if (trim($texto) !== '') {
            $this->SetXY($xObs + 1, $y + 1);
            TcpdfFuenteArial::aplicar($this, '', 8);
            TcpdfMultiCellJustificado::escribir($this, $anchoObs - 2, 3.5, $texto);
        }

        $yFin = max($y + $altoMin, $this->GetY() + 1);
        $this->Rect($xObs, $y, $anchoObs, $yFin - $y);
        $this->SetY($yFin);

        return $yFin;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarBloquesInferiores(float $y, array $datos): void
    {
        $x = self::MARGEN_IZQ;
        $this->SetXY($x, $y);

        TcpdfFuenteArial::aplicar($this, 'B', 6);
        $this->Cell(30, 5, '', 1, 0, 'L');
        $this->Cell(30, 5, 'Firma del Docente', 1, 0, 'C');
        $this->Cell(30, 5, 'Firma Padre,Madre o Tutor', 1, 1, 'C');
        $this->Cell(30, 8, 'PRIMERA ETAPA', 1, 0, 'C');
        $this->Cell(30, 8, '', 1, 0, 'L');
        $this->Cell(30, 8, '', 1, 1, 'L');
        $this->Cell(30, 8, 'SEGUNDA ETAPA', 1, 0, 'C');
        $this->Cell(30, 8, '', 1, 0, 'L');
        $this->Cell(30, 8, '', 1, 1, 'L');
        $this->Cell(30, 8, 'APRECIACIÓN FINAL', 1, 0, 'C');
        $this->Cell(30, 8, '', 1, 0, 'L');
        $this->Cell(30, 8, '', 1, 1, 'L');

        $yBloque = $y;
        $this->SetXY($x + 100, $yBloque);
        TcpdfFuenteArial::aplicar($this, 'B', 6);
        $this->MultiCell(40, 3, 'Escala de Calificaciones', 1, 'C');
        $this->SetXY($x + 100, $yBloque + 3);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->MultiCell(
            40,
            3,
            "Excelente (E)\nMuy Bueno (MB)\nBueno (B)\nSatisfactorio (S)\nNo Satisfactorio (NS)",
            1,
            'C',
        );

        $this->SetXY($x + 160, $yBloque);
        TcpdfFuenteArial::aplicar($this, 'B', 6);
        $this->Cell(90, 5, 'EXÁMENES COMPLEMENTARIOS', 1, 1, 'L');
        $yExam = $yBloque + 5;
        $this->SetXY($x + 160, $yExam);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(30, 5, 'Fecha', 1, 0, 'C');
        $this->Cell(30, 5, 'Espacio Curricular', 1, 0, 'C');
        $this->Cell(30, 5, 'Calificación', 1, 1, 'C');
        for ($i = 0; $i < 3; $i++) {
            $this->SetXY($x + 160, $yExam + 5 + ($i * 5));
            $this->Cell(30, 5, '', 1, 0, 'C');
            $this->Cell(30, 5, '', 1, 0, 'L');
            $this->Cell(30, 5, '', 1, 1, 'L');
        }

        $yRes = $yBloque + 34;
        $obsAnual = trim((string) ($datos['obsAnual'] ?? ''));
        $this->SetXY($x, $yRes);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->MultiCell(250, 3, 'Resultado Final:     '.$obsAnual, 0, 'L');
        $this->Ln(4);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->MultiCell(
            250,
            3,
            '                                                                                                                     Fecha: ............................                                   Firma del/de la Director/a: ............................................                                           Sello del Est.Educativo',
            0,
            'L',
        );
    }

    /**
     * Texto rotado 90° centrado en la celda (márgenes superior/inferior iguales al imprimir).
     *
     * Patrón probado en planilla: Rotate(90, x, y) + Text — Translate hace desaparecer el trazo en TCPDF.
     */
    private function dibujarTextoVerticalCentradoEnCelda(float $xCentro, float $yCelda, float $altoCelda, string $texto, float $fuentePt = self::FUENTE_ENCABEZADO_MATERIAS): void
    {
        if ($texto === '') {
            return;
        }

        TcpdfFuenteArial::aplicar($this, 'I', $fuentePt);
        $longitud = $this->GetStringWidth($texto);
        $yCentro = $yCelda + ($altoCelda / 2);
        // Rotate(90) es CCW: el texto crece hacia arriba; ancla al borde inferior del bloque centrado.
        $yAncla = $yCentro + ($longitud / 2);

        $this->StartTransform();
        $this->Rotate(90, $xCentro, $yAncla);
        $this->Text($xCentro - 1, $yAncla - 1, $texto);
        $this->StopTransform();
    }
}
