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

    private const FILL_GRIS = [232, 232, 232];

    private const ANCHO_NRO = 7.0;

    private const ANCHO_NOMBRE = 50.0;

    private const ANCHO_NOTA = 5.0;

    private const ANCHO_DNI = 15.0;

    private const ANCHO_OBS = 25.0;

    private const ALTURA_FILA_ALUMNO = 4.0;

    private const ALTURA_ENC_MATERIAS = 40.0;

    private const Y_INICIO_FILAS = 97.0;

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
     */
    private function dibujarEncabezado(array $sec): void
    {
        $escudo = PlanillaCalificacionesPrimarioDatos::rutaEscudoProvincia();
        if ($escudo !== null) {
            $this->Image($escudo, 45, 11, 20, 20, '', '', '', false, 300);
        }

        $cicloGrado = (string) ($sec['cicloGrado'] ?? 'PRIMERO');
        $esCicloPrimero = (bool) ($sec['esCicloPrimero'] ?? true);

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
        $this->Cell(180, 4, $lineaCiclo, 0, 2, 'L');

        $grado = (string) ($sec['grado'] ?? '');
        $division = (string) ($sec['division'] ?? '');

        $this->SetX(self::MARGEN_IZQ);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(7, 7, '', 1, 0, 'C');
        $this->Cell(15, 7, 'Grado', 1, 0, 'C');
        $this->Cell(10, 7, $grado, 1, 0, 'C');
        $this->Cell(15, 7, 'División', 1, 0, 'C');
        $this->Cell(10, 7, $division, 1, 0, 'C');

        $anchoEspacios = $esCicloPrimero ? 50.0 : 55.0;
        $this->Cell($anchoEspacios, 7, 'Espacios Curriculares', 1, 0, 'C');

        $x = $this->GetX();
        $y = $this->GetY();

        if ($esCicloPrimero) {
            $this->MultiCell(20, 3.5, 'Espac. Curric. Proyecto Instit.', 1, 'C');
            $this->SetXY($x + 20, $y);
        } else {
            $this->MultiCell(30, 3.5, 'Espacios Curriculares Proyecto Instititucional', 1, 'C');
            $this->SetXY($x + 30, $y);
        }

        $this->Cell(10, 7, '', 1, 0, 'C');
        $this->Cell(self::ANCHO_DNI, 7, 'D.N.I.', 1, 0, 'C');
        $this->Cell(self::ANCHO_OBS, 7, 'Observaciones', 1, 0, 'C');
        $this->Ln(20);
    }

    /**
     * @param  array<string, mixed>  $sec
     */
    private function dibujarCuerpo(array $sec): float
    {
        /** @var list<array{materia: string, abrev: string}> $materias */
        $materias = $sec['materias'] ?? [];
        /** @var list<array{nro: int, nombre: string, dni: string, obsAnual: string, notas: list<string>}> $alumnos */
        $alumnos = $sec['alumnos'] ?? [];
        $cantMate = count($materias);

        $this->SetXY(self::MARGEN_IZQ, 61);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(self::ANCHO_NRO, self::ALTURA_ENC_MATERIAS, 'Nº', 1, 0, 'C');
        $this->Cell(self::ANCHO_NOMBRE, self::ALTURA_ENC_MATERIAS, 'APELLIDO Y NOMBRES', 1, 0, 'C');

        $xBase = 70.0;
        $x = $xBase;
        foreach ($materias as $m) {
            $x += 5.0;
            $nombre = trim((string) ($m['materia'] ?? ''));
            $this->SetXY($x - 3, 61);
            $this->Cell(self::ANCHO_NOTA, self::ALTURA_ENC_MATERIAS, '', 1, 0, 'C');
            $this->dibujarTextoVertical($x, 100, $nombre);
        }

        $xFinMaterias = 72.0 + ($cantMate * self::ANCHO_NOTA);
        $this->SetXY($xFinMaterias, 61);
        $this->Cell(self::ANCHO_DNI, self::ALTURA_ENC_MATERIAS, '', 1, 0, 'C');
        $this->Cell(self::ANCHO_OBS, self::ALTURA_ENC_MATERIAS, '', 1, 0, 'C');

        $etapa = (int) ($this->contexto['etapa'] ?? 1);
        $esApreciacionFinal = $etapa === PlanillaCalificacionesPrimarioDatos::ETAPA_APRECIACION_FINAL;

        $f = 0;
        foreach ($alumnos as $alumno) {
            $f++;
            $y = self::Y_INICIO_FILAS + ($f * self::ALTURA_FILA_ALUMNO);

            $this->SetXY(self::MARGEN_IZQ, $y);
            TcpdfFuenteArial::aplicar($this, '', 6);
            $this->Cell(self::ANCHO_NRO, self::ALTURA_FILA_ALUMNO, (string) ($alumno['nro'] ?? $f), 1, 0, 'C');
            $this->Cell(self::ANCHO_NOMBRE, self::ALTURA_FILA_ALUMNO, (string) ($alumno['nombre'] ?? ''), 1, 0, 'L');

            $this->SetXY(72, $y);
            $notas = is_array($alumno['notas'] ?? null) ? $alumno['notas'] : [];
            for ($i = 0; $i < $cantMate; $i++) {
                $valor = (string) ($notas[$i] ?? '');
                $resaltar = $esApreciacionFinal && $i < 3;
                if ($resaltar) {
                    $this->SetFillColor(...self::FILL_GRIS);
                }
                $this->Cell(self::ANCHO_NOTA, self::ALTURA_FILA_ALUMNO, $valor, 1, 0, 'C', $resaltar);
                if ($resaltar) {
                    $this->SetFillColor(255, 255, 255);
                }
            }

            $this->Cell(self::ANCHO_DNI, self::ALTURA_FILA_ALUMNO, (string) ($alumno['dni'] ?? ''), 1, 0, 'C');
            $this->dibujarCeldaObservaciones($alumno, $esApreciacionFinal);
        }

        if ($f === 0) {
            return self::Y_INICIO_FILAS + self::ALTURA_FILA_ALUMNO;
        }

        return self::Y_INICIO_FILAS + ($f * self::ALTURA_FILA_ALUMNO) + self::ALTURA_FILA_ALUMNO;
    }

    /**
     * @param  array<string, mixed>  $alumno
     */
    private function dibujarCeldaObservaciones(array $alumno, bool $esApreciacionFinal): void
    {
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

    private function dibujarTextoVertical(float $x, float $yBase, string $texto): void
    {
        if ($texto === '') {
            return;
        }

        TcpdfFuenteArial::aplicar($this, 'I', 5);
        $this->StartTransform();
        $this->Rotate(90, $x, $yBase);
        $this->Text($x - 1, $yBase - 1, $texto);
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
        $this->Cell(3, 3, 'Mestra de Grado                                               Director/a', 0, 2, 'L');
    }
}
