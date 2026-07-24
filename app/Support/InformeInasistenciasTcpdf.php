<?php

namespace App\Support;

use App\Models\Inasistencia;
use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use Illuminate\Support\Collection;
use TCPDF;

/**
 * Informe de inasistencias del estudiante (A4 vertical, TCPDF, fuente Arial).
 */
final class InformeInasistenciasTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 12.0;

    private const MARGEN_DER = 12.0;

    private const MARGEN_SUP = 10.0;

    private const MARGEN_INF = 10.0;

    private const ANCHO_UTIL = 186.0;

    private const ALTURA_FILA = 3.4;

    private const ALTURA_ENC_TABLA = 4.0;

    private const ALTURA_CABECERA_INST = 16.0;

    /** @var array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string} */
    private array $header;

    /**
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    private function __construct(array $header)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->header = $header;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Informe de inasistencias');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(true, self::MARGEN_INF);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
    }

    /**
     * @param  array<string, mixed>  $datos  Salida de {@see InformeInasistencias::datosPdf()}
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generar(array $datos, array $header): self
    {
        $pdf = new self($header);
        $pdf->AddPage();
        $pdf->dibujarInforme($datos);

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
            $pdf->AddPage();
            $pdf->dibujarInforme($datos);
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
    private function dibujarInforme(array $datos): void
    {
        $y = $this->GetY();
        if ($y < self::MARGEN_SUP) {
            $y = self::MARGEN_SUP;
        }

        $y = $this->dibujarMarcoCabecera($y);
        $y = $this->dibujarTituloAlumno($y, $datos);
        $y = $this->dibujarTablaDetalle($y, $datos);
        $y = $this->dibujarTotales($y, $datos);
        $this->dibujarFirmas($y);
    }

    private function dibujarMarcoCabecera(float $y): float
    {
        $x = self::MARGEN_IZQ;
        $w = self::ANCHO_UTIL;
        $h = self::ALTURA_CABECERA_INST;

        $this->SetDrawColor(17, 17, 17);
        $this->RoundedRect($x, $y, $w, $h, 2.0, '1111', 'D');

        $logo = $this->header['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), $x + 2, $y + 1.5, 11, 13, '', '', '', false, 300);
        }

        $insti = trim((string) ($this->header['insti'] ?? ''));
        $direccion = trim((string) ($this->header['direccion'] ?? ''));
        $localidad = trim((string) ($this->header['localidad'] ?? ''));
        $lineaDir = trim($direccion.($direccion !== '' && $localidad !== '' ? ' — ' : '').$localidad);
        $cue = trim((string) ($this->header['cue'] ?? ''));
        $ee = trim((string) ($this->header['ee'] ?? ''));
        $lineaIds = trim(($cue !== '' ? 'CUE: '.$cue : '').(($cue !== '' && $ee !== '') ? '   ' : '').($ee !== '' ? 'EE: '.$ee : ''));

        $this->SetXY($x, $y + 2);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell($w, 4, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        if ($lineaDir !== '') {
            TcpdfFuenteArial::aplicar($this, '', 6.5);
            $this->Cell($w, 3, $lineaDir, 0, 2, 'C');
        }
        if ($lineaIds !== '') {
            TcpdfFuenteArial::aplicar($this, '', 5.5);
            $this->Cell($w, 3, $lineaIds, 0, 2, 'C');
        }

        return $y + $h + 2;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarTituloAlumno(float $y, array $datos): float
    {
        $x = self::MARGEN_IZQ;
        $w = self::ANCHO_UTIL;
        $ano = (int) ($datos['ano'] ?? now()->year);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->SetXY($x, $y);
        $this->Cell($w, 4, 'INFORME DE INASISTENCIAS — '.$ano, 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, 'B', 7.5);
        $alumno = trim((string) ($datos['alumnoLinea'] ?? ''));
        $dni = trim((string) ($datos['dni'] ?? ''));
        $lineaAlumno = $alumno.($dni !== '' ? ' — '.$dni : '');
        $this->Cell($w, 3.5, mb_strtoupper($lineaAlumno), 0, 2, 'C');

        $curso = trim((string) ($datos['cursoLabel'] ?? ''));
        if ($curso !== '') {
            $this->Cell($w, 3.5, $curso, 0, 2, 'C');
        }

        if (! empty($datos['filtroFechasActivo'])) {
            TcpdfFuenteArial::aplicar($this, '', 6.5);
            $this->Cell(
                $w,
                3,
                'Período: '.($datos['fechaDesde'] ?? '').' — '.($datos['fechaHasta'] ?? ''),
                0,
                2,
                'C',
            );
        }

        return $this->GetY() + 1.5;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarTablaDetalle(float $y, array $datos): float
    {
        $x = self::MARGEN_IZQ;
        $wFecha = 33.5;
        $wCant = 22.0;
        $wTipo = 52.0;
        $wJust = 18.5;
        $wObs = self::ANCHO_UTIL - $wFecha - $wCant - $wTipo - $wJust;

        $this->SetXY($x, $y);
        $this->dibujarEncabezadoTablaDetalle($x, $wFecha, $wCant, $wTipo, $wJust, $wObs);

        /** @var Collection<int, Inasistencia> $inasistencias */
        $inasistencias = $datos['inasistencias'] ?? collect();

        TcpdfFuenteArial::aplicar($this, '', 6.5);
        if ($inasistencias->isEmpty()) {
            $this->Cell(self::ANCHO_UTIL, self::ALTURA_FILA, 'Sin inasistencias registradas en el período.', 1, 1, 'C');

            return $this->GetY() + 1;
        }

        foreach ($inasistencias as $i) {
            if ($this->GetY() + self::ALTURA_FILA > $this->getPageHeight() - self::MARGEN_INF) {
                $this->AddPage();
                $this->dibujarEncabezadoTablaDetalle($x, $wFecha, $wCant, $wTipo, $wJust, $wObs);
            }

            $just = strtoupper(trim((string) ($i->just ?? '')));
            $codigoJust = $just === 'J' ? 'J' : 'I';
            $cant = $i->cantidad !== null
                ? number_format((float) $i->cantidad, 2, ',', '')
                : '—';

            $this->SetX($x);
            $this->Cell($wFecha, self::ALTURA_FILA, $i->fecha?->format('d/m/Y') ?? '—', 1, 0, 'C');
            $this->Cell($wCant, self::ALTURA_FILA, $cant, 1, 0, 'C');
            $this->Cell($wTipo, self::ALTURA_FILA, $this->truncar($i->etiquetaTipo(), 28), 1, 0, 'L');
            $this->Cell($wJust, self::ALTURA_FILA, $codigoJust, 1, 0, 'C');
            $this->Cell($wObs, self::ALTURA_FILA, $this->truncar(trim((string) ($i->obs ?? '')), 42), 1, 1, 'L');
        }

        return $this->GetY() + 1;
    }

    private function dibujarEncabezadoTablaDetalle(
        float $x,
        float $wFecha,
        float $wCant,
        float $wTipo,
        float $wJust,
        float $wObs,
    ): void {
        $this->SetX($x);
        $this->SetFillColor(245, 245, 245);
        TcpdfFuenteArial::aplicar($this, 'B', 6.5);
        $this->Cell($wFecha, self::ALTURA_ENC_TABLA, 'Fecha', 1, 0, 'C', true);
        $this->Cell($wCant, self::ALTURA_ENC_TABLA, 'Cantidad', 1, 0, 'C', true);
        $this->Cell($wTipo, self::ALTURA_ENC_TABLA, 'Tipo', 1, 0, 'C', true);
        $this->Cell($wJust, self::ALTURA_ENC_TABLA, 'Just. / Injus.', 1, 0, 'C', true);
        $this->Cell($wObs, self::ALTURA_ENC_TABLA, 'Observaciones', 1, 1, 'C', true);
        TcpdfFuenteArial::aplicar($this, '', 6.5);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarTotales(float $y, array $datos): float
    {
        $resumen = $datos['resumen'] ?? null;
        if (! $resumen instanceof InasistenciasResumen) {
            return $y;
        }

        $this->SetXY(self::MARGEN_IZQ, $y);
        $items = [
            ['Inasistencias justificadas:', $resumen->formatear($resumen->justificadas)],
            ['Inasistencias injustificadas:', $resumen->formatear($resumen->injustificadas)],
            ['Total de inasistencias:', $resumen->formatear($resumen->totalClase())],
            ['Inasistencias a educación física:', (string) $resumen->educacionFisicaRegistros],
        ];

        foreach ($items as [$etiqueta, $valor]) {
            TcpdfFuenteArial::aplicar($this, 'B', 7);
            $this->Cell(62, 3.2, $etiqueta, 0, 0, 'L');
            TcpdfFuenteArial::aplicar($this, '', 7);
            $this->Cell(0, 3.2, $valor, 0, 1, 'L');
        }

        return $this->GetY() + 1;
    }

    private function dibujarFirmas(float $y): void
    {
        $yFirma = $y + 6;
        if ($yFirma + 12 > $this->getPageHeight() - self::MARGEN_INF) {
            $this->AddPage();
            $yFirma = $this->GetY() + 4;
        }

        $wMitad = self::ANCHO_UTIL / 2;
        TcpdfFuenteArial::aplicar($this, '', 6.5);

        $this->SetXY(self::MARGEN_IZQ, $yFirma);
        $this->Cell($wMitad - 4, 0, '', 'T', 0, 'C');
        $this->SetXY(self::MARGEN_IZQ, $yFirma + 1);
        $this->Cell($wMitad - 4, 3, 'Firma del Preceptor/a', 0, 0, 'C');

        $xDer = self::MARGEN_IZQ + $wMitad + 4;
        $this->SetXY($xDer, $yFirma);
        $this->Cell($wMitad - 4, 0, '', 'T', 0, 'C');
        $this->SetXY($xDer, $yFirma + 1);
        $this->Cell($wMitad - 4, 3, 'Firma Responsable', 0, 0, 'C');

        $this->SetY($yFirma + 8);
    }

    private function truncar(string $texto, int $max): string
    {
        $texto = trim($texto);
        if (mb_strlen($texto) <= $max) {
            return $texto;
        }

        return mb_substr($texto, 0, max(1, $max - 1)).'…';
    }
}
