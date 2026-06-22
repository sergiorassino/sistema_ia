<?php

namespace App\Support\CalificacionesPrimario;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Planilla de calificaciones — nivel primario (A4 vertical, layout legacy FPDF → TCPDF).
 */
final class PlanillaCalificacionesPrimarioTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 15.0;

    private const ANCHO_UTIL = 180.0;

    private const FILL_GRIS = [232, 232, 232];

    private const ANCHO_NRO = 7.0;

    /** Legacy 50 mm; ajustado para A4 vertical (40 mm + 10 %). */
    private const ANCHO_NOMBRE = 44.0;

    private const ANCHO_GRADO_LABEL = 13.2;

    private const ANCHO_GRADO_VALOR = 8.8;

    private const ANCHO_DIVISION_LABEL = 13.2;

    private const ANCHO_DIVISION_VALOR = 8.8;

    private const ANCHO_NOTA = 5.0;

    private const ANCHO_DNI = 15.0;

    private const ANCHO_OBS = 25.0;

    private const ALTURA_FILA_ALUMNO = 4.0;

    private const ALTURA_ENC_MATERIAS = 52.0;

    private const Y_ENC_MATERIAS = 61.0;

    private const X_INICIO_MATERIAS = self::MARGEN_IZQ + self::ANCHO_NRO + self::ANCHO_NOMBRE;

    private const FUENTE_ENC_MATERIA_MAX = 5.0;

    private const FUENTE_ENC_MATERIA_MIN = 3.5;

    /** @var array<string, mixed> */
    private array $contexto;

    /** @var list<array<string, mixed>> */
    private array $secciones;

    /**
     * @param  array<string, mixed>  $contexto  Salida de {@see PlanillaCalificacionesPrimarioDatos::contextoPdf()}
     * @param  list<array<string, mixed>>  $secciones
     */
    private function __construct(array $contexto, array $secciones)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->contexto = $contexto;
        $this->secciones = $secciones;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Planilla de Calificaciones');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetLeftMargin(self::MARGEN_IZQ);
        $this->SetFillColor(...self::FILL_GRIS);
        $this->SetLineWidth(0.2);
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @param  list<array<string, mixed>>  $secciones
     */
    public static function generar(array $contexto, array $secciones): self
    {
        $pdf = new self($contexto, $secciones);
        foreach ($secciones as $sec) {
            $pdf->dibujarHoja($sec);
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
     * @param  array<string, mixed>  $sec
     */
    private function dibujarHoja(array $sec): void
    {
        $this->AddPage();
        $this->dibujarEncabezado($sec);
        $yFinCuerpo = $this->dibujarCuerpo($sec);
        $this->dibujarPie($yFinCuerpo);
    }

    /**
     * @param  array<string, mixed>  $sec
     * @return array{cantCurr: int, cantInst: int, anchoCurr: float, anchoInst: float, xDni: float, xObs: float}
     */
    private function layoutMaterias(array $sec): array
    {
        $cantCurr = count($sec['materiasCurriculares'] ?? []);
        $cantInst = count($sec['materiasInstitucionales'] ?? []);
        $anchoCurr = $cantCurr * self::ANCHO_NOTA;
        $anchoInst = $cantInst * self::ANCHO_NOTA;
        $xDni = self::X_INICIO_MATERIAS + $anchoCurr + $anchoInst;
        $xObs = $xDni + self::ANCHO_DNI;

        return [
            'cantCurr' => $cantCurr,
            'cantInst' => $cantInst,
            'anchoCurr' => $anchoCurr,
            'anchoInst' => $anchoInst,
            'xDni' => $xDni,
            'xObs' => $xObs,
        ];
    }

    /**
     * @param  array<string, mixed>  $sec
     */
    private function dibujarEncabezado(array $sec): void
    {
        $escudo = PlanillaCalificacionesPrimarioDatos::rutaEscudoProvincia();
        if ($escudo !== null) {
            $this->Image($escudo, 45, 11, 20, 20, '', '', '', false, 300);
        }

        $cicloGrado = (string) ($sec['cicloGrado'] ?? 'PRIMERO');
        $layout = $this->layoutMaterias($sec);

        $this->SetXY(120, 12);
        TcpdfFuenteArial::aplicar($this, 'B', 16);
        $this->Cell(50, 8, 'Planilla de Calificaciones', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 13);
        $tituloCiclo = $cicloGrado === 'PRIMERO' ? 'Primer Ciclo' : 'Segundo Ciclo';
        $this->Cell(50, 6, $tituloCiclo, 1, 2, 'C');

        $this->SetXY(30, 30);
        TcpdfFuenteArial::aplicar($this, 'B', 6);
        $this->Cell(50, 3, 'GOBIERNO DE LA PROVINCIA DE CÓRDOBA', 0, 2, 'C');
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(50, 3, 'MINISTERIO DE EDUCACIÓN', 0, 2, 'C');
        $this->Cell(50, 3, 'SECRETARÍA DE EDUCACIÓN', 0, 2, 'C');
        $this->Cell(50, 3, 'DIRECCIÓN GENERAL DE INSTITUTOS PRIVADOS DE ENSEÑANZA', 0, 2, 'C');

        $insti = (string) ($this->contexto['insti'] ?? '');
        $categoria = (string) ($this->contexto['categoria'] ?? '');
        $localidad = (string) ($this->contexto['localidad'] ?? '');
        $direccion = (string) ($this->contexto['direccion'] ?? '');
        $departamento = (string) ($this->contexto['departamento'] ?? '');

        $this->SetXY(100, 30);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(50, 4, 'CENTRO EDUCATIVO:  '.$insti, 0, 2, 'L');
        $this->Cell(50, 4, 'Categoría:  '.$categoria, 0, 2, 'L');
        $this->Cell(50, 4, 'Localidad:  '.$localidad, 0, 2, 'L');

        $this->SetXY(150, 34);
        $this->Cell(50, 4, 'Domicilio:  '.$direccion, 0, 2, 'L');
        $this->Cell(50, 4, 'Departamento:  '.$departamento, 0, 2, 'L');

        $etapaEtiqueta = (string) ($this->contexto['etapaEtiqueta'] ?? 'PRIMERA');
        $anoLetras = (string) ($this->contexto['anoLetras'] ?? '');
        $lineaCiclo = 'CICLO:  '.$cicloGrado
            .'                                       ETAPA: '.$etapaEtiqueta
            .'                                                 CORRESPONDIENTE AL AÑO: '.$anoLetras;

        $this->SetXY(self::MARGEN_IZQ, 50);
        $this->Cell(self::ANCHO_UTIL, 4, $lineaCiclo, 0, 2, 'L');

        $grado = (string) ($sec['grado'] ?? '');
        $division = (string) ($sec['division'] ?? '');

        $this->SetX(self::MARGEN_IZQ);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(self::ANCHO_NRO, 7, '', 1, 0, 'C');
        $this->Cell(self::ANCHO_GRADO_LABEL, 7, 'Grado', 1, 0, 'C');
        $this->Cell(self::ANCHO_GRADO_VALOR, 7, $grado, 1, 0, 'C');
        $this->Cell(self::ANCHO_DIVISION_LABEL, 7, 'División', 1, 0, 'C');
        $this->Cell(self::ANCHO_DIVISION_VALOR, 7, $division, 1, 0, 'C');

        $anchoCurr = max($layout['anchoCurr'], self::ANCHO_NOTA);
        if ($layout['cantCurr'] > 0) {
            $anchoCurr = $layout['anchoCurr'];
            $this->Cell($anchoCurr, 7, 'Espacios Curriculares', 1, 0, 'C');
        }

        $xInst = $this->GetX();
        $yInst = $this->GetY();
        $altoFilaEnc = 7.0;
        $anchoInst = $layout['anchoInst'];
        if ($layout['cantInst'] > 0) {
            $anchoInst = max($layout['anchoInst'], self::ANCHO_NOTA);
            $this->dibujarEtiquetaInstitucionalEncabezado($xInst, $yInst, $anchoInst, $altoFilaEnc);
            $this->SetXY($xInst + $anchoInst, $yInst);
        } elseif ($layout['cantCurr'] === 0) {
            $this->dibujarEtiquetaInstitucionalEncabezado($xInst, $yInst, self::ANCHO_NOTA, $altoFilaEnc);
            $this->SetXY($xInst + self::ANCHO_NOTA, $yInst);
        }

        $this->SetXY($layout['xDni'], $yInst);
        $this->Cell(self::ANCHO_DNI, $altoFilaEnc, 'D.N.I.', 1, 0, 'C');
        $this->Cell(self::ANCHO_OBS, $altoFilaEnc, 'Observaciones', 1, 0, 'C');
        $this->Ln($altoFilaEnc);
    }

    private function dibujarEtiquetaInstitucionalEncabezado(float $x, float $y, float $ancho, float $alto): void
    {
        $this->Rect($x, $y, $ancho, $alto);
        TcpdfFuenteArial::aplicar($this, '', 5);
        $this->MultiCell(
            $ancho,
            $alto / 2,
            "Espacios Curric. Proyecto\ninstitucional",
            0,
            'C',
            false,
            0,
            $x,
            $y,
            true,
            0,
            false,
            true,
            $alto,
            'M',
        );
    }

    /**
     * @param  array<string, mixed>  $sec
     */
    private function dibujarCuerpo(array $sec): float
    {
        /** @var list<array{materia: string, abrev: string}> $materiasCurriculares */
        $materiasCurriculares = $sec['materiasCurriculares'] ?? [];
        /** @var list<array{materia: string, abrev: string}> $materiasInstitucionales */
        $materiasInstitucionales = $sec['materiasInstitucionales'] ?? [];
        /** @var list<array{nro: int, nombre: string, dni: string, obsAnual: string, notas: list<string>}> $alumnos */
        $alumnos = $sec['alumnos'] ?? [];
        $layout = $this->layoutMaterias($sec);
        $cantMate = $layout['cantCurr'] + $layout['cantInst'];
        $yInicioFilas = self::Y_ENC_MATERIAS + self::ALTURA_ENC_MATERIAS - self::ALTURA_FILA_ALUMNO;

        $this->SetXY(self::MARGEN_IZQ, self::Y_ENC_MATERIAS);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(self::ANCHO_NRO, self::ALTURA_ENC_MATERIAS, 'Nº', 1, 0, 'C');
        $this->Cell(self::ANCHO_NOMBRE, self::ALTURA_ENC_MATERIAS, 'APELLIDO Y NOMBRES', 1, 0, 'C');

        $x = self::X_INICIO_MATERIAS;
        foreach ($materiasCurriculares as $m) {
            $xCentro = $x + (self::ANCHO_NOTA / 2);
            $this->SetXY($x, self::Y_ENC_MATERIAS);
            $this->Cell(self::ANCHO_NOTA, self::ALTURA_ENC_MATERIAS, '', 1, 0, 'C');
            $this->dibujarTextoVerticalEnCelda(
                $xCentro,
                self::Y_ENC_MATERIAS,
                self::ALTURA_ENC_MATERIAS,
                trim((string) ($m['materia'] ?? '')),
            );
            $x += self::ANCHO_NOTA;
        }

        foreach ($materiasInstitucionales as $m) {
            $xCentro = $x + (self::ANCHO_NOTA / 2);
            $this->SetXY($x, self::Y_ENC_MATERIAS);
            $this->Cell(self::ANCHO_NOTA, self::ALTURA_ENC_MATERIAS, '', 1, 0, 'C');
            $this->dibujarTextoVerticalEnCelda(
                $xCentro,
                self::Y_ENC_MATERIAS,
                self::ALTURA_ENC_MATERIAS,
                trim((string) ($m['materia'] ?? '')),
            );
            $x += self::ANCHO_NOTA;
        }

        $this->SetXY($layout['xDni'], self::Y_ENC_MATERIAS);
        $this->Cell(self::ANCHO_DNI, self::ALTURA_ENC_MATERIAS, '', 1, 0, 'C');
        $this->Cell(self::ANCHO_OBS, self::ALTURA_ENC_MATERIAS, '', 1, 0, 'C');

        $etapa = (int) ($this->contexto['etapa'] ?? 1);
        $esApreciacionFinal = $etapa === CalificacionesPrimarioCatalogo::ETAPA_APRECIACION_FINAL;

        $f = 0;
        foreach ($alumnos as $alumno) {
            $f++;
            $y = $yInicioFilas + ($f * self::ALTURA_FILA_ALUMNO);

            $this->SetXY(self::MARGEN_IZQ, $y);
            TcpdfFuenteArial::aplicar($this, '', 6);
            $this->Cell(self::ANCHO_NRO, self::ALTURA_FILA_ALUMNO, (string) ($alumno['nro'] ?? $f), 1, 0, 'C');
            $this->Cell(self::ANCHO_NOMBRE, self::ALTURA_FILA_ALUMNO, (string) ($alumno['nombre'] ?? ''), 1, 0, 'L');

            $this->SetXY(self::X_INICIO_MATERIAS, $y);
            $notas = is_array($alumno['notas'] ?? null) ? $alumno['notas'] : [];
            for ($i = 0; $i < $cantMate; $i++) {
                $valor = (string) ($notas[$i] ?? '');
                $resaltar = $esApreciacionFinal && $i < 3 && $i < $layout['cantCurr'];
                if ($resaltar) {
                    $this->SetFillColor(...self::FILL_GRIS);
                }
                $this->Cell(self::ANCHO_NOTA, self::ALTURA_FILA_ALUMNO, $valor, 1, 0, 'C', $resaltar);
                if ($resaltar) {
                    $this->SetFillColor(255, 255, 255);
                }
            }

            $this->SetXY($layout['xDni'], $y);
            $this->Cell(self::ANCHO_DNI, self::ALTURA_FILA_ALUMNO, (string) ($alumno['dni'] ?? ''), 1, 0, 'C');
            $this->dibujarCeldaObservaciones($alumno, $esApreciacionFinal, $layout['xObs'], $y);
        }

        if ($f === 0) {
            return $yInicioFilas + self::ALTURA_FILA_ALUMNO;
        }

        return $yInicioFilas + ($f * self::ALTURA_FILA_ALUMNO) + self::ALTURA_FILA_ALUMNO;
    }

    /**
     * @param  array<string, mixed>  $alumno
     */
    private function dibujarCeldaObservaciones(array $alumno, bool $esApreciacionFinal, float $xObs, float $y): void
    {
        $this->SetXY($xObs, $y);

        if (! $esApreciacionFinal) {
            $this->Cell(self::ANCHO_OBS, self::ALTURA_FILA_ALUMNO, '', 1, 0, 'L');

            return;
        }

        $obs = (string) ($alumno['obsAnual'] ?? '');
        if (strlen($obs) > 28) {
            TcpdfFuenteArial::aplicar($this, '', 4);
            $this->MultiCell(self::ANCHO_OBS, 2, $obs, 1, 'L');
        } else {
            TcpdfFuenteArial::aplicar($this, '', 6);
            $this->Cell(self::ANCHO_OBS, self::ALTURA_FILA_ALUMNO, $obs, 1, 0, 'L');
        }
    }

    private function dibujarTextoVerticalEnCelda(float $xCentro, float $yCelda, float $altoCelda, string $texto): void
    {
        if ($texto === '') {
            return;
        }

        $fuentePt = self::FUENTE_ENC_MATERIA_MAX;
        $maxLong = $altoCelda - 3.0;

        TcpdfFuenteArial::aplicar($this, 'I', $fuentePt);
        $longitud = $this->GetStringWidth($texto);
        while ($longitud > $maxLong && $fuentePt > self::FUENTE_ENC_MATERIA_MIN) {
            $fuentePt -= 0.25;
            TcpdfFuenteArial::aplicar($this, 'I', $fuentePt);
            $longitud = $this->GetStringWidth($texto);
        }

        $yCentro = $yCelda + ($altoCelda / 2);
        $yAncla = $yCentro + ($longitud / 2);

        $this->StartTransform();
        $this->Rotate(90, $xCentro, $yAncla);
        $this->Text($xCentro - 0.5, $yAncla - 0.5, $texto);
        $this->StopTransform();
    }

    private function dibujarPie(float $yTabla): void
    {
        $this->Ln(6);
        $x = $this->GetX();
        $y = max($yTabla + 6, $this->GetY());

        $this->SetXY($x + 35, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 6);
        $this->MultiCell(25, 3, "Escala de Calificaciones\nde los Aprendizajes", 1, 'C');

        $this->SetXY($x + 35, $y + 9);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->MultiCell(25, 3, "Excelente (E)\nMuy Bueno (MB)\nBueno (B)\nSatisfactorio (S)\nNo Satisfactorio (NS)\n", 1, 'C');

        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->SetXY($x + 70, $y);
        $this->Cell(40, 5, 'Para uso alumnos de 6º', 1, 2, 'C');
        $this->Cell(25, 4, 'RESUMEN FINAL', 1, 0, 'C');
        $this->Cell(5, 4, 'V', 1, 0, 'C');
        $this->Cell(5, 4, 'M', 1, 0, 'C');
        $this->Cell(5, 4, 'T', 1, 2, 'C');

        $this->SetXY($x + 70, $y + 9);
        $this->Cell(25, 4, 'Aprobado', 1, 0, 'C');
        $this->Cell(5, 4, '', 1, 0, 'C');
        $this->Cell(5, 4, '', 1, 0, 'C');
        $this->Cell(5, 4, '', 1, 2, 'C');

        $this->SetXY($x + 70, $y + 13);
        $this->Cell(25, 4, 'No Aprobado', 1, 0, 'C');
        $this->Cell(5, 4, '', 1, 0, 'C');
        $this->Cell(5, 4, '', 1, 0, 'C');
        $this->Cell(5, 4, '', 1, 2, 'C');

        $this->SetXY($x + 70, $y + 17);
        $this->Cell(25, 4, '% Aprobado', 1, 0, 'C');
        $this->Cell(5, 4, '', 1, 0, 'C');
        $this->Cell(5, 4, '', 1, 0, 'C');
        $this->Cell(5, 4, '', 1, 2, 'C');

        $this->SetXY($x + 70, $y + 21);
        $this->Cell(25, 4, 'Total de Alumnos', 1, 0, 'C');
        $this->Cell(5, 4, '', 1, 0, 'C');
        $this->Cell(5, 4, '', 1, 0, 'C');
        $this->Cell(5, 4, '', 1, 2, 'C');

        $this->SetXY($x + 120, $y);
        $this->Cell(5, 4, 'Lugar: ................................................................', 0, 2, 'L');
        $this->Cell(5, 4, 'Fecha: ................. de ....................................de 20  ...........', 0, 2, 'L');

        $this->SetXY($x + 120, $y + 18);
        $this->Cell(3, 3, '............................                                         .............................', 0, 2, 'L');
        $this->Cell(3, 3, 'Mestra/o de Grado                                               Director/a', 0, 2, 'L');
    }
}
