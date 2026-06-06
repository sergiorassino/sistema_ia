<?php

namespace App\Support\Examenes;

use Carbon\Carbon;
use TCPDF;

/**
 * Acta de compromiso para matriculación con tres espacios curriculares pendientes (tercer materia).
 * Formato legacy FPDF → TCPDF.
 */
final class ActaCompromisoTercerMateriaTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const MARGEN_DER = 15.0;

    private const ANCHO_UTIL = 170.0;

    private const FUENTE = 'dejavusans';

    private const ALTURA_CAJA_ENCABEZADO = 26.0;

    private const LOGO_ANCHO = 15.0;

    private const LOGO_ALTO = 20.0;

    /** @var array{
     *     instiNombre: string,
     *     direccion: string,
     *     localidad: string,
     *     cue: string,
     *     ee: string,
     *     fechaEmision: string,
     *     apenom: string,
     *     dni: string,
     *     nombreTercerMateria: string,
     *     nombreCursoTercerMateria: string,
     *     cursoActual: string,
     *     logo_abs: ?string
     * } */
    private array $datos;

    /**
     * @param  array{
     *     instiNombre: string,
     *     direccion: string,
     *     localidad: string,
     *     cue: string,
     *     ee: string,
     *     fechaEmision: string,
     *     apenom: string,
     *     dni: string,
     *     nombreTercerMateria: string,
     *     nombreCursoTercerMateria: string,
     *     cursoActual: string,
     *     logo_abs: ?string
     * }  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->datos = $datos;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Acta de compromiso — Tercer materia');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(true, 15);
        $this->SetMargins(self::MARGEN_IZQ, 10, self::MARGEN_DER);
        $this->SetFillColor(232, 232, 232);
        $this->SetLineWidth(0.2);
    }

    /**
     * @param  array{
     *     instiNombre: string,
     *     direccion: string,
     *     localidad: string,
     *     cue: string,
     *     ee: string,
     *     fechaEmision: string,
     *     apenom: string,
     *     dni: string,
     *     nombreTercerMateria: string,
     *     nombreCursoTercerMateria: string,
     *     cursoActual: string,
     *     logo_abs: ?string
     * }  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage();
        $pdf->dibujarDocumento();

        return $pdf;
    }

    private function dibujarDocumento(): void
    {
        $this->dibujarEncabezado();
        $this->dibujarCuerpoNormativo();
        $this->dibujarFecha();
        $this->dibujarTextoPadres();
        $this->dibujarFirmasPadres();
        $this->dibujarCompromisoEstudiante();
        $this->dibujarFirmaEstudiante();
    }

    private function dibujarEncabezado(): void
    {
        $this->dibujarHeaderInstitucional();
        $this->dibujarTituloDocumento();
    }

    /** Encabezado institucional (mismo criterio que `pdf/partials/header.blade.php`). */
    private function dibujarHeaderInstitucional(): void
    {
        $y0 = 10.0;
        $logo = $this->datos['logo_abs'] ?? null;
        $insti = trim($this->datos['instiNombre']);
        $direccion = trim($this->datos['direccion']);
        $localidad = trim($this->datos['localidad']);

        $lineaDir = $direccion;
        if ($direccion !== '' && $localidad !== '') {
            $lineaDir .= ' — '.$localidad;
        } elseif ($localidad !== '') {
            $lineaDir = $localidad;
        }

        $cue = trim($this->datos['cue']);
        $ee = trim($this->datos['ee']);
        $lineaIds = '';
        if ($cue !== '') {
            $lineaIds = 'CUE: '.$cue;
        }
        if ($ee !== '') {
            $lineaIds .= ($lineaIds !== '' ? '   ' : '').'EE: '.$ee;
        }

        $this->Rect(self::MARGEN_IZQ, $y0, self::ANCHO_UTIL, self::ALTURA_CAJA_ENCABEZADO, 'D');

        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $this->Image(
                $logo,
                self::MARGEN_IZQ + 5,
                $y0 + 3,
                self::LOGO_ANCHO,
                self::LOGO_ALTO,
                '',
                '',
                '',
                false,
                300,
            );
        }

        $this->SetXY(self::MARGEN_IZQ, $y0 + 4);
        $this->SetFont(self::FUENTE, 'B', 12);
        $this->Cell(self::ANCHO_UTIL, 6, $insti !== '' ? $insti : 'Institución educativa', 0, 2, 'C');

        if ($lineaDir !== '') {
            $this->SetFont(self::FUENTE, '', 9);
            $this->Cell(self::ANCHO_UTIL, 4.5, $lineaDir, 0, 2, 'C');
        }

        if ($lineaIds !== '') {
            $this->SetFont(self::FUENTE, '', 6.5);
            $this->Cell(self::ANCHO_UTIL, 4, $lineaIds, 0, 2, 'C');
        }

        $this->SetY($y0 + self::ALTURA_CAJA_ENCABEZADO + 2);
    }

    private function dibujarTituloDocumento(): void
    {
        $this->SetFont(self::FUENTE, '', 9);
        $this->Cell(self::ANCHO_UTIL, 5, 'ACTA COMPROMISO PARA LA MATRICULACIÓN', 0, 2, 'C');
        $this->Cell(self::ANCHO_UTIL, 5, 'CON TRES (3) ESPACIOS CURRICULARES PENDIENTES', 0, 2, 'C');
        $this->Ln(8);
    }

    private function dibujarCuerpoNormativo(): void
    {
        $texto = 'Resolución D.G.I.P.E. Nº 0010/10 - Anexo I y sus rectificativas y aclaratorias, Resoluciones de la D.G.I.P.E. Nº 0018/10 y Nº 0026/11
2.2.3.- Los alumnos que al momento de matricularse adeuden tres (3) asignaturas y habiéndose presentado a rendir, al menos en una de las tres disciplinas en el turno Febrero-Marzo, podrán cursar el curso inmediato superior a condición de que cumplimente en una (1) de las tres (3), con el plan de trabajo que el profesor de esa disciplina elaborará a tal fin, previa Acta de Compromiso con el padre, madre o tutor.
2.2.3.1.- Para cumplimentar el punto anterior, el profesor deberá preparar un Plan de Trabajo que exigirá al alumno producciones escritas que se ajustarán a las características propias de la asignatura. El docente hará una evaluación de las mismas requiriendo que efectúe, si así correspondiere, las correcciones pertinentes.
2.2.3.2.- El alumno deberá cumplimentar con dos (2) trabajos prácticos por trimestre. Aquellos alumnos que realizaron y aprobaron el 80% de los trabajos podrán acreditar dicha asignatura en la instancia de Coloquio.
2.2.3.3.- De no cumplimentar con los trabajos acordados en el punto anterior el alumno deberá acreditar la asignatura en el turno febrero-marzo ante mesa examinadora.';

        $this->SetFont(self::FUENTE, 'I', 9);
        $this->MultiCell(self::ANCHO_UTIL, 5, $texto, 0, 'J');
        $this->Ln(12);
    }

    private function dibujarFecha(): void
    {
        $localidad = trim($this->datos['localidad']);
        $fecha = trim($this->datos['fechaEmision']);
        $linea = ($localidad !== '' ? $localidad.' ' : '').$fecha;

        $this->SetFont(self::FUENTE, '', 9);
        $this->Cell(self::ANCHO_UTIL, 5, $linea, 0, 1, 'R');
        $this->Ln(12);
    }

    private function dibujarTextoPadres(): void
    {
        $d = $this->datos;
        $texto = 'Los abajo firmantes, padres del/a estudiante '.$d['apenom'].' D.N.I. Nº '.$d['dni']
            .', hemos solicitado al Equipo Directivo de '.$d['instiNombre'].', la Matrícula para nuestro/a hijo/a para cursar '
            .$d['cursoActual'].' durante el presente Ciclo Lectivo, habiendo optado por realizar el Plan de Trabajo necesario para cumplimentar el espacio curricular '
            .$d['nombreTercerMateria'].' de '.$d['nombreCursoTercerMateria']
            .' asumiendo el compromiso de acompañarlo/a y exigirle el cumplimiento de lo que establece la Resolución D.G.I.P.E. 0010/10 (y sus rectificativas, aclaratorias y ampliatorias, Resoluciones de la D.G.I.P.E. 0018/10 y 0026/11), cuyo fragmento hemos leído en la presente y declaramos conocer';

        $this->SetFont(self::FUENTE, '', 9);
        $this->MultiCell(self::ANCHO_UTIL, 5, $texto, 0, 'J');
        $this->Ln(20);
    }

    private function dibujarFirmasPadres(): void
    {
        $this->SetFont(self::FUENTE, '', 9);
        $this->Cell(85, 5, '...........................................................', 0, 0, 'C');
        $this->Cell(85, 5, '...........................................................', 0, 0, 'C');
        $this->Ln(4);

        $this->SetFont(self::FUENTE, '', 6);
        $this->Cell(85, 5, 'Firma de la Madre/Tutora', 0, 0, 'C');
        $this->Cell(85, 5, 'Firma del Padre/Tutor', 0, 0, 'C');
        $this->Ln(7);

        $this->SetFont(self::FUENTE, '', 9);
        $this->Cell(85, 5, '...........................................................', 0, 0, 'C');
        $this->Cell(85, 5, '...........................................................', 0, 0, 'C');
        $this->Ln(4);

        $this->SetFont(self::FUENTE, '', 6);
        $this->Cell(85, 5, 'Aclaración', 0, 0, 'C');
        $this->Cell(85, 5, 'Aclaración', 0, 0, 'C');
        $this->Ln(7);

        $this->SetFont(self::FUENTE, '', 9);
        $this->Cell(85, 5, '...........................................................', 0, 0, 'C');
        $this->Cell(85, 5, '...........................................................', 0, 0, 'C');
        $this->Ln(4);

        $this->SetFont(self::FUENTE, '', 6);
        $this->Cell(85, 5, 'D.N.I.', 0, 0, 'C');
        $this->Cell(85, 5, 'D.N.I.', 0, 0, 'C');
        $this->Ln(7);
    }

    private function dibujarCompromisoEstudiante(): void
    {
        $d = $this->datos;
        $texto = 'Yo, '.$d['apenom'].', DNI '.$d['dni'].', de '.$d['cursoActual']
            .', me comprometo a cumplir con el Plan de Trabajo para cumplimentar el espacio curricular '
            .$d['nombreTercerMateria'].', en la forma y términos establecidos por la Institución.';

        $this->SetFont(self::FUENTE, '', 9);
        $this->MultiCell(self::ANCHO_UTIL, 5, $texto, 0, 'J');
        $this->Ln(15);
    }

    private function dibujarFirmaEstudiante(): void
    {
        $this->SetX(110);
        $this->SetFont(self::FUENTE, '', 9);
        $this->Cell(50, 5, '...........................................................', 0, 0, 'R');
        $this->Ln(4);
        $this->SetFont(self::FUENTE, '', 6);
        $this->SetX(110);
        $this->Cell(50, 5, 'Firma Estudiante', 0, 0, 'R');
    }

    /**
     * @param  array{
     *     instiNombre: string,
     *     direccion: string,
     *     localidad: string,
     *     cue: string,
     *     ee: string,
     *     fechaEmision: string,
     *     apenom: string,
     *     dni: string,
     *     nombreTercerMateria: string,
     *     nombreCursoTercerMateria: string,
     *     cursoActual: string,
     *     logo_abs: ?string
     * }  $datosActa
     */
    public static function datosDesdeContexto(array $datosActa, ?string $fechaYmd = null): array
    {
        $header = schoolPdfHeaderData();
        $insti = trim((string) ($header['insti'] ?? ''));
        $localidad = trim((string) ($header['localidad'] ?? ''));

        $fecha = $fechaYmd !== null && $fechaYmd !== ''
            ? Carbon::createFromFormat('Y-m-d', $fechaYmd)->format('d/m/Y')
            : now()->format('d/m/Y');

        return array_merge($datosActa, [
            'instiNombre' => $insti !== '' ? mb_strtoupper($insti, 'UTF-8') : 'Institución educativa',
            'direccion' => trim((string) ($header['direccion'] ?? '')),
            'localidad' => $localidad,
            'cue' => trim((string) ($header['cue'] ?? '')),
            'ee' => trim((string) ($header['ee'] ?? '')),
            'fechaEmision' => $fecha,
            'logo_abs' => $header['logo_file'] ?? null,
        ]);
    }
}
