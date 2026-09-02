<?php

namespace App\Support\Listados;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfLogoInstitucional;
use TCPDF;

/**
 * PDF listado de familias — A4 vertical, TCPDF.
 */
final class ListadoFamiliasTcpdf extends TCPDF
{
    private const ORIGEN_X = 10.0;

    private const ANCHO_BLOQUE = 190.0;

    private const MARGEN_DER = 10.0;

    private const MARGEN_SUP = 8.0;

    private const ALTO_ENCABEZADO = 20.0;

    private const LOGO_ANCHO = 16.0;

    private const LOGO_ALTO = 16.0;

    private const ALTO_LINEA = 4.2;

    private const MAX_LINEAS_FILA = 2;

    private const Y_MAX = 287.0;

    private const COLS_FAMILIA = 5;

    /** @var array<string, mixed> */
    private array $datos;

    private float $yTablaInicio = 0.0;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->datos = $datos;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle((string) ($datos['tituloDocumento'] ?? 'Listado de familias'));
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false, 10);
        $this->SetMargins(self::ORIGEN_X, self::MARGEN_SUP, self::MARGEN_DER);
        $this->SetDrawColor(0, 0, 0);
        $this->setCellPaddings(0.6, 0.35, 0.6, 0.35);
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
        $y = $this->dibujarEncabezadoInstitucional(8.0);
        $this->yTablaInicio = $this->dibujarEncabezadoTabla($y);
        $y = $this->yTablaInicio;

        $grupos = (array) ($this->datos['grupos'] ?? []);
        if ($grupos === []) {
            TcpdfFuenteArial::aplicar($this, '', 8);
            $this->SetXY(self::ORIGEN_X, $y + 4);
            $this->Cell(self::ANCHO_BLOQUE, 6, 'No hay registros para los filtros aplicados.', 0, 1, 'C');

            return;
        }

        foreach ($grupos as $grupo) {
            $y = $this->dibujarGrupo($y, (array) $grupo);
        }
    }

    private function dibujarEncabezadoInstitucional(float $y): float
    {
        $header = (array) ($this->datos['pdfHeader'] ?? []);
        $insti = trim((string) ($header['insti'] ?? config('tenant.nombre', '')));
        $fecha = (string) ($this->datos['fechaInforme'] ?? '');
        $titulo = trim((string) ($this->datos['tituloInforme'] ?? 'LISTADO DE FAMILIAS'));
        $filtros = trim((string) ($this->datos['filtrosLinea'] ?? ''));

        $this->Rect(self::ORIGEN_X, $y, self::ANCHO_BLOQUE, self::ALTO_ENCABEZADO);

        $logoFile = $header['logo_file'] ?? null;
        TcpdfLogoInstitucional::dibujar(
            $this,
            self::ORIGEN_X + 3,
            $y + 2,
            self::LOGO_ANCHO,
            self::LOGO_ALTO,
            is_string($logoFile) ? $logoFile : null,
        );

        $this->SetXY(self::ORIGEN_X, $y + 2);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(self::ANCHO_BLOQUE, 5, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(self::ANCHO_BLOQUE, 4.5, $titulo.($fecha !== '' ? ' - '.$fecha : ''), 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->Cell(self::ANCHO_BLOQUE, 4, $filtros !== '' ? $filtros : '—', 0, 2, 'C');

        return $y + self::ALTO_ENCABEZADO + 2.0;
    }

    private function dibujarEncabezadoTabla(float $y): float
    {
        $etiquetas = array_values((array) ($this->datos['encabezados'] ?? []));

        return $this->dibujarFilaCeldas($y, $etiquetas, true);
    }

    /**
     * @param  array<string, mixed>  $grupo
     */
    private function dibujarGrupo(float $y, array $grupo): float
    {
        $hijos = array_values((array) ($grupo['hijos'] ?? []));
        if ($hijos === []) {
            $hijos = [['', '', '', '']];
        }

        $familia = array_values((array) ($grupo['familia'] ?? []));
        $anchosFam = $this->anchosFamilia();
        $anchosHijo = $this->anchosHijo();

        $restantes = $hijos;
        while ($restantes !== []) {
            $espacio = self::Y_MAX - $y;
            $lote = [];
            $altos = [];
            $suma = 0.0;

            foreach ($restantes as $hijo) {
                $valoresHijo = array_values((array) $hijo);
                $alto = $this->alturaConAnchos($valoresHijo, $anchosHijo, false);
                if ($lote !== [] && ($suma + $alto) > $espacio) {
                    break;
                }
                $lote[] = $valoresHijo;
                $altos[] = $alto;
                $suma += $alto;
            }

            if ($lote === []) {
                $this->AddPage();
                $y = $this->dibujarEncabezadoInstitucional(8.0);
                $this->yTablaInicio = $this->dibujarEncabezadoTabla($y);
                $y = $this->yTablaInicio;

                continue;
            }

            if ($y > $this->yTablaInicio + 0.4 && ($suma > $espacio)) {
                $this->AddPage();
                $y = $this->dibujarEncabezadoInstitucional(8.0);
                $this->yTablaInicio = $this->dibujarEncabezadoTabla($y);
                $y = $this->yTablaInicio;

                continue;
            }

            $altoFamTxt = $this->alturaConAnchos($familia, $anchosFam, false);
            if ($altoFamTxt > $suma) {
                $altos[count($altos) - 1] += $altoFamTxt - $suma;
                $suma = $altoFamTxt;
            }

            if ($y + $suma > self::Y_MAX && $y > $this->yTablaInicio + 0.4) {
                $this->AddPage();
                $y = $this->dibujarEncabezadoInstitucional(8.0);
                $this->yTablaInicio = $this->dibujarEncabezadoTabla($y);
                $y = $this->yTablaInicio;

                continue;
            }

            $this->dibujarCeldas($y, $familia, $anchosFam, self::ORIGEN_X, $suma, false, false);
            $yHijo = $y;
            $xHijo = self::ORIGEN_X + array_sum($anchosFam);
            foreach ($lote as $i => $hijo) {
                $this->dibujarCeldas($yHijo, $hijo, $anchosHijo, $xHijo, $altos[$i], false, true);
                $yHijo += $altos[$i];
            }

            $y += $suma;
            $restantes = array_slice($restantes, count($lote));

            if ($restantes !== []) {
                $this->AddPage();
                $y = $this->dibujarEncabezadoInstitucional(8.0);
                $this->yTablaInicio = $this->dibujarEncabezadoTabla($y);
                $y = $this->yTablaInicio;
            }
        }

        return $y;
    }

    /**
     * @param  list<mixed>  $valores
     * @param  list<float>  $anchos
     */
    private function dibujarCeldas(
        float $y,
        array $valores,
        array $anchos,
        float $x,
        float $alto,
        bool $encabezado,
        bool $esHijo,
    ): void {
        TcpdfFuenteArial::aplicar($this, $encabezado ? 'B' : '', $encabezado ? 6.5 : 6);

        foreach ($valores as $i => $texto) {
            $w = $anchos[$i] ?? 20.0;
            $align = match (true) {
                $encabezado => 'C',
                $esHijo && $i === 3 => 'C',
                ! $esHijo && $i === 0 => 'C',
                default => 'L',
            };
            $this->MultiCell(
                $w,
                $alto,
                (string) $texto,
                1,
                $align,
                false,
                0,
                $x,
                $y,
                true,
                0,
                false,
                true,
                $alto,
                'M',
                false,
            );
            $x += $w;
        }
    }

    /**
     * @param  list<mixed>  $valores
     */
    private function dibujarFilaCeldas(float $y, array $valores, bool $encabezado): float
    {
        $alto = $this->alturaFila($valores, $encabezado);
        $anchos = $this->anchos();
        $x = self::ORIGEN_X;

        TcpdfFuenteArial::aplicar($this, $encabezado ? 'B' : '', $encabezado ? 6.5 : 6);

        foreach ($valores as $i => $texto) {
            $w = $anchos[$i] ?? 20.0;
            $align = match (true) {
                $encabezado => 'C',
                $i === 0 => 'C',
                default => 'L',
            };
            $this->MultiCell(
                $w,
                $alto,
                (string) $texto,
                1,
                $align,
                false,
                0,
                $x,
                $y,
                true,
                0,
                false,
                true,
                $alto,
                'M',
                false,
            );
            $x += $w;
        }

        return $y + $alto;
    }

    /**
     * @param  list<mixed>  $valores
     * @param  list<float>  $anchos
     */
    private function alturaConAnchos(array $valores, array $anchos, bool $encabezado): float
    {
        TcpdfFuenteArial::aplicar($this, $encabezado ? 'B' : '', $encabezado ? 6.5 : 6);
        $maxLineas = 1;

        foreach ($valores as $i => $texto) {
            $w = max(1.0, ($anchos[$i] ?? 20.0) - 1.2);
            $lineas = (int) $this->getNumLines((string) $texto, $w);
            $maxLineas = max($maxLineas, $lineas);
        }

        $maxLineas = min(self::MAX_LINEAS_FILA, max(1, $maxLineas));

        return $maxLineas * self::ALTO_LINEA;
    }

    /**
     * @param  list<mixed>  $valores
     */
    private function alturaFila(array $valores, bool $encabezado): float
    {
        return $this->alturaConAnchos($valores, $this->anchos(), $encabezado);
    }

    /**
     * @return list<float>
     */
    private function anchosFamilia(): array
    {
        return array_slice($this->anchos(), 0, self::COLS_FAMILIA);
    }

    /**
     * @return list<float>
     */
    private function anchosHijo(): array
    {
        return array_slice($this->anchos(), self::COLS_FAMILIA);
    }

    /**
     * @return list<float>
     */
    private function anchos(): array
    {
        $anchos = array_values((array) ($this->datos['anchos'] ?? []));

        return array_map(static fn ($v) => (float) $v, $anchos);
    }
}
