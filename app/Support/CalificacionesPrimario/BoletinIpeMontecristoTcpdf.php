<?php

namespace App\Support\CalificacionesPrimario;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfMultiCellJustificado;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use TCPDF;

/**
 * Boletín de calificaciones — variante Montecristo (A4 apaisado, espacios extracurriculares institucionales).
 */
final class BoletinIpeMontecristoTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const ANCHO_TABLA = 260.0;

    private const ANCHO_MATERIA = 50.0;

    private const ANCHO_CALIF = 15.0;

    private const ANCHO_INTENSIF = 10.0;

    private const ANCHO_SINTESIS_ETAPA1 = 195.0;

    private const ANCHO_SINTESIS_ETAPA2 = 185.0;

    private const X_SINTESIS = 70.0;

    private const X_CALIF_ETAPA1 = 265.0;

    private const X_CALIF_ETAPA2 = 255.0;

    private const X_INTENSIF = 270.0;

    private const ALTO_ENCABEZADO_INST = 22.0;

    private const ANCHO_LOGO = 20.0;

    private const ALTO_LOGO = 20.0;

    private const FILL_GRIS = [232, 232, 232];

    private const ALTURA_PAGINA = 210.0;

    /** Separación entre la última fila y los bloques de escala / firmas (mm). */
    private const SEPARACION_PIE = 2.0;

    /** Espacio reservado al pie: separación + escalas + firmas (mm). */
    private const ALTURA_RESERVADA_PIE = 33.0;

    /** Alto de cada fila del bloque de firmas (mm). */
    private const ALTO_FILA_FIRMA = 3.0;

    /** @var array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string} */
    private array $header;

    private bool $mostrarMarcaAgua;

    public function __construct(array $header, bool $mostrarMarcaAgua = false)
    {
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8', false);
        $this->header = $header;
        $this->mostrarMarcaAgua = $mostrarMarcaAgua;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Boletín de calificaciones');
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
    public static function generarHoja(array $datos, array $header, bool $mostrarMarcaAgua = false): self
    {
        $pdf = new self($header, $mostrarMarcaAgua);
        $pdf->AddPage();
        $pdf->dibujarHoja($datos);

        return $pdf;
    }

    /**
     * @param  list<array<string, mixed>>  $hojas
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generarLote(array $hojas, array $header, bool $mostrarMarcaAgua = false): self
    {
        $pdf = new self($header, $mostrarMarcaAgua);
        foreach ($hojas as $datos) {
            $pdf->AddPage();
            $pdf->dibujarHoja($datos);
        }

        return $pdf;
    }

    public static function respuestaHttp(self $pdf, string $nombreArchivo): Response
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
    private function dibujarHoja(array $datos): void
    {
        $etapa = (int) ($datos['etapa'] ?? 1);
        $etapa = $etapa === 2 ? 2 : 1;

        $this->dibujarEncabezado($datos);
        $yTopGrilla = $this->GetY();
        $yGrilla = $this->dibujarEncabezadoCuerpo($etapa);
        if ($this->mostrarMarcaAgua) {
            $yFinGrillaEst = $yGrilla + $this->estimarAlturaFilas($datos, $etapa);
            $this->dibujarMarcaAgua($yTopGrilla, $yFinGrillaEst);
        }
        $y = $this->dibujarFilas($yGrilla, $datos, $etapa);
        $this->dibujarBloquesInferiores($y + self::SEPARACION_PIE, $datos);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarEncabezado(array $datos): void
    {
        $xy1 = 20.0;
        $insti = trim((string) ($this->header['insti'] ?? ''));
        if ($insti === '') {
            $insti = 'Institución';
        }

        $this->SetDrawColor(0, 0, 0);
        $this->Rect(self::MARGEN_IZQ, $xy1, self::ANCHO_TABLA, self::ALTO_ENCABEZADO_INST);

        $logo = $this->resolverLogoArchivo();
        if ($logo !== null) {
            $this->Image(
                $logo,
                self::MARGEN_IZQ + 5,
                $xy1 + 1,
                self::ANCHO_LOGO,
                self::ALTO_LOGO,
                '',
                '',
                '',
                false,
                300,
            );
        }

        $this->SetXY(self::MARGEN_IZQ, $xy1);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(self::ANCHO_TABLA, 7, $insti, 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 8);
        $titulo = trim((string) ($datos['titulo'] ?? 'BOLETÍN DE CALIFICACIONES'));
        $ano = (int) ($datos['ano'] ?? now()->year);
        $this->Cell(self::ANCHO_TABLA, 5, $titulo.' - '.$ano, 0, 2, 'C');

        $alumno = trim((string) ($datos['alumnoLinea'] ?? ''));
        $dni = trim((string) ($datos['dni'] ?? ''));
        $lineaAlumno = $alumno.($dni !== '' ? ' - '.$dni : '');
        $this->Cell(self::ANCHO_TABLA, 5, $lineaAlumno, 0, 2, 'C');

        $curso = trim((string) ($datos['cursoLabel'] ?? ''));
        $this->Cell(self::ANCHO_TABLA, 5, $curso, 0, 2, 'C');

        $this->Ln(7);
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

    private function dibujarEncabezadoCuerpo(int $etapa): float
    {
        $this->SetX(self::MARGEN_IZQ);
        TcpdfFuenteArial::aplicar($this, 'I', 9);

        $subtitulo = $etapa === 1
            ? 'ESPACIOS CURRICULARES / EXTRACURRICULARES: SÍNTESIS Y CALIFICACIÓN PRIMERA ETAPA'
            : 'ESPACIOS CURRICULARES / EXTRACURRICULARES: SÍNTESIS Y CALIFICACIÓN SEGUNDA ETAPA';
        $this->Cell(self::ANCHO_TABLA, 6, $subtitulo, 0, 1, 'C');

        TcpdfFuenteArial::aplicar($this, '', 6);
        $yEncabezado = $this->GetY();
        $altoEncabezado = 8.0;
        $this->MultiCell(
            self::ANCHO_MATERIA,
            $altoEncabezado,
            'Espacios Curriculares/Extracurriculares',
            1,
            'C',
            false,
            0,
            self::MARGEN_IZQ,
            $yEncabezado,
            true,
            0,
            false,
            true,
            $altoEncabezado,
            'M'
        );

        if ($etapa === 1) {
            $this->SetXY(self::MARGEN_IZQ + self::ANCHO_MATERIA, $yEncabezado);
            $this->Cell(self::ANCHO_SINTESIS_ETAPA1, $altoEncabezado, 'Síntesis 1º Etapa', 1, 0, 'C');
            $this->Cell(self::ANCHO_CALIF, $altoEncabezado, 'Calif.1º Etapa', 1, 1, 'C');
        } else {
            $this->SetXY(self::MARGEN_IZQ + self::ANCHO_MATERIA, $yEncabezado);
            $this->Cell(self::ANCHO_SINTESIS_ETAPA2, $altoEncabezado, 'Síntesis 2º Etapa', 1, 0, 'C');
            $this->Cell(self::ANCHO_CALIF, $altoEncabezado, 'Calif.2º Etapa', 1, 0, 'C');
            $this->Cell(self::ANCHO_INTENSIF, $altoEncabezado, 'Intensif.', 1, 1, 'C');
        }

        return $this->GetY();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarFilas(float $yInicio, array $datos, int $etapa): float
    {
        /** @var list<array{materia: string, tipo: string, sintesis: string, ic01: string, ic02: string, dic: string}> $filas */
        $filas = $datos['filas'] ?? [];
        $y = $yInicio;

        foreach ($filas as $fila) {
            $tipo = (string) ($fila['tipo'] ?? 'materia');
            $materia = trim((string) ($fila['materia'] ?? ''));

            if ($tipo === 'justificadas' || $tipo === 'injustificadas') {
                continue;
            }

            $sintesis = trim((string) ($fila['sintesis'] ?? ''));
            $calif = $etapa === 1
                ? trim((string) ($fila['ic01'] ?? ''))
                : trim((string) ($fila['ic02'] ?? ''));
            $intensif = trim((string) ($fila['dic'] ?? ''));

            $anchoSintesis = $etapa === 1 ? self::ANCHO_SINTESIS_ETAPA1 : self::ANCHO_SINTESIS_ETAPA2;
            $alto = $this->medirAltoSintesis($sintesis, $anchoSintesis);
            $alto = max($alto, 7.0);

            $y = $this->asegurarEspacioFila($y, $alto, $etapa);

            $this->dibujarCeldaSintesis(self::X_SINTESIS, $y, $anchoSintesis, $alto, $sintesis);
            $this->dibujarCeldaMateria(self::MARGEN_IZQ, $y, self::ANCHO_MATERIA, $alto, $materia);

            if ($etapa === 1) {
                $this->dibujarCeldaTexto(self::X_CALIF_ETAPA1, $y, self::ANCHO_CALIF, $alto, $calif, 8);
            } else {
                $this->dibujarCeldaTexto(self::X_CALIF_ETAPA2, $y, self::ANCHO_CALIF, $alto, $calif, 8);
                $this->dibujarCeldaTexto(self::X_INTENSIF, $y, self::ANCHO_INTENSIF, $alto, $intensif, 8);
            }

            $y += $alto;
        }

        return $y;
    }

    private function asegurarEspacioFila(float $y, float $altoNecesario, int $etapa): float
    {
        if ($y + $altoNecesario <= self::ALTURA_PAGINA - self::ALTURA_RESERVADA_PIE) {
            return $y;
        }

        $this->AddPage();

        return $this->dibujarEncabezadoCuerpo($etapa);
    }

    private function medirAltoSintesis(string $texto, float $ancho): float
    {
        if (trim($texto) === '') {
            return 7.0;
        }

        TcpdfFuenteArial::aplicar($this, '', 8);

        return max(7.0, $this->getStringHeight($ancho - 2, $texto) + 2);
    }

    private function dibujarCeldaSintesis(float $x, float $y, float $ancho, float $alto, string $texto): void
    {
        $this->Rect($x, $y, $ancho, $alto);

        if (trim($texto) === '') {
            return;
        }

        $this->SetXY($x + 1, $y + 1);
        TcpdfFuenteArial::aplicar($this, '', 8);
        TcpdfMultiCellJustificado::escribir($this, $ancho - 2, 3.5, $texto);
    }

    private function dibujarCeldaMateria(float $x, float $y, float $ancho, float $alto, string $texto): void
    {
        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->MultiCell($ancho, $alto, $texto, 1, 'C', false, 0, $x, $y, true, 0, false, true, $alto, 'M');
    }

    private function dibujarCeldaTexto(float $x, float $y, float $ancho, float $alto, string $texto, int $fuente): void
    {
        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, '', $fuente);
        $this->MultiCell($ancho, $alto, $texto, 1, 'C', false, 0, $x, $y, true, 0, false, true, $alto, 'M');
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function estimarAlturaFilas(array $datos, int $etapa): float
    {
        /** @var list<array{materia: string, tipo: string, sintesis: string, ic01: string, ic02: string, dic: string}> $filas */
        $filas = $datos['filas'] ?? [];
        $altura = 0.0;

        foreach ($filas as $fila) {
            $tipo = (string) ($fila['tipo'] ?? 'materia');
            if ($tipo === 'justificadas' || $tipo === 'injustificadas') {
                continue;
            }

            $sintesis = trim((string) ($fila['sintesis'] ?? ''));
            $anchoSintesis = $etapa === 1 ? self::ANCHO_SINTESIS_ETAPA1 : self::ANCHO_SINTESIS_ETAPA2;
            $altura += max($this->medirAltoSintesis($sintesis, $anchoSintesis), 7.0);
        }

        return $altura > 0.0 ? $altura : 7.0;
    }

    /**
     * Marca centrada en la grilla. Se invoca antes de dibujar filas para quedar detrás de los datos.
     */
    private function dibujarMarcaAgua(float $yTop, float $yBottom): void
    {
        $cx = self::MARGEN_IZQ + self::ANCHO_TABLA / 2;
        $cy = $yTop + ($yBottom - $yTop) * 0.54;
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

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarBloquesInferiores(float $y, array $datos): void
    {
        $x = self::MARGEN_IZQ;
        $yAprec = $y;

        $this->SetXY($x, $yAprec);
        TcpdfFuenteArial::aplicar($this, 'B', 6);
        $this->MultiCell(50, 3, 'Escala de Apreciación', 1, 'C');
        $this->SetXY($x, $yAprec + 3);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->MultiCell(
            50,
            3,
            "Muy Logrado (ML)\nLogrado (L)\nEscasamente Logrado (EL)\nPendiente (P)\nEn Proceso (EP)\nProyecto Pedagógico Individual. (PPI)",
            1,
            'C',
        );

        $this->SetXY($x + 55, $yAprec);
        TcpdfFuenteArial::aplicar($this, 'B', 6);
        $this->MultiCell(25, 3, "Escala de Calificaciones\nde los Aprendizajes", 1, 'C');
        $this->SetXY($x + 55, $yAprec + 9);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->MultiCell(
            25,
            3,
            "Excelente (E)\nMuy Bueno (MB)\nBueno (B)\nSatisfactorio (S)\nNo Satisfactorio (NS)",
            1,
            'C',
        );

        $yFirmas = max($this->GetY(), $yAprec + 24) - 5.0;
        $director = trim((string) ($datos['directorFirma'] ?? ''));
        $hFirma = self::ALTO_FILA_FIRMA;

        $this->SetXY(self::MARGEN_IZQ, $yFirmas);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(80, $hFirma, '', 0, 0, 'C');
        $this->Cell(60, $hFirma, '...........................................................', 0, 0, 'C');
        $this->Cell(50, $hFirma, '...........................................................', 0, 0, 'C');
        $this->Cell(100, $hFirma, '...........................................................', 0, 1, 'C');

        $this->Cell(80, $hFirma, '', 0, 0, 'C');
        $this->Cell(60, $hFirma, 'Firma del Responsable', 0, 0, 'C');
        $this->Cell(50, $hFirma, 'Firma del Docente', 0, 0, 'C');
        $this->Cell(105, $hFirma, $director !== '' ? $director : 'Directora', 0, 1, 'C');

        $this->Cell(80, $hFirma, '', 0, 0, 'C');
        $this->Cell(45, $hFirma, '', 0, 0, 'C');
        $this->Cell(55, $hFirma, '', 0, 0, 'C');
        $this->Cell(45, $hFirma, 'sello', 0, 0, 'C');
        $this->Cell(35, $hFirma, 'Directora', 0, 1, 'C');
    }
}
