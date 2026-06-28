<?php

namespace App\Support\CalificacionesPrimario\Epq;

use App\Support\Pdf\TcpdfFuenteArial;
use Illuminate\Http\Response;
use TCPDF;

/**
 * Boletín (Prim) EPQ — A4 apaisado, anverso y reverso (layout legacy ScriptCase).
 */
final class BoletinPrimEpqTcpdf extends TCPDF
{
    /** @param  array<string, mixed>  $datos */
    private function __construct(private array $datos)
    {
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8', false);
        $this->SetCreator('Sistema Escolar');
        $this->SetTitle('Boletín Primario EPQ');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetMargins(0, 0, 0);
        $this->SetAutoPageBreak(false);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generarCompleto(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage();
        $pdf->dibujarAnverso();
        $pdf->AddPage();
        $pdf->dibujarReverso();

        return $pdf;
    }

    /**
     * @param  list<array<string, mixed>>  $hojas
     */
    public static function generarLote(array $hojas, string $cara = 'completo'): self
    {
        abort_unless($hojas !== [], 404);

        $pdf = null;
        foreach ($hojas as $datos) {
            if ($pdf === null) {
                $pdf = new self($datos);
            } else {
                $pdf->datos = $datos;
            }

            if ($cara === 'anverso' || $cara === 'completo') {
                $pdf->AddPage();
                $pdf->dibujarAnverso();
            }
            if ($cara === 'reverso' || $cara === 'completo') {
                $pdf->AddPage();
                $pdf->dibujarReverso();
            }
        }

        return $pdf;
    }

    public static function respuestaHttp(self $pdf, string $nombreArchivo): Response
    {
        $contenido = $pdf->Output($nombreArchivo, 'S');

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombreArchivo.'"',
        ]);
    }

    private function dibujarAnverso(): void
    {
        $d = $this->datos;
        $left = 10.0;

        // --- Mitad izquierda: contraportada ---
        $this->SetLeftMargin($left);
        $this->SetXY($left + 24, 10);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(21, 6, 'FECHA DE', 1, 0, 'C');
        $this->Cell(83, 6, 'CAMBIOS DE ESCUELA', 1, 1, 'C');

        TcpdfFuenteArial::aplicar($this, '', 8);
        $yEnc = $this->GetY();
        $this->SetXY($left, $yEnc);
        $this->MultiCell(24, 8, 'Escuela en la que inicia el Año', 1, 'C');
        $this->SetXY($left + 24, $yEnc);
        $this->Cell(10, 8, 'Ingreso', 1, 0, 'C');
        $this->Cell(10, 8, 'Egreso', 1, 0, 'C');
        $this->Cell(10, 8, 'Fecha', 1, 0, 'C');
        $this->Cell(30, 8, 'Causas', 1, 0, 'C');
        $this->Cell(25, 8, 'Pasa a la Escuela', 1, 0, 'C');
        $this->SetXY($left + 109, $yEnc);
        $this->MultiCell(19, 8, 'Firma del/la Director/a', 1, 'C');
        $this->SetXY($left, $yEnc + 8);

        for ($i = 0; $i < 4; $i++) {
            $this->Cell(24, 10, '', 1);
            $this->Cell(10, 10, '', 1);
            $this->Cell(10, 10, '', 1);
            $this->Cell(10, 10, '', 1);
            $this->Cell(30, 10, '', 1);
            $this->Cell(25, 10, '', 1);
            $this->Cell(19, 10, '', 1);
            $this->Ln();
        }

        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->dibujarTablaEscalaCalificaciones($left, 70.0);

        $this->dibujarTablaEscalaConducta($left + 70.0, 70.0);

        $this->SetLeftMargin($left);
        $this->SetXY($left, 115);
        TcpdfFuenteArial::aplicar($this, '', 10);
        foreach (['Cambios de Domicilio:', 'Observaciones:', 'Promedio General:', 'Promovido a:', 'Debe Recuperar:'] as $label) {
            $this->Cell(40, 6, $label, 0);
            $this->Cell(100, 6, '_____________________________________________', 0, 1);
            if ($label === 'Cambios de Domicilio:' || $label === 'Observaciones:') {
                $this->Cell(100, 6, '_________________________________________________________________', 0, 1);
            }
            $this->Ln(1);
        }

        $this->Ln(35);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->SetLeftMargin(20);
        $this->Cell(40, 4, 'Firma del Docente', 'T', 0, 'C');
        $this->SetLeftMargin(80);
        $this->Cell(50, 4, 'Firma de la Directora', 'T', 0, 'C');

        // --- Mitad derecha: portada ---
        $this->SetLeftMargin(160);
        $this->Rect(150, 10, 140, 190, 'D');

        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->SetY(15);
        $this->Cell(130, 5, 'MINISTERIO DE EDUCACIÓN', 0, 1, 'C');
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(130, 5, 'S.P.E.P.', 0, 1, 'C');
        TcpdfFuenteArial::aplicar($this, 'B', 12);
        $this->Cell(130, 7, 'DOCUMENTO DE EVALUACIÓN', 0, 1, 'C');
        TcpdfFuenteArial::aplicar($this, '', 11);
        $this->Cell(130, 5, 'NIVEL PRIMARIO', 0, 1, 'C');

        $this->Rect(277, 13, 10, 10);

        $yFinTextoSuperior = $this->GetY();
        $yInicioNombreEscuela = 85.0;
        $logoAncho = 30.0;
        $logoAlto = 30.0;
        $panelCentroX = 150.0 + 140.0 / 2;
        $yLogo = $yFinTextoSuperior + (($yInicioNombreEscuela - $yFinTextoSuperior) - $logoAlto) / 2;
        $xLogo = $panelCentroX - $logoAncho / 2;

        $membrete = $d['membrete_portada_file'] ?? null;
        if (is_string($membrete) && is_file($membrete)) {
            $this->Image($membrete, $xLogo, $yLogo, $logoAncho, $logoAlto);
        }

        TcpdfFuenteArial::aplicar($this, 'B', 16);
        $this->SetY($yInicioNombreEscuela);
        $this->Cell(130, 8, (string) ($d['insti'] ?? ''), 0, 1, 'C');
        TcpdfFuenteArial::aplicar($this, '', 12);
        $this->Cell(130, 5, 'PP ESCOLAPIOS', 0, 1, 'C');
        TcpdfFuenteArial::aplicar($this, '', 10);
        $dirLoc = trim((string) ($d['direccion'] ?? ''));
        if ((string) ($d['localidad'] ?? '') !== '') {
            $dirLoc = $dirLoc !== '' ? $dirLoc.' - '.($d['localidad'] ?? '') : (string) ($d['localidad'] ?? '');
        }
        $this->Cell(130, 5, $dirLoc, 0, 1, 'C');
        $this->Cell(130, 5, (string) ($d['provincia'] ?? ''), 0, 1, 'C');

        $this->Ln(5);
        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->Cell(40, 6, 'Grado:    '.($d['nombreCurso'] ?? ''), 0, 0);
        $this->Cell(30, 6, 'Sección:    '.($d['seccion'] ?? ''), 0, 0);
        $this->Cell(50, 6, 'Nº Inscripción:   '.($d['legajo'] ?? ''), 0, 1);
        $this->Cell(25, 6, 'Alumno:        '.($d['apellido'] ?? '').', '.($d['nombre'] ?? ''), 0, 1);
        $this->Cell(73, 6, 'Fecha de Nacimiento:    '.($d['fechnaci'] ?? ''), 0, 0);
        $this->Cell(20, 6, 'DNI Nº:   '.($d['dni'] ?? ''), 0, 1);
        $this->Cell(55, 6, 'Nombre del Padre o Tutor:   '.($d['nombretut'] ?? ''), 0, 1);

        $this->Ln(10);
        TcpdfFuenteArial::aplicar($this, 'B', 12);
        $this->Cell(20, 6, 'AÑO:', 0, 0);
        TcpdfFuenteArial::aplicar($this, '', 12);
        $this->Cell(20, 6, (string) ($d['anoLectivo'] ?? ''), 0, 1);

        $this->Ln(30);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->SetLeftMargin(230);
        $this->Cell(40, 4, 'Firma del Docente', 'T', 0, 'C');
    }

    private function dibujarTablaEscalaCalificaciones(float $x, float $y): void
    {
        $anchoCol1 = 15.0;
        $anchoCol2 = 45.0;
        $altoFila = 7.0;

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell($anchoCol1 + $anchoCol2, $altoFila, 'ESCALA DE CALIFICACIONES', 1, 0, 'C');

        TcpdfFuenteArial::aplicar($this, '', 9);
        $filas = [
            ['10', 'Sobresaliente  S'],
            ['8-9', 'Muy Bueno  MB'],
            ['6-7', 'Bueno  B'],
            ['4-5', 'Regular  R'],
            ['1-2-3', 'Insuficiente  I'],
        ];

        foreach ($filas as $i => $row) {
            $yFila = $y + $altoFila * ($i + 1);
            $this->SetXY($x, $yFila);
            $this->Cell($anchoCol1, $altoFila, $row[0], 1, 0, 'C');
            $this->Cell($anchoCol2, $altoFila, $row[1], 1, 0, 'L');
        }
    }

    private function dibujarTablaEscalaConducta(float $x, float $y): void
    {
        $anchoCol1 = 15.0;
        $anchoCol2 = 43.0;
        $altoFila = 7.0;

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell($anchoCol1 + $anchoCol2, $altoFila, 'ESCALA DE CONDUCTA', 1, 0, 'C');

        TcpdfFuenteArial::aplicar($this, '', 9);
        $filas = [
            ['S', 'Sobresaliente'],
            ['MB', 'Muy Bueno'],
            ['B', 'Bueno'],
            ['R', 'Regular'],
            ['I', 'Insuficiente'],
        ];

        foreach ($filas as $i => $row) {
            $yFila = $y + $altoFila * ($i + 1);
            $this->SetXY($x, $yFila);
            $this->Cell($anchoCol1, $altoFila, $row[0], 1, 0, 'C');
            $this->Cell($anchoCol2, $altoFila, $row[1], 1, 0, 'L');
        }
    }

    private function dibujarReverso(): void
    {
        $d = $this->datos;
        $info = is_array($d['info'] ?? null) ? $d['info'] : [];
        $left = 10.0;

        $this->SetLeftMargin($left);
        $this->SetXY($left, 10);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(20, 10, 'Alumno:', 0, 0);
        $this->Cell(80, 10, ($d['apellido'] ?? '').', '.($d['nombre'] ?? ''), 0, 1);

        $yHeadCalif = $this->GetY();
        $this->dibujarEncabezadoTablaCalificaciones($left, $yHeadCalif);
        $this->SetXY($left, $yHeadCalif + 24.0);

        $califs = is_array($d['calificaciones'] ?? null) ? $d['calificaciones'] : [];
        foreach ($califs as $row) {
            $materia = (string) ($row['materia'] ?? '');
            if (mb_strlen($materia) > 25) {
                $materia = mb_substr($materia, 0, 25).'...';
            }
            TcpdfFuenteArial::aplicar($this, '', 6);
            $this->Cell(33, 7, $materia, 1, 0, 'L');
            TcpdfFuenteArial::aplicar($this, '', 10);
            foreach (CalificacionesEpqCatalogo::CAMPOS_NOTA as $campo) {
                $this->Cell(14, 7, (string) ($row[$campo] ?? ''), 1, 0, 'C');
            }
            $this->Ln();
        }

        // Asistencia
        $this->Ln(2);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(43, 6, '', 0, 0);
        $this->Cell(22, 6, '1º TRIMESTRE', 1, 0, 'C');
        $this->Cell(22, 6, '2º TRIMESTRE', 1, 0, 'C');
        $this->Cell(22, 6, '3º TRIMESTRE', 1, 0, 'C');
        $this->Cell(22, 6, 'TOTAL', 1, 1, 'C');

        $yAsist = $this->GetY();
        $this->Cell(25, 3, '', 'LT', 1);
        $this->Cell(25, 3, 'CONTROL DE', 'L', 1);
        $this->Cell(25, 3, 'ASISTENCIA', 'L', 1);
        $this->Cell(25, 3, '', 'L', 1);
        $this->SetXY($left + 25, $yAsist);
        $this->Cell(18, 6, 'Días Hábiles', 1, 0, 'C');
        $this->SetXY($left + 25, $yAsist + 6);
        $this->Cell(18, 6, 'Inasistencia', 1, 0, 'C');
        $this->SetXY($left + 43, $yAsist);
        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->Cell(22, 6, (string) ($d['habiles1t'] ?? ''), 1, 0, 'C');
        $this->Cell(22, 6, (string) ($d['habiles2t'] ?? ''), 1, 0, 'C');
        $this->Cell(22, 6, (string) ($d['habiles3t'] ?? ''), 1, 0, 'C');
        $this->Cell(22, 6, (string) ($d['habilesTot'] ?? ''), 1, 0, 'C');
        $this->SetXY($left + 43, $yAsist + 6);
        $this->Cell(22, 6, (string) ($info['md01'] ?? ''), 1, 0, 'C');
        $this->Cell(22, 6, (string) ($info['md02'] ?? ''), 1, 0, 'C');
        $this->Cell(22, 6, (string) ($info['md03'] ?? ''), 1, 0, 'C');
        $this->Cell(22, 6, (string) ($info['md04'] ?? ''), 1, 0, 'C');
        $this->SetXY($left, $yAsist + 12);

        $this->filaAsistencia('Respeto por las Normas de Conviv.', [
            (string) ($info['md05'] ?? ''),
            (string) ($info['md06'] ?? ''),
            (string) ($info['md07'] ?? ''),
            (string) ($info['md08'] ?? ''),
        ]);
        $this->filaAsistencia('Llamados de atención', [
            (string) ($info['md09'] ?? ''),
            (string) ($info['md10'] ?? ''),
            (string) ($info['md11'] ?? ''),
            (string) ($info['md12'] ?? ''),
        ]);
        $this->filaFirma('FIRMA DEL MAESTRO', 11);
        $this->filaFirma('FIRMA DEL DIRECTOR', 11);
        $this->filaFirma('FIRMA DEL PADRE O TUTOR', 11);
        $this->Ln(1);
        $this->filaAsistencia('ACOMPAÑAMIENTO FAMILIAR', [
            (string) ($info['md13'] ?? ''),
            (string) ($info['md14'] ?? ''),
            (string) ($info['md15'] ?? ''),
            (string) ($info['md16'] ?? ''),
        ], 8);

        // Mitad derecha: habilidades
        $this->dibujarMitadDerechaReverso($d, $info);
    }

    private function dibujarEncabezadoTablaCalificaciones(float $x, float $y): void
    {
        $altoHead = 24.0;
        $altoCompTitulo = 12.0;
        $altoCompSub = 12.0;
        $anchoComp = 42.0;
        $anchoSub = 14.0;
        $xComp = $x + 33.0 + 14.0 * 4;

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(33, $altoHead, 'ÁREAS', 1, 0, 'C');
        $this->Cell(14, $altoHead, '1º TRIM', 1, 0, 'C');
        $this->Cell(14, $altoHead, '2º TRIM', 1, 0, 'C');
        $this->Cell(14, $altoHead, '3º TRIM', 1, 0, 'C');
        $this->Cell(14, $altoHead, 'PR.FI', 1, 0, 'C');

        $this->Rect($xComp, $y, $anchoComp, $altoCompTitulo);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->SetXY($xComp, $y + 2.5);
        $this->Cell($anchoComp, 3.5, 'COMPENSACIÓN DE', 0, 0, 'C');
        $this->SetXY($xComp, $y + 6.5);
        $this->Cell($anchoComp, 3.5, 'APRENDIZAJE', 0, 0, 'C');

        $this->SetXY($xComp, $y + $altoCompTitulo);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell($anchoSub, $altoCompSub, 'Dic', 1, 0, 'C');
        $this->Cell($anchoSub, $altoCompSub, 'Feb', 1, 0, 'C');
        $this->Cell($anchoSub, $altoCompSub, 'CalDef', 1, 0, 'C');
    }

    /**
     * @param  array<string, mixed>  $d
     * @param  array<string, string>  $info
     */
    private function dibujarMitadDerechaReverso(array $d, array $info): void
    {
        $xPanel = 160.0;
        $xTrim = $xPanel;
        $xTexto = $xPanel + 26.0;
        $anchoTrim = 26.0;
        $anchoTexto = 94.0;
        $altoBloque = 42.0;
        $altoLinea = 14.0;
        $altoFilaSocial = 7.0;

        $this->SetLeftMargin($xPanel);
        $this->SetXY($xPanel, 10.0);
        TcpdfFuenteArial::aplicar($this, '', 11);
        $this->Cell(40, 8, 'Grado:  '.($d['nombreCurso'] ?? ''), 0, 0);
        $this->Cell(25, 8, 'Sección:  '.($d['seccion'] ?? ''), 0, 0);
        $this->Cell(35, 8, 'Turno:  '.($d['turno'] ?? ''), 0, 0);
        $this->Cell(30, 8, 'Año:  '.($d['anoLectivo'] ?? ''), 0, 1);

        $y = $this->GetY();
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->SetXY($xPanel, $y);
        $this->Cell(120, 6, 'Habilidades Intelectuales y Prácticas', 1, 0, 'C');
        $y += 6;

        $bloques = [
            ['1º TRIMESTRE', 'md17', 'md20', 'md23'],
            ['2º TRIMESTRE', 'md18', 'md21', 'md24'],
            ['3º TRIMESTRE', 'md19', 'md22', 'md25'],
        ];

        foreach ($bloques as [$titulo, $destaca, $trabaja, $mejorar]) {
            $this->SetXY($xTrim, $y);
            TcpdfFuenteArial::aplicar($this, '', 7);
            $this->Cell($anchoTrim, $altoBloque, $titulo, 1, 0, 'C');

            $this->dibujarCeldaMultilineaReverso($xTexto, $y, $anchoTexto, $altoLinea, 'Se destaca en: '.($info[$destaca] ?? ''));
            $this->dibujarCeldaMultilineaReverso($xTexto, $y + $altoLinea, $anchoTexto, $altoLinea, 'Trabaja bien en: '.($info[$trabaja] ?? ''));
            $this->dibujarCeldaMultilineaReverso($xTexto, $y + $altoLinea * 2, $anchoTexto, $altoLinea, 'Debe mejorar en: '.($info[$mejorar] ?? ''));

            $y += $altoBloque;
        }

        $y += 2;
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->SetXY($xPanel, $y);
        $this->Cell(58, $altoFilaSocial, 'HABILIDADES PERSONALES Y SOCIALES', 1, 0, 'C');
        $this->Cell(23, $altoFilaSocial, '1º TRIMESTRE', 1, 0, 'C');
        $this->Cell(23, $altoFilaSocial, '2º TRIMESTRE', 1, 0, 'C');
        $this->Cell(23, $altoFilaSocial, '3º TRIMESTRE', 1, 0, 'C');
        $y += $altoFilaSocial;

        $this->dibujarFilaSocialReverso($xPanel, $y, $altoFilaSocial, 'Colabora en tareas grupales', 'md26', 'md27', 'md28', $info);
        $y += $altoFilaSocial;
        $this->dibujarFilaSocialReverso($xPanel, $y, $altoFilaSocial, 'Asume tareas por sí mismo', 'md29', 'md30', 'md31', $info);
        $y += $altoFilaSocial;

        $this->SetXY($xPanel, $y);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(58, $altoFilaSocial / 2, 'Acepta normas grupales e', 'LTR', 2, 'L');
        $this->Cell(58, $altoFilaSocial / 2, 'institucionales de convivencia', 'LBR', 0, 'L');
        $this->SetXY($xPanel + 58, $y);
        $this->Cell(23, $altoFilaSocial, (string) ($info['md32'] ?? ''), 1, 0, 'C');
        $this->Cell(23, $altoFilaSocial, (string) ($info['md33'] ?? ''), 1, 0, 'C');
        $this->Cell(23, $altoFilaSocial, (string) ($info['md34'] ?? ''), 1, 0, 'C');
        $y += $altoFilaSocial;

        $this->dibujarFilaSocialReverso($xPanel, $y, $altoFilaSocial, 'Establece contacto social con sus pares', 'md35', 'md36', 'md37', $info);
    }

    private function dibujarCeldaMultilineaReverso(
        float $x,
        float $y,
        float $ancho,
        float $alto,
        string $texto,
        string $align = 'L',
        int $fuente = 10,
    ): void {
        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, '', $fuente);
        $this->MultiCell(
            $ancho,
            $alto,
            $texto,
            1,
            $align,
            false,
            0,
            $x,
            $y,
            true,
            0,
            false,
            true,
            $alto,
            'T',
        );
    }

    /** @param  array<string, string>  $info */
    private function dibujarFilaSocialReverso(
        float $x,
        float $y,
        float $alto,
        string $label,
        string $c1,
        string $c2,
        string $c3,
        array $info,
    ): void {
        $this->dibujarCeldaMultilineaReverso($x, $y, 58.0, $alto, $label, 'L', 7);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->SetXY($x + 58.0, $y);
        $this->Cell(23, $alto, (string) ($info[$c1] ?? ''), 1, 0, 'C');
        $this->Cell(23, $alto, (string) ($info[$c2] ?? ''), 1, 0, 'C');
        $this->Cell(23, $alto, (string) ($info[$c3] ?? ''), 1, 0, 'C');
    }

    /** @param  list<string>  $valores */
    private function filaAsistencia(string $label, array $valores, float $altura = 6): void
    {
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(43, $altura, $label, 1, 0, 'C');
        TcpdfFuenteArial::aplicar($this, '', 10);
        foreach ($valores as $v) {
            $this->Cell(22, $altura, $v, 1, 0, 'C');
        }
        $this->Ln();
    }

    private function filaFirma(string $label, float $altura): void
    {
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(43, $altura, $label, 1, 0, 'C');
        TcpdfFuenteArial::aplicar($this, '', 10);
        for ($i = 0; $i < 4; $i++) {
            $this->Cell(22, $altura, '', 1, 0, 'C');
        }
        $this->Ln();
    }
}
