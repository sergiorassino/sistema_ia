<?php

namespace App\Support\Listados;

use App\Support\Pdf\TcpdfFuenteArial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use TCPDF;

/**
 * PDF «ESTUDIANTES DATOS» (viajes / salidas educativas) — TCPDF A4 apaisado.
 */
final class EstudiantesDatosTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 8.0;

    private const MARGEN_DER = 8.0;

    private const MARGEN_SUP = 10.0;

    private const MARGEN_INF = 10.0;

    /** Ancho útil A4 apaisado (297 mm) menos márgenes laterales. */
    private const ANCHO_UTIL = 281.0;

    private const ALTURA_FILA_MIN = 4.0;

    private const ALTURA_ENCABEZADO_TABLA_MIN = 5.0;

    private const TAMANO_FUENTE_DATOS = 6.0;

    private const TAMANO_FUENTE_ENC_TABLA = 5.5;

    /** @var list<float> Suma = 281 mm */
    private const ANCHOS = [
        8.0,   // Nº
        41.0,  // APELLIDO Y NOMBRES
        20.0,  // DNI
        25.0,  // CURSO y DIVISIÓN
        18.0,  // FECHA NACIMIENTO
        43.0,  // DOMICILIO
        15.0,  // GRUPO SANGUÍNEO
        37.0,  // ADULTO RESP. 1 (MADRE)
        37.0,  // ADULTO RESP. 2 (PADRE)
        37.0,  // TUTOR
    ];

    /** @var array<string, mixed> */
    private array $datos;

    private float $yMax = 0.0;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8', false);
        $this->datos = $datos;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Estudiantes datos');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
        $this->SetDrawColor(204, 204, 204);
        $this->setCellHeightRatio(1.05);
        $this->recalcularYMax();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);

        /** @var Collection<int, object> $filas */
        $filas = $datos['filas'] ?? collect();
        /** @var EstudiantesDatosExporter $exporter */
        $exporter = $datos['exporter'] ?? new EstudiantesDatosExporter;

        $pdf->AddPage('L', 'A4');
        $pdf->dibujarEncabezadoInstitucional();
        $pdf->dibujarContexto();
        $pdf->dibujarEncabezadoTabla();

        if ($filas->isEmpty()) {
            $pdf->dibujarMensajeVacio();

            return $pdf;
        }

        $numero = 0;
        foreach ($filas as $alumno) {
            $numero++;
            $valores = array_map(
                fn ($v) => trim((string) $v),
                $exporter->filaExport($alumno, $numero),
            );
            $alturaFila = $pdf->alturaFila($valores, false);

            if ($pdf->GetY() + $alturaFila > $pdf->yMax) {
                $pdf->AddPage('L', 'A4');
                $pdf->dibujarEncabezadoInstitucional();
                $pdf->dibujarContexto();
                $pdf->dibujarEncabezadoTabla();
            }

            $pdf->dibujarFilaDatos($valores, false);
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

    private function recalcularYMax(): void
    {
        $this->yMax = $this->getPageHeight() - self::MARGEN_INF;
    }

    private function dibujarEncabezadoInstitucional(): void
    {
        /** @var array<string, mixed> $header */
        $header = $this->datos['pdfHeader'] ?? [];

        $x = self::MARGEN_IZQ;
        $y = self::MARGEN_SUP;
        $w = self::ANCHO_UTIL;
        $h = 18.0;

        $this->SetDrawColor(17, 17, 17);
        $this->RoundedRect($x, $y, $w, $h, 2.0, '1111', 'D');

        $logo = $this->resolverLogoArchivo($header);
        if ($logo !== null) {
            $this->Image($logo, $x + 2, $y + 2, 12, 14, '', '', '', false, 300);
        }

        $insti = trim((string) ($header['insti'] ?? ''));
        $direccion = trim((string) ($header['direccion'] ?? ''));
        $localidad = trim((string) ($header['localidad'] ?? ''));
        $lineaDir = trim($direccion.($direccion !== '' && $localidad !== '' ? ' — ' : '').$localidad);
        $cue = trim((string) ($header['cue'] ?? ''));
        $ee = trim((string) ($header['ee'] ?? ''));
        $lineaIds = trim(($cue !== '' ? 'CUE: '.$cue : '').(($cue !== '' && $ee !== '') ? '   ' : '').($ee !== '' ? 'EE: '.$ee : ''));

        $this->SetXY($x, $y + 2.5);
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

        $this->SetY($y + $h + 2);
    }

    private function dibujarContexto(): void
    {
        $x = self::MARGEN_IZQ;
        $w = self::ANCHO_UTIL;
        $nivel = (string) ($this->datos['nivelNombre'] ?? '—');
        $ano = $this->datos['ano'] ?? null;
        $anoTxt = $ano !== null && $ano !== '' ? (string) $ano : '—';
        $total = (int) ($this->datos['totalAlumnos'] ?? 0);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->SetXY($x, $this->GetY());
        $this->Cell($w, 4, 'ESTUDIANTES DATOS — VIAJES / SALIDAS EDUCATIVAS', 0, 2, 'L');

        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell($w, 3.5, 'Nivel: '.$nivel.'   |   Año lectivo: '.$anoTxt.'   |   Alumnos: '.$total, 0, 2, 'L');
        $this->Ln(1);
    }

    private function dibujarEncabezadoTabla(): void
    {
        $this->dibujarFilaDatos(EstudiantesDatosExporter::ENCABEZADOS, true);
    }

    private function dibujarMensajeVacio(): void
    {
        $x = self::MARGEN_IZQ;
        $y = $this->GetY();
        TcpdfFuenteArial::aplicar($this, '', self::TAMANO_FUENTE_DATOS);
        $this->SetFillColor(255, 255, 255);
        $this->SetXY($x, $y);
        $this->Cell(self::ANCHO_UTIL, self::ALTURA_FILA_MIN, 'No hay alumnos seleccionados.', 1, 1, 'C');
    }

    /**
     * @param  list<string|int>  $valores
     */
    private function dibujarFilaDatos(array $valores, bool $encabezado): void
    {
        $y = $this->GetY();
        $altura = $this->alturaFila($valores, $encabezado);
        $x = self::MARGEN_IZQ;

        if ($encabezado) {
            $this->SetFillColor(193, 215, 218);
        } else {
            $this->SetFillColor(255, 255, 255);
        }

        foreach ($valores as $i => $texto) {
            $ancho = self::ANCHOS[$i] ?? 0;
            $align = $i === 0 ? 'C' : 'L';
            $fontSize = $encabezado ? self::TAMANO_FUENTE_ENC_TABLA : self::TAMANO_FUENTE_DATOS;

            TcpdfFuenteArial::aplicar($this, $encabezado ? 'B' : '', $fontSize);
            $this->SetXY($x, $y);
            $this->MultiCell(
                $ancho,
                $altura,
                (string) $texto,
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
                false
            );
            $x += $ancho;
        }

        $this->SetXY(self::MARGEN_IZQ, $y + $altura);
    }

    /**
     * @param  list<string|int>  $valores
     */
    private function alturaFila(array $valores, bool $encabezado): float
    {
        $max = $encabezado ? self::ALTURA_ENCABEZADO_TABLA_MIN : self::ALTURA_FILA_MIN;

        foreach ($valores as $i => $texto) {
            $ancho = self::ANCHOS[$i] ?? 0;
            $fontSize = $encabezado ? self::TAMANO_FUENTE_ENC_TABLA : self::TAMANO_FUENTE_DATOS;
            TcpdfFuenteArial::aplicar($this, $encabezado ? 'B' : '', $fontSize);
            $h = $this->getStringHeight(max(1, $ancho - 1.2), (string) $texto);
            $max = max($max, $h + 0.6);
        }

        return $max;
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function resolverLogoArchivo(array $header): ?string
    {
        $logo = $header['logo_file'] ?? null;
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
        if (is_file($fallback)) {
            return $fallback;
        }

        return null;
    }
}
