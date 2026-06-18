<?php

namespace App\Support\Listados;

use Illuminate\Support\Collection;
use TCPDF;

/**
 * Listado con cuadriculado — TCPDF A4 vertical.
 * Columnas: Nº, Apellido y Nombre, cuadros vacíos para uso eventual.
 */
final class ListadoEstudiantesFormatoCuadriculadoTcpdf extends TCPDF
{
    use ListadoEstudiantesFormatoTcpdfComun;

    private const CANTIDAD_CUADROS = 18;

    private bool $primeraPaginaDocumento = true;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->formatoInicializarTcpdf($datos, 'Listado con cuadriculado');
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);

        /** @var list<array{cursoLabel: string, alumnos: Collection<int, object>}> $bloques */
        $bloques = $datos['bloques'] ?? [];

        foreach ($bloques as $idx => $bloque) {
            if ($idx > 0) {
                $pdf->formatoNuevaPagina();
            } else {
                $pdf->AddPage('P', 'A4');
                $pdf->primeraPaginaDocumento = false;
            }
            $pdf->renderBloqueCurso($bloque);
        }

        if ($pdf->primeraPaginaDocumento) {
            $pdf->AddPage('P', 'A4');
            $pdf->formatoDibujarEncabezadoInstitucional();
            $pdf->formatoDibujarTituloDocumento('Listado con cuadriculado');
            $pdf->formatoDibujarLineaCurso('—');
            $pdf->dibujarEncabezadoTabla();
            $pdf->formatoDibujarMensajeVacio(collect());
        }

        return $pdf;
    }

    /**
     * @param  array{cursoLabel: string, alumnos: Collection<int, object>}  $bloque
     */
    private function renderBloqueCurso(array $bloque): void
    {
        $cursoLabel = (string) ($bloque['cursoLabel'] ?? '—');
        $alumnos = $bloque['alumnos'] ?? collect();

        $this->formatoDibujarEncabezadoInstitucional();
        $this->formatoDibujarTituloDocumento('Listado con cuadriculado');
        $this->formatoDibujarLineaCurso($cursoLabel);
        $this->dibujarEncabezadoTabla();

        if ($alumnos->isEmpty()) {
            $this->formatoDibujarMensajeVacio($alumnos);

            return;
        }

        $numero = 0;
        foreach ($alumnos as $alumno) {
            $numero++;
            if ($this->GetY() + self::FORMATO_ALTURA_FILA > $this->formatoYMax) {
                $this->formatoNuevaPagina();
                $this->formatoDibujarEncabezadoInstitucional();
                $this->formatoDibujarTituloDocumento('Listado con cuadriculado');
                $this->formatoDibujarLineaCurso($cursoLabel);
                $this->dibujarEncabezadoTabla();
            }
            $this->dibujarFilaAlumno($numero, $alumno);
        }
    }

    private function dibujarEncabezadoTabla(): void
    {
        $y = $this->GetY();
        $x = self::FORMATO_MARGEN_IZQ;
        $altura = self::FORMATO_ALTURA_FILA;

        $this->formatoDibujarCelda($x, $y, self::FORMATO_ANCHO_NUM, $altura, 'Nº', true, false, 'C');
        $x += self::FORMATO_ANCHO_NUM;
        $this->formatoDibujarCelda($x, $y, self::FORMATO_ANCHO_NOMBRE, $altura, 'Apellido y Nombre', true, false, 'L');
        $x += self::FORMATO_ANCHO_NOMBRE;

        $anchoCuadro = $this->anchoCuadro();
        for ($i = 1; $i <= self::CANTIDAD_CUADROS; $i++) {
            $this->formatoDibujarCelda($x, $y, $anchoCuadro, $altura, '', true);
            $x += $anchoCuadro;
        }

        $this->SetXY(self::FORMATO_MARGEN_IZQ, $y + $altura);
    }

    private function dibujarFilaAlumno(int $numero, object $alumno): void
    {
        $y = $this->GetY();
        $x = self::FORMATO_MARGEN_IZQ;
        $altura = self::FORMATO_ALTURA_FILA;

        $this->formatoDibujarCelda($x, $y, self::FORMATO_ANCHO_NUM, $altura, (string) $numero, false, false, 'C');
        $x += self::FORMATO_ANCHO_NUM;
        $this->formatoDibujarCelda($x, $y, self::FORMATO_ANCHO_NOMBRE, $altura, $this->formatoNombreAlumno($alumno), false, false, 'L');
        $x += self::FORMATO_ANCHO_NOMBRE;

        $anchoCuadro = $this->anchoCuadro();
        for ($i = 0; $i < self::CANTIDAD_CUADROS; $i++) {
            $this->formatoDibujarCelda($x, $y, $anchoCuadro, $altura, '', false);
            $x += $anchoCuadro;
        }

        $this->SetXY(self::FORMATO_MARGEN_IZQ, $y + $altura);
    }

    private function anchoCuadro(): float
    {
        $resto = self::FORMATO_ANCHO_UTIL - self::FORMATO_ANCHO_NUM - self::FORMATO_ANCHO_NOMBRE;

        return round($resto / self::CANTIDAD_CUADROS, 3);
    }
}
