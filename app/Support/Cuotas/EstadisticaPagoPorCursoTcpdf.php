<?php

namespace App\Support\Cuotas;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use TCPDF;

/**
 * Estadística de pago por curso y cuota — TCPDF A4 apaisado.
 */
final class EstadisticaPagoPorCursoTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 15.0;

    private const MARGEN_DER = 15.0;

    private const ANCHO_BLOQUE = 267.0;

    private const ANCHO_CURSO = 28.0;

    private const ANCHO_CONCEPTO = 52.0;

    private const ANCHO_MES = 17.0;

    private const ALTURA_FILA = 4.5;

    private const Y_LIMITE = 200.0;

    /** @var array<string, mixed> */
    private array $datos;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8', false);
        $this->datos = $datos;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Estadística de pago por curso y cuota');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false, 8);
        $this->SetMargins(self::MARGEN_IZQ, 8, self::MARGEN_DER);
        $this->SetDrawColor(0, 0, 0);
        $this->SetFillColor(255, 255, 255);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->nuevaPagina();

        /** @var list<array{nombre: string, meses: array<int, array<string, float|int>>}> $cursos */
        $cursos = $datos['cursos'] ?? [];
        foreach ($cursos as $curso) {
            $pdf->asegurarEspacio();
            $pdf->dibujarBloque((string) ($curso['nombre'] ?? ''), $curso['meses'] ?? [], false, 'Porcentaje de Deuda');
        }

        /** @var array<int, array<string, float|int>> $totales */
        $totales = $datos['totales'] ?? [];
        $pdf->asegurarEspacio();
        $pdf->dibujarBloque('Totales', $totales, true, 'Total Porcentaje Adeudado');

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

    private function nuevaPagina(): void
    {
        $this->AddPage('L', 'A4');
        $this->dibujarEncabezadoInstitucional();
        $this->dibujarEncabezadoColumnas();
    }

    private function asegurarEspacio(): void
    {
        if ($this->GetY() + $this->altoBloque() > self::Y_LIMITE) {
            $this->nuevaPagina();
        }
    }

    private function dibujarEncabezadoInstitucional(): void
    {
        /** @var array<string, mixed> $header */
        $header = $this->datos['header'] ?? [];
        $ano = (int) ($this->datos['ano'] ?? now()->year);
        $fecha = (string) ($this->datos['fechaTexto'] ?? '');

        $x = self::MARGEN_IZQ;
        $y = 8.0;
        $this->Rect($x, $y, self::ANCHO_BLOQUE, 18);

        $logo = $header['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), $x + 3, $y + 1.5, 15, 15, '', '', '', false, 300);
        }

        $insti = trim((string) ($header['insti'] ?? ''));
        $this->SetXY($x, $y + 2);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(self::ANCHO_BLOQUE, 5, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(self::ANCHO_BLOQUE, 4, 'ESTADÍSTICA DE PAGO POR CURSO Y CUOTA - '.$ano, 0, 2, 'C');
        $this->Cell(self::ANCHO_BLOQUE, 4, 'Fecha para el cálculo:   '.$fecha, 0, 2, 'C');
    }

    private function dibujarEncabezadoColumnas(): void
    {
        $this->SetXY(self::MARGEN_IZQ, 28);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(self::ANCHO_CURSO, 5, 'Sala/Grado/Curso', 1, 0, 'C');
        $this->Cell(self::ANCHO_CONCEPTO, 5, '', 1, 0, 'C');
        foreach (EstadisticaPagoPorCursoDatos::MESES as $mes) {
            $this->Cell(self::ANCHO_MES, 5, EstadisticaPagoPorCursoDatos::ETIQUETAS_MES[$mes] ?? (string) $mes, 1, 0, 'C');
        }
        $this->SetY(34.5);
    }

    /**
     * @param  array<int, array<string, float|int>>  $meses
     */
    private function dibujarBloque(string $titulo, array $meses, bool $negrita, string $etiquetaPorcentaje): void
    {
        $y = $this->GetY();
        $x = self::MARGEN_IZQ;
        $alto = $this->altoBloque();
        TcpdfFuenteArial::aplicar($this, $negrita ? 'B' : '', 6);
        $this->Rect($x, $y, self::ANCHO_CURSO, $alto);
        $lineas = max(1, $this->getNumLines($titulo, self::ANCHO_CURSO, false, true, null, 0));
        $altoTexto = min($alto, $lineas * self::ALTURA_FILA);
        $this->MultiCell(
            self::ANCHO_CURSO,
            self::ALTURA_FILA,
            $titulo,
            0,
            'C',
            false,
            0,
            $x,
            $y + (($alto - $altoTexto) / 2),
            true,
            0,
            false,
            true,
            $altoTexto,
            'T',
        );

        $filaY = $y;
        foreach (EstadisticaPagoPorCursoDatos::filasInforme() as $fila) {
            $etiqueta = $fila['etiqueta'];
            if ($fila['clave'] === 'porcentaje') {
                $etiqueta = $etiquetaPorcentaje;
            }
            $this->dibujarFila($x + self::ANCHO_CURSO, $filaY, $etiqueta, $fila['clave'], $fila['tipo'], $meses, $negrita);
            $filaY += self::ALTURA_FILA;
        }

        $this->SetXY(self::MARGEN_IZQ, $y + $this->altoBloque() + 1.2);
    }

    private function altoBloque(): float
    {
        return count(EstadisticaPagoPorCursoDatos::filasInforme()) * self::ALTURA_FILA;
    }

    /**
     * @param  array<int, array<string, float|int>>  $meses
     */
    private function dibujarFila(float $x, float $y, string $etiqueta, string $clave, string $tipo, array $meses, bool $negrita): void
    {
        TcpdfFuenteArial::aplicar($this, $negrita ? 'B' : '', 6);
        $this->SetXY($x, $y);
        $this->Cell(self::ANCHO_CONCEPTO, self::ALTURA_FILA, $etiqueta, 1, 0, 'R');
        foreach (EstadisticaPagoPorCursoDatos::MESES as $mes) {
            $celda = $meses[$mes] ?? EstadisticaPagoPorCursoDatos::celdaVacia();
            $this->Cell(self::ANCHO_MES, self::ALTURA_FILA, $this->formatear($celda[$clave] ?? 0, $tipo), 1, 0, 'C');
        }
    }

    private function formatear(float|int $valor, string $tipo): string
    {
        if ($tipo === 'int') {
            return (string) (int) $valor;
        }

        if ($tipo === 'pct') {
            return number_format((float) $valor, 2, '.', '').' %';
        }

        return number_format((float) $valor, 2, '.', '');
    }
}
