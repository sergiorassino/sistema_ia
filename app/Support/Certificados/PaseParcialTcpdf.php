<?php

namespace App\Support\Certificados;

use TCPDF;

/**
 * Solicitud de pase (A4, formato legacy FPDF → TCPDF).
 */
final class PaseParcialTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const ANCHO_UTIL = 180.0;

    private const FUENTE = 'dejavusans';

    private const ALTURA_FILA_NOTA = 4.0;

    private const ANCHO_MATERIA = 64.0;

    private const ANCHO_SEP = 1.0;

    private const ANCHO_EVAL = 12.0;

    private const ANCHO_CEL_NOTA = 4.0;

    private const ANCHO_JIS = 8.0;

    private const Y_MAX_FILA = 265.0;

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
        $this->SetTitle('Solicitud de Pase');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(true, 12);
        $this->SetMargins(self::MARGEN_IZQ, 10, 15);
        $this->SetFillColor(255, 255, 255);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage();
        $pdf->dibujarEncabezado();
        $pdf->dibujarCartaSolicitud();
        $pdf->dibujarFirmasPadreEstudiante();
        $pdf->dibujarLineaSeparadora();
        $pdf->dibujarInformeIntro();
        $pdf->dibujarTablaCalificaciones();
        $pdf->dibujarCierreAutorizacion();
        $pdf->dibujarFirmasDirectora();

        return $pdf;
    }

    private function dibujarEncabezado(): void
    {
        $inst = $this->datos['institucion'] ?? [];
        $insti = trim((string) ($inst['insti'] ?? ''));
        $logo = $inst['logo_abs'] ?? null;
        $impreso = trim((string) ($this->datos['impreso_en'] ?? ''));

        $this->SetFont(self::FUENTE, '', 6);
        $this->SetXY(140, 5);
        $this->Cell(50, 3, $impreso, 0, 0, 'C');

        $y0 = 10.0;
        $this->Rect(self::MARGEN_IZQ, $y0, self::ANCHO_UTIL, 23.0, 'D');

        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $this->Image($logo, self::MARGEN_IZQ + 5, $y0 + 1, 21, 21, '', '', '', false, 300);
        }

        $this->SetXY(self::MARGEN_IZQ, $y0 + 5);
        $this->SetFont(self::FUENTE, 'B', 10);
        $this->Cell(self::ANCHO_UTIL, 7, $insti, 0, 2, 'C');

        $this->SetFont(self::FUENTE, 'B', 9);
        $this->Cell(self::ANCHO_UTIL, 5, 'SOLICITUD DE PASE', 0, 2, 'C');

        $this->SetY($this->GetY() + 2);
    }

    private function dibujarCartaSolicitud(): void
    {
        $leg = $this->datos['legajo'] ?? [];
        $sol = $this->datos['solicitud'] ?? [];
        $inst = $this->datos['institucion'] ?? [];

        $apellido = trim((string) ($leg['apellido'] ?? ''));
        $nombre = trim((string) ($leg['nombre'] ?? ''));
        $cursec = trim((string) ($sol['cursec'] ?? ''));
        $destino = trim((string) ($sol['destino'] ?? ''));
        $fecha = trim((string) ($sol['fecha'] ?? ''));
        $localidad = trim((string) ($inst['localidad'] ?? ''));

        $this->Ln(9);
        $this->SetFont(self::FUENTE, '', 10);
        $this->Cell(self::ANCHO_UTIL, 5, $localidad.':  '.$fecha, 0, 1, 'C');
        $this->Ln(3);

        $this->Write(5, 'A las autoridades del    '.$destino);
        $this->Ln(5);

        $texto = 'El que suscribe,    '.$apellido.' '.$nombre
            .', alumno/a regular de  '.$cursec
            .' del Establecimiento a su cargo, solicita quiera tomar a bien concederle el PASE para el '
            .$destino
            .' donde desea continuar sus estudios. Sin más, la saluda atentamente.';

        $this->Write(5, $texto);
        $this->Ln(2);
    }

    private function dibujarFirmasPadreEstudiante(): void
    {
        $this->Ln(25);
        $y = $this->GetY();

        $this->SetFont(self::FUENTE, '', 6);
        $this->SetXY(40, $y);
        $this->Cell(50, 5, '..........................................................', 0, 0, 'C');
        $this->SetXY(40, $y + 5);
        $this->Cell(50, 5, 'Firma del Padre, Tutor o Encargado', 0, 0, 'C');

        $this->SetXY(120, $y);
        $this->Cell(50, 5, '..........................................................', 0, 0, 'C');
        $this->SetXY(120, $y + 5);
        $this->Cell(50, 5, 'Firma del Estudiante', 0, 0, 'C');
    }

    private function dibujarLineaSeparadora(): void
    {
        $y = $this->GetY() + 10;
        $this->Line(self::MARGEN_IZQ, $y, self::MARGEN_IZQ + self::ANCHO_UTIL - 10, $y);
        $this->SetY($y + 5);
    }

    private function dibujarInformeIntro(): void
    {
        $leg = $this->datos['legajo'] ?? [];
        $sol = $this->datos['solicitud'] ?? [];
        $inf = $this->datos['informe'] ?? [];

        $apellido = trim((string) ($leg['apellido'] ?? ''));
        $nombre = trim((string) ($leg['nombre'] ?? ''));
        $cursec = trim((string) ($sol['cursec'] ?? ''));
        $inas = (int) ($inf['inasistencias'] ?? 0);
        $amon = (int) ($inf['amonestaciones'] ?? 0);
        $obs = (int) ($inf['observaciones'] ?? 0);

        $this->Ln(10);
        $this->SetFont(self::FUENTE, 'B', 10);
        $this->Write(5, 'INFORME');
        $this->Ln(10);

        $this->SetFont(self::FUENTE, '', 10);
        $this->Write(5, 'El/La estudiante, '.$apellido.' '.$nombre.', de  '.$cursec
            .', ha incurrido en '.$inas.' inasistencias,  '.$amon.' amonestaciones y '.$obs.' observaciones');
        $this->Ln(5);
        $this->Write(5, 'En el término transcurrido registra las siguientes calificaciones.');
        $this->Ln(10);
    }

    private function dibujarTablaCalificaciones(): void
    {
        /** @var list<array<string, string>> $filas */
        $filas = $this->datos['calificaciones'] ?? [];
        if ($filas === []) {
            return;
        }

        $this->dibujarEncabezadoTablaNotas();

        foreach ($filas as $fila) {
            if ($this->GetY() > self::Y_MAX_FILA) {
                $this->AddPage();
                $this->dibujarEncabezadoTablaNotas();
            }
            $this->dibujarFilaNotas($fila);
        }

        $this->SetY($this->GetY() + 10);
    }

    private function dibujarEncabezadoTablaNotas(): void
    {
        $x0 = self::MARGEN_IZQ;

        $this->SetFont(self::FUENTE, '', 6);
        $this->SetX($x0);
        $this->Cell(self::ANCHO_MATERIA, 11, 'Espacio Curricular', 1, 0, 'C');
        $this->Cell(self::ANCHO_SEP, 6, '', 0, 0, 'C');

        for ($e = 1; $e <= 8; $e++) {
            $this->Cell(self::ANCHO_EVAL, 5, 'Eval. '.$e, 1, 0, 'C');
            if ($e < 8) {
                $this->Cell(self::ANCHO_SEP, 6, '', 0, 0, 'C');
            }
        }

        $this->Cell(self::ANCHO_SEP, 6, '', 0, 0, 'C');
        $this->Cell(self::ANCHO_JIS, 5, 'JIS 1', 1, 0, 'C');
        $this->Cell(self::ANCHO_SEP, 6, '', 0, 0, 'C');
        $this->Cell(self::ANCHO_JIS, 5, 'JIS 2', 1, 1, 'C');

        $this->SetFont(self::FUENTE, '', 5);
        $this->SetX($x0);
        $this->Cell(self::ANCHO_MATERIA, 6, '', 0, 0, 'C');
        $this->Cell(self::ANCHO_SEP, 6, '', 0, 0, 'C');

        for ($e = 1; $e <= 8; $e++) {
            $this->Cell(self::ANCHO_CEL_NOTA, 6, 'N', 1, 0, 'C');
            $this->Cell(self::ANCHO_CEL_NOTA, 6, 'R1', 1, 0, 'C');
            $this->Cell(self::ANCHO_CEL_NOTA, 6, 'R2', 1, 0, 'C');
            if ($e < 8) {
                $this->Cell(self::ANCHO_SEP, 6, '', 0, 0, 'C');
            }
        }

        $this->Cell(self::ANCHO_SEP, 6, '', 0, 0, 'C');
        $this->Cell(self::ANCHO_CEL_NOTA, 6, 'N', 1, 0, 'C');
        $this->Cell(self::ANCHO_CEL_NOTA, 6, 'R', 1, 0, 'C');
        $this->Cell(self::ANCHO_SEP, 6, '', 0, 0, 'C');
        $this->Cell(self::ANCHO_CEL_NOTA, 6, 'N', 1, 0, 'C');
        $this->Cell(self::ANCHO_CEL_NOTA, 6, 'R', 1, 1, 'C');
    }

    /**
     * @param  array<string, string>  $fila
     */
    private function dibujarFilaNotas(array $fila): void
    {
        $y = $this->GetY();
        $materia = trim((string) ($fila['materia'] ?? ''));
        if (mb_strlen($materia, 'UTF-8') > 38) {
            $materia = mb_substr($materia, 0, 38, 'UTF-8').'...';
        }

        $this->SetFont(self::FUENTE, '', 7);
        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->Cell(self::ANCHO_MATERIA, self::ALTURA_FILA_NOTA, $materia, 1, 0, 'L');

        $this->SetFont(self::FUENTE, '', 6);
        $this->Cell(self::ANCHO_SEP, self::ALTURA_FILA_NOTA, '', 0, 0, 'C');

        for ($e = 1; $e <= 8; $e++) {
            $base = ($e - 1) * 3 + 1;
            $this->Cell(self::ANCHO_CEL_NOTA, self::ALTURA_FILA_NOTA, $fila['ic'.str_pad((string) $base, 2, '0', STR_PAD_LEFT)] ?? '', 1, 0, 'C');
            $this->Cell(self::ANCHO_CEL_NOTA, self::ALTURA_FILA_NOTA, $fila['ic'.str_pad((string) ($base + 1), 2, '0', STR_PAD_LEFT)] ?? '', 1, 0, 'C');
            $this->Cell(self::ANCHO_CEL_NOTA, self::ALTURA_FILA_NOTA, $fila['ic'.str_pad((string) ($base + 2), 2, '0', STR_PAD_LEFT)] ?? '', 1, 0, 'C');
            if ($e < 8) {
                $this->Cell(self::ANCHO_SEP, self::ALTURA_FILA_NOTA, '', 0, 0, 'C');
            }
        }

        $this->Cell(self::ANCHO_SEP, self::ALTURA_FILA_NOTA, '', 0, 0, 'C');
        $this->Cell(self::ANCHO_CEL_NOTA, self::ALTURA_FILA_NOTA, $fila['ic25'] ?? '', 1, 0, 'C');
        $this->Cell(self::ANCHO_CEL_NOTA, self::ALTURA_FILA_NOTA, $fila['ic26'] ?? '', 1, 0, 'C');
        $this->Cell(self::ANCHO_SEP, self::ALTURA_FILA_NOTA, '', 0, 0, 'C');
        $this->Cell(self::ANCHO_CEL_NOTA, self::ALTURA_FILA_NOTA, $fila['ic27'] ?? '', 1, 0, 'C');
        $this->Cell(self::ANCHO_CEL_NOTA, self::ALTURA_FILA_NOTA, $fila['ic28'] ?? '', 1, 1, 'C');
    }

    private function dibujarCierreAutorizacion(): void
    {
        $leg = $this->datos['legajo'] ?? [];
        $sol = $this->datos['solicitud'] ?? [];

        $apellido = trim((string) ($leg['apellido'] ?? ''));
        $nombre = trim((string) ($leg['nombre'] ?? ''));
        $cursec = trim((string) ($sol['cursec'] ?? ''));
        $destino = trim((string) ($sol['destino'] ?? ''));

        $this->SetFont(self::FUENTE, '', 10);
        $this->Write(5, 'Vista la solicitud que antecede y la información producida, autorizase el pase del/de la estudiante '
            .$apellido.' '.$nombre.', de  '.$cursec.', para el  '.$destino);
        $this->Ln(5);
        $this->Write(5, 'Se hace constar que las calificaciones, inasistencias y demás antecedentes, son los que se consignan en el informe que antecede y que el/la estudiante se retira por su propia voluntad.');
        $this->Ln(2);
    }

    private function dibujarFirmasDirectora(): void
    {
        $this->Ln(25);
        $y = $this->GetY();

        $this->SetFont(self::FUENTE, '', 6);
        $this->SetXY(40, $y);
        $this->Cell(50, 5, '..........................................................', 0, 0, 'C');
        $this->SetXY(40, $y + 5);
        $this->Cell(50, 5, 'Firma', 0, 0, 'C');

        $this->SetXY(120, $y);
        $this->Cell(50, 5, '..........................................................', 0, 0, 'C');
        $this->SetXY(120, $y + 5);
        $this->Cell(50, 5, 'Firma de la Directora', 0, 0, 'C');
    }
}
