<?php

namespace App\Support\Alumnos;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Ficha de matrícula pedagógica Caixal (A4 vertical).
 *
 * Adaptación del FPDF legacy ScriptCase: cabecera institucional, datos del alumno,
 * madre, padre y tutor, aviso de solicitud y firma.
 */
final class FichaMatriculaCaixalsfTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const ANCHO_CABECERA = 180.0;

    private const ANCHO_SECCION = 160.0;

    /** @var array<string, mixed> */
    private array $datos;

    /** @var array<string, mixed> */
    private array $header;

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $header
     */
    private function __construct(array $datos, array $header)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->datos = $datos;
        $this->header = $header;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Ficha de matrícula pedagógica');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_IZQ, 10, 10);
        $this->SetFillColor(232, 232, 232);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $header
     */
    public static function generar(array $datos, array $header): self
    {
        $pdf = new self($datos, $header);
        $pdf->AddPage();
        $pdf->dibujarDocumento();

        return $pdf;
    }

    /**
     * @param  list<array<string, mixed>>  $hojas
     * @param  array<string, mixed>  $header
     */
    public static function generarLote(array $hojas, array $header): self
    {
        $pdf = new self([], $header);

        foreach ($hojas as $datos) {
            $pdf->datos = $datos;
            $pdf->header = is_array($datos['header'] ?? null) ? $datos['header'] : $header;
            $pdf->AddPage();
            $pdf->dibujarDocumento();
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

    private function dibujarDocumento(): void
    {
        $y = 10.0;
        $y = $this->dibujarCabecera($y);
        $y = $this->dibujarLineaCurso($y);
        $y = $this->dibujarSeccionAlumno($y);
        $y = $this->dibujarSeccionMadre($y + 4);
        $y = $this->dibujarSeccionPadre($y + 4);
        $y = $this->dibujarSeccionTutor($y + 10);
        $this->dibujarAvisoYFirma($y + 6);
    }

    private function dibujarCabecera(float $y): float
    {
        $insti = trim((string) ($this->header['insti'] ?? ''));
        $direccion = trim((string) ($this->header['direccion'] ?? ''));
        $localidad = trim((string) ($this->header['localidad'] ?? ''));
        $telefono = trim((string) ($this->header['telefono'] ?? ''));
        $ciclo = trim((string) ($this->datos['cicloLectivo'] ?? ''));

        TcpdfFuenteArial::aplicar($this, 'B', 12);
        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->Cell(self::ANCHO_CABECERA, 6, $insti !== '' ? $insti : ' ', 0, 1, 'C');

        TcpdfFuenteArial::aplicar($this, 'I', 8);
        $this->SetX(self::MARGEN_IZQ);
        $this->Cell(self::ANCHO_CABECERA, 6, $direccion.' - '.$localidad.' - '.$telefono, 0, 1, 'C');

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->SetX(self::MARGEN_IZQ);
        $this->Cell(
            self::ANCHO_CABECERA,
            6,
            'FICHA DE MATRÍCULA PEDAGÓGICA - CICLO LECTIVO '.$ciclo,
            0,
            1,
            'C',
        );

        return $this->GetY() + 6;
    }

    private function dibujarLineaCurso(float $y): float
    {
        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, 6, 'Curso al que ingresa: ', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(30, 6, (string) ($this->datos['curso'] ?? ''), 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(40, 6, 'Fecha Solicitud de Matrícula : ', 0, 0, 'L');
        $this->Cell(30, 6, (string) ($this->datos['fechActDatos'] ?? ''), 0, 0, 'L');
        $this->Cell(15, 6, 'Legajo : ', 0, 0, 'L');
        $this->Cell(15, 6, (string) ($this->datos['legajo'] ?? ''), 0, 1, 'L');

        return $y + 6 + 1 + 6;
    }

    private function dibujarSeccionAlumno(float $y): float
    {
        $d = $this->datos;
        $y = $this->encabezadoSeccion($y, 'DATOS DEL ALUMNO');
        $y = $this->fila($y, 'APELLIDO Y NOMBRE: ', trim($d['apellido'].' '.$d['nombre']), 40);
        $y = $this->fila($y, 'D.N.I.: ', (string) ($d['dni'] ?? ''), 20);
        $y = $this->fila($y, 'Nacionalidad: ', (string) ($d['nacion'] ?? ''), 20);
        $y = $this->fila($y, 'Fecha de Nacimiento: ', (string) ($d['fechnaci'] ?? ''), 30);
        $y = $this->fila($y, 'Lugar de Nacimiento (ciudad): ', (string) ($d['ln_ciudad'] ?? ''), 50);
        $y = $this->fila($y, 'Departamento ', (string) ($d['ln_depto'] ?? ''), 30);
        $y = $this->fila($y, 'Provincia ', (string) ($d['ln_provincia'] ?? ''), 30);
        $y = $this->fila($y, 'País ', (string) ($d['ln_pais'] ?? ''), 30);
        $y = $this->fila($y, 'Dirección: ', (string) ($d['callenum'] ?? ''), 20);
        $y = $this->fila($y, 'Barrio: ', (string) ($d['barrio'] ?? ''), 20);
        $y = $this->fila($y, 'Localidad: ', (string) ($d['localidad'] ?? ''), 20);
        $y = $this->fila($y, 'Celular del Estudiante: ', (string) ($d['telefono'] ?? ''), 60);
        $y = $this->fila($y, 'Email del Estudiante: ', (string) ($d['email'] ?? ''), 60);

        return $y;
    }

    private function dibujarSeccionMadre(float $y): float
    {
        $d = $this->datos;
        $y = $this->encabezadoSeccion($y, 'DATOS DE LA MADRE');
        $y = $this->fila($y, 'Apellido y nombre: ', (string) ($d['nombremad'] ?? ''), 30);
        $y = $this->fila($y, 'D.N.I.: ', (string) ($d['dnimad'] ?? ''), 30);
        $y = $this->fila($y, 'Domicilio: ', (string) ($d['domimad'] ?? ''), 30);
        $y = $this->fila($y, 'Teléfono: ', (string) ($d['telemad'] ?? ''), 30);
        $y = $this->fila($y, 'Ocupación: ', (string) ($d['ocupacmad'] ?? ''), 30);
        $y = $this->fila($y, 'Lugar de Trabajo: ', (string) ($d['lugtramad'] ?? ''), 30);
        $y = $this->fila($y, 'Teléfono laboral: ', (string) ($d['telltm'] ?? ''), 30);

        return $this->fila($y, 'Email: ', (string) ($d['emailmad'] ?? ''), 30);
    }

    private function dibujarSeccionPadre(float $y): float
    {
        $d = $this->datos;
        $y = $this->encabezadoSeccion($y, 'DATOS DEL PADRE');
        $y = $this->fila($y, 'Apellido y nombre: ', (string) ($d['nombrepad'] ?? ''), 30);
        $y = $this->fila($y, 'D.N.I.: ', (string) ($d['dnipad'] ?? ''), 30);
        $y = $this->fila($y, 'Domicilio: ', (string) ($d['domipad'] ?? ''), 30);
        $y = $this->fila($y, 'Teléfono: ', (string) ($d['telepad'] ?? ''), 30);
        $y = $this->fila($y, 'Ocupación: ', (string) ($d['ocupacpad'] ?? ''), 30);
        $y = $this->fila($y, 'Lugar de Trabajo: ', (string) ($d['lugtrapad'] ?? ''), 30);
        $y = $this->fila($y, 'Teléfono laboral: ', (string) ($d['telltp'] ?? ''), 30);

        return $this->fila($y, 'Email: ', (string) ($d['emailpad'] ?? ''), 30);
    }

    private function dibujarSeccionTutor(float $y): float
    {
        $d = $this->datos;
        $y = $this->encabezadoSeccion($y, 'DATOS DEL TUTOR LEGAL');
        $y = $this->fila($y, 'Apellido y nombre: ', (string) ($d['nombretut'] ?? ''), 30);
        $y = $this->fila($y, 'Ocupación: ', (string) ($d['ocupactut'] ?? ''), 30);

        return $this->fila($y, 'Domicilio: ', (string) ($d['domitut'] ?? ''), 30);
    }

    private function dibujarAvisoYFirma(float $y): void
    {
        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, 'I', 8);
        $this->MultiCell(
            155,
            5,
            'El registro e impresión de esta SOLICITUD DE MATRÍCULA no implica quedar matriculado automáticamente; el trámite deberá ser completado en el Colegio, en los días estipulados en la Circular de Matrícula.',
            0,
            'C',
            false,
            1,
        );

        $this->SetXY(self::MARGEN_IZQ, $this->GetY() + 15);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Write(
            4,
            '              Córdoba ........ de .................... de ..........                                                                                      Firma del Padre/Tutor  ',
        );
    }

    private function encabezadoSeccion(float $y, string $titulo): float
    {
        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(self::ANCHO_SECCION, 5, $titulo, 1, 1, 'C', true);

        return $this->GetY();
    }

    private function fila(float $y, string $etiqueta, string $valor, float $wEtiqueta): float
    {
        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell($wEtiqueta, 5, $etiqueta, 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', 8);
        $anchoValor = max(8.0, self::ANCHO_CABECERA - $wEtiqueta);
        $this->Cell($anchoValor, 5, $valor, 0, 1, 'L');

        return $this->GetY();
    }
}
