<?php

namespace App\Support\Listados;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfLogoInstitucional;
use Illuminate\Support\Collection;
use TCPDF;

/**
 * Utilidades compartidas para PDFs del módulo «Listados de Estudiantes con Formato».
 */
trait ListadoEstudiantesFormatoTcpdfComun
{
    protected const FORMATO_MARGEN_IZQ = 8.0;

    protected const FORMATO_MARGEN_DER = 8.0;

    protected const FORMATO_MARGEN_SUP = 10.0;

    protected const FORMATO_MARGEN_INF = 10.0;

    protected const FORMATO_ANCHO_UTIL = 194.0;

    protected const FORMATO_ALTURA_FILA = 5.0;

    protected const FORMATO_ANCHO_NUM = 7.0;

    protected const FORMATO_ANCHO_NOMBRE = 62.0;

    /**
     * Si es true (cuadriculado, renglón, calendario): título, nivel, año y curso +1 pt;
     * apellido y nombre a 7 pt. El listado para registro de firmas deja esto en false.
     */
    protected bool $formatoFuentesListadoAmpliadas = false;

    /** @var array<string, mixed> */
    protected array $formatoDatos = [];

    protected float $formatoYMax = 0.0;

    protected function formatoInicializarTcpdf(array $datos, string $tituloDocumento): void
    {
        $this->formatoDatos = $datos;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle($tituloDocumento);
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::FORMATO_MARGEN_IZQ, self::FORMATO_MARGEN_SUP, self::FORMATO_MARGEN_DER);
        $this->SetDrawColor(120, 120, 120);
        $this->setCellHeightRatio(1.05);
        $this->formatoRecalcularYMax();
    }

    public static function formatoRespuestaHttp(self $pdf, string $nombreArchivo): \Illuminate\Http\Response
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

    protected function formatoRecalcularYMax(): void
    {
        $this->formatoYMax = $this->getPageHeight() - self::FORMATO_MARGEN_INF;
    }

    protected function formatoNuevaPagina(): void
    {
        $this->AddPage('P', 'A4');
        $this->formatoRecalcularYMax();
    }

    protected function formatoNombreAlumno(object $alumno): string
    {
        $apellido = trim((string) (
            $alumno->apellido
            ?? $alumno->{ListadoCursoPdfFieldCatalog::alias('legajos.apellido')}
            ?? ''
        ));
        $nombre = trim((string) (
            $alumno->nombre
            ?? $alumno->{ListadoCursoPdfFieldCatalog::alias('legajos.nombre')}
            ?? ''
        ));
        $texto = EstudiantesDatosConsulta::formatearApellidoNombre($apellido, $nombre);

        return $texto !== '' ? $texto : '—';
    }

    protected function formatoDibujarEncabezadoInstitucional(): void
    {
        /** @var array<string, mixed> $header */
        $header = $this->formatoDatos['pdfHeader'] ?? [];

        $x = self::FORMATO_MARGEN_IZQ;
        $y = self::FORMATO_MARGEN_SUP;
        $w = self::FORMATO_ANCHO_UTIL;
        $h = 20.0;

        $this->SetDrawColor(17, 17, 17);
        $this->SetTextColor(0, 0, 0);
        $this->RoundedRect($x, $y, $w, $h, 2.0, '1111', 'D');

        $logoFile = isset($header['logo_file']) && is_string($header['logo_file']) ? $header['logo_file'] : null;
        TcpdfLogoInstitucional::dibujar($this, $x + 2, $y + 2, 12, 14, $logoFile);

        $insti = trim((string) ($header['insti'] ?? config('tenant.nombre', '')));
        $direccion = trim((string) ($header['direccion'] ?? ''));
        $localidad = trim((string) ($header['localidad'] ?? ''));
        $lineaDir = trim($direccion.($direccion !== '' && $localidad !== '' ? ' — ' : '').$localidad);
        $cue = trim((string) ($header['cue'] ?? ''));
        $ee = trim((string) ($header['ee'] ?? ''));
        $lineaIds = trim(($cue !== '' ? 'CUE: '.$cue : '').(($cue !== '' && $ee !== '') ? '   ' : '').($ee !== '' ? 'EE: '.$ee : ''));

        $this->SetXY($x, $y + 2.5);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell($w, 4, $insti !== '' ? $insti : 'Institución', 0, 2, 'C', false);

        if ($lineaDir !== '') {
            TcpdfFuenteArial::aplicar($this, '', 6.5);
            $this->Cell($w, 3, $lineaDir, 0, 2, 'C', false);
        }
        if ($lineaIds !== '') {
            TcpdfFuenteArial::aplicar($this, '', 5.5);
            $this->Cell($w, 3, $lineaIds, 0, 2, 'C', false);
        }

        $this->SetTextColor(0, 0, 0);
        $this->SetY($y + $h + 2);
    }

    protected function formatoDibujarTituloDocumento(string $titulo): void
    {
        $x = self::FORMATO_MARGEN_IZQ;
        $w = self::FORMATO_ANCHO_UTIL;
        $nivel = (string) ($this->formatoDatos['nivelNombre'] ?? '—');
        $ano = $this->formatoDatos['ano'] ?? null;
        $anoTxt = $ano !== null && $ano !== '' ? (string) $ano : '—';

        $tamanoTitulo = $this->formatoFuentesListadoAmpliadas ? 9.0 : 8.0;
        $tamanoContexto = $this->formatoFuentesListadoAmpliadas ? 8.0 : 7.0;
        $altoTitulo = $this->formatoFuentesListadoAmpliadas ? 4.5 : 4.0;
        $altoContexto = $this->formatoFuentesListadoAmpliadas ? 4.0 : 3.5;

        TcpdfFuenteArial::aplicar($this, 'B', $tamanoTitulo);
        $this->SetXY($x, $this->GetY());
        $this->Cell($w, $altoTitulo, mb_strtoupper($titulo), 0, 2, 'L');

        TcpdfFuenteArial::aplicar($this, '', $tamanoContexto);
        $this->Cell($w, $altoContexto, 'Nivel: '.$nivel.'   |   Año lectivo: '.$anoTxt, 0, 2, 'L');
        $this->Ln(0.5);
    }

    protected function formatoDibujarLineaCurso(string $cursoLabel, ?string $detalleExtra = null): void
    {
        $x = self::FORMATO_MARGEN_IZQ;
        $w = self::FORMATO_ANCHO_UTIL;

        $tamanoCurso = $this->formatoFuentesListadoAmpliadas ? 8.5 : 7.5;

        TcpdfFuenteArial::aplicar($this, 'B', $tamanoCurso);
        $this->SetXY($x, $this->GetY());
        $linea = 'Curso: '.$cursoLabel;
        if ($detalleExtra !== null && $detalleExtra !== '') {
            $linea .= '   |   '.$detalleExtra;
        }
        $this->Cell($w, 4, $linea, 0, 2, 'L');
        $this->Ln(0.5);
    }

    protected function formatoDibujarCelda(
        float $x,
        float $y,
        float $ancho,
        float $altura,
        string $texto,
        bool $encabezado = false,
        bool $finDeSemana = false,
        string $align = 'C',
        ?float $tamanoFuente = null,
    ): void {
        if ($encabezado) {
            $this->SetFillColor(193, 215, 218);
        } elseif ($finDeSemana) {
            $this->SetFillColor(220, 220, 220);
        } else {
            $this->SetFillColor(255, 255, 255);
        }

        TcpdfFuenteArial::aplicar(
            $this,
            $encabezado ? 'B' : '',
            $tamanoFuente ?? ($encabezado ? 6.0 : 5.5)
        );
        $this->SetTextColor(0, 0, 0);
        $this->SetXY($x, $y);
        $this->MultiCell(
            $ancho,
            $altura,
            $texto,
            1,
            $align,
            true,
            0,
            $x,
            $y,
            true,
            0,
            false,
            true,
            $altura,
            'M',
            false,
        );
    }

    /**
     * @param  Collection<int, object>  $alumnos
     */
    protected function formatoDibujarMensajeVacio(Collection $alumnos): void
    {
        if ($alumnos->isNotEmpty()) {
            return;
        }

        $x = self::FORMATO_MARGEN_IZQ;
        $y = $this->GetY();
        TcpdfFuenteArial::aplicar($this, '', 6.0);
        $this->SetFillColor(255, 255, 255);
        $this->SetXY($x, $y);
        $this->Cell(self::FORMATO_ANCHO_UTIL, self::FORMATO_ALTURA_FILA, 'No hay alumnos matriculados en este curso.', 1, 1, 'C');
    }

    protected function formatoDibujarCeldaUnaLinea(
        float $x,
        float $y,
        float $ancho,
        float $altura,
        string $texto,
        bool $encabezado = false,
        bool $finDeSemana = false,
        string $align = 'C',
        ?float $tamanoFuente = null,
    ): void {
        if ($encabezado) {
            $this->SetFillColor(193, 215, 218);
        } elseif ($finDeSemana) {
            $this->SetFillColor(220, 220, 220);
        } else {
            $this->SetFillColor(255, 255, 255);
        }

        $this->SetTextColor(0, 0, 0);
        TcpdfFuenteArial::aplicar($this, $encabezado ? 'B' : '', $tamanoFuente ?? ($encabezado ? 6.0 : 5.5));
        $this->SetXY($x, $y);
        $this->Cell($ancho, $altura, $texto, 1, 0, $align, true, '', 0, false, 'T', 'M');
    }

    protected function formatoTamanoFuenteParaAnchoCelda(float $ancho, bool $dosCifras = false): float
    {
        if ($ancho >= 5.5) {
            return 6.0;
        }
        if ($ancho >= 4.8) {
            return $dosCifras ? 5.5 : 6.0;
        }
        if ($ancho >= 4.2) {
            return $dosCifras ? 5.0 : 5.5;
        }

        return $dosCifras ? 4.5 : 5.0;
    }
}
