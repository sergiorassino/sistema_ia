<?php

namespace App\Support\Alumnos;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use App\Support\Pdf\TcpdfMultiCellJustificado;
use TCPDF;

/**
 * Ficha de matrícula IESS / IESS VCP (A4 vertical).
 *
 * Adaptación del FPDF legacy ScriptCase: cabecera con logo, datos del estudiante,
 * madre, padre, datos escolares (líneas en blanco), información adicional,
 * AEC (primario) y autorización de imágenes (secundario).
 */
final class FichaMatriculaIessTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 30.0;

    private const ANCHO_BLOQUE = 160.0;

    private const ALTURA_FILA = 5.0;

    /** @var array<string, mixed> */
    private array $datos;

    /** @var array{insti: string, direccion: string, localidad: string, provincia?: string, cue: string, ee: string, logo_file: ?string} */
    private array $header;

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{insti: string, direccion: string, localidad: string, provincia?: string, cue: string, ee: string, logo_file: ?string}  $header
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
        $this->SetMargins(self::MARGEN_IZQ, 10, 20);
        $this->SetFillColor(232, 232, 232);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{insti: string, direccion: string, localidad: string, provincia?: string, cue: string, ee: string, logo_file: ?string}  $header
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
     * @param  array{insti: string, direccion: string, localidad: string, provincia?: string, cue: string, ee: string, logo_file: ?string}  $header
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
        $this->dibujarCabecera();
        $this->dibujarSeccionEstudiante();
        $this->dibujarSeccionMadre();
        $this->dibujarSeccionPadre();
        $this->dibujarSeccionDatosEscolares();
        $this->dibujarInformacionAdicional();
        $this->dibujarAecSiPrimario();
        $this->dibujarFirmaPrincipal();
        $this->dibujarAutorizacionImagenesSiSecundario();
    }

    private function idNivel(): int
    {
        return (int) ($this->datos['idNivel'] ?? 0);
    }

    private function dibujarCabecera(): void
    {
        $logo = $this->header['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            try {
                $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), 40, 11, 15, 20, '', '', '', false, 300);
            } catch (\Throwable) {
                // Sin logo si el archivo no es válido.
            }
        }

        $insti = trim((string) ($this->header['insti'] ?? ''));
        $direccion = trim((string) ($this->header['direccion'] ?? ''));
        $localidad = trim((string) ($this->header['localidad'] ?? ''));
        $provincia = trim((string) ($this->header['provincia'] ?? ''));
        $partesDir = array_values(array_filter([$direccion, $localidad, $provincia], fn ($v) => $v !== ''));
        $lineaDir = implode(' - ', $partesDir);
        $ciclo = trim((string) ($this->datos['cicloLectivo'] ?? ''));

        TcpdfFuenteArial::aplicar($this, 'B', 12);
        $this->SetXY(60, 17);
        $this->Cell(110, 6, $insti !== '' ? $insti : 'Institución', 0, 0, 'C', false);

        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->SetXY(60, 23);
        $this->Cell(110, 4, $lineaDir, 0, 0, 'C', false);

        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->SetXY(60, 27);
        $this->Cell(
            110,
            6,
            'FICHA DE MATRÍCULA - CICLO LECTIVO '.$ciclo,
            0,
            0,
            'C',
            false,
        );

        $this->SetXY(self::MARGEN_IZQ, 35);
        $this->Ln(2);
    }

    private function encabezadoSeccion(string $titulo): void
    {
        $this->Ln(1);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(self::ANCHO_BLOQUE, self::ALTURA_FILA, $titulo, 1, 1, 'C', true);
        $this->Ln(3);
    }

    private function dibujarSeccionEstudiante(): void
    {
        $d = $this->datos;
        $this->encabezadoSeccion('DATOS DE ESTUDIANTE');

        $apenom = trim(trim((string) ($d['apellido'] ?? '')).' '.trim((string) ($d['nombre'] ?? '')));
        $grupsang = trim((string) ($d['grupsang'] ?? ''));
        $textoGs = 'Grupo sanguíneo: '.($grupsang !== '' ? $grupsang : '............');

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(40, self::ALTURA_FILA, 'APELLIDO Y NOMBRE: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $anchoNombre = self::ANCHO_BLOQUE - 40;
        $anchoGs = 48.0;
        $this->Cell($anchoNombre - $anchoGs, self::ALTURA_FILA, $apenom, 0, 0, 'L', false);
        $this->Cell($anchoGs, self::ALTURA_FILA, $textoGs, 0, 1, 'R', false);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(20, self::ALTURA_FILA, 'D.N.I.: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, self::ALTURA_FILA, (string) ($d['dni'] ?? ''), 0, 0, 'L', false);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(20, self::ALTURA_FILA, 'Legajo: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, self::ALTURA_FILA, (string) ($d['legajo'] ?? ''), 0, 1, 'L', false);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(20, self::ALTURA_FILA, 'Sexo: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, self::ALTURA_FILA, (string) ($d['sexo'] ?? ''), 0, 0, 'L', false);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(30, self::ALTURA_FILA, 'Fecha de Nacimiento: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, self::ALTURA_FILA, (string) ($d['fechnaci'] ?? ''), 0, 1, 'L', false);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(30, self::ALTURA_FILA, 'Lugar de Nacimiento: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(80, self::ALTURA_FILA, (string) ($d['ln_ciudad'] ?? ''), 0, 0, 'L', false);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(20, self::ALTURA_FILA, 'Nacionalidad: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, self::ALTURA_FILA, (string) ($d['nacion'] ?? ''), 0, 1, 'L', false);

        $this->filaSimple('Dirección: ', (string) ($d['callenum'] ?? ''), 20);
        $this->filaSimple('Barrio: ', (string) ($d['barrio'] ?? ''), 20);
        $this->filaSimple('Localidad: ', (string) ($d['localidad'] ?? ''), 20);
        $this->filaSimple('Teléfono: ', (string) ($d['telefono'] ?? ''), 20);

        if ($this->idNivel() === 3) {
            $this->filaSimple('Email: ', (string) ($d['email'] ?? ''), 20);
        }
    }

    private function dibujarSeccionMadre(): void
    {
        $d = $this->datos;
        $this->encabezadoSeccion('DATOS DE LA MADRE');

        $this->filaSimple('Apellido y nombre: ', (string) ($d['nombremad'] ?? ''), 30, 100);
        $this->filaSimple('D.N.I.: ', (string) ($d['dnimad'] ?? ''), 30, 30);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(30, self::ALTURA_FILA, 'Teléfono: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(60, self::ALTURA_FILA, (string) ($d['telemad'] ?? ''), 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(20, self::ALTURA_FILA, 'Email: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, self::ALTURA_FILA, (string) ($d['emailmad'] ?? ''), 0, 1, 'L', false);

        $this->filaSimple('Ocupación: ', (string) ($d['ocupacmad'] ?? ''), 20, 80);
    }

    private function dibujarSeccionPadre(): void
    {
        $d = $this->datos;
        $this->encabezadoSeccion('DATOS DEL PADRE');

        $this->filaSimple('Apellido y nombre: ', (string) ($d['nombrepad'] ?? ''), 30, 100);
        $this->filaSimple('D.N.I.: ', (string) ($d['dnipad'] ?? ''), 30, 30);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(30, self::ALTURA_FILA, 'Teléfono: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(60, self::ALTURA_FILA, (string) ($d['telepad'] ?? ''), 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(20, self::ALTURA_FILA, 'Email: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, self::ALTURA_FILA, (string) ($d['emailpad'] ?? ''), 0, 1, 'L', false);

        $this->filaSimple('Ocupación: ', (string) ($d['ocupacpad'] ?? ''), 20, 80);
    }

    private function dibujarSeccionDatosEscolares(): void
    {
        $this->encabezadoSeccion('DATOS ESCOLARES');

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(
            self::ANCHO_BLOQUE,
            self::ALTURA_FILA,
            'Curso al que perteneció el año anterior: ............................................   División: ................',
            0,
            1,
            'L',
            false,
        );

        if ($this->idNivel() === 3) {
            $this->Cell(
                self::ANCHO_BLOQUE,
                self::ALTURA_FILA,
                'Materias que adeuda: .................................................................................................................................',
                0,
                1,
                'L',
                false,
            );
        }

        $this->Cell(
            self::ANCHO_BLOQUE,
            self::ALTURA_FILA,
            'Tiene hermanas/os en la institución: ...................................................................................',
            0,
            1,
            'L',
            false,
        );
    }

    private function dibujarInformacionAdicional(): void
    {
        $d = $this->datos;
        $this->encabezadoSeccion('INFORMACIÓN ADICIONAL');

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(50, self::ALTURA_FILA, '¿Con quién vive el/la estudiante?: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(80, self::ALTURA_FILA, (string) ($d['vivecon'] ?? ''), 0, 1, 'L', false);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(self::ANCHO_BLOQUE, self::ALTURA_FILA, 'Responsable Contable (a quién emitir factura electrónica)', 0, 1, 'L', false);

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(
            self::ANCHO_BLOQUE,
            self::ALTURA_FILA,
            'Apellido y Nombre: ...........................................................................................................  CUIL:.................................',
            0,
            1,
            'L',
            false,
        );

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(40, self::ALTURA_FILA, 'Observaciones: ', 0, 0, 'L', false);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(120, self::ALTURA_FILA, (string) ($d['obs'] ?? $d['obs_web'] ?? ''), 0, 1, 'L', false);
    }

    private function dibujarAecSiPrimario(): void
    {
        if ($this->idNivel() !== 2) {
            return;
        }

        $this->Ln(5);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $texto = 'He tomado conocimiento de los Acuerdos Escolares de Convivencia (AEC) de la Esc. Primaria de Aplicación del IESS y de su Reglamento Interno. Doy mi conformidad, aceptando los lineamientos que regulan la convivencia escolar y las normativas que hacen posible la misma.';
        $this->SetX(self::MARGEN_IZQ);
        TcpdfMultiCellJustificado::escribir($this, self::ANCHO_BLOQUE, 4, $texto);
        $this->Ln(5);
    }

    private function dibujarFirmaPrincipal(): void
    {
        $this->Ln(8);
        $this->Ln(5);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Write(
            4,
            'Villa Carlos Paz ........ de .................................. de ..........                      Firma de Padre / Madre /Tutor                                           Aclaración',
        );
        $this->Ln(3);
    }

    private function dibujarAutorizacionImagenesSiSecundario(): void
    {
        if ($this->idNivel() !== 3) {
            return;
        }

        $y = $this->GetY();
        $this->Line(30, $y + 3, 200, $y + 3);
        $this->Ln(5);

        $intro = 'Con la inclusión de las nuevas tecnologías dentro de los medios didácticos al alcance de la comunidad escolar y la posibilidad de que en estos puedan aparecer imágenes de sus hijos/as durante la realización de las actividades escolares, es que por la presente solicitamos su consentimiento para poder publicar imágenes en las cuales aparezcan individualmente o en grupo en las diferentes actividades realizadas en el Instituto y fuera del mismo, en actividades extraescolares.';

        $autorizacion = 'Por la presente, ......................................................................................   DNI ................................., y como Madre/Padre/Tutor del alumno/a, autorizo al IESS (IESS VCP) al uso pedagógico de las imágenes obtenidas en actividades lectivas, complementarias y extraescolares organizadas por el Instituto para ser publicadas (soporte tradicional y/o digital) en diversos espacios de la institución (trasparentes, pasillos) y en los sitios web del mismo, grupo de Whatsapp Institucionales, así como también filmaciones destinadas a la difusión educativa y/o fotográfica para publicaciones del ámbito educativo de la escuela.';

        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->SetX(self::MARGEN_IZQ);
        TcpdfMultiCellJustificado::escribir($this, self::ANCHO_BLOQUE, 3.8, $intro);
        $this->Ln(2);
        $this->SetX(self::MARGEN_IZQ);
        TcpdfMultiCellJustificado::escribir($this, self::ANCHO_BLOQUE, 3.8, $autorizacion);

        $this->Ln(12);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Write(
            4,
            '              Firma de Madre / Padre / Tutor                                                      Aclaración                                                                          D.N.I.  ',
        );
    }

    private function filaSimple(string $etiqueta, string $valor, float $wEtiqueta, float $wValor = 0): void
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
}
