<?php

namespace App\Support\CalificacionesPrimario;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use App\Support\Pdf\TcpdfMultiCellJustificado;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use TCPDF;

/**
 * IPE primario — variante Caixal SF (A4 vertical, layout legacy FPDF → TCPDF).
 *
 * Grilla fija: hasta 16 materias + 2 filas de inasistencias; observaciones y firmas a la derecha.
 */
final class BoletinIpeCaixalsfTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const ANCHO_UTIL = 170.0;

    private const ALTURA_FILA = 8.0;

    private const ANCHO_COL_MATERIA = 60.0;

    private const ANCHO_COL_ETAPA = 8.0;

    private const ANCHO_COL_AF = 10.0;

    private const X_COL_DERECHA = 115.0;

    private const ANCHO_ETIQUETA_ETAPA = 15.0;

    private const ANCHO_OBS_ETAPA = 60.0;

    private const FILL_GRIS = [232, 232, 232];

    private const ALTO_ENCABEZADO_INST = 22.0;

    private const ANCHO_LOGO = 20.0;

    private const ALTO_LOGO = 20.0;

    private const MAX_MATERIAS = 16;

    private const SEP_ENC_DATOS = 9.0;

    private const ALTO_ESCALA_TIT = 7.0;

    private const ALTO_ESCALA_CUERPO = 28.0;

    private const ALTO_FILA_FIRMA1 = 17.0;

    private const ALTO_FILA_FIRMA2 = 18.0;

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
        $etapa = (int) ($datos['etapa'] ?? 1);
        $etapa = $etapa === 2 ? 2 : 1;
        $xy1 = self::MARGEN_IZQ;

        $yOrigen = $this->dibujarEncabezado($xy1, $datos);

        $filasTabla = self::MAX_MATERIAS + 2;
        $yDatos = $yOrigen + self::SEP_ENC_DATOS;
        $ySeccionInf = $yDatos + ($filasTabla * self::ALTURA_FILA) + 4.0;
        $altoObs = $ySeccionInf - 4.0 - $yOrigen;
        $altoBloqueInf = self::ALTO_ESCALA_TIT + self::ALTO_ESCALA_CUERPO;

        $this->dibujarEncabezadoGrilla($yOrigen);
        $this->dibujarFilasCalificaciones($yDatos, $datos, $etapa);
        $this->dibujarObservacionesEtapa($yOrigen, $altoObs, $datos, $etapa);
        $this->dibujarEscalaCalificaciones($ySeccionInf, $altoBloqueInf);
        $this->dibujarFirmas($ySeccionInf);

        if ($etapa === 2) {
            $yCompl = $ySeccionInf + $altoBloqueInf + 2.0;
            $this->dibujarExamenesComplementarios(self::X_COL_DERECHA, $yCompl);
            $this->dibujarResultadoFinal($yCompl, $datos);
            $this->SetXY(self::MARGEN_IZQ, $ySeccionInf + $altoBloqueInf + 30.0);
            TcpdfFuenteArial::aplicar($this, 'B', 5);
            $this->Cell(
                self::ANCHO_UTIL,
                5,
                'Sello del Establecimiento                                                                                                                                    Firma de la Directora',
                0,
                0,
                'C',
            );
            $yFinal = $ySeccionInf + $altoBloqueInf + 38.0;
        } else {
            $yFinal = $ySeccionInf + $altoBloqueInf + 6.0;
        }

        $this->dibujarObservacionFinalCidi($yFinal, $etapa);
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
                TcpdfImagenPng::fuenteTcpdf($logo),
                $xy1 + 3,
                $xy1 + 1,
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
        $subtitulo = trim((string) ($datos['subtitulo'] ?? $datos['titulo'] ?? 'INFORME DE PROGRESO ESCOLAR'));
        $ano = (int) ($datos['ano'] ?? now()->year);
        $this->Cell(self::ANCHO_UTIL, 5, $subtitulo.' - '.$ano, 0, 2, 'C');

        $alumno = trim((string) ($datos['alumnoLinea'] ?? ''));
        $dni = trim((string) ($datos['dni'] ?? ''));
        $lineaAlumno = $alumno.($dni !== '' ? ' - '.$dni : '');
        $this->Cell(self::ANCHO_UTIL, 5, $lineaAlumno, 0, 2, 'C');

        $curso = trim((string) ($datos['cursoLabel'] ?? ''));
        $this->Cell(self::ANCHO_UTIL, 5, $curso, 0, 2, 'C');

        return $this->GetY() + 2.0;
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

    private function dibujarEncabezadoGrilla(float $y): void
    {
        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(self::ANCHO_COL_MATERIA, self::ALTURA_FILA, 'Espacios Curriculares', 1, 0, 'C');
        $this->Cell(self::ANCHO_COL_ETAPA, self::ALTURA_FILA, '1ºE', 1, 0, 'C');
        $this->Cell(self::ANCHO_COL_ETAPA, self::ALTURA_FILA, '2ºE', 1, 0, 'C');
        $this->Cell(self::ANCHO_COL_AF, self::ALTURA_FILA, 'A.F.', 1, 0, 'C');
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarFilasCalificaciones(float $y, array $datos, int $etapa): void
    {
        /** @var list<array{materia: string, ic01: string, ic02: string, ic03: string, indice: int}> $filas */
        $filas = $datos['filas'] ?? [];
        $f = 0;
        $l = 0;
        $yFila = $y;

        foreach ($filas as $fila) {
            $f++;
            $l++;
            $this->dibujarFilaMateria(
                $yFila,
                mb_substr(trim((string) ($fila['materia'] ?? '')), 0, 39),
                trim((string) ($fila['ic01'] ?? '')),
                trim((string) ($fila['ic02'] ?? '')),
                trim((string) ($fila['ic03'] ?? '')),
                $etapa,
                $f < 4,
            );
            $yFila += self::ALTURA_FILA;
        }

        while ($l < self::MAX_MATERIAS) {
            $l++;
            $f++;
            $this->dibujarFilaMateria($yFila, '', '', '', '', $etapa, $f < 4);
            $yFila += self::ALTURA_FILA;
        }

        $this->dibujarFilaInasistencia(
            $yFila,
            'Inasistencias Justificadas',
            (string) ($datos['just1'] ?? ''),
            (string) ($datos['just2'] ?? ''),
            (string) ($datos['justAf'] ?? ''),
            $etapa,
        );
        $yFila += self::ALTURA_FILA;

        $this->dibujarFilaInasistencia(
            $yFila,
            'Inasistencias Injustificadas',
            (string) ($datos['inju1'] ?? ''),
            (string) ($datos['inju2'] ?? ''),
            (string) ($datos['injuAf'] ?? ''),
            $etapa,
        );
    }

    private function dibujarFilaMateria(
        float $y,
        string $materia,
        string $ic01,
        string $ic02,
        string $ic03,
        int $etapa,
        bool $rellenarAf,
    ): void {
        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(self::ANCHO_COL_MATERIA, self::ALTURA_FILA, $materia, 1, 0, 'L');
        $this->Cell(self::ANCHO_COL_ETAPA, self::ALTURA_FILA, $ic01, 1, 0, 'C');

        if ($etapa === 1) {
            $this->Cell(self::ANCHO_COL_ETAPA, self::ALTURA_FILA, '', 1, 0, 'C');
            $this->aplicarCeldaAf('', $rellenarAf);
        } else {
            $this->Cell(self::ANCHO_COL_ETAPA, self::ALTURA_FILA, $ic02, 1, 0, 'C');
            $this->aplicarCeldaAf($ic03, $rellenarAf);
        }
    }

    private function dibujarFilaInasistencia(
        float $y,
        string $etiqueta,
        string $v1,
        string $v2,
        string $vAf,
        int $etapa,
    ): void {
        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(self::ANCHO_COL_MATERIA, self::ALTURA_FILA, $etiqueta, 1, 0, 'L');

        if ($etapa === 1) {
            $this->Cell(self::ANCHO_COL_ETAPA, self::ALTURA_FILA, $v1, 1, 0, 'C');
            $this->Cell(self::ANCHO_COL_ETAPA, self::ALTURA_FILA, '', 1, 0, 'C');
            $this->Cell(self::ANCHO_COL_AF, self::ALTURA_FILA, '', 1, 0, 'C', false);
        } else {
            $this->Cell(self::ANCHO_COL_ETAPA, self::ALTURA_FILA, $v1, 1, 0, 'C');
            $this->Cell(self::ANCHO_COL_ETAPA, self::ALTURA_FILA, $v2, 1, 0, 'C');
            $this->Cell(self::ANCHO_COL_AF, self::ALTURA_FILA, $vAf, 1, 0, 'C', false);
        }
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
    private function dibujarObservacionesEtapa(float $yOrigen, float $altoObs, array $datos, int $etapa): void
    {
        $obs = trim((string) ($datos['obsEtapa'] ?? ''));
        $etiqueta = $etapa === 1 ? 'PRIMERA ETAPA' : 'SEGUNDA ETAPA';
        $x = self::X_COL_DERECHA;

        $this->SetDrawColor(0, 0, 0);
        $this->Rect($x, $yOrigen, self::ANCHO_ETIQUETA_ETAPA, $altoObs);
        $this->Rect($x + self::ANCHO_ETIQUETA_ETAPA, $yOrigen, self::ANCHO_OBS_ETAPA, $altoObs);

        $this->SetXY($x, $yOrigen);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->MultiCell(self::ANCHO_ETIQUETA_ETAPA, 10, $etiqueta, 0, 'C');

        $this->SetXY($x + self::ANCHO_ETIQUETA_ETAPA, $yOrigen);
        TcpdfFuenteArial::aplicar($this, '', 7);
        if ($obs !== '') {
            TcpdfMultiCellJustificado::escribir($this, self::ANCHO_OBS_ETAPA, 4.0, $obs);
        }
    }

    private function dibujarEscalaCalificaciones(float $y, float $altoBloqueInf): void
    {
        $this->SetDrawColor(0, 0, 0);
        $this->Rect(self::MARGEN_IZQ, $y, 86.0, $altoBloqueInf);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->Cell(86.0, self::ALTO_ESCALA_TIT, 'Escala de Calificaciones', 0, 2, 'C');
        $this->Line(self::MARGEN_IZQ, $y + self::ALTO_ESCALA_TIT, 106.0, $y + self::ALTO_ESCALA_TIT);

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->SetXY(self::MARGEN_IZQ, $y + self::ALTO_ESCALA_TIT + 2.0);
        $this->MultiCell(
            86.0,
            5,
            "Excelente (E)\nMuy Bueno (MB)\nBueno (B)\nSatisfactorio (S)\nNo Satisfactorio (NS)",
            0,
            'C',
        );
    }

    private function dibujarFirmas(float $y): void
    {
        $x = self::X_COL_DERECHA;
        TcpdfFuenteArial::aplicar($this, '', 6);

        $this->SetXY($x, $y);
        $this->Cell(20, self::ALTO_FILA_FIRMA1, '', 1, 0, 'C');
        $this->Cell(55, self::ALTO_FILA_FIRMA1, '', 1, 0, 'L');
        $this->SetXY($x + 1, $y + 2);
        $this->MultiCell(18, 3, "Firma\ndel/de la\nDocente", 0, 'C');

        $y2 = $y + self::ALTO_FILA_FIRMA1;
        $this->SetXY($x, $y2);
        $this->Cell(20, self::ALTO_FILA_FIRMA2, '', 1, 0, 'C');
        $this->Cell(55, self::ALTO_FILA_FIRMA2, '', 1, 0, 'L');
        $this->SetXY($x + 1, $y2 + 2);
        $this->MultiCell(18, 3, "Firma del\nPadre, Madre\no Tutor", 0, 'C');
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarResultadoFinal(float $y, array $datos): void
    {
        $obsAnual = trim((string) ($datos['obsAnual'] ?? ''));

        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->MultiCell(30, 6, 'Resultado Final:', 1, 'C');
        $this->SetXY(50, $y);
        $this->MultiCell(65, 6, $obsAnual, 1, 'L');
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
            ? 'El IPE oficial de la Primera Etapa puede ser visualizado por padre, madre o tutor a través del Ciudadano Digital (CIDI)'
            : 'El IPE oficial de la Segunda Etapa puede ser visualizado por padre, madre o tutor a través del Ciudadano Digital (CIDI)';

        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 5);
        $this->MultiCell(30, 4, 'Observaciones', 1, 'C');

        $this->SetXY(50, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 5);
        $this->MultiCell(140, 4, $texto, 1, 'L');
    }
}
