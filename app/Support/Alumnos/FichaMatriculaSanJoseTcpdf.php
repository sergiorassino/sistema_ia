<?php

namespace App\Support\Alumnos;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Ficha de solicitud de matrícula San José (A4 vertical, legacy FPDF).
 */
final class FichaMatriculaSanJoseTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 30.0;

    private const ANCHO_BLOQUE = 160.0;

    private const ALTURA_FILA = 5.0;

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
        $this->SetTitle('Ficha de solicitud de matrícula');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetLeftMargin(self::MARGEN_IZQ);
        $this->SetMargins(self::MARGEN_IZQ, 10, 20);
        $this->SetFillColor(232, 232, 232);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage();
        $pdf->dibujarDocumento();

        return $pdf;
    }

    /**
     * @param  list<array<string, mixed>>  $hojas
     */
    public static function generarLote(array $hojas): self
    {
        $pdf = new self($hojas[0] ?? []);

        foreach ($hojas as $datos) {
            $pdf->datos = $datos;
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
        $d = $this->datos;
        $insti = trim((string) ($d['insti'] ?? ''));
        $ciclo = trim((string) ($d['cicloLectivo'] ?? ''));

        $this->dibujarFotoOpcional();

        $this->SetXY(self::MARGEN_IZQ, 10);
        TcpdfFuenteArial::aplicar($this, 'B', 12);
        $this->Cell(self::ANCHO_BLOQUE, 6, $insti !== '' ? $insti : 'Institución', 0, 1, 'C');

        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(
            self::ANCHO_BLOQUE,
            6,
            'FICHA DE SOLICITUD MATRÍCULA - CICLO LECTIVO '.$ciclo,
            0,
            1,
            'C',
        );
        $this->Ln(2);

        $this->dibujarEncabezadoSolicitud();
        $this->dibujarSeccionEstudiante();
        $this->dibujarSeccionAdultosResponsables();
        $this->dibujarTelefonosAlternativos();
        $this->dibujarDeclaracion();
        $this->dibujarFirmas();
    }

    private function dibujarFotoOpcional(): void
    {
        $ruta = trim((string) ($this->datos['fotoCarnet'] ?? ''));
        if ($ruta === '' || ! is_readable($ruta)) {
            return;
        }

        try {
            $this->Image($ruta, self::MARGEN_IZQ, 5, 20, 20);
        } catch (\Throwable) {
            // Sin foto si el archivo no es válido.
        }
    }

    private function dibujarEncabezadoSolicitud(): void
    {
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(
            self::ANCHO_BLOQUE,
            self::ALTURA_FILA,
            'Fecha: ..............................................................................                                   Nº de Orden: ................................................',
            0,
            1,
            'L',
        );
        $this->Cell(
            self::ANCHO_BLOQUE,
            self::ALTURA_FILA,
            'Número de Matrícula: ..............................................................................      (Nº del recibo de pago de la reserva de banco)',
            0,
            1,
            'L',
        );
        $this->Cell(
            self::ANCHO_BLOQUE,
            self::ALTURA_FILA,
            '                             Autoridades del Establecimiento:   Quien suscribe, solicita a Uds la Matrícula de Inscripción',
            0,
            1,
            'L',
        );
        $this->Cell(
            self::ANCHO_BLOQUE,
            self::ALTURA_FILA,
            '                             en  ...............................................  sala / grado / año, a cuyo fin proporciona los siguientes datos:',
            0,
            1,
            'L',
        );
        $this->Ln(3);
    }

    private function dibujarSeccionEstudiante(): void
    {
        $d = $this->datos;

        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(self::ANCHO_BLOQUE, self::ALTURA_FILA, 'DATOS DEL/DE LA ESTUDIANTE', 1, 1, 'C', true);
        $this->Ln(3);

        $this->filaSubrayada('APELLIDO Y NOMBRES (completos): ', trim($d['apellido'].' '.$d['nombre']), 55, 100);

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, 3, 'Lugar de Nacimiento; ', 0, 0, 'L');
        $this->celdaValorSubrayada(40, (string) ($d['ln_ciudad'] ?? ''));
        $this->Cell(30, 3, '', 0, 0);
        $this->Cell(30, 3, 'Fecha de Nacimiento: ', 0, 0, 'L');
        $this->celdaValorSubrayada(30, (string) ($d['fechnaci'] ?? ''));
        $this->Ln(5);

        $this->Cell(25, 3, 'Nacionalidad: ', 0, 0, 'L');
        $this->celdaValorSubrayada(40, (string) ($d['nacion'] ?? ''));
        $this->Cell(15, 3, '', 0, 0);
        $this->Cell(10, 3, 'Edad: ', 0, 0, 'L');
        $this->celdaValorSubrayada(15, (string) ($d['edad'] ?? ''));
        $this->Cell(15, 3, '', 0, 0);
        $this->Cell(10, 3, 'D.N.I.: ', 0, 0, 'L');
        $this->celdaValorSubrayada(20, (string) ($d['dni'] ?? ''));
        $this->Ln(5);

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(
            self::ANCHO_BLOQUE,
            3,
            'Curso al que perteneció el año anterior: .........................................................     Materias que adeuda (señalar curso)................',
            0,
            1,
            'L',
        );
        $this->Ln(1);
        $this->Cell(
            self::ANCHO_BLOQUE,
            3,
            '.................................................................................................................................................................................................',
            0,
            1,
            'L',
        );
        $this->Ln(2);

        $this->Cell(
            self::ANCHO_BLOQUE,
            3,
            'Centro educativo del que proviene: .................................................................................................................',
            0,
            1,
            'L',
        );
        $this->Ln(5);

        $this->Cell(
            self::ANCHO_BLOQUE,
            3,
            'El alumno vive con:  .................................................................................................................',
            0,
            1,
            'L',
        );
        $this->Ln(5);

        $this->filaSubrayada('Domicilio: (donde vive el alumno) : ', (string) ($d['callenum'] ?? ''), 50, 70);

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(15, 3, 'Barrio: ', 0, 0, 'L');
        $this->celdaValorSubrayada(40, (string) ($d['barrio'] ?? ''));
        $this->Cell(10, 3, '', 0, 0);
        $this->Cell(20, 3, 'Localidad: ', 0, 0, 'L');
        $this->celdaValorSubrayada(30, (string) ($d['localidad'] ?? ''));
        $this->Cell(10, 3, '', 0, 0);
        $this->Cell(10, 3, 'C.P.: ', 0, 0, 'L');
        $this->celdaValorSubrayada(20, (string) ($d['codpos'] ?? ''));
        $this->Ln(5);

        $this->filaSubrayada('Teléfono (especificar de quién es): ', (string) ($d['telefono'] ?? ''), 45, 70);

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(28, 3, 'Obra Social (sí/no):', 0, 0, 'L');
        $this->celdaValorSubrayada(10, (string) ($d['obso_sn'] ?? ''));
        $this->Cell(10, 3, '', 0, 0);
        $this->Cell(20, 3, 'Nombre: ', 0, 0, 'L');
        $this->celdaValorSubrayada(30, (string) ($d['obso_nombre'] ?? ''));
        $this->Cell(10, 3, '', 0, 0);
        $this->Cell(20, 3, 'Nº Afiliado: ', 0, 0, 'L');
        $this->celdaValorSubrayada(30, (string) ($d['obso_nro'] ?? ''));
        $this->Ln(5);

        $this->Cell(20, 3, 'Religión:', 0, 0, 'L');
        $this->celdaValorSubrayada(40, (string) ($d['religion'] ?? ''));
        $this->Cell(30, 3, '', 0, 0);
        $this->Cell(20, 3, 'Sacramentos: ', 0, 0, 'L');
        $this->celdaValorSubrayada(40, (string) ($d['sacramentos'] ?? ''));
        $this->Ln(8);
    }

    private function dibujarSeccionAdultosResponsables(): void
    {
        $d = $this->datos;

        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(self::ANCHO_BLOQUE, self::ALTURA_FILA, 'DATOS DE ADULTOS RESPONSABLES', 1, 1, 'C', true);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(self::ANCHO_BLOQUE, 5, '(En caso que el alumno viva con sus padres, no consignar el domicilio)', 0, 1, 'C');
        $this->Ln(1);

        $this->dibujarBloqueAdultoResponsable('ADULTO RESPONSABLE 1', $d, 'pad');
        $this->Ln(3);
        $this->dibujarBloqueAdultoResponsable('ADULTO RESPONSABLE 2', $d, 'mad');
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private function dibujarBloqueAdultoResponsable(string $titulo, array $d, string $prefijo): void
    {
        $this->filaSubrayada(
            $titulo.': Apellido y Nombres (completos): ',
            (string) ($d['nombre'.$prefijo] ?? ''),
            80,
            80,
        );

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(20, 3, 'Nacionalidad: ', 0, 0, 'L');
        $this->celdaValorSubrayada(40, (string) ($d['nacion'.$prefijo] ?? ''));
        $this->Cell(4, 3, '', 0, 0);
        $this->Cell(13, 3, '¿Vive? : ', 0, 0, 'L');
        $this->celdaValorSubrayada(10, (string) ($d['vive'.$prefijo] ?? ''));
        $this->Cell(4, 3, '', 0, 0);
        $this->Cell(10, 3, 'D.N.I.: ', 0, 0, 'L');
        $this->celdaValorSubrayada(17, (string) ($d['dni'.$prefijo] ?? ''));
        $this->Cell(4, 3, '', 0, 0);
        $this->Cell(20, 3, 'Fecha de Nac.: ', 0, 0, 'L');
        $this->celdaValorSubrayada(15, (string) ($d['fechnac'.$prefijo] ?? ''));
        $this->Ln(5);

        $this->Cell(20, 3, 'Domicilio:', 0, 0, 'L');
        $this->celdaValorSubrayada(40, (string) ($d['domi'.$prefijo] ?? ''));
        $this->Cell(30, 3, '', 0, 0);
        $this->Cell(20, 3, 'Localidad: ', 0, 0, 'L');
        $this->celdaValorSubrayada(40, (string) ($d['loca'.$prefijo] ?? ''));
        $this->Ln(5);

        $this->Cell(20, 3, 'Teléfono:', 0, 0, 'L');
        $this->celdaValorSubrayada(30, (string) ($d['tele'.$prefijo] ?? ''));
        $this->Cell(30, 3, '', 0, 0);
        $this->Cell(20, 3, 'Ocupación: ', 0, 0, 'L');
        $this->celdaValorSubrayada(40, (string) ($d['ocupac'.$prefijo] ?? ''));
        $this->Ln(5);

        $this->filaSubrayada('Lugar de Trabajo: (consignar nombre)', (string) ($d['lugtra'.$prefijo] ?? ''), 50, 90);
        $this->filaSubrayada('E-mail:', (string) ($d['email'.$prefijo] ?? ''), 15, 60);
        $this->Ln(3);
    }

    private function dibujarTelefonosAlternativos(): void
    {
        $d = $this->datos;

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(40, 3, 'TELEFONOS ALTERNATIVOS:', 0, 1, 'L');
        $this->Ln(3);

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, 3, 'Apellido y Nombres:', 0, 0, 'L');
        $this->celdaValorSubrayada(50, (string) ($d['telealte1_nom'] ?? ''));
        $this->Cell(10, 3, '', 0, 0);
        $this->Cell(20, 3, 'Teléfono: ', 0, 0, 'L');
        $this->celdaValorSubrayada(40, (string) ($d['telealte1_tel'] ?? ''));
        $this->Ln(5);

        $this->Cell(30, 3, 'Apellido y Nombres:', 0, 0, 'L');
        $this->celdaValorSubrayada(50, (string) ($d['telealte2_nom'] ?? ''));
        $this->Cell(10, 3, '', 0, 0);
        $this->Cell(20, 3, 'Teléfono: ', 0, 0, 'L');
        $this->celdaValorSubrayada(40, (string) ($d['telealte2_tel'] ?? ''));
        $this->Ln(6);
    }

    private function dibujarDeclaracion(): void
    {
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(100, 4, 'Mediante la presente, declaro: ', 0, 1, 'L');

        $texto = "1. Adhesión de la familia al Ideario y Proyecto Educativo Institucional.\n"
            ."2. Adhesión y cumplimiento del Acuerdo Escolar de Convivencia y del Reglamento del nivel correspondiente.\n"
            ."3. Aceptación de las disposiciones arancelarias y cumplimiento del pago mensual de los aranceles fijados.\n"
            ."4. Compromiso de informar cualquier cambio en los datos consignados en el formulario de matrícula.\n"
            ."5. Aceptación de notificaciones de contenido institucional vía correo electrónico.\n"
            ."6. Autorización del uso de la imagen del estudiante con fines institucionales.\n"
            ."7. Compromiso de presentar el Certificado Único de Salud (C.U.S.) para Educación Física debidamente completado el primer día de clases.";

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->MultiCell(self::ANCHO_BLOQUE + 10, 4, $texto, 0, 'L');

        $this->Ln(3);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(self::ANCHO_BLOQUE + 10, 3, 'Córdoba,  ............./............./.................', 0, 1, 'L');
        $this->Ln(8);
    }

    private function dibujarFirmas(): void
    {
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(40, 3, '                    ..............................................................', 0, 0, 'L');
        $this->Cell(50, 3, '', 0, 0);
        $this->Cell(40, 3, '...............................................................', 0, 1, 'L');

        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(40, 3, '                                           Adulto Responsable', 0, 0, 'L');
        $this->Cell(50, 3, '', 0, 0);
        $this->Cell(40, 3, '                         Adulto Responsable', 0, 1, 'L');
    }

    private function filaSubrayada(string $etiqueta, string $valor, float $wEtiqueta, float $wValor): void
    {
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell($wEtiqueta, 3, $etiqueta, 0, 0, 'L');
        $this->celdaValorSubrayada($wValor, $valor);
        $this->Ln(5);
    }

    private function celdaValorSubrayada(float $ancho, string $valor): void
    {
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell($ancho, 3, $valor, 'B', 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', 8);
    }
}
