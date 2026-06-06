<?php

namespace App\Support\Listados;

use App\Models\Sexo;
use App\Support\Pdf\TcpdfFuenteArial;
use Illuminate\Support\Collection;
use TCPDF;

/**
 * Listado de estudiantes por curso — TCPDF A4 (vertical u horizontal según cantidad de columnas).
 */
final class ListadoCursoTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 8.0;

    private const MARGEN_DER = 8.0;

    private const MARGEN_SUP = 10.0;

    private const MARGEN_INF = 10.0;

    /** Ancho útil A4 vertical (210 mm) menos márgenes laterales. */
    private const ANCHO_UTIL_P = 194.0;

    /** Ancho útil A4 apaisado (297 mm) menos márgenes laterales. */
    private const ANCHO_UTIL_L = 281.0;

    private const ALTURA_FILA_MIN = 4.0;

    private const ALTURA_ENCABEZADO_TABLA_MIN = 5.0;

    private const TAMANO_FUENTE_DATOS = 6.0;

    private const TAMANO_FUENTE_ENC_TABLA = 6.0;

    private const TAMANO_FUENTE_COND = 5.0;

    private bool $apaisado;

    /** @var array<string, mixed> */
    private array $datos;

    /** @var list<array{key: string, label: string, alias: string}> */
    private array $columnasMeta = [];

    /** @var list<string> */
    private array $campos = [];

    /** @var list<float> */
    private array $anchosMm = [];

    private float $anchoUtil = self::ANCHO_UTIL_P;

    private float $yMax = 0.0;

    private bool $primeraPaginaDocumento = true;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos, bool $apaisado)
    {
        $orient = $apaisado ? 'L' : 'P';
        parent::__construct($orient, 'mm', 'A4', true, 'UTF-8', false);
        $this->datos = $datos;
        $this->apaisado = $apaisado;
        $this->anchoUtil = $apaisado ? self::ANCHO_UTIL_L : self::ANCHO_UTIL_P;

        /** @var list<array{key: string, label: string, alias: string}> $columnasMeta */
        $columnasMeta = $datos['columnasMeta'] ?? [];
        $this->columnasMeta = $columnasMeta;

        /** @var list<string> $campos */
        $campos = $datos['campos'] ?? array_column($columnasMeta, 'key');
        $this->campos = $campos;
        $this->anchosMm = ListadoCursoPdfColumnWidths::anchosMm($this->anchoUtil, $this->campos);

        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Listado de estudiantes');
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
        $apaisado = (bool) ($datos['apaisado'] ?? false);
        $pdf = new self($datos, $apaisado);

        /** @var list<array{cursoLabel: string, alumnos: Collection<int, object>}> $bloques */
        $bloques = $datos['bloques'] ?? [];

        foreach ($bloques as $idx => $bloque) {
            if ($idx > 0) {
                $pdf->nuevaPagina();
            } else {
                $pdf->AddPage($pdf->orientacionPagina(), 'A4');
                $pdf->primeraPaginaDocumento = false;
            }

            $pdf->renderBloqueCurso($bloque);
        }

        if ($pdf->primeraPaginaDocumento) {
            $pdf->AddPage($pdf->orientacionPagina(), 'A4');
            $pdf->primeraPaginaDocumento = false;
            $pdf->dibujarEncabezadoInstitucional();
            $pdf->dibujarSubtitulo();
            $pdf->dibujarContexto('—');
            $pdf->dibujarEncabezadoTabla();
            $pdf->dibujarMensajeVacio();
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

    private function orientacionPagina(): string
    {
        return $this->apaisado ? 'L' : 'P';
    }

    private function recalcularYMax(): void
    {
        $this->yMax = $this->getPageHeight() - self::MARGEN_INF;
    }

    private function nuevaPagina(): void
    {
        $this->AddPage($this->orientacionPagina(), 'A4');
        $this->recalcularYMax();
    }

    /**
     * @param  array{cursoLabel: string, alumnos: Collection<int, object>}  $bloque
     */
    private function renderBloqueCurso(array $bloque): void
    {
        $cursoLabel = (string) ($bloque['cursoLabel'] ?? '—');
        $alumnos = $bloque['alumnos'] ?? collect();

        $this->dibujarEncabezadoInstitucional();
        $this->dibujarSubtitulo();
        $this->dibujarContexto($cursoLabel);
        $this->dibujarEncabezadoTabla();

        if ($alumnos->isEmpty()) {
            $this->dibujarMensajeVacio();

            return;
        }

        $numero = 0;
        foreach ($alumnos as $alumno) {
            $numero++;
            $valores = $this->valoresFila($numero, $alumno);
            $alturaFila = $this->alturaFila($valores, false);

            if ($this->GetY() + $alturaFila > $this->yMax) {
                $this->nuevaPagina();
                $this->dibujarEncabezadoInstitucional();
                $this->dibujarSubtitulo();
                $this->dibujarContexto($cursoLabel);
                $this->dibujarEncabezadoTabla();
            }

            $this->dibujarFilaDatos($valores, false);
        }
    }

    private function dibujarEncabezadoInstitucional(): void
    {
        /** @var array<string, mixed> $header */
        $header = $this->datos['pdfHeader'] ?? [];

        $x = self::MARGEN_IZQ;
        $y = self::MARGEN_SUP;
        $w = $this->anchoUtil;
        $h = 18.0;

        $this->SetDrawColor(17, 17, 17);
        $this->RoundedRect($x, $y, $w, $h, 2.0, '1111', 'D');

        $logo = $header['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
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

    private function dibujarContexto(string $cursoLabel): void
    {
        $x = self::MARGEN_IZQ;
        $w = $this->anchoUtil;
        $nivel = (string) ($this->datos['nivelNombre'] ?? '—');
        $ano = $this->datos['ano'] ?? null;
        $anoTxt = $ano !== null && $ano !== '' ? (string) $ano : '—';
        $condicion = (string) ($this->datos['modoEstudiantesPdf'] ?? '—');

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->SetXY($x, $this->GetY());
        $this->Cell($w, 4, 'LISTADO DE ESTUDIANTES', 0, 2, 'L');

        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell($w, 3.5, 'Nivel: '.$nivel.'   |   Año lectivo: '.$anoTxt, 0, 2, 'L');
        $this->Cell($w, 3.5, 'Condición: '.$condicion.'   |   Curso: '.$cursoLabel, 0, 2, 'L');
        $this->Ln(1);
    }

    private function dibujarSubtitulo(): void
    {
        $subtitulo = trim((string) ($this->datos['subtitulo'] ?? ''));
        if ($subtitulo === '') {
            return;
        }

        $x = self::MARGEN_IZQ;
        $w = $this->anchoUtil;

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->SetXY($x, $this->GetY());
        $this->MultiCell($w, 4.5, $subtitulo, 0, 'C', false, 1);
        $this->Ln(1.5);
    }

    private function dibujarEncabezadoTabla(): void
    {
        $titulos = ['Nº'];
        foreach ($this->columnasMeta as $col) {
            $titulos[] = $col['key'] === 'condiciones.condicion' ? 'Cond.' : $col['label'];
        }
        $this->dibujarFilaDatos($titulos, true);
    }

    private function dibujarMensajeVacio(): void
    {
        $x = self::MARGEN_IZQ;
        $y = $this->GetY();
        TcpdfFuenteArial::aplicar($this, '', self::TAMANO_FUENTE_DATOS);
        $this->SetFillColor(255, 255, 255);
        $this->SetXY($x, $y);
        $this->Cell($this->anchoUtil, self::ALTURA_FILA_MIN, 'No hay alumnos matriculados en este curso.', 1, 1, 'C');
    }

    /**
     * @param  list<string>  $valores
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
            $ancho = $this->anchosMm[$i] ?? 0;
            $esNum = $i === 0;
            $esCond = ! $esNum && ($this->columnasMeta[$i - 1]['key'] ?? '') === 'condiciones.condicion';
            $align = $esNum ? 'C' : ($esCond ? 'R' : 'L');
            $fontSize = $esCond && ! $encabezado ? self::TAMANO_FUENTE_COND : ($encabezado ? self::TAMANO_FUENTE_ENC_TABLA : self::TAMANO_FUENTE_DATOS);

            TcpdfFuenteArial::aplicar($this, $encabezado ? 'B' : '', $fontSize);
            $this->SetXY($x, $y);
            $valign = 'M';
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
                $valign,
                false
            );
            $x += $ancho;
        }

        $this->SetXY(self::MARGEN_IZQ, $y + $altura);
    }

    /**
     * @param  list<string>  $valores
     */
    private function alturaFila(array $valores, bool $encabezado): float
    {
        $max = $encabezado ? self::ALTURA_ENCABEZADO_TABLA_MIN : self::ALTURA_FILA_MIN;

        foreach ($valores as $i => $texto) {
            $ancho = $this->anchosMm[$i] ?? 0;
            $esCond = $i > 0 && ($this->columnasMeta[$i - 1]['key'] ?? '') === 'condiciones.condicion';
            $fontSize = $esCond && ! $encabezado ? self::TAMANO_FUENTE_COND : ($encabezado ? self::TAMANO_FUENTE_ENC_TABLA : self::TAMANO_FUENTE_DATOS);
            TcpdfFuenteArial::aplicar($this, $encabezado ? 'B' : '', $fontSize);
            $h = $this->getStringHeight(max(1, $ancho - 1.2), $texto);
            $max = max($max, $h + 0.6);
        }

        return $max;
    }

    /**
     * @return list<string>
     */
    private function valoresFila(int $numero, object $alumno): array
    {
        $valores = [(string) $numero];

        foreach ($this->columnasMeta as $col) {
            if ($col['key'] === ListadoCursoPdfFieldCatalog::KEY_APELLIDO_NOMBRE) {
                $valores[] = ListadoCursoPdfFieldCatalog::valorApellidoNombre($alumno);

                continue;
            }

            $alias = $col['alias'];
            $v = $alumno->{$alias} ?? null;
            $valores[] = $this->formatearValor($col['key'], $v);
        }

        return $valores;
    }

    private function formatearValor(string $key, mixed $v): string
    {
        if ($v === null || $v === '') {
            return '—';
        }

        if ($key === 'legajos.sexo') {
            return Sexo::etiquetaParaValorAlmacenado($v) ?: '—';
        }

        if (is_numeric($v) && (str_contains($key, 'bloq') || str_ends_with($key, 'inscripto') || str_contains($key, 'acept'))) {
            return $v ? 'Sí' : 'No';
        }

        return trim((string) $v);
    }
}
