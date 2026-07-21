<?php

namespace App\Support\Tea;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use TCPDF;

/**
 * Impresos TEA Montecristo (TCPDF, diseño legacy ScriptCase por situación).
 */
final class TeaRegistroMontecristoTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const ANCHO_BLOQUE = 170.0;

    private const Y_ENCABEZADO = 30.0;

    private const Y_ENCABEZADO_TIPO2 = 20.0;

    private const ALTURA_ENCABEZADO = 22.0;

    /** @var array<string, mixed> */
    private array $datos;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->datos = $datos;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Informe TEA');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetLeftMargin(self::MARGEN_IZQ);
        $this->SetMargins(self::MARGEN_IZQ, 10, 20);
        $this->SetTextColor(33, 33, 33);
    }

    /** @return list<int> */
    public static function tiposSoportados(): array
    {
        return [1, 2, 3, 4, 5];
    }

    public static function soportaTipo(int $idTipo): bool
    {
        return in_array($idTipo, self::tiposSoportados(), true);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $idTipo = (int) ($datos['idTipo'] ?? 0);
        abort_unless(self::soportaTipo($idTipo), 404, 'Impreso TEA no implementado para esta situación.');

        $pdf = new self($datos);
        $pdf->AddPage();
        $pdf->dibujarDocumento();

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

    private function dibujarDocumento(): void
    {
        match ((int) ($this->datos['idTipo'] ?? 0)) {
            1 => $this->dibujarTipo1InformeFamilia3Inasistencias(),
            2 => $this->dibujarTipo2InformeDirectivosCitacion(),
            3 => $this->dibujarTipo3ActaCompromisoCitacion(),
            4 => $this->dibujarTipo4NotificacionRiesgoCitacion(),
            5 => $this->dibujarTipo5NotificacionTea(),
            default => abort(404, 'Impreso TEA no implementado para esta situación.'),
        };
    }

    private function dibujarTipo1InformeFamilia3Inasistencias(): void
    {
        $this->dibujarEncabezadoInstitucional();

        $this->Ln(2);
        TcpdfFuenteArial::aplicar($this, '', 11);
        $this->Cell(self::ANCHO_BLOQUE + 10, 4, 'INFORME A LA FAMILIA – 3 INASISTENCIAS INJUSTIFICADAS', 0, 2, 'C');

        $this->Ln(9);
        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->Cell(self::ANCHO_BLOQUE, 4, 'Fecha: '.($this->datos['fecha'] ?? ''), 0, 2, 'R');

        $this->Ln(2);
        $apellido = (string) ($this->datos['apellido'] ?? '');
        $nombre = (string) ($this->datos['nombre'] ?? '');
        $this->Cell(self::ANCHO_BLOQUE + 10, 4, 'A la familia de: '.$apellido.', '.$nombre, 0, 2, 'L');

        $this->Ln(2);
        $this->Cell(self::ANCHO_BLOQUE + 10, 4, 'Curso: '.($this->datos['curso'] ?? ''), 0, 2, 'L');

        $this->Ln(10);
        $this->dibujarCuerpoTipo1();

        $this->Ln(20);
        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->Cell(self::ANCHO_BLOQUE, 4, 'Nombre y firma del Preceptor/a: ', 0, 2, 'R');
    }

    private function dibujarTipo2InformeDirectivosCitacion(): void
    {
        $this->dibujarEncabezadoInstitucional(self::Y_ENCABEZADO_TIPO2);

        $this->Ln(2);
        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell(
            self::ANCHO_BLOQUE + 10,
            4,
            'INFORME A DIRECTIVOS Y CITACIÓN A ADULTOS RESPONSABLES – 5 INASISTENCIAS',
            0,
            2,
            'C',
        );

        $this->Ln(9);
        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->Cell(self::ANCHO_BLOQUE, 4, 'Fecha: '.($this->datos['fecha'] ?? ''), 0, 2, 'R');

        $this->Ln(2);
        $this->Cell(self::ANCHO_BLOQUE + 10, 4, 'A Equipo Directivo', 0, 2, 'L');

        $this->Ln(2);
        $this->Cell(self::ANCHO_BLOQUE + 10, 4, 'Curso: '.($this->datos['curso'] ?? ''), 0, 2, 'L');

        $this->Ln(6);
        $this->dibujarCuerpoTipo2Directivos();

        $this->Ln(15);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(self::ANCHO_BLOQUE, 4, 'Nombre y firma del Preceptor', 0, 2, 'R');

        $this->Ln(5);
        $yLinea = $this->GetY();
        $this->Line(self::MARGEN_IZQ, $yLinea, self::MARGEN_IZQ + self::ANCHO_BLOQUE, $yLinea);

        $this->Ln(10);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(self::ANCHO_BLOQUE + 10, 4, 'INFORME PARA ADULTOS RESPONSABLES', 0, 2, 'C');

        $header = (array) ($this->datos['header'] ?? []);
        $insti = trim((string) ($header['insti'] ?? ''));
        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->Cell(self::ANCHO_BLOQUE + 10, 7, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        $this->Ln(2);
        $this->Cell(self::ANCHO_BLOQUE, 4, 'Fecha: '.($this->datos['fecha'] ?? ''), 0, 2, 'R');

        $this->Ln(2);
        $apellido = (string) ($this->datos['apellido'] ?? '');
        $nombre = (string) ($this->datos['nombre'] ?? '');
        $this->Cell(self::ANCHO_BLOQUE + 10, 4, 'A la familia de: '.$apellido.', '.$nombre, 0, 2, 'L');

        $this->Ln(2);
        $this->Cell(self::ANCHO_BLOQUE + 10, 4, 'Curso: '.($this->datos['curso'] ?? ''), 0, 2, 'L');

        $this->Ln(2);
        $this->dibujarCuerpoTipo2CitacionFamilia();

        $this->Ln(20);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(
            self::ANCHO_BLOQUE + 10,
            4,
            'Nombre y Firma de Padre, Madre o Tutor                                                                                     Nombre y firma del Directivo: ',
            0,
            2,
            'C',
        );
    }

    private function dibujarTipo3ActaCompromisoCitacion(): void
    {
        $this->dibujarEncabezadoInstitucional(self::Y_ENCABEZADO_TIPO2);

        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell(self::ANCHO_BLOQUE + 10, 4, 'ACTA COMPROMISO – 10 INASISTENCIAS JUSTIFICADAS O NO', 0, 2, 'C');
        $this->Cell(self::ANCHO_BLOQUE + 10, 4, 'CITACIÓN A ADULTOS RESPONSABLES', 0, 2, 'C');

        $this->Ln(9);
        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->Cell(self::ANCHO_BLOQUE, 4, 'Fecha: '.($this->datos['fecha'] ?? ''), 0, 2, 'R');

        $this->Ln(2);
        $apellido = (string) ($this->datos['apellido'] ?? '');
        $nombre = (string) ($this->datos['nombre'] ?? '');
        $this->Cell(self::ANCHO_BLOQUE + 10, 4, 'A la familia de: '.$apellido.', '.$nombre, 0, 2, 'L');

        $this->Ln(2);
        $this->Cell(self::ANCHO_BLOQUE + 10, 4, 'Curso: '.($this->datos['curso'] ?? ''), 0, 2, 'L');

        $this->Ln(6);
        $this->dibujarCuerpoTipo3Citacion();

        $this->Ln(15);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(150, 4, 'Nombre y firma del Directivo: ', 0, 2, 'R');

        $this->AddPage();
        $this->dibujarPaginaActaCompromiso();
    }

    private function dibujarTipo4NotificacionRiesgoCitacion(): void
    {
        $this->dibujarEncabezadoNotificacionRiesgoCitacion();

        $this->Ln(9);
        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->Cell(self::ANCHO_BLOQUE, 4, 'Fecha: '.($this->datos['fecha'] ?? ''), 0, 2, 'R');

        $this->Ln(2);
        $apellido = (string) ($this->datos['apellido'] ?? '');
        $nombre = (string) ($this->datos['nombre'] ?? '');
        $this->Cell(self::ANCHO_BLOQUE + 10, 4, 'A la familia de: '.$apellido.', '.$nombre, 0, 2, 'L');

        $this->Ln(2);
        $this->Cell(self::ANCHO_BLOQUE + 10, 4, 'Curso: '.($this->datos['curso'] ?? ''), 0, 2, 'L');

        $this->Ln(6);
        $this->dibujarCuerpoTipo4Citacion();

        $this->Ln(15);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(150, 4, 'Nombre y firma del Directivo: ', 0, 2, 'R');

        $this->AddPage();
        $this->dibujarPaginaTipo4Acta();
    }

    private function dibujarTipo5NotificacionTea(): void
    {
        $this->dibujarEncabezadoNotificacionTea();

        $this->Ln(5);
        $this->Ln(10);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(self::ANCHO_BLOQUE + 10, 4, 'NOTIFICACIÓN', 0, 2, 'C');

        $this->Ln(12);
        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->Cell(self::ANCHO_BLOQUE, 4, 'Fecha: '.($this->datos['fecha'] ?? ''), 0, 2, 'R');

        $this->Ln(2);
        $this->dibujarCuerpoTipo5Notificacion();

        $this->dibujarFirmasActaReunion();
    }

    private function dibujarEncabezadoNotificacionTea(): void
    {
        $x = self::MARGEN_IZQ;
        $y = self::Y_ENCABEZADO_TIPO2;
        $w = self::ANCHO_BLOQUE;

        $this->SetDrawColor(17, 17, 17);
        $this->Rect($x, $y, $w, self::ALTURA_ENCABEZADO);

        $header = (array) ($this->datos['header'] ?? []);
        $logo = pdfHeaderLogoAbsolutePath($header);
        if (is_string($logo) && $logo !== '') {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), $x + 3, $y + 1, 17, 20, '', '', '', false, 300);
        }

        $insti = trim((string) ($header['insti'] ?? ''));
        $this->SetXY($x, $y + 5);
        TcpdfFuenteArial::aplicar($this, '', 12);
        $this->Cell($w + 10, 7, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(
            $w + 10,
            4,
            'Notificación sobre Trayectoria Estudiantil Asistida (TEA)',
            0,
            2,
            'C',
        );
    }

    private function dibujarEncabezadoNotificacionRiesgoCitacion(): void
    {
        $x = self::MARGEN_IZQ;
        $y = self::Y_ENCABEZADO_TIPO2;
        $w = self::ANCHO_BLOQUE;

        $header = (array) ($this->datos['header'] ?? []);
        $logo = pdfHeaderLogoAbsolutePath($header);
        if (is_string($logo) && $logo !== '') {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), $x + 3, $y + 1, 17, 20, '', '', '', false, 300);
        }

        $insti = trim((string) ($header['insti'] ?? ''));
        $this->SetXY($x, $y + 3);
        TcpdfFuenteArial::aplicar($this, '', 11);
        $this->Cell($w + 10, 5, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(
            $w + 10,
            3.5,
            'NOTIFICACIÓN DE SITUACIÓN DE RIESGO Y PROPUESTAS PEDAGÓGICAS',
            0,
            2,
            'C',
        );
        $this->Cell($w + 10, 3.5, '20 INASISTENCIAS JUSTIFICADAS O NO', 0, 2, 'C');
        $this->Cell($w + 10, 3.5, 'CITACIÓN A ADULTOS RESPONSABLES', 0, 2, 'C');

        $yFin = $this->GetY() + 1.5;
        $this->SetDrawColor(17, 17, 17);
        $this->Rect($x, $y, $w, $yFin - $y);
    }

    private function dibujarPaginaActaCompromiso(): void
    {
        $this->dibujarEncabezadoActaCompromiso();

        $this->Ln(12);
        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->Cell(self::ANCHO_BLOQUE, 4, 'Fecha: '.($this->datos['fecha'] ?? ''), 0, 2, 'R');

        $this->Ln(2);
        $this->dibujarCuerpoTipo3Acta();

        $this->dibujarFirmasActaReunion();
    }

    private function dibujarPaginaTipo4Acta(): void
    {
        $this->dibujarEncabezadoActaCompromiso();

        $this->Ln(12);
        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->Cell(self::ANCHO_BLOQUE, 4, 'Fecha: '.($this->datos['fecha'] ?? ''), 0, 2, 'R');

        $this->Ln(2);
        $this->dibujarCuerpoTipo4Acta();

        $this->dibujarFirmasActaReunion();
    }

    private function dibujarFirmasActaReunion(): void
    {
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Ln(20);
        $this->Cell(
            self::ANCHO_BLOQUE,
            4,
            'Firma del/de la Estudiante: .........................................................................       Aclaración: ....................................................',
            0,
            2,
            'L',
        );
        $this->Ln(20);
        $this->Cell(
            self::ANCHO_BLOQUE,
            4,
            'Firma del/de los Adultos responsables: .........................................................................       Aclaración: ....................................................',
            0,
            2,
            'L',
        );
        $this->Ln(20);
        $this->Cell(
            self::ANCHO_BLOQUE,
            4,
            'Firma del/de la Preceptor/a: .........................................................................      Aclaración: ....................................................',
            0,
            2,
            'L',
        );
        $this->Ln(20);
        $this->Cell(
            self::ANCHO_BLOQUE,
            4,
            'Firma del Directivo:.........................................................................      Aclaración: ....................................................',
            0,
            2,
            'L',
        );
    }

    private function dibujarEncabezadoActaCompromiso(): void
    {
        $x = self::MARGEN_IZQ;
        $y = self::Y_ENCABEZADO_TIPO2;
        $w = self::ANCHO_BLOQUE;

        $this->SetDrawColor(17, 17, 17);
        $this->Rect($x, $y, $w, self::ALTURA_ENCABEZADO);

        $header = (array) ($this->datos['header'] ?? []);
        $logo = pdfHeaderLogoAbsolutePath($header);
        if (is_string($logo) && $logo !== '') {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), $x + 3, $y + 1, 17, 20, '', '', '', false, 300);
        }

        $insti = trim((string) ($header['insti'] ?? ''));
        $this->SetXY($x, $y + 5);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell($w + 10, 7, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell($w + 10, 4, 'ACTA COMPROMISO', 0, 2, 'C');
    }

    private function dibujarEncabezadoInstitucional(float $y = self::Y_ENCABEZADO): void
    {
        $x = self::MARGEN_IZQ;
        $w = self::ANCHO_BLOQUE;
        $h = self::ALTURA_ENCABEZADO;

        $this->SetDrawColor(17, 17, 17);
        $this->Rect($x, $y, $w, $h);

        $header = (array) ($this->datos['header'] ?? []);
        $logo = pdfHeaderLogoAbsolutePath($header);
        if (is_string($logo) && $logo !== '') {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), $x + 3, $y + 1, 17, 20, '', '', '', false, 300);
        }

        $insti = trim((string) ($header['insti'] ?? ''));
        $this->SetXY($x, $y + 5);
        TcpdfFuenteArial::aplicar($this, '', 12);
        $this->Cell($w, 7, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');
    }

    private function dibujarCuerpoTipo1(): void
    {
        ['apellido' => $apellido, 'nombre' => $nombre, 'dni' => $dni, 'total' => $total] = $this->variablesAlumnoHtml();

        TcpdfFuenteArial::aplicar($this, '', 10);

        $html = '<p>Estimada familia:
Nos dirigimos a ustedes para informarles, en cumplimiento a lo establecido por la Resolución 188/18 del Ministerio de Educación de la Provincia de Córdoba; que el/la estudiante '
            .$apellido.', '.$nombre.', DNI: '.$dni.', <b>ha registrado '.$total.' inasistencias de las cuales 3 son injustificadas</b> en el presente ciclo lectivo.</p>
<p>Solicitamos su acompañamiento para garantizar la continuidad en la trayectoria escolar y evitar inconvenientes derivados de ausencias reiteradas.
Ante cualquier duda o inconveniente, estamos a disposición para dialogar sobre estrategias que favorezcan la asistencia regular del/la estudiante.</p>
<p>Atentamente,</p>';

        $this->writeHTML($html, true, false, true, false, '');
    }

    private function dibujarCuerpoTipo2Directivos(): void
    {
        ['apellido' => $apellido, 'nombre' => $nombre, 'dni' => $dni, 'total' => $total] = $this->variablesAlumnoHtml();

        TcpdfFuenteArial::aplicar($this, '', 10);

        $html = '<p>Nos dirigimos a ustedes para informares, en cumplimiento a lo establecido por la Resolución 188/18 del Ministerio de Educación de la Provincia de Córdoba; que el/la estudiante '
            .$apellido.', '.$nombre.', DNI: '.$dni.', <b>ha registrado '.$total.' inasistencias de las cuales 5 son injustificadas</b> en el presente ciclo lectivo.</p>
<p>De acuerdo con el protocolo de seguimiento de inasistencias, corresponde citar a los adultos responsables para analizar la situación y definir estrategias que favorezcan su asistencia.</p>
<p>Se adjunta la citación para los adultos responsables, con el fin de coordinar una reunión en la institución el día ....................... a las .......... horas.</p>
<p>Atentamente,</p>';

        $this->writeHTML($html, true, false, true, false, '');
    }

    private function dibujarCuerpoTipo3Citacion(): void
    {
        ['apellido' => $apellido, 'nombre' => $nombre, 'dni' => $dni] = $this->variablesAlumnoHtml();

        TcpdfFuenteArial::aplicar($this, '', 10);

        $html = '<p>Estimada familia: </p>
<p>Nos dirigimos a ustedes para informares, en cumplimiento a lo establecido por la Resolución 188/18 del Ministerio de Educación de la Provincia de Córdoba; que el/la estudiante '
            .$apellido.', '.$nombre.', DNI: '.$dni.', <b>ha registrado 10 inasistencias</b> en el presente ciclo lectivo.</p>
<p>Solicitamos su presencia en la institución el día ....................... a las .......... horas para firmar el <b>Acta Compromiso</b> con el objetivo de garantizar la asistencia del/de la estudiante a clase.</p>
<p>Esperamos contar con su presencia.</p>
<p>Atentamente,</p>';

        $this->writeHTML($html, true, false, true, false, '');
    }

    private function dibujarCuerpoTipo4Citacion(): void
    {
        ['apellido' => $apellido, 'nombre' => $nombre, 'dni' => $dni] = $this->variablesAlumnoHtml();

        TcpdfFuenteArial::aplicar($this, '', 10);

        $html = '<p>Estimada familia: </p>
<p>Nos dirigimos a ustedes para informarles, en cumplimiento a lo establecido por la Resolución 188/18 del Ministerio de Educación de la Provincia de Córdoba; que el/la estudiante '
            .$apellido.', '.$nombre.', DNI: '.$dni.', <b>ha registrado 20 inasistencias</b> en el presente ciclo lectivo.</p>
<p>Solicitamos su presencia en la institución el día ....................... a las .......... horas para <b>notificar la situación de riesgo.</b></p>
<p>Esperamos contar con su presencia.</p>
<p>Atentamente,</p>';

        $this->writeHTML($html, true, false, true, false, '');
    }

    private function dibujarCuerpoTipo5Notificacion(): void
    {
        ['apellido' => $apellido, 'nombre' => $nombre, 'dni' => $dni] = $this->variablesAlumnoHtml();
        ['insti' => $insti, 'localidad' => $localidad] = $this->variablesInstitucionHtml();

        TcpdfFuenteArial::aplicar($this, '', 10);

        $html = '<style>p { line-height: 2; }</style><p>En la ciudad de '.$localidad.' siendo las ................ horas, se reúnen en la institución educativa '
            .$insti.' un representante del equipo directivo, el/la estudiante '.$apellido.', '.$nombre.' DNI: '.$dni.', el/los Adulto/s responsable/s del estudiante, y el/la preceptor/a con el objetivo de notificar que, frente a la cantidad de inasistencias acumuladas (25 inasistencias) el estudiante queda en <b>condición de Trayectoria Estudiantil Asistida (TEA)</b> según lo establecido por la Resolución 188/18 del Ministerio de Educación de la Provincia de Córdoba.</p>
<p>Se informa a la familia que, bajo esta condición, se implementarán estrategias pedagógicas específicas para evitar la desvinculación escolar y garantizar el acceso a los aprendizajes. Entre ellas se incluyen:<br>
• Plan de acompañamiento y seguimiento individualizado.<br>
• Compromiso de asistir regularmente a clases.<br></p>';

        $this->Ln(2);
        $this->writeHTML($html, true, false, true, false, '');
    }

    private function dibujarCuerpoTipo4Acta(): void
    {
        ['apellido' => $apellido, 'nombre' => $nombre, 'dni' => $dni] = $this->variablesAlumnoHtml();
        ['insti' => $insti, 'localidad' => $localidad] = $this->variablesInstitucionHtml();

        TcpdfFuenteArial::aplicar($this, '', 10);

        $html = '<style>p { line-height: 2; }</style><p>En la ciudad de '.$localidad.' siendo las ................ horas, se reúnen en la institución educativa '
            .$insti.' un representante del equipo directivo, el/la estudiante '.$apellido.', '.$nombre.' DNI: '.$dni.', el/los Adulto/s responsable/s del estudiante, y el/la preceptor/a con el objetivo de abordar la situación de inasistencias acumuladas (20 inasistencias) lo que coloca su <b>trayectoria escolar en situación de riesgo</b> y en cumplimiento de la Resolución 188/18 del Ministerio de Educación de la Provincia de Córdoba.</p>
<p>Para garantizar su continuidad educativa, se han definido las siguientes acciones pedagógicas:<br>
• Dialogar nuevamente sobre la importancia de la asistencia regular y su impacto en el aprendizaje.<br>
• Solicitar el compromiso de la familia y el estudiante en mejorar la asistencia para evitar que éste llegue a la condición de estudiante en Trayectoria escolar Asistida (TEA).<br>
• Poner en conocimiento la implicancia de la condición Trayectoria Escolar Asistida.<br>
• Se ofrece acompañamiento a través del Equipo de Orientación.<br></p>';

        $this->Ln(2);
        $this->writeHTML($html, true, false, true, false, '');
    }

    private function dibujarCuerpoTipo3Acta(): void
    {
        ['apellido' => $apellido, 'nombre' => $nombre, 'dni' => $dni] = $this->variablesAlumnoHtml();
        ['insti' => $insti, 'localidad' => $localidad] = $this->variablesInstitucionHtml();

        TcpdfFuenteArial::aplicar($this, '', 10);

        $html = '<style>p { line-height: 2; }</style><p>En la ciudad de '.$localidad.' siendo las ................ horas, se reúnen en la institución educativa '
            .$insti.' un representante del equipo directivo, el/la estudiante '.$apellido.', '.$nombre.' DNI: '.$dni.', el/los Adulto/s responsable/s del estudiante, y el/la preceptor/a con el objetivo de abordar la situación de inasistencias acumuladas (10 inasistencias) y en cumplimiento a lo establecido por la Resolución 188/18 del Ministerio de Educación de la Provincia de Córdoba.<br>
Se dialoga sobre la importancia de la asistencia regular y su impacto en el aprendizaje.<br>
Esperamos contar con su presencia y colaboración.<br>
Se solicita el compromiso de la familia y del/de la estudiante en mejorar la asistencia.</p>';

        $this->Ln(2);
        $this->writeHTML($html, true, false, true, false, '');
    }

    private function dibujarCuerpoTipo2CitacionFamilia(): void
    {
        ['apellido' => $apellido, 'nombre' => $nombre, 'dni' => $dni, 'total' => $total] = $this->variablesAlumnoHtml();

        TcpdfFuenteArial::aplicar($this, '', 10);

        $html = '<p>Estimada familia: </p>
<p>Nos dirigimos a ustedes para informares, en cumplimiento a lo establecido por la Resolución 188/18 del Ministerio de Educación de la Provincia de Córdoba; que el/la estudiante '
            .$apellido.', '.$nombre.', DNI: '.$dni.', <b>ha registrado '.$total.' inasistencias de las cuales 5 son injustificadas</b> en el presente ciclo lectivo.</p>
<p>Citamos a Uds. en la institución el día ....................... a las .......... horas para dialogar sobre la situación y acordar estrategias para mejorar su asistencia.</p>
<p>Esperamos contar con su colaboración.</p>';

        $this->writeHTML($html, true, false, true, false, '');
    }

    /**
     * @return array{apellido: string, nombre: string, dni: string, total: string}
     */
    private function variablesAlumnoHtml(): array
    {
        return [
            'apellido' => htmlspecialchars((string) ($this->datos['apellido'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'nombre' => htmlspecialchars((string) ($this->datos['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'dni' => htmlspecialchars((string) ($this->datos['dni'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'total' => number_format((float) ($this->datos['totalInasistencias'] ?? 0), 2, ',', ''),
        ];
    }

    /**
     * @return array{insti: string, localidad: string}
     */
    private function variablesInstitucionHtml(): array
    {
        $header = (array) ($this->datos['header'] ?? []);

        return [
            'insti' => htmlspecialchars(trim((string) ($header['insti'] ?? '')), ENT_QUOTES, 'UTF-8'),
            'localidad' => htmlspecialchars(trim((string) ($header['localidad'] ?? '')), ENT_QUOTES, 'UTF-8'),
        ];
    }
}
