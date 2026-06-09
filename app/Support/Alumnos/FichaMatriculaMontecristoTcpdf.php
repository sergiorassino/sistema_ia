<?php

namespace App\Support\Alumnos;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Ficha de solicitud de matrícula Montecristo (Legal vertical, legacy FPDF).
 */
final class FichaMatriculaMontecristoTcpdf extends TCPDF
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
        parent::__construct('P', 'mm', 'Legal', true, 'UTF-8', false);
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

        if (! empty($d['matriculaCondicional'])) {
            TcpdfFuenteArial::aplicar($this, '', 8);
            $this->Cell(
                self::ANCHO_BLOQUE,
                6,
                'MATRÍCULA CONDICIONAL: Debe saldar toda la deuda para poder matricularse en el año subsiguiente',
                0,
                1,
                'C',
            );
        }

        $this->Ln(4);
        $this->dibujarEncabezadoSolicitud();
        $this->dibujarSeccionAlumno();
        $this->dibujarSeccionPadres();
        $this->dibujarTelefonosAlternativos();
        $this->dibujarFirmasSolicitud();
        $this->dibujarComprobante();
    }

    private function dibujarEncabezadoSolicitud(): void
    {
        $d = $this->datos;

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(
            self::ANCHO_BLOQUE,
            self::ALTURA_FILA,
            'Fecha:     '.(string) ($d['fechaMatriculacion'] ?? '').'                                                                                    Nº de Orden: ................................................',
            0,
            1,
            'L',
        );
        $this->Cell(
            self::ANCHO_BLOQUE,
            self::ALTURA_FILA,
            'Número de Matrícula:  '.(string) ($d['nroMatricula'] ?? '').'                (Nº del recibo de pago de la reserva de banco)',
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

        $curso = trim((string) ($d['curso'] ?? ''));
        $nivel = trim((string) ($d['nombreNivel'] ?? ''));
        $cursoLinea = trim($curso.' '.$nivel);

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, self::ALTURA_FILA, '                             en   ', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(50, self::ALTURA_FILA, $cursoLinea, 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(70, self::ALTURA_FILA, ',   a cuyo fin proporciona los siguientes datos:', 0, 1, 'L');
        $this->Ln(6);
    }

    private function dibujarSeccionAlumno(): void
    {
        $d = $this->datos;

        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(self::ANCHO_BLOQUE, self::ALTURA_FILA, 'DATOS DEL ALUMNO', 1, 1, 'C', true);
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

        $cursoAnt = trim((string) ($d['cursoAnterior'] ?? '').' '.(string) ($d['nombreNivelAnterior'] ?? ''));
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(53, 3, 'Curso al que perteneció el año anterior: ', 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(50, 3, $cursoAnt, 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(60, 3, 'Materias que adeuda (señalar curso)................', 0, 1, 'L');
        $this->Ln(1);
        $this->MultiCell(
            self::ANCHO_BLOQUE,
            3,
            '...........................................................................................................................'
            ."\n".'......................................................................',
            0,
            'L',
        );
        $this->Ln(2);

        $this->filaSubrayada('Centro educativo del que proviene:   ', (string) ($d['escori'] ?? ''), 45, 70);
        $this->filaSubrayada('El alumno vive con:', (string) ($d['vivecon'] ?? ''), 35, 70);
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

    private function dibujarSeccionPadres(): void
    {
        $d = $this->datos;

        $this->Ln(4);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(self::ANCHO_BLOQUE, self::ALTURA_FILA, 'DATOS DE LOS PADRES / TUTOR', 1, 1, 'C', true);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(self::ANCHO_BLOQUE, 5, '(En caso que el alumno viva con sus padres, no consignar el domicilio)', 0, 1, 'C');
        $this->Ln(2);

        $this->dibujarBloqueProgenitor('PADRE', $d, 'pad');
        $this->Ln(4);
        $this->dibujarBloqueProgenitor('MADRE', $d, 'mad');
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private function dibujarBloqueProgenitor(string $titulo, array $d, string $prefijo): void
    {
        $this->filaSubrayada(
            $titulo.': Apellido y Nombres (completos): ',
            (string) ($d['nombre'.$prefijo] ?? ''),
            55,
            100,
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
        $this->Ln(2);
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
        $this->Ln(12);
    }

    private function dibujarFirmasSolicitud(): void
    {
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(40, 3, '                    ..............................................................', 0, 0, 'L');
        $this->Cell(50, 3, '', 0, 0);
        $this->Cell(40, 3, '...............................................................', 0, 1, 'L');

        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(40, 3, '                                           Firma de la Madre / Tutor', 0, 0, 'L');
        $this->Cell(50, 3, '', 0, 0);
        $this->Cell(40, 3, '                         Firma del Padre / Tutor', 0, 1, 'L');
        $this->Ln(6);
    }

    private function dibujarComprobante(): void
    {
        $d = $this->datos;
        $x = $this->GetX();
        $y = $this->GetY();
        $this->Line($x, $y, 190, $y);

        $this->Ln(6);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(15, 3, '(Completar y cortar. Entregar como comprobante de matriculación)', 0, 1, 'L');
        $this->Ln(3);

        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(190, 6, (string) ($d['insti'] ?? ''), 0, 1, 'C');

        $nivel = trim((string) ($d['nombreNivel'] ?? ''));
        $nro = trim((string) ($d['nroMatricula'] ?? ''));
        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->Cell(15, 3, '                                                '.$nivel.'               Matrícula Nº '.$nro, 0, 1, 'L');
        $this->Ln(6);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(100, 3, trim($d['apellido'].' '.$d['nombre']), 'B', 1, 'L');
        $this->Ln(3);

        $curso = trim((string) ($d['curso'] ?? '').' '.(string) ($d['nombreNivel'] ?? ''));
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(190, 3, 'Ha sido matriculado/a en '.$curso.'  Turno: Mañana / Tarde (marcar lo que corresponda)', 0, 1, 'L');
        $this->Ln(3);

        $localidad = trim((string) ($d['localidadInstitucion'] ?? 'Monte Cristo'));
        $this->Cell(190, 3, $localidad.', '.(string) ($d['fechaMatriculacion'] ?? ''), 0, 1, 'L');
        $this->Ln(6);

        $this->Cell(190, 3, '                                                                                                                                              ........................................................................', 0, 1, 'L');
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(190, 3, '                                                                    Sello                                                                                                         Firma manuscrita del Secretario', 0, 1, 'L');
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
