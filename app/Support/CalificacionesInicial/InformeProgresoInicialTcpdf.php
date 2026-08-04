<?php

namespace App\Support\CalificacionesInicial;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use App\Support\Pdf\TcpdfMultiCellJustificado;
use Illuminate\Support\Facades\Storage;
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

    private const ANCHO_LOGO = 21.78;

    private const ALTO_LOGO = 21.78;

    /** @var array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string} */
    private array $header;

    private bool $mostrarMarcaAgua;

    private string $implementacion;

    private ?string $escudoProvincia = null;

    /**
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    private function __construct(array $header, bool $mostrarMarcaAgua = false, string $implementacion = 'estandar')
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->header = $header;
        $this->mostrarMarcaAgua = $mostrarMarcaAgua;
        $this->implementacion = $implementacion !== '' ? $implementacion : 'estandar';
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
    public static function generar(array $datos, array $header, bool $mostrarMarcaAgua = false, string $implementacion = 'estandar'): self
    {
        $pdf = new self($header, $mostrarMarcaAgua, $implementacion);
        $pdf->dibujarInformeAlumno($datos);

        return $pdf;
    }

    /**
     * @param  list<array<string, mixed>>  $hojas
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generarLote(array $hojas, array $header, bool $mostrarMarcaAgua = false, string $implementacion = 'estandar'): self
    {
        $pdf = new self($header, $mostrarMarcaAgua, $implementacion);
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

        /** @var array<string, mixed> $alumno */
        $alumno = (array) ($datos['alumno'] ?? []);

        /** @var list<array<string, mixed>> $materias */
        $materias = (array) ($datos['materias'] ?? []);
        foreach ($materias as $materia) {
            $this->paginaMateria($materia, $alumno, $etapa, $nombreEtapa);
        }

        if (! $this->esImplementacionMontecristo()) {
            $this->paginaCierre(
                $nombreEtapa,
                (string) ($datos['justificadas'] ?? ''),
                (string) ($datos['injustificadas'] ?? ''),
            );
        }
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
     * @param  array<string, mixed>  $alumno
     */
    private function paginaMateria(array $materia, array $alumno, int $etapa, string $nombreEtapa): void
    {
        $nombre = trim((string) ($materia['materia'] ?? ''));
        $esObservaciones = mb_strtoupper($nombre) === 'OBSERVACIONES';
        $esObsFinal = mb_strtoupper($nombre) === 'OBSERVACIÓN FINAL';

        $this->AddPage('P', 'A4');

        $tituloEncabezado = $esObservaciones ? trim($nombre.' '.$nombreEtapa) : $nombre;
        $docente = trim((string) ($materia['docente'] ?? ''));
        $lineaCursoAlumno = trim((string) ($alumno['lineaCursoAlumno'] ?? ''));

        $yInicioMarca = 18.0;
        $yContenido = $this->dibujarEncabezadoMateria($tituloEncabezado, $docente, $lineaCursoAlumno);
        $this->SetXY(self::MARGEN_IZQ, $yContenido);
        TcpdfFuenteArial::aplicar($this, '', 13);

        $indicador1 = self::normalizarComillas((string) ($materia['indicador1'] ?? ''));
        $indicador2 = self::normalizarComillas((string) ($materia['indicador2'] ?? ''));
        $etapa1 = self::normalizarComillas((string) ($materia['etapa1'] ?? ''));
        $etapa2 = self::normalizarComillas((string) ($materia['etapa2'] ?? ''));

        $mostrarAprendizajes = ! $this->esImplementacionMontecristo();

        if ($etapa === 1) {
            if (! $esObsFinal) {
                if ($mostrarAprendizajes) {
                    $this->Cell(self::ANCHO_CONTENIDO, 7, 'APRENDIZAJES', 1, 2, 'C');
                    $this->Ln(3);
                    TcpdfFuenteArial::aplicar($this, '', 10);
                    TcpdfMultiCellJustificado::escribir($this, self::ANCHO_CONTENIDO, 5, $indicador1);
                    $this->Ln(3);
                    TcpdfFuenteArial::aplicar($this, '', 13);
                }
                $this->Cell(self::ANCHO_CONTENIDO, 7, 'PRIMERA ETAPA', 1, 2, 'C');
                $this->Ln(3);
            }
            TcpdfFuenteArial::aplicar($this, '', 10);
            TcpdfMultiCellJustificado::escribir($this, self::ANCHO_CONTENIDO, 5, $etapa1);
        } else {
            if (! $esObsFinal) {
                if ($mostrarAprendizajes) {
                    $this->Cell(self::ANCHO_CONTENIDO, 7, 'APRENDIZAJES', 1, 2, 'C');
                    $this->Ln(3);
                    TcpdfFuenteArial::aplicar($this, '', 10);
                    TcpdfMultiCellJustificado::escribir($this, self::ANCHO_CONTENIDO, 5, $indicador2);
                    $this->Ln(3);
                    TcpdfFuenteArial::aplicar($this, '', 13);
                }
                $this->Cell(self::ANCHO_CONTENIDO, 7, 'SEGUNDA ETAPA', 1, 2, 'C');
                $this->Ln(3);
            }
            TcpdfFuenteArial::aplicar($this, '', 10);
            TcpdfMultiCellJustificado::escribir($this, self::ANCHO_CONTENIDO, 5, $etapa2);
        }

        if ($this->mostrarMarcaAgua) {
            $yFinMarca = min($this->GetY() + 4, 285.0);
            $this->dibujarMarcaAgua($yInicioMarca, $yFinMarca);
        }
    }

    private function dibujarEncabezadoMateria(string $tituloMateria, string $docente, string $lineaCursoAlumno): float
    {
        $yInicio = 18.0;

        $logo = $this->resolverLogoArchivo();
        if ($logo !== null) {
            $this->Image(
                TcpdfImagenPng::fuenteTcpdf($logo),
                self::MARGEN_IZQ,
                $yInicio,
                self::ANCHO_LOGO,
                self::ALTO_LOGO,
                '',
                '',
                '',
                false,
                300,
            );
        }

        $this->SetXY(self::MARGEN_IZQ, $yInicio);
        TcpdfFuenteArial::aplicar($this, 'B', 13);
        $this->Cell(self::ANCHO_BLOQUE, 7, mb_strtoupper($tituloMateria), 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 13);
        if ($docente !== '') {
            $this->Cell(self::ANCHO_BLOQUE, 7, $docente, 0, 2, 'C');
        }
        if ($lineaCursoAlumno !== '') {
            $this->Cell(self::ANCHO_BLOQUE, 7, $lineaCursoAlumno, 0, 2, 'C');
        }

        return max($this->GetY() + 5, $yInicio + self::ALTO_LOGO + 5);
    }

    private function resolverLogoArchivo(): ?string
    {
        $logo = $this->header['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            return $logo;
        }

        $path = entoInstitutionalLogoStoragePath();
        if (is_string($path) && $path !== '') {
            $abs = Storage::disk('public')->path($path);
            if (is_string($abs) && $abs !== '' && is_file($abs)) {
                return $abs;
            }
        }

        $fallback = public_path('img/3.png');

        return is_file($fallback) ? $fallback : null;
    }

    /**
     * Marca «SIN VALOR LEGAL» sobre indicadores y observaciones (autogestión familia).
     */
    private function dibujarMarcaAgua(float $yTop, float $yBottom): void
    {
        $cx = self::MARGEN_IZQ + self::ANCHO_CONTENIDO / 2;
        $cy = $yTop + max(12.0, ($yBottom - $yTop) * 0.52);
        $this->SetAlpha(0.52);
        $this->SetTextColor(168, 168, 168);
        TcpdfFuenteArial::aplicar($this, 'B', 22);
        $this->StartTransform();
        $this->Rotate(-29, $cx, $cy);
        $this->Text($cx - 38, $cy - 2, 'SIN VALOR LEGAL');
        $this->StopTransform();
        $this->SetAlpha(1);
        $this->SetTextColor(0, 0, 0);
    }

    private function paginaCierre(string $nombreEtapa, string $justificadas, string $injustificadas): void
    {
        $this->AddPage('P', 'A4');
        $this->Ln(30);

        TcpdfFuenteArial::aplicar($this, '', 13);
        $this->Cell(170, 7, $nombreEtapa, 0, 2, 'C');
        $this->Ln(12);

        $this->dibujarCeldasInasistencias($justificadas, $injustificadas);
        $this->Ln(12);

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

    private function dibujarCeldasInasistencias(string $justificadas, string $injustificadas): void
    {
        $anchoEtiqueta = 55.0;
        $anchoValor = 25.0;
        $alto = 10.0;

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell($anchoEtiqueta, $alto, 'Inasistencias Justificadas', 1, 0, 'C');
        $this->Cell($anchoValor, $alto, $justificadas, 1, 0, 'C');
        $this->Cell($anchoEtiqueta, $alto, 'Inasistencias Injustificadas', 1, 0, 'C');
        $this->Cell($anchoValor, $alto, $injustificadas, 1, 1, 'C');
    }

    private function esImplementacionMontecristo(): bool
    {
        return $this->implementacion === 'montecristo';
    }

    private static function normalizarComillas(string $texto): string
    {
        return str_replace(['“', '”', '‘', '’'], ['"', '"', "'", "'"], $texto);
    }
}
