<?php

namespace App\Support\Listados;

use Illuminate\Support\Collection;
use TCPDF;

/**
 * Listado con calendario mensual — TCPDF A4 vertical.
 * Columnas: Nº, Apellido y Nombre, días 1..último del mes (fines de semana en gris).
 */
final class ListadoEstudiantesFormatoCalendarioTcpdf extends TCPDF
{
    use ListadoEstudiantesFormatoTcpdfComun;

    private const FORMATO_ANCHO_NOMBRE_CALENDARIO = 52.0;

    private int $mes = 0;

    private int $ano = 0;

    /** @var list<array{dia: int, esFinDeSemana: bool}> */
    private array $diasMes = [];

    private float $anchoDia = 4.0;

    private bool $primeraPaginaDocumento = true;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->formatoInicializarTcpdf($datos, 'Listado con calendario');
        $this->formatoFuentesListadoAmpliadas = true;

        $this->mes = ListadoEstudiantesFormatoMes::normalizarMes($datos['mes'] ?? 0);
        $this->ano = ListadoEstudiantesFormatoMes::normalizarAno($datos['ano'] ?? 0);
        $this->diasMes = ListadoEstudiantesFormatoMes::diasDelMes($this->ano, $this->mes);
        $this->anchoDia = $this->calcularAnchoDia();
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
            $pdf->formatoDibujarTituloDocumento('Listado con calendario');
            $pdf->formatoDibujarLineaCurso('—', $pdf->etiquetaMesAno());
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
        $detalleMes = $this->etiquetaMesAno();

        $this->formatoDibujarEncabezadoInstitucional();
        $this->formatoDibujarTituloDocumento('Listado con calendario');
        $this->formatoDibujarLineaCurso($cursoLabel, $detalleMes);
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
                $this->formatoDibujarTituloDocumento('Listado con calendario');
                $this->formatoDibujarLineaCurso($cursoLabel, $detalleMes);
                $this->dibujarEncabezadoTabla();
            }
            $this->dibujarFilaAlumno($numero, $alumno);
        }
    }

    private function etiquetaMesAno(): string
    {
        $nombreMes = ListadoEstudiantesFormatoMes::nombreMes($this->mes);
        if ($nombreMes === '' || $this->ano < 1) {
            return '';
        }

        return 'Mes: '.$nombreMes.' '.$this->ano;
    }

    private function dibujarEncabezadoTabla(): void
    {
        $y = $this->GetY();
        $x = self::FORMATO_MARGEN_IZQ;
        $altura = self::FORMATO_ALTURA_FILA;

        $this->formatoDibujarCelda($x, $y, self::FORMATO_ANCHO_NUM, $altura, 'Nº', true, false, 'C');
        $x += self::FORMATO_ANCHO_NUM;
        $this->formatoDibujarCelda($x, $y, self::FORMATO_ANCHO_NOMBRE_CALENDARIO, $altura, 'Apellido y Nombre', true, false, 'L');
        $x += self::FORMATO_ANCHO_NOMBRE_CALENDARIO;

        foreach ($this->diasMes as $diaInfo) {
            $dia = (int) $diaInfo['dia'];
            $this->formatoDibujarCeldaUnaLinea(
                $x,
                $y,
                $this->anchoDia,
                $altura,
                (string) $dia,
                true,
                (bool) $diaInfo['esFinDeSemana'],
                'C',
                $this->formatoTamanoFuenteParaAnchoCelda($this->anchoDia, $dia >= 10),
            );
            $x += $this->anchoDia;
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
        $this->formatoDibujarCelda($x, $y, self::FORMATO_ANCHO_NOMBRE_CALENDARIO, $altura, $this->formatoNombreAlumno($alumno), false, false, 'L', 7.0);
        $x += self::FORMATO_ANCHO_NOMBRE_CALENDARIO;

        foreach ($this->diasMes as $diaInfo) {
            $this->formatoDibujarCeldaUnaLinea(
                $x,
                $y,
                $this->anchoDia,
                $altura,
                '',
                false,
                (bool) $diaInfo['esFinDeSemana'],
            );
            $x += $this->anchoDia;
        }

        $this->SetXY(self::FORMATO_MARGEN_IZQ, $y + $altura);
    }

    private function calcularAnchoDia(): float
    {
        $cantidad = max(count($this->diasMes), 1);
        $resto = self::FORMATO_ANCHO_UTIL - self::FORMATO_ANCHO_NUM - self::FORMATO_ANCHO_NOMBRE_CALENDARIO;

        return round($resto / $cantidad, 3);
    }
}
