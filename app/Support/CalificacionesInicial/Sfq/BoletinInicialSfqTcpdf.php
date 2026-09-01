<?php

namespace App\Support\CalificacionesInicial\Sfq;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use App\Support\Pdf\TcpdfLogoInstitucional;
use Illuminate\Http\Response;
use TCPDF;

/**
 * Informes inicial SFQ — pedagógico (una etapa) o Bellas Artes (tres etapas), layout legacy ScriptCase → TCPDF.
 */
final class BoletinInicialSfqTcpdf extends TCPDF
{
    private const MARGEN = 20.0;

    private const ANCHO_BLOQUE = 180.0;

    private const FILL_GRIS = [230, 230, 230];

    /** Pedagógico (legacy) */
    private const PED_ANCHO_EDANI = 25.0;

    private const PED_ANCHO_INDICADOR = 120.0;

    private const PED_ANCHO_NOTA = 35.0;

    private const PED_ALTO_FILA = 8.0;

    /** Bellas Artes (legacy ScriptCase) */
    private const BA_ANCHO_EDANI = 45.0;

    private const BA_ANCHO_INDICADOR = 100.0;

    private const BA_ANCHO_NOTA = 35.0;

    private const BA_ALTO_FILA = 8.0;

    private const BA_ALTO_BANDA = 7.0;

    private const BA_ALTO_ENCABEZADO = 5.0;

    private bool $mostrarMarcaAgua = false;

    /** @param  array<string, mixed>  $datos */
    private function __construct(private array $datos)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->SetCreator('Sistema Escolar');
        $this->SetTitle('Informe Pedagógico');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetMargins(0, 0, 0);
        $this->SetFillColor(...self::FILL_GRIS);
        $this->SetAutoPageBreak(false);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generarHoja(array $datos, bool $mostrarMarcaAgua = false): self
    {
        $pdf = new self($datos);
        $pdf->mostrarMarcaAgua = $mostrarMarcaAgua;
        $pdf->AddPage();
        $pdf->dibujarInforme();

        return $pdf;
    }

    /**
     * @param  list<array<string, mixed>>  $hojas
     */
    public static function generarLote(array $hojas): self
    {
        abort_unless($hojas !== [], 404);

        $pdf = null;
        foreach ($hojas as $datos) {
            if ($pdf === null) {
                $pdf = new self($datos);
            } else {
                $pdf->datos = $datos;
            }
            $pdf->AddPage();
            $pdf->dibujarInforme();
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

    private function dibujarInforme(): void
    {
        if (($this->datos['variante'] ?? '') === 'bellas_artes') {
            $this->dibujarEncabezado(true);
            $this->dibujarSeccionesBellasArtes((array) ($this->datos['secciones'] ?? []));
            $this->dibujarFirmasBellasArtes();
        } else {
            $this->dibujarEncabezado(false);
            $this->dibujarEncabezadoTablaPedagogico();
            $this->dibujarCuerpoTabla(
                (array) ($this->datos['gruposEdani'] ?? []),
                self::PED_ANCHO_EDANI,
                self::PED_ANCHO_INDICADOR,
                self::PED_ANCHO_NOTA,
                self::PED_ALTO_FILA,
                9,
                8,
                true,
            );
            $this->dibujarObservaciones(trim((string) ($this->datos['observaciones'] ?? '')));
            $this->dibujarFirmas(30);
        }

        if ($this->mostrarMarcaAgua) {
            $this->dibujarMarcaAgua();
        }
    }

    /** Encabezado legacy ScriptCase (pedagógico y Bellas Artes). */
    private function dibujarEncabezado(bool $bellasArtes): void
    {
        $d = $this->datos;
        $x = self::MARGEN;
        $yTop = 20.0;

        $this->SetXY($x, $yTop);
        $this->Rect($x, $yTop, self::ANCHO_BLOQUE, 23.0, 'D');

        $membrete = $d['membrete'] ?? null;
        if (is_string($membrete) && $membrete !== '' && is_file($membrete)) {
            $this->Image(
                TcpdfImagenPng::fuenteTcpdf($membrete),
                $x + 5,
                $yTop + 1,
                21,
                21,
                '',
                '',
                '',
                false,
                300,
            );
        } else {
            TcpdfLogoInstitucional::dibujarAjustado($this, $x + 5, $yTop + 1, 21, 21);
        }

        $apellido = trim((string) ($d['apellido'] ?? ''));
        $nombre = trim((string) ($d['nombre'] ?? ''));
        $alumnoLinea = $apellido !== '' || $nombre !== ''
            ? $apellido.', '.$nombre
            : '—';

        $this->SetXY(0, $yTop + 3);
        TcpdfFuenteArial::aplicar($this, '', 12);
        $this->Cell(210, 7, (string) ($d['tituloInstitucion'] ?? 'E.P. SAN FRANCISCO'), 0, 1, 'C');

        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->Cell(210, 5, 'Alumno: '.$alumnoLinea, 0, 1, 'C');

        $cursec = trim((string) ($d['cursec'] ?? ''));
        $ano = (string) ($d['anoLectivo'] ?? '');

        if ($bellasArtes) {
            $this->Cell(210, 7, 'NIVEL INICIAL - '.$cursec.' - Año: '.$ano, 0, 1, 'C');
        } else {
            $nombreEtapa = (string) ($d['nombreEtapa'] ?? '');
            $this->Cell(210, 7, 'NIVEL INICIAL - '.$cursec.' - '.$nombreEtapa.' - Año: '.$ano, 0, 1, 'C');
        }

        TcpdfFuenteArial::aplicar($this, 'B', 11);
        $this->Ln(2);
        $tituloInforme = $bellasArtes ? 'INFORME PEDAGÓGICO - BELLAS ARTES' : 'INFORME PEDAGÓGICO';
        $this->Cell(210, 7, $tituloInforme, 0, 1, 'C');

        $this->SetLeftMargin($x);
        $this->SetX($x);
        TcpdfFuenteArial::aplicar($this, '', 10);
        $docente = trim((string) ($d['docente'] ?? ''));
        $this->Cell(self::ANCHO_BLOQUE, 7, 'Docente: '.($docente !== '' ? $docente : '—'), 0, 1, 'L');
        $this->Ln(3);
    }

    private function dibujarEncabezadoTablaPedagogico(): void
    {
        $x = self::MARGEN;
        $y = $this->GetY();

        TcpdfFuenteArial::aplicar($this, 'I', 7);
        $this->SetXY($x, $y);
        $this->Cell(self::PED_ANCHO_EDANI, self::PED_ALTO_FILA, 'Eje formativo EDANI', 1, 0, 'C');
        $this->Cell(self::PED_ANCHO_INDICADOR, self::PED_ALTO_FILA, 'INDICADOR DE OBSERVACIÓN', 1, 0, 'C');
        $this->Cell(self::PED_ANCHO_NOTA, self::PED_ALTO_FILA, '', 1, 0, 'C');
        $this->Ln(2);
    }

    /**
     * @param  list<array{titulo: string, observaciones: string, gruposEdani: list<array{edani: string, filas: list<array{indicador: string, nota: string}>}>}>  $secciones
     */
    private function dibujarSeccionesBellasArtes(array $secciones): void
    {
        foreach ($secciones as $seccion) {
            $this->dibujarSeccionBellasArtesLegacy($seccion);
        }
    }

    /**
     * Una etapa Bellas Artes — flujo secuencial legacy (banda + tabla + observaciones).
     *
     * @param  array{titulo: string, observaciones: string, gruposEdani: list<array{edani: string, filas: list<array{indicador: string, nota: string}>}>}  $seccion
     */
    private function dibujarSeccionBellasArtesLegacy(array $seccion): void
    {
        $this->SetLeftMargin(self::MARGEN);
        $this->SetX(self::MARGEN);
        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->Cell(self::ANCHO_BLOQUE, self::BA_ALTO_BANDA, (string) ($seccion['titulo'] ?? ''), 1, 1, 'C', true);
        $this->Ln(2);

        $this->dibujarEncabezadoTablaBellasArtes();
        $this->dibujarCuerpoTabla(
            (array) ($seccion['gruposEdani'] ?? []),
            self::BA_ANCHO_EDANI,
            self::BA_ANCHO_INDICADOR,
            self::BA_ANCHO_NOTA,
            self::BA_ALTO_FILA,
            7,
            8,
            true,
        );

        $obs = trim((string) ($seccion['observaciones'] ?? ''));
        $this->Ln(10);
        $this->SetX(30.0);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, 8, 'Observaciones de la Docente:', 0, 1, 'C');
        $this->SetX(30.0);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->MultiCell(160, 5, $obs !== '' ? $obs : ' ', 0, 'L', false);
    }

    private function dibujarEncabezadoTablaBellasArtes(): void
    {
        $x = self::MARGEN;

        TcpdfFuenteArial::aplicar($this, 'I', 7);
        $this->SetX($x);
        $this->Cell(self::BA_ANCHO_EDANI, self::BA_ALTO_ENCABEZADO, 'Eje formativo EDANI', 1, 0, 'C');
        $this->Cell(self::BA_ANCHO_INDICADOR, self::BA_ALTO_ENCABEZADO, 'INDICADOR DE OBSERVACIÓN', 1, 0, 'C');
        $this->Cell(self::BA_ANCHO_NOTA, self::BA_ALTO_ENCABEZADO, '', 1, 0, 'C');
        $this->Ln(-2);
    }

    /**
     * Cuerpo de tabla — flujo secuencial legacy ScriptCase (Ln por fila, MultiCell EDANI al cambiar grupo).
     *
     * @param  list<array{edani: string, filas: list<array{indicador: string, nota: string}>}>  $grupos
     */
    private function dibujarCuerpoTabla(
        array $grupos,
        float $anchoEdani,
        float $anchoIndicador,
        float $anchoNota,
        float $altoFila,
        int $fuenteEdani,
        int $fuenteIndicador,
        bool $lnAntesFilaLegacy = false,
    ): void {
        $xInd = self::MARGEN + $anchoEdani;

        foreach ($grupos as $grupo) {
            /** @var list<array{indicador: string, nota: string}> $filas */
            $filas = (array) ($grupo['filas'] ?? []);
            if ($filas === []) {
                continue;
            }

            $cantFilas = count($filas);
            $hEdani = $cantFilas * $altoFila;
            $edaniDibujado = false;

            foreach ($filas as $fila) {
                if ($lnAntesFilaLegacy) {
                    $this->Ln($altoFila);
                }

                if (! $edaniDibujado) {
                    TcpdfFuenteArial::aplicar($this, 'B', $fuenteEdani);
                    $this->MultiCell(
                        $anchoEdani,
                        $hEdani,
                        (string) ($grupo['edani'] ?? ''),
                        1,
                        'L',
                        false,
                        0,
                        '',
                        '',
                        true,
                        0,
                        true,
                        32,
                        'M',
                        true,
                    );
                    $edaniDibujado = true;
                }

                $this->SetX($xInd);
                TcpdfFuenteArial::aplicar($this, '', $fuenteIndicador);
                $this->Cell($anchoIndicador, $altoFila, (string) ($fila['indicador'] ?? ''), 1, 0, 'L');
                $this->Cell($anchoNota, $altoFila, (string) ($fila['nota'] ?? ''), 1, 0, 'C');
            }
        }
    }

    private function dibujarObservaciones(string $obs): void
    {
        $this->Ln(10);
        $xObs = 30.0;

        $this->SetX($xObs);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(30, 8, 'Observaciones de la Docente:', 0, 1, 'C');

        $this->SetX($xObs);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->MultiCell(160, 5, $obs !== '' ? $obs : ' ', 0, 'L', false);
    }

    private function dibujarFirmasBellasArtes(): void
    {
        $this->Ln(5);
        $this->dibujarFirmas(10);
    }

    private function dibujarFirmas(float $lnAntes): void
    {
        $anchoFirma = 60.0;
        $alturaFirma = 10.0;

        if ($lnAntes > 0) {
            $this->Ln($lnAntes);
        }
        $this->SetLeftMargin(self::MARGEN);
        TcpdfFuenteArial::aplicar($this, '', 10);

        $this->Cell($anchoFirma, $alturaFirma, '....................', 0, 0, 'C');
        $this->Cell($anchoFirma, $alturaFirma, '....................', 0, 0, 'C');
        $this->Cell($anchoFirma, $alturaFirma, '....................', 0, 1, 'C');

        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell($anchoFirma, 6, 'Firma Tutor', 0, 0, 'C');
        $this->Cell($anchoFirma, 6, 'Firma Docente', 0, 0, 'C');
        $this->Cell($anchoFirma, 6, 'Firma Directivo', 0, 1, 'C');
    }

    /**
     * Marca «SIN VALOR LEGAL» (autogestión familia). No aplica en Secretaría ni Docentes.
     */
    private function dibujarMarcaAgua(): void
    {
        $cx = self::MARGEN + self::ANCHO_BLOQUE / 2;
        $cy = 140.0;
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
}
