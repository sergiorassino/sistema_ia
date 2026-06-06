<?php

namespace App\Support\MatrizAnaliticos;

use TCPDF;

/**
 * Certificado analítico — reverso (5.º y 6.º año + pie, formato Legal).
 */
final class AnaliticoReversoTcpdf extends TCPDF
{
    use AnaliticoTcpdfGrilla;

    private const MARGEN_IZQ = 20.0;

    private const MARGEN_DER = 15.0;

    private const MARGEN_SUP = 10.0;

    private const ANCHO_UTIL = 175.0;

    /** Mismo ancho que la grilla de calificaciones (mm). */
    private const ANCHO_TABLA = 190.0;

    private const FUENTE = 'dejavusans';

    private const ALTURA_LINEA_PIE = 4.0;

    /** Anchos mínimos de subrayado (mm), legacy formulario preimpreso. */
    private const CAMPO_LOC_MIN = 59.0;

    private const CAMPO_DIA_MIN = 20.0;

    private const CAMPO_MES_MIN = 34.0;

    private const CAMPO_ANIO_MIN = 22.0;

    /** @var array<string, mixed> */
    private array $datos;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('P', 'mm', 'LEGAL', true, 'UTF-8', false);
        $this->datos = $datos;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Certificado analítico — reverso');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(true, 12);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
        $this->grillaConfigurarFill();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage();

        foreach ($datos['anios'] ?? [] as $bloque) {
            if (! is_array($bloque)) {
                continue;
            }
            $pdf->grillaDibujarBloqueAnio(
                self::MARGEN_IZQ,
                self::ANCHO_UTIL,
                (string) ($bloque['titulo'] ?? ''),
                is_array($bloque['filas'] ?? null) ? $bloque['filas'] : [],
            );
            $pdf->Ln(6);
        }

        $pdf->dibujarPie();

        return $pdf;
    }

    private function dibujarPie(): void
    {
        $pie = is_array($this->datos['pie'] ?? null) ? $this->datos['pie'] : [];
        $leg = is_array($this->datos['legajo'] ?? null) ? $this->datos['legajo'] : [];

        $cohorte = trim((string) ($pie['analCohorte'] ?? ''));
        $observaciones = trim((string) ($pie['analObservaciones'] ?? ''));
        $paraCompletar = trim((string) ($pie['analParaCompletar'] ?? ''));
        $validez = trim((string) ($pie['analValidez'] ?? ''));
        $serie = trim((string) ($pie['serie'] ?? ''));
        $libroFolio = trim((string) ($pie['analLibroFolio'] ?? ''));
        $localidad = trim((string) ($pie['localidadEmision'] ?? ''));
        $dia = trim((string) ($pie['diaEmision'] ?? ''));
        $mes = trim((string) ($pie['mesEmision'] ?? ''));
        $anio = trim((string) ($pie['anioEmision'] ?? ''));
        $paraPre = trim((string) ($pie['analParaPre'] ?? ''));

        $apellido = trim((string) ($leg['apellido'] ?? ''));
        $nombre = trim((string) ($leg['nombre'] ?? ''));
        $dni = trim((string) ($leg['dni'] ?? ''));

        $bordeDer = self::MARGEN_IZQ + self::ANCHO_TABLA;
        $h = self::ALTURA_LINEA_PIE;

        $this->SetFont(self::FUENTE, '', 9);

        $this->celdaAnchoUtil('Cohorte:   '.$cohorte, $h);
        $this->Ln(3);

        $this->SetX(self::MARGEN_IZQ);
        $this->MultiCell(self::ANCHO_TABLA, $h, 'Observaciones: '.$observaciones, 0, 'L');
        $this->Ln(3);

        $y = $this->GetY();
        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->Cell(28, $h, ' El/La alumno/a       ', 0, 0, 'L');
        $lineaAlumno = trim($apellido.' '.$nombre.', D.N.I. '.$dni);
        $this->textoCentradoEnRango(45, $bordeDer, $y, $h, $lineaAlumno);
        $this->subrayadoPie(45, $bordeDer, $y);
        $this->SetY($y + $h);
        $this->Ln(1);

        if ($paraCompletar !== '') {
            $this->SetX(self::MARGEN_IZQ);
            $this->MultiCell(self::ANCHO_TABLA, $h, $paraCompletar, 0, 'L');
        }
        $this->Ln(3);

        $this->celdaAnchoUtil('VALIDEZ NACIONAL  '.$validez, $h);
        $this->Ln(1);
        $this->celdaAnchoUtil('Serie:  '.$serie.' -  Libro y Folio:  '.$libroFolio, $h);
        $this->Ln(1);

        $this->dibujarParrafoCertificadoConCampos($localidad, $dia, $mes, $anio);
        $this->Ln(1);

        $this->SetX(self::MARGEN_IZQ);
        $this->MultiCell(
            self::ANCHO_TABLA,
            $h,
            'Para ser presentado ante las autoridades de:  '.$paraPre,
            0,
            'L',
        );
    }

    private function celdaAnchoUtil(string $texto, float $h): void
    {
        $this->SetX(self::MARGEN_IZQ);
        $this->Cell(self::ANCHO_TABLA, $h, $texto, 0, 1, 'L');
    }

    private function textoCentradoEnRango(float $x1, float $x2, float $y, float $h, string $texto): void
    {
        if ($texto === '') {
            return;
        }
        $this->SetFont(self::FUENTE, '', 9);
        $this->SetXY($x1, $y);
        $this->Cell($x2 - $x1, $h, $texto, 0, 0, 'C');
    }

    private function subrayadoPie(float $x1, float $x2, float $y): void
    {
        $this->Line($x1, $y + 3.5, $x2, $y + 3.5);
    }

    private function dibujarParrafoCertificadoConCampos(
        string $localidad,
        string $dia,
        string $mes,
        string $anio,
    ): void {
        $segmentos = $this->segmentosCertificadoParcial($localidad, $dia, $mes, $anio);
        $h = self::ALTURA_LINEA_PIE;
        $y = $this->GetY();
        $x = self::MARGEN_IZQ;
        $anchoMax = self::ANCHO_TABLA;
        $anchoLinea = 0.0;
        /** @var list<array{t: string, v: string, min?: float, w: float}> $enLinea */
        $enLinea = [];

        $this->SetFont(self::FUENTE, '', 9);

        $nuevaLinea = function () use (&$x, &$y, &$anchoLinea, &$enLinea, $h): void {
            $this->dibujarLineaParrafoFormulario($enLinea, $x, $y, $h);
            $y += $h;
            $x = self::MARGEN_IZQ;
            $anchoLinea = 0.0;
            $enLinea = [];
        };

        foreach ($segmentos as $seg) {
            $w = $seg['w'];
            if ($anchoLinea + $w > $anchoMax && $enLinea !== []) {
                $nuevaLinea();
            }
            $enLinea[] = $seg;
            $anchoLinea += $w;
        }

        if ($enLinea !== []) {
            $nuevaLinea();
        }

        $this->SetY($y);
    }

    /**
     * @return list<array{t: string, v: string, min?: float, w: float}>
     */
    private function segmentosCertificadoParcial(
        string $localidad,
        string $dia,
        string $mes,
        string $anio,
    ): array {
        $definicion = [
            ['t' => 'text', 'v' => 'En fe de lo cual se extiende el presente CERTIFICADO ANALÍTICO PARCIAL, sin raspaduras ni enmiendas en la localidad de '],
            ['t' => 'field', 'v' => $localidad, 'min' => self::CAMPO_LOC_MIN],
            ['t' => 'text', 'v' => ' de la Provincia de Córdoba, República Argentina, a los '],
            ['t' => 'field', 'v' => $dia, 'min' => self::CAMPO_DIA_MIN],
            ['t' => 'text', 'v' => ' días del mes de '],
            ['t' => 'field', 'v' => $mes, 'min' => self::CAMPO_MES_MIN],
            ['t' => 'text', 'v' => ' de '],
            ['t' => 'field', 'v' => $anio, 'min' => self::CAMPO_ANIO_MIN],
            ['t' => 'text', 'v' => ' .-'],
        ];

        $out = [];
        foreach ($definicion as $seg) {
            if ($seg['t'] === 'field') {
                $out[] = [
                    't' => 'field',
                    'v' => $seg['v'],
                    'min' => $seg['min'],
                    'w' => $this->anchoSegmentoCampo($seg['v'], $seg['min']),
                ];

                continue;
            }

            foreach ($this->expandirTextoEnTokens((string) $seg['v']) as $token) {
                $out[] = [
                    't' => 'text',
                    'v' => $token,
                    'w' => $this->anchoSegmentoTexto($token),
                ];
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function expandirTextoEnTokens(string $texto): array
    {
        if ($texto === '') {
            return [];
        }

        $partes = preg_split('/(\s+)/u', $texto, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($partes === false) {
            return [$texto];
        }

        return array_values($partes);
    }

    private function anchoSegmentoTexto(string $texto): float
    {
        return $this->GetStringWidth($texto, self::FUENTE, '', 9);
    }

    private function anchoSegmentoCampo(string $valor, float $minimo): float
    {
        $wTexto = $valor !== '' ? $this->GetStringWidth($valor, self::FUENTE, '', 9) + 2.0 : 0.0;

        return max($minimo, $wTexto);
    }

    /**
     * @param  list<array{t: string, v: string, min?: float, w: float}>  $segmentos
     */
    private function dibujarLineaParrafoFormulario(array $segmentos, float $xInicio, float $y, float $h): void
    {
        $x = $xInicio;
        $this->SetFont(self::FUENTE, '', 9);

        foreach ($segmentos as $seg) {
            $w = $seg['w'];
            if ($seg['t'] === 'field') {
                $this->subrayadoPie($x, $x + $w, $y);
                $this->SetXY($x, $y);
                $this->Cell($w, $h, $seg['v'], 0, 0, 'C');
            } else {
                $this->SetXY($x, $y);
                $this->Cell($w, $h, $seg['v'], 0, 0, 'L');
            }
            $x += $w;
        }
    }
}
