<?php

namespace App\Support\Listados;

use Illuminate\Support\Collection;
use TCPDF;

/**
 * Listado para registro de firmas — TCPDF A4 vertical.
 * Columnas: Nº, Estudiantes, madre/padre (4 franjas), Firma, Aclaración.
 * Adaptación del PDF FPDF legacy (fila de 20 mm por estudiante).
 */
final class ListadoEstudiantesFormatoRegistroFirmasTcpdf extends TCPDF
{
    use ListadoEstudiantesFormatoTcpdfComun;

    private const ANCHO_NUM = 8.0;

    private const ANCHO_NOMBRE = 52.0;

    private const ANCHO_FAMILIA = 52.0;

    private const ANCHO_FIRMA = 38.0;

    private const ANCHO_ACLARACION = 44.0;

    private const ALTURA_FILA = 20.0;

    private const ALTURA_SUBFILA = 5.0;

    private const TITULO = 'Listado para registro de firmas';

    private bool $primeraPaginaDocumento = true;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->formatoInicializarTcpdf($datos, self::TITULO);
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
            $pdf->formatoDibujarTituloDocumento(self::TITULO);
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

        $this->dibujarCabeceraPagina($cursoLabel);

        if ($alumnos->isEmpty()) {
            $this->formatoDibujarMensajeVacio($alumnos);

            return;
        }

        $numero = 0;
        foreach ($alumnos as $alumno) {
            $numero++;
            if ($this->GetY() + self::ALTURA_FILA > $this->formatoYMax) {
                $this->formatoNuevaPagina();
                $this->dibujarCabeceraPagina($cursoLabel);
            }
            $this->dibujarFilaAlumno($numero, $alumno);
        }
    }

    private function dibujarCabeceraPagina(string $cursoLabel): void
    {
        $this->formatoDibujarEncabezadoInstitucional();
        $this->formatoDibujarTituloDocumento(self::TITULO);
        $this->formatoDibujarLineaCurso($cursoLabel);
        $this->dibujarEncabezadoTabla();
    }

    private function dibujarEncabezadoTabla(): void
    {
        $y = $this->GetY();
        $x = self::FORMATO_MARGEN_IZQ;
        $altura = 7.0;

        $this->formatoDibujarCeldaUnaLinea($x, $y, self::ANCHO_NUM, $altura, 'Nº', true, false, 'C', 7.0);
        $x += self::ANCHO_NUM;
        $this->formatoDibujarCeldaUnaLinea($x, $y, self::ANCHO_NOMBRE, $altura, 'Estudiantes', true, false, 'C', 7.0);
        $x += self::ANCHO_NOMBRE;
        $this->formatoDibujarCeldaUnaLinea($x, $y, self::ANCHO_FAMILIA, $altura, '', true, false, 'L', 7.0);
        $x += self::ANCHO_FAMILIA;
        $this->formatoDibujarCeldaUnaLinea($x, $y, self::ANCHO_FIRMA, $altura, 'Firma', true, false, 'C', 7.0);
        $x += self::ANCHO_FIRMA;
        $this->formatoDibujarCeldaUnaLinea($x, $y, self::ANCHO_ACLARACION, $altura, 'Aclaración', true, false, 'C', 7.0);

        $this->SetXY(self::FORMATO_MARGEN_IZQ, $y + $altura);
    }

    private function dibujarFilaAlumno(int $numero, object $alumno): void
    {
        $y = $this->GetY();
        $x = self::FORMATO_MARGEN_IZQ;
        $altura = self::ALTURA_FILA;

        $this->formatoDibujarCeldaUnaLinea($x, $y, self::ANCHO_NUM, $altura, (string) $numero, false, false, 'C', 8.0);
        $x += self::ANCHO_NUM;
        $this->formatoDibujarCeldaUnaLinea(
            $x,
            $y,
            self::ANCHO_NOMBRE,
            $altura,
            $this->formatoNombreAlumno($alumno),
            false,
            false,
            'L',
            8.0,
        );
        $x += self::ANCHO_NOMBRE;

        $this->dibujarCeldasFamilia($x, $y, $alumno);
        $x += self::ANCHO_FAMILIA;

        $this->formatoDibujarCeldaUnaLinea($x, $y, self::ANCHO_FIRMA, $altura, '', false, false, 'L', 8.0);
        $x += self::ANCHO_FIRMA;
        $this->formatoDibujarCeldaUnaLinea($x, $y, self::ANCHO_ACLARACION, $altura, '', false, false, 'L', 8.0);

        $this->SetXY(self::FORMATO_MARGEN_IZQ, $y + $altura);
    }

    private function dibujarCeldasFamilia(float $x, float $y, object $alumno): void
    {
        $madre = trim((string) ($alumno->nombremad ?? ''));
        $padre = trim((string) ($alumno->nombrepad ?? ''));
        $sub = self::ALTURA_SUBFILA;

        $lineas = [
            $madre,
            $padre,
            '',
            '',
        ];

        foreach ($lineas as $i => $texto) {
            $this->formatoDibujarCeldaUnaLinea(
                $x,
                $y + ($i * $sub),
                self::ANCHO_FAMILIA,
                $sub,
                $texto,
                false,
                false,
                'L',
                6.0,
            );
        }
    }
}
