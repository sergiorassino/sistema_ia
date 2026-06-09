<?php

namespace App\Support\Alumnos;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Ficha de matrícula con aceptación de documentos (A4 vertical, una página, membrete institucional SE).
 */
final class FichaMatriculaConAceptacionTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 30.0;

    private const MARGEN_SUP = 6.0;

    private const ANCHO_BLOQUE = 160.0;

    private const ALTURA_CABECERA_INST = 13.0;

    /** Altura de filas de datos (formato legacy FPDF). */
    private const ALTURA_FILA = 5.0;

    /** Ahorro vertical respecto al bloque de firma legacy (Ln 20 → 5). */
    private const ESPACIO_ANTES_FIRMA = 5.0;

    /** @var array<string, mixed> */
    private array $datos;

    /** @var array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string} */
    private array $header;

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    private function __construct(array $datos, array $header)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->datos = $datos;
        $this->header = $header;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Ficha de matrícula');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, 20);
        $this->SetFillColor(232, 232, 232);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
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
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generarLote(array $hojas, array $header): self
    {
        $pdf = new self([], $header);

        foreach ($hojas as $datos) {
            $pdf->datos = $datos;
            $pdf->header = $datos['header'] ?? $header;
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
        $y = self::MARGEN_SUP;
        $y = $this->dibujarMarcoCabecera($y);
        $y = $this->dibujarTituloFicha($y);
        $y = $this->dibujarLineaMatriculaCurso($y);

        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->dibujarSeccionAlumno();
        $this->dibujarSeccionMadre();
        $this->dibujarSeccionPadre();
        $this->dibujarSeccionTutor();
        $this->dibujarInformacionAdicional();
        $this->dibujarConsentimiento();
        $this->dibujarFirma();
    }

    private function dibujarMarcoCabecera(float $y): float
    {
        $x = self::MARGEN_IZQ;
        $w = self::ANCHO_BLOQUE;
        $h = self::ALTURA_CABECERA_INST;

        $this->SetDrawColor(17, 17, 17);
        $this->RoundedRect($x, $y, $w, $h, 1.5, '1111', 'D');

        $logo = $this->header['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $this->Image($logo, $x + 1.5, $y + 1, 9, 11, '', '', '', false, 300);
        }

        $insti = trim((string) ($this->header['insti'] ?? ''));
        $direccion = trim((string) ($this->header['direccion'] ?? ''));
        $localidad = trim((string) ($this->header['localidad'] ?? ''));
        $lineaDir = trim($direccion.($direccion !== '' && $localidad !== '' ? ' — ' : '').$localidad);
        $cue = trim((string) ($this->header['cue'] ?? ''));
        $ee = trim((string) ($this->header['ee'] ?? ''));
        $lineaIds = trim(($cue !== '' ? 'CUE: '.$cue : '').(($cue !== '' && $ee !== '') ? '   ' : '').($ee !== '' ? 'EE: '.$ee : ''));

        $this->SetXY($x, $y + 1.5);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell($w, 3.2, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        if ($lineaDir !== '') {
            TcpdfFuenteArial::aplicar($this, '', 6);
            $this->Cell($w, 2.6, $lineaDir, 0, 2, 'C');
        }
        if ($lineaIds !== '') {
            TcpdfFuenteArial::aplicar($this, '', 5.5);
            $this->Cell($w, 2.4, $lineaIds, 0, 2, 'C');
        }

        return $y + $h + 1.0;
    }

    private function dibujarTituloFicha(float $y): float
    {
        $ciclo = (string) ($this->datos['cicloLectivo'] ?? '');
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->Cell(
            self::ANCHO_BLOQUE,
            6,
            'FICHA DE MATRÍCULA - CICLO LECTIVO '.$ciclo,
            0,
            0,
            'C',
            false,
        );

        return $y + 6;
    }

    private function dibujarLineaMatriculaCurso(float $y): float
    {
        $nroMat = (string) ($this->datos['nroMatricula'] ?? '');
        $curso = (string) ($this->datos['curso'] ?? '');
        $cursoLinea = $curso !== ''
            ? $curso
            : '..................................................';

        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->Ln(2);

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(70, 6, 'Nº de Matrícula:  '.$nroMat.'                                              ', 0, 0, 'L', false);
        $this->Cell(110, 6, 'Sala / Grado / Curso: '.$cursoLinea.' ', 0, 1, 'L', false);
        $this->Ln(1);

        return $this->GetY();
    }

    private function encabezadoSeccion(string $titulo): void
    {
        $this->Ln(1);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(self::ANCHO_BLOQUE, self::ALTURA_FILA, $titulo, 1, 1, 'C', true);
    }

    private function filaEtiquetaValor(string $etiqueta, string $valor, float $wEtiqueta, float $wValor = 0): void
    {
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell($wEtiqueta, self::ALTURA_FILA, $etiqueta, 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        if ($wValor > 0) {
            $this->Cell($wValor, self::ALTURA_FILA, $valor, 0, 1, 'L', false);
        } else {
            $this->Cell(0, self::ALTURA_FILA, $valor, 0, 1, 'L', false);
        }
    }

    private function dibujarSeccionAlumno(): void
    {
        $d = $this->datos;
        $this->encabezadoSeccion('DATOS DEL ALUMNO');

        $this->filaEtiquetaValor('APELLIDO Y NOMBRE: ', trim($d['apellido'].' '.$d['nombre']), 40, 100);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(20, self::ALTURA_FILA, 'D.N.I.:: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, self::ALTURA_FILA, (string) $d['dni'], 0, 0, 'L', false);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(20, self::ALTURA_FILA, 'Sexo: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, self::ALTURA_FILA, (string) $d['sexo'], 0, 0, 'L', false);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(30, self::ALTURA_FILA, 'Fecha de Nacimiento: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, self::ALTURA_FILA, (string) $d['fechnaci'], 0, 1, 'L', false);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(30, self::ALTURA_FILA, 'Lugar de Nacimiento: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(80, self::ALTURA_FILA, (string) $d['ln_ciudad'], 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(20, self::ALTURA_FILA, 'Nacionalidad: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, self::ALTURA_FILA, (string) $d['nacion'], 0, 1, 'L', false);

        $this->filaEtiquetaValor('Dirección: ', (string) $d['callenum'], 20);
        $this->filaEtiquetaValor('Barrio: ', (string) $d['barrio'], 20);
        $this->filaEtiquetaValor('Localidad: ', (string) $d['localidad'], 20, 80);
        $this->filaEtiquetaValor('Celular del Estudiante: ', (string) $d['telefono'], 40);
        $this->filaEtiquetaValor('Email Institucional: ', (string) $d['email'], 40);
        $this->filaEtiquetaValor('Escuela de Origen: ', (string) $d['escori'], 40);
        $this->filaEtiquetaValor('Necesidades Especiales: ', (string) $d['needes'], 40, 20);

        $this->filaEtiquetaValor(
            'Necesidades Especiales (Centro o Profesional que lo acompaña y teléfono de contacto): ',
            '',
            100,
        );
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->MultiCell(0, self::ALTURA_FILA, (string) $d['needes_detalle'], 0, 'L', false, 1);
    }

    private function dibujarSeccionMadre(): void
    {
        $d = $this->datos;
        $this->encabezadoSeccion('DATOS DE LA MADRE');
        $this->filaEtiquetaValor('Apellido y nombre: ', (string) $d['nombremad'], 30, 100);
        $this->filaEtiquetaValor('D.N.I.: ', (string) $d['dnimad'], 30, 30);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(30, self::ALTURA_FILA, 'Teléfono: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(60, self::ALTURA_FILA, (string) $d['telemad'], 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(20, self::ALTURA_FILA, 'Email: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, self::ALTURA_FILA, (string) $d['emailmad'], 0, 1, 'L', false);

        $this->filaEtiquetaValor('Ocupación: ', (string) $d['ocupacmad'], 20, 80);
    }

    private function dibujarSeccionPadre(): void
    {
        $d = $this->datos;
        $this->encabezadoSeccion('DATOS DEL PADRE');
        $this->filaEtiquetaValor('Apellido y nombre: ', (string) $d['nombrepad'], 30, 100);
        $this->filaEtiquetaValor('D.N.I.: ', (string) $d['dnipad'], 30, 30);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(30, self::ALTURA_FILA, 'Teléfono: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(60, self::ALTURA_FILA, (string) $d['telepad'], 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(20, self::ALTURA_FILA, 'Email: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, self::ALTURA_FILA, (string) $d['emailpad'], 0, 1, 'L', false);

        $this->filaEtiquetaValor('Ocupación: ', (string) $d['ocupacpad'], 20, 80);
    }

    private function dibujarSeccionTutor(): void
    {
        $d = $this->datos;
        $this->encabezadoSeccion('DATOS DEL TUTOR LEGAL');
        $this->filaEtiquetaValor('Apellido y nombre: ', (string) $d['nombretut'], 30, 100);
        $this->filaEtiquetaValor('D.N.I.: ', (string) $d['dnitut'], 30, 30);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(30, self::ALTURA_FILA, 'Teléfono: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(60, self::ALTURA_FILA, (string) $d['teletut'], 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(20, self::ALTURA_FILA, 'Email: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, self::ALTURA_FILA, (string) $d['emailtut'], 0, 1, 'L', false);

        $this->filaEtiquetaValor('Ocupación: ', (string) $d['ocupactut'], 20, 80);
    }

    private function dibujarInformacionAdicional(): void
    {
        $d = $this->datos;
        $this->encabezadoSeccion('INFORMACIÓN ADICIONAL');

        $this->filaEtiquetaValor('Estado civil de los Padres: ', (string) $d['ec_padres'], 40, 80);
        $this->filaEtiquetaValor('¿Con quién vive el estudiante?: ', (string) $d['vivecon'], 50, 80);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(
            50,
            self::ALTURA_FILA,
            'En caso de emergencia e imposibilitados de contactar a los padres o tutor comunicar para el retiro de estudiantes a: ',
            0,
            1,
            'L',
            false,
        );

        $this->filaEtiquetaValor('Teléfono de contacto 1: ', (string) $d['contacto1'], 40, 80);
        $this->filaEtiquetaValor('Teléfono de contacto 2: ', (string) $d['contacto2'], 40, 80);
        $this->filaEtiquetaValor('Teléfono de contacto 3: ', (string) $d['contacto3'], 40, 80);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(
            50,
            self::ALTURA_FILA,
            'Personas autorizadas para el reitro del estudiante en caso de imposibilidad de contactar: ',
            0,
            1,
            'L',
            false,
        );

        $this->filaEtiquetaValor('Apellido, Nombre, Relación, Teléfono (1): ', (string) $d['retira1'], 60, 80);

        if (! empty($d['mostrarRetira2'])) {
            $this->filaEtiquetaValor('Apellido, Nombre, Relación, Teléfono (2): ', (string) $d['retira2'], 60, 80);
        }

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(40, self::ALTURA_FILA, 'OBSERVACIONES: ', 0, 1, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->MultiCell(0, self::ALTURA_FILA, (string) $d['obs_web'], 0, 'L', false, 1);
        $this->Ln(1);
    }

    private function dibujarConsentimiento(): void
    {
        $d = $this->datos;
        $texto = 'ACEPTO Y DOY MI CONSENTIMIENTO EN LO REFERIDO A: COMPROMISO EDUCATIVO, AEC y NORMATIVAS DEL NIVEL Y AUTORIZACIÓN PARA EL TRASLADO POR LOS ESPACIOS INSTITUCIONALES, EN REPRESENTACIÓN DE LA RESPONSABILIDAD PARENTAL Y ASUMIENDO EL COMPROMISO DE INFORMAR EL OTRO PROGENITOR Y/O TUTOR LEGAL
Mediante la presente, manifiesto que toda la información aquí consignada es veraz y ha sido entregada de buena fe, comprometiéndome a informar oportunamente a la Institución sobre cualquier modificación que pudiera surgir.';

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->MultiCell(155, 4, $texto, 0, 'L', false, 1);

        $lineaReg = trim((string) $d['reglamApenom'])
            .' - '.trim((string) $d['reglamDni'])
            .' - '.trim((string) $d['reglamEmail']);

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, self::ALTURA_FILA, $lineaReg, 0, 1, 'L', false);
    }

    private function dibujarFirma(): void
    {
        $this->Ln(self::ESPACIO_ANTES_FIRMA);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Write(
            4,
            '              Córdoba ........ de .................... de ..........                                                                                      Firma del Padre/Tutor  ',
        );
    }
}
