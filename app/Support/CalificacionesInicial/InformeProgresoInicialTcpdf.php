<?php

namespace App\Support\CalificacionesInicial;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfMultiCellJustificado;
use TCPDF;

/**
 * Informe de Progreso Escolar — nivel inicial (A4 vertical, layout legacy FPDF → TCPDF).
 */
final class InformeProgresoInicialTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const ANCHO_BLOQUE = 180.0;

    private const ANCHO_CONTENIDO = 160.0;

    private const FILL_GRIS = [232, 232, 232];

    /** @var array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string} */
    private array $header;

    private ?string $escudoProvincia = null;

    /**
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    private function __construct(array $header)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->header = $header;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Informe de Progreso Escolar');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetLeftMargin(self::MARGEN_IZQ);
        $this->SetFillColor(...self::FILL_GRIS);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generar(array $datos, array $header): self
    {
        $pdf = new self($header);
        $pdf->dibujarInformeAlumno($datos);

        return $pdf;
    }

    /**
     * @param  list<array<string, mixed>>  $hojas
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generarLote(array $hojas, array $header): self
    {
        $pdf = new self($header);
        foreach ($hojas as $datos) {
            $pdf->dibujarInformeAlumno($datos);
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
     * @param  array<string, mixed>  $datos
     */
    private function dibujarInformeAlumno(array $datos): void
    {
        $escudo = $datos['escudoProvincia'] ?? null;
        $this->escudoProvincia = is_string($escudo) && $escudo !== '' && is_file($escudo) ? $escudo : null;

        $this->paginaMensajeFamilia();
        $this->paginaPortada($datos);

        $etapa = (int) ($datos['etapa'] ?? 1);
        $etapa = $etapa === 2 ? 2 : 1;
        $nombreEtapa = (string) ($datos['nombreEtapa'] ?? ($etapa === 1 ? 'PRIMERA ETAPA' : 'SEGUNDA ETAPA'));

        /** @var list<array<string, mixed>> $materias */
        $materias = (array) ($datos['materias'] ?? []);
        foreach ($materias as $materia) {
            $this->paginaMateria($materia, $etapa, $nombreEtapa);
        }

        /** @var array{just1e: string, just2e: string, inju1e: string, inju2e: string} $inas */
        $inas = (array) ($datos['inasistencias'] ?? []);
        $this->paginaInasistencias($inas, $etapa, $nombreEtapa);
    }

    private function dibujarEncabezadoProvincial(float $yInicio = 43.0): void
    {
        $escudo = $this->escudoProvincia;
        if ($escudo !== null && is_file($escudo)) {
            $this->Image($escudo, 102, 21, 15, 15, '', '', '', false, 300);
        }

        $this->SetXY(self::MARGEN_IZQ, $yInicio);
        TcpdfFuenteArial::aplicar($this, 'B', 12);
        $this->Cell(self::ANCHO_BLOQUE, 5, 'GOBIERNO DE LA PROVINCIA DE CÓRDOBA', 0, 2, 'C');
        TcpdfFuenteArial::aplicar($this, '', 12);
        $this->Cell(self::ANCHO_BLOQUE, 5, 'MINISTERIO DE EDUCACIÓN', 0, 2, 'C');
        $this->Cell(self::ANCHO_BLOQUE, 5, 'SECRETARÍA DE ESTADO DE EDUCACIÓN', 0, 2, 'C');
    }

    private function paginaMensajeFamilia(): void
    {
        $this->AddPage('P', 'A4');
        $this->dibujarEncabezadoProvincial();

        $y = $this->GetY() + 10;
        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, '', 13);
        $this->Cell(self::ANCHO_CONTENIDO, 7, 'Mensaje a la Familia', 0, 2, 'L');

        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->Ln(17);

        $parrafos = [
            'La Educación Inicial constituye la primera unidad pedagógica del Sistema Educativo Provincial. Promueve el aprendizaje y desarrollo de los niños y niñas como personas sujetos de derecho y partícipes activos de un proceso de formación integral, miembros de una familia y de una comunidad.',
            'Para ello, el Jardín de Infantes ofrece oportunidades de expresión y comunicación a través de los lenguajes oral, escrito, plástico, musical y corporal, y variadas posibilidades de exploración del ambiente natural y social cercano, de desarrollo de la capacidad creativa y el placer por el conocimiento.',
            'El objetivo de este Informe es comunicar a la familia los logros y avances en los aprendizajes que el/la niño/a ha podido desarrollar en el Jardín, a partir de experiencias lúdicas, lecturas compartidas e intercambios con sus compañeros y los maestros.',
            'Para acompañar y compartir esta etapa tan importante en la vida de los niños, es necesario el trabajo conjunto entre la escuela y la familia, la participación, el fortalecimiento de los vínculos.',
            'Es nuestro desafío, el de cada uno y el de todos, trabajar por la educación que reciben los niños en su ingreso a la escolaridad formal, en el marco de la Ley de Educación de la Provincia de Córdoba N° 9870.',
        ];

        foreach ($parrafos as $texto) {
            TcpdfMultiCellJustificado::escribir($this, self::ANCHO_BLOQUE, 5, $texto);
            $this->Ln(7);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function paginaPortada(array $datos): void
    {
        $this->AddPage('P', 'A4');
        $this->dibujarEncabezadoProvincial();
        $this->Ln(7);

        TcpdfFuenteArial::aplicar($this, '', 12);
        $this->Cell(self::ANCHO_BLOQUE, 5, 'DIRECCIÓN  GENERAL DE INSTITUTOS PRIVADOS DE ENSEÑANZA', 0, 2, 'C');
        $this->Ln(5);

        TcpdfFuenteArial::aplicar($this, 'B', 13);
        $this->Cell(self::ANCHO_BLOQUE, 7, 'EDUCACIÓN INICIAL - JARDÍN DE INFANTES', 0, 2, 'C');
        $this->Ln(7);

        $insti = trim((string) ($datos['insti'] ?? $this->header['insti'] ?? ''));
        if ($insti === '') {
            $insti = 'Institución';
        }
        TcpdfFuenteArial::aplicar($this, 'B', 12);
        $this->Cell(self::ANCHO_BLOQUE, 7, $insti, 0, 2, 'C');

        $direccion = trim((string) ($datos['direccion'] ?? ''));
        $localidad = trim((string) ($datos['localidad'] ?? ''));
        $departamento = trim((string) ($datos['departamento'] ?? ''));

        $this->Ln(5);
        TcpdfFuenteArial::aplicar($this, '', 11);
        $this->Cell(self::ANCHO_BLOQUE, 7, 'Domicilio: '.$direccion, 0, 2, 'L');
        $this->Ln(5);
        $this->Cell(self::ANCHO_BLOQUE, 7, 'Localidad: '.$localidad, 0, 2, 'L');
        $this->Ln(5);
        $this->Cell(self::ANCHO_BLOQUE, 7, 'Departamento: '.$departamento, 0, 2, 'L');

        $this->Ln(5);
        TcpdfFuenteArial::aplicar($this, 'B', 13);
        $this->Cell(self::ANCHO_BLOQUE, 7, 'INFORME DE PROGRESO ESCOLAR', 0, 2, 'C');

        $ano = (int) ($datos['ano'] ?? now()->year);
        $this->Cell(self::ANCHO_BLOQUE, 7, 'AÑO: '.$ano, 0, 2, 'C');

        /** @var array<string, mixed> $alumno */
        $alumno = (array) ($datos['alumno'] ?? []);
        $edadSala = trim((string) ($alumno['edadSala'] ?? ''));
        $this->Ln(5);
        TcpdfFuenteArial::aplicar($this, '', 10);
        if ($edadSala !== '') {
            $this->Cell(self::ANCHO_BLOQUE, 7, $edadSala, 0, 2, 'C');
        }

        $apellido = trim((string) ($alumno['apellido'] ?? ''));
        $nombre = trim((string) ($alumno['nombre'] ?? ''));
        $this->Ln(5);
        $this->Cell(self::ANCHO_BLOQUE, 7, 'Alumno/a:   '.$apellido.' '.$nombre, 0, 2, 'L');
        $this->Ln(5);
        $this->Cell(self::ANCHO_BLOQUE, 7, 'Tipo y Número de Documento:   '.trim((string) ($alumno['dni'] ?? '')), 0, 2, 'L');
        $this->Ln(5);
        $this->Cell(
            self::ANCHO_BLOQUE,
            7,
            'Lugar y Fecha de Nacimiento:   '.trim((string) ($alumno['ln_ciudad'] ?? '')).' '.trim((string) ($alumno['fechnaci'] ?? '')),
            0,
            2,
            'L',
        );
        $this->Ln(5);
        $this->Cell(self::ANCHO_BLOQUE, 7, 'Nacionalidad:   '.trim((string) ($alumno['nacion'] ?? '')), 0, 2, 'L');
        $this->Ln(5);
        $this->Cell(self::ANCHO_BLOQUE, 7, 'Provincia:   '.trim((string) ($alumno['ln_provincia'] ?? '')), 0, 2, 'L');
        $this->Ln(5);
        $domicilio = trim(implode(' ', array_filter([
            (string) ($alumno['callenum'] ?? ''),
            (string) ($alumno['barrio'] ?? ''),
            (string) ($alumno['localidad'] ?? ''),
        ])));
        $this->Cell(self::ANCHO_BLOQUE, 7, 'Domicilio:   '.$domicilio, 0, 2, 'L');
        $this->Ln(5);
        $cursec = trim((string) ($alumno['cursec'] ?? ''));
        $turno = trim((string) ($alumno['turno'] ?? ''));
        $this->Cell(self::ANCHO_BLOQUE, 7, 'Sala:   '.$cursec.'                   Turno:   '.$turno, 0, 2, 'L');
        $this->Ln(5);
        $this->Cell(self::ANCHO_BLOQUE, 7, 'Matrícula Nº:   '.trim((string) ($alumno['nroMatricula'] ?? '')), 0, 2, 'L');
    }

    /**
     * @param  array<string, mixed>  $materia
     */
    private function paginaMateria(array $materia, int $etapa, string $nombreEtapa): void
    {
        $nombre = trim((string) ($materia['materia'] ?? ''));
        $esObservaciones = mb_strtoupper($nombre) === 'OBSERVACIONES';
        $esObsFinal = mb_strtoupper($nombre) === 'OBSERVACIÓN FINAL';

        $this->AddPage('P', 'A4');

        $this->SetXY(self::MARGEN_IZQ, 20);
        TcpdfFuenteArial::aplicar($this, 'B', 13);

        if ($esObservaciones) {
            $this->MultiCell(self::ANCHO_CONTENIDO, 7, $nombre.' '.$nombreEtapa, 0, 'C');
        } else {
            $this->MultiCell(self::ANCHO_CONTENIDO, 7, $nombre, 0, 'C');
        }

        $this->Ln(3);
        TcpdfFuenteArial::aplicar($this, '', 13);

        $indicador1 = self::normalizarComillas((string) ($materia['indicador1'] ?? ''));
        $indicador2 = self::normalizarComillas((string) ($materia['indicador2'] ?? ''));
        $etapa1 = self::normalizarComillas((string) ($materia['etapa1'] ?? ''));
        $etapa2 = self::normalizarComillas((string) ($materia['etapa2'] ?? ''));

        if ($etapa === 1) {
            if (! $esObsFinal) {
                $this->Cell(self::ANCHO_CONTENIDO, 7, 'APRENDIZAJES', 1, 2, 'C');
                $this->Ln(3);
                TcpdfFuenteArial::aplicar($this, '', 10);
                TcpdfMultiCellJustificado::escribir($this, self::ANCHO_CONTENIDO, 5, $indicador1);
                $this->Ln(3);
                TcpdfFuenteArial::aplicar($this, '', 13);
                $this->Cell(self::ANCHO_CONTENIDO, 7, 'PRIMERA ETAPA', 1, 2, 'C');
                $this->Ln(3);
            }
            TcpdfFuenteArial::aplicar($this, '', 10);
            TcpdfMultiCellJustificado::escribir($this, self::ANCHO_CONTENIDO, 5, $etapa1);
        } else {
            if (! $esObsFinal) {
                $this->Cell(self::ANCHO_CONTENIDO, 7, 'APRENDIZAJES', 1, 2, 'C');
                $this->Ln(3);
                TcpdfFuenteArial::aplicar($this, '', 10);
                TcpdfMultiCellJustificado::escribir($this, self::ANCHO_CONTENIDO, 5, $indicador2);
                $this->Ln(3);
                TcpdfFuenteArial::aplicar($this, '', 13);
                $this->Cell(self::ANCHO_CONTENIDO, 7, 'SEGUNDA ETAPA', 1, 2, 'C');
                $this->Ln(3);
            }
            TcpdfFuenteArial::aplicar($this, '', 10);
            TcpdfMultiCellJustificado::escribir($this, self::ANCHO_CONTENIDO, 5, $etapa2);
        }
    }

    /**
     * @param  array{just1e: string, just2e: string, inju1e: string, inju2e: string}  $inas
     */
    private function paginaInasistencias(array $inas, int $etapa, string $nombreEtapa): void
    {
        $this->AddPage('P', 'A4');
        $this->Ln(30);

        TcpdfFuenteArial::aplicar($this, '', 13);
        $this->Cell(170, 7, $nombreEtapa, 0, 2, 'C');
        $this->Ln(10);

        TcpdfFuenteArial::aplicar($this, '', 11);
        $this->Cell(50, 10, 'ASISTENCIAS', 1, 0, 'C');
        $this->Cell(120, 5, 'INASISTENCIAS', 1, 2, 'C');
        $this->Cell(60, 5, 'JUSTIFICADAS', 1, 0, 'C');
        $this->Cell(60, 5, 'INJUSTIFICADAS', 1, 1, 'C');

        $just = $etapa === 1 ? trim((string) ($inas['just1e'] ?? '')) : trim((string) ($inas['just2e'] ?? ''));
        $inju = $etapa === 1 ? trim((string) ($inas['inju1e'] ?? '')) : trim((string) ($inas['inju2e'] ?? ''));

        $this->Cell(50, 8, '', 1, 0, 'C');
        $this->Cell(60, 8, $just, 1, 0, 'C');
        $this->Cell(60, 8, $inju, 1, 1, 'C');
        $this->Ln(20);

        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell(60, 5, 'LUGAR Y FECHA:', 0, 2, 'C');
        $this->Ln(30);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(self::ANCHO_BLOQUE, 5, 'Docente                                                                                       Sello                                                                                             Director/a', 0, 2, 'C');
        $this->Ln(20);
        $this->Cell(self::ANCHO_BLOQUE, 5, 'FIRMA DEL PADRE / MADRE O TUTOR', 0, 2, 'L');

        TcpdfFuenteArial::aplicar($this, 'B', 13);
        $this->Ln(10);
        $this->Cell(self::ANCHO_CONTENIDO, 7, 'CAMBIO DE JARDÍN DE INFANTES', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->Ln(5);
        $this->Cell(self::ANCHO_CONTENIDO, 7, 'La primer casilla debe ser llenada por el Jardín de Infantes que inicia el Informe', 0, 2, 'C');
        $this->Ln(15);

        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(40, 20, 'Jardín de Infantes', 1, 0, 'C');
        $this->Cell(20, 20, 'Nº de Inscrip.', 1, 0, 'C');
        $x = $this->GetX();
        $y = $this->GetY();
        $this->Cell(30, 10, 'Fecha de:', 1, 2, 'C');
        $this->Cell(15, 10, 'Ingreso', 1, 0, 'C');
        $this->Cell(15, 10, 'Egreso', 1, 0, 'C');
        $this->SetXY($x + 30, $y);
        $this->Cell(40, 20, 'Causa', 1, 0, 'C');
        $this->Cell(40, 20, 'Firma del/la Director/a', 1, 1, 'C');

        for ($i = 0; $i < 2; $i++) {
            $this->Cell(40, 20, '', 1, 0, 'C');
            $this->Cell(20, 20, '', 1, 0, 'C');
            $this->Cell(30, 20, '', 1, 0, 'C');
            $this->Cell(40, 20, '', 1, 0, 'C');
            $this->Cell(40, 20, '', 1, 1, 'C');
        }
    }

    private static function normalizarComillas(string $texto): string
    {
        return str_replace(['“', '”', '‘', '’'], ['"', '"', "'", "'"], $texto);
    }
}
