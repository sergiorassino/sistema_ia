<?php

namespace App\Support\CalificacionesPrimario;

use App\Support\Pdf\TcpdfFuenteArial;
use Illuminate\Support\Facades\Storage;
use TCPDF;

/**
 * Informe de Progreso Escolar (IPE) — primario, A4 vertical (layout legacy FPDF → TCPDF).
 */
final class BoletinIpeTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const ANCHO_UTIL = 170.0;

    private const ALTURA_FILA = 8.0;

    private const ANCHO_COL_MATERIA = 60.0;

    private const ANCHO_COL_ETAPA = 8.0;

    private const ANCHO_COL_AF = 10.0;

    /** Columna derecha: observaciones + firmas (legacy x=115). */
    private const X_COL_DERECHA = 115.0;

    private const ANCHO_ETIQUETA_ETAPA = 15.0;

    private const ANCHO_OBS_ETAPA = 60.0;

    /** Alto fijo del bloque de observaciones de etapa (legacy). */
    private const ALTO_BLOQUE_OBS = 110.0;

    private const FILL_GRIS = [232, 232, 232];

    private const ALTO_ENCABEZADO_INST = 22.0;

    private const ANCHO_LOGO = 18.0;

    private const ALTO_LOGO = 18.0;

    /** @var array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string} */
    private array $header;

    public function __construct(array $header)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->header = $header;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Informe de Progreso Escolar');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_IZQ, 15, 15);
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
     * @param  array<string, mixed>  $datos
     */
    private function dibujarHoja(array $datos): void
    {
        $etapa = (int) ($datos['etapa'] ?? 1);
        $etapa = $etapa === 2 ? 2 : 1;

        $yGrillaInicio = $this->dibujarEncabezado(self::MARGEN_IZQ, $datos);
        $y = $this->dibujarEncabezadoGrilla($yGrillaInicio);
        $yFinTabla = $this->dibujarFilasCalificaciones($y, $datos, $etapa) + 10;

        $this->dibujarObservacionesEtapa($yGrillaInicio, $datos, $etapa);
        $yDespuesEscala = $this->dibujarEscalaCalificaciones($yFinTabla + 4);
        $yDespuesFirmas = $this->dibujarFirmas($yFinTabla, $yGrillaInicio);

        $yBloqueInferior = max($yDespuesEscala, $yDespuesFirmas) + 4;

        if ($etapa === 2) {
            $yBloqueInferior = $this->dibujarBloquesSegundaEtapa($yFinTabla, $datos, $yBloqueInferior);
        }

        $this->dibujarObservacionFinalCidi($yBloqueInferior, $etapa);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarEncabezado(float $xy1, array $datos): float
    {
        $insti = trim((string) ($this->header['insti'] ?? ''));
        if ($insti === '') {
            $insti = 'Institución';
        }

        $this->SetDrawColor(0, 0, 0);
        $this->Rect($xy1, $xy1, self::ANCHO_UTIL, self::ALTO_ENCABEZADO_INST);

        $logo = $this->resolverLogoArchivo();
        if ($logo !== null) {
            $this->Image(
                $logo,
                $xy1 + 2,
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

        $this->SetXY($xy1, $xy1);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(self::ANCHO_UTIL, 7, $insti, 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 8);
        $titulo = trim((string) ($datos['titulo'] ?? 'INFORME DE PROGRESO ESCOLAR'));
        $ano = (int) ($datos['ano'] ?? now()->year);
        $this->Cell(self::ANCHO_UTIL, 5, $titulo.' - '.$ano, 0, 2, 'C');

        $alumno = trim((string) ($datos['alumnoLinea'] ?? ''));
        $dni = trim((string) ($datos['dni'] ?? ''));
        $lineaAlumno = $alumno.($dni !== '' ? ' - '.$dni : '');
        $this->Cell(self::ANCHO_UTIL, 5, $lineaAlumno, 0, 2, 'C');

        $curso = trim((string) ($datos['cursoLabel'] ?? ''));
        $this->Cell(self::ANCHO_UTIL, 5, $curso, 0, 2, 'C');

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

    private function dibujarEncabezadoGrilla(float $y): float
    {
        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(self::ANCHO_COL_MATERIA, self::ALTURA_FILA, 'Espacios Curriculares', 1, 0, 'C');
        $this->Cell(self::ANCHO_COL_ETAPA, self::ALTURA_FILA, '1ºE', 1, 0, 'C');
        $this->Cell(self::ANCHO_COL_ETAPA, self::ALTURA_FILA, '2ºE', 1, 0, 'C');
        $this->Cell(self::ANCHO_COL_AF, self::ALTURA_FILA, 'A.F.', 1, 0, 'C');

        return $y + self::ALTURA_FILA;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarFilasCalificaciones(float $y, array $datos, int $etapa): float
    {
        /** @var list<array{materia: string, ic01: string, ic02: string, ic03: string, indice: int}> $filas */
        $filas = $datos['filas'] ?? [];

        foreach ($filas as $fila) {
            $this->SetXY(self::MARGEN_IZQ, $y);
            TcpdfFuenteArial::aplicar($this, '', 7);

            $materia = mb_substr(trim((string) ($fila['materia'] ?? '')), 0, 39);
            $ic01 = trim((string) ($fila['ic01'] ?? ''));
            $ic02 = trim((string) ($fila['ic02'] ?? ''));
            $ic03 = trim((string) ($fila['ic03'] ?? ''));
            $indice = (int) ($fila['indice'] ?? 0);
            $rellenarAf = $indice > 0 && $indice < 4;

            $this->Cell(self::ANCHO_COL_MATERIA, self::ALTURA_FILA, $materia, 1, 0, 'L');
            $this->Cell(self::ANCHO_COL_ETAPA, self::ALTURA_FILA, $ic01, 1, 0, 'C');

            if ($etapa === 1) {
                $this->Cell(self::ANCHO_COL_ETAPA, self::ALTURA_FILA, '', 1, 0, 'C');
                $this->aplicarCeldaAf('', $rellenarAf);
            } else {
                $this->Cell(self::ANCHO_COL_ETAPA, self::ALTURA_FILA, $ic02, 1, 0, 'C');
                $this->aplicarCeldaAf($ic03, $rellenarAf);
            }

            $y += self::ALTURA_FILA;
        }

        return $y;
    }

    private function aplicarCeldaAf(string $texto, bool $rellenar): void
    {
        if ($rellenar) {
            $this->SetFillColor(...self::FILL_GRIS);
        }
        $this->Cell(self::ANCHO_COL_AF, self::ALTURA_FILA, $texto, 1, 0, 'C', $rellenar);
        $this->SetFillColor(255, 255, 255);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarObservacionesEtapa(float $yGrillaInicio, array $datos, int $etapa): void
    {
        $obs = trim((string) ($datos['obsEtapa'] ?? ''));
        $etiqueta = $etapa === 1 ? 'PRIMERA ETAPA' : 'SEGUNDA ETAPA';
        $x = self::X_COL_DERECHA;
        $h = self::ALTO_BLOQUE_OBS;

        $this->SetDrawColor(0, 0, 0);
        $this->Rect($x, $yGrillaInicio, self::ANCHO_ETIQUETA_ETAPA, $h);
        $this->Rect($x + self::ANCHO_ETIQUETA_ETAPA, $yGrillaInicio, self::ANCHO_OBS_ETAPA, $h);

        $this->SetXY($x, $yGrillaInicio + 38);
        TcpdfFuenteArial::aplicar($this, 'B', 7);
        $this->MultiCell(self::ANCHO_ETIQUETA_ETAPA, 5, $etiqueta, 0, 'C');

        $this->SetXY($x + self::ANCHO_ETIQUETA_ETAPA + 1, $yGrillaInicio + 2);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->MultiCell(self::ANCHO_OBS_ETAPA - 2, 4, $obs, 0, 'L');
    }

    private function dibujarEscalaCalificaciones(float $y): float
    {
        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(86, 7, 'Escala de Calificaciones', 1, 2, 'C');

        $this->SetX(self::MARGEN_IZQ);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $escala = "Excelente (E)\nMuy Bueno (MB)\nBueno (B)\nSatisfactorio (S)\nNo Satisfactorio (NS)";
        $this->MultiCell(86, 5, $escala, 1, 'C');

        return $this->GetY() + 2;
    }

    /**
     * Firmas en columna derecha: debajo del bloque de observaciones o cerca del fin de la grilla (el mayor).
     */
    private function dibujarFirmas(float $yFinTabla, float $yGrillaInicio): float
    {
        $x = self::X_COL_DERECHA;
        $yDebajoObs = $yGrillaInicio + self::ALTO_BLOQUE_OBS + 2;
        $yDocente = max($yDebajoObs, $yFinTabla - 20);
        $yPadre = max($yDocente + 11, $yFinTabla - 5, $yDebajoObs + 11);
        $altoFirma = 9.0;

        $this->SetXY($x, $yDocente);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(20, $altoFirma, '', 1, 0, 'C');
        $this->SetXY($x, $yDocente + 1);
        $this->MultiCell(20, 2.8, "Firma\ndel/de la\nDocente", 0, 'C');
        $this->SetXY($x + 20, $yDocente);
        $this->Cell(55, $altoFirma, '', 1, 0, 'L');

        $this->SetXY($x, $yPadre);
        $this->Cell(20, $altoFirma, '', 1, 0, 'C');
        $this->SetXY($x, $yPadre + 1);
        $this->MultiCell(20, 2.8, "Firma del\nPadre, Madre\no Tutor", 0, 'C');
        $this->SetXY($x + 20, $yPadre);
        $this->Cell(55, $altoFirma, '', 1, 0, 'L');

        return $yPadre + $altoFirma;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarBloquesSegundaEtapa(float $yFinTabla, array $datos, float $yMinimo): float
    {
        $x = self::X_COL_DERECHA;
        $yExam = max($yFinTabla + 12, $yMinimo);
        $this->dibujarExamenesComplementarios($x, $yExam);

        $obsAnual = trim((string) ($datos['obsAnual'] ?? ''));
        $yRes = $yExam + 28;
        $this->SetXY(self::MARGEN_IZQ, $yRes);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(30, 6, 'Resultado Final:', 1, 0, 'C');
        $this->SetXY(50, $yRes);
        $this->Cell(70, 6, $obsAnual, 1, 0, 'L');
        $this->SetXY(120, $yRes);
        $this->Cell(70, 6, 'Fecha:', 1, 0, 'L');

        $ySello = $yRes + 10;
        $this->SetXY(self::MARGEN_IZQ, $ySello);
        TcpdfFuenteArial::aplicar($this, 'B', 5);
        $this->Cell(
            self::ANCHO_UTIL,
            5,
            'Sello del Establecimiento                                                                                                                                    Firma de la Directora',
            0,
            0,
            'C',
        );

        return $ySello + 8;
    }

    private function dibujarExamenesComplementarios(float $x, float $y): void
    {
        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 6);
        $this->Cell(75, 5, 'EXÁMENES COMPLEMENTARIOS', 1, 1, 'C');

        $yEnc = $y + 5;
        $this->SetXY($x, $yEnc);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(15, 5, 'Fecha', 1, 0, 'C');
        $this->Cell(30, 5, 'Espacio Curricular', 1, 0, 'C');
        $this->Cell(30, 5, 'Calificación', 1, 1, 'C');

        for ($i = 0; $i < 3; $i++) {
            $this->SetXY($x, $yEnc + 5 + ($i * 5));
            $this->Cell(15, 5, '', 1, 0, 'C');
            $this->Cell(30, 5, '', 1, 0, 'L');
            $this->Cell(30, 5, '', 1, 1, 'L');
        }
    }

    private function dibujarObservacionFinalCidi(float $y, int $etapa): void
    {
        $texto = $etapa === 1
            ? 'El IEF/IPE oficial de la Primera Etapa puede ser visualizado por padre, madre o tutor a través del Ciudadano Digital (CIDI)'
            : 'El IEF/IPE oficial de la Segunda Etapa puede ser visualizado por padre, madre o tutor a través del Ciudadano Digital (CIDI)';

        TcpdfFuenteArial::aplicar($this, '', 6);
        $alturaFila = max(8.0, $this->getStringHeight(140, $texto) + 2);

        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 6);
        $this->Cell(30, $alturaFila, 'Observaciones', 1, 0, 'C');
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(140, $alturaFila, $texto, 1, 1, 'L');
    }
}
