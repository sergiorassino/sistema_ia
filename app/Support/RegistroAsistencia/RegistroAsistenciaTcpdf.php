<?php

namespace App\Support\RegistroAsistencia;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Registro de Asistencia mensual (A4 vertical, TCPDF) — réplica del FPDF/ScriptCase legacy.
 */
final class RegistroAsistenciaTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 16.0;

    private const ANCHO_NRO = 4.0;

    private const ANCHO_NOMBRE = 34.0;

    private const ANCHO_DIA = 4.0;

    private const ALTO_FILA = 5.0;

    private const ALUMNOS_POR_PAGINA = 49;

    private const Y_PRIMERA_FILA = 41.0;

    /** @var array{insti: string, direccion: string, localidad: string, provincia?: string, cue: string, ee: string, logo_file: ?string} */
    private array $header;

    private string $nombreMes = '';

    private int $ano = 0;

    private string $nivelEtiqueta = '';

    /**
     * @param  array{insti: string, direccion: string, localidad: string, provincia?: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    private function __construct(array $header)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->header = $header;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Registro de Asistencia');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false, 10);
        $this->SetMargins(self::MARGEN_IZQ, 10, 10);
        $this->SetFillColor(232, 232, 232);
    }

    /**
     * @param  array<string, mixed>  $datos  Salida de {@see RegistroAsistenciaDatos::build()}
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos['header'] ?? schoolPdfHeaderData());
        $pdf->nombreMes = (string) ($datos['nombre_mes'] ?? '');
        $pdf->ano = (int) ($datos['ano'] ?? 0);
        $pdf->nivelEtiqueta = (string) ($datos['nivel_etiqueta'] ?? '');

        foreach ($datos['cursos'] ?? [] as $curso) {
            $pdf->dibujarCurso($curso);
        }

        return $pdf;
    }

    public static function respuestaHttp(self $pdf, string $nombreArchivo): \Illuminate\Http\Response
    {
        $bin = $pdf->Output($nombreArchivo, 'S');

        return response($bin, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombreArchivo.'"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $curso
     */
    private function dibujarCurso(array $curso): void
    {
        $this->AddPage('P', 'A4');
        $this->dibujarEncabezadoInstitucional((string) ($curso['cursec'] ?? ''));
        $this->dibujarEncabezadoColumnas();

        $alumnos = $curso['alumnos'] ?? [];
        $feriados = is_array($curso['feriados'] ?? null) ? $curso['feriados'] : [];
        $diasEnMes = (int) ($curso['dias_en_mes'] ?? 31);
        $cantAlu = count($alumnos);
        $fEnPagina = 0;
        $yUltima = self::Y_PRIMERA_FILA;

        foreach ($alumnos as $idx => $alu) {
            $nro = (int) ($alu['nro'] ?? ($idx + 1));
            if ($nro > 1 && (($nro - 1) % self::ALUMNOS_POR_PAGINA) === 0) {
                $this->AddPage('P', 'A4');
                $this->dibujarEncabezadoInstitucional((string) ($curso['cursec'] ?? '').' (cont.)');
                $this->dibujarEncabezadoColumnas();
                $fEnPagina = 0;
            }
            $fEnPagina++;
            $y = self::Y_PRIMERA_FILA + ($fEnPagina - 1) * self::ALTO_FILA;
            $yUltima = $y;
            $this->dibujarFilaAlumno($alu, $y);
        }

        if ($cantAlu === 0) {
            $yUltima = self::Y_PRIMERA_FILA - self::ALTO_FILA;
        }

        // Etiquetas de feriados: fuera del loop de celdas (Rotate no debe mover el cursor de la grilla).
        $this->dibujarEtiquetasFeriados($feriados, $diasEnMes);

        $yTotales = $yUltima + self::ALTO_FILA;
        $this->dibujarTotalesPorDia($curso['totales_dia'] ?? [], $diasEnMes, $yTotales);
        $this->dibujarCuadrosEstadistica($curso['estadisticas'] ?? [], $yTotales + 7);
    }

    private function dibujarEncabezadoInstitucional(string $cursec): void
    {
        $this->SetLeftMargin(self::MARGEN_IZQ);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->SetXY(140, 5);
        $this->Cell(20, 3, now()->format('d-m-y H:i'), 0, 2, 'C');

        $this->Rect(16, 10, 186, 23);

        $logo = $this->header['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            try {
                $this->Image($logo, 25, 11, 21, 21);
            } catch (\Throwable) {
                // sin logo
            }
        }

        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->SetXY(20, 13);
        $this->Cell(180, 7, (string) ($this->header['insti'] ?? ''), 0, 2, 'C');
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(180, 5, $cursec.' - '.$this->nombreMes.' - '.$this->ano, 0, 2, 'C');
        $this->Cell(180, 5, $this->nivelEtiqueta, 0, 2, 'C');
    }

    private function dibujarEncabezadoColumnas(): void
    {
        $this->SetXY(self::MARGEN_IZQ, 36);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(self::ANCHO_NRO, self::ALTO_FILA, '', 1, 0, 'C');
        $this->Cell(self::ANCHO_NOMBRE, self::ALTO_FILA, '', 1, 0, 'L');
        for ($d = 1; $d <= 31; $d++) {
            $this->Cell(self::ANCHO_DIA, self::ALTO_FILA, (string) $d, 1, 0, 'C');
        }
        TcpdfFuenteArial::aplicar($this, '', 4);
        $this->Cell(self::ANCHO_DIA, self::ALTO_FILA, 'Tot', 1, 0, 'C');
        $this->Cell(self::ANCHO_DIA, self::ALTO_FILA, 'Jus', 1, 0, 'C');
        $this->Cell(self::ANCHO_DIA, self::ALTO_FILA, 'Inj', 1, 0, 'C');
        $this->Cell(self::ANCHO_DIA, self::ALTO_FILA, 'E.F', 1, 0, 'C');
        $this->Cell(self::ANCHO_DIA, self::ALTO_FILA, 'Acu', 1, 0, 'C');
        $this->Cell(self::ANCHO_DIA, self::ALTO_FILA, 'AEF', 1, 1, 'C');
    }

    /**
     * @param  array<string, mixed>  $alu
     */
    private function dibujarFilaAlumno(array $alu, float $y): void
    {
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->Cell(self::ANCHO_NRO, self::ALTO_FILA, (string) ($alu['nro'] ?? ''), 1, 0, 'C');
        $this->Cell(self::ANCHO_NOMBRE, self::ALTO_FILA, (string) ($alu['apenom'] ?? ''), 1, 0, 'L');

        TcpdfFuenteArial::aplicar($this, '', 4);
        $celdas = $alu['celdas'] ?? [];
        for ($d = 1; $d <= 31; $d++) {
            $this->Cell(self::ANCHO_DIA, self::ALTO_FILA, (string) ($celdas[$d] ?? ''), 1, 0, 'C');
        }

        $this->Cell(self::ANCHO_DIA, self::ALTO_FILA, (string) ($alu['tot'] ?? ''), 1, 0, 'C');
        $this->Cell(self::ANCHO_DIA, self::ALTO_FILA, (string) ($alu['jus'] ?? ''), 1, 0, 'C');
        $this->Cell(self::ANCHO_DIA, self::ALTO_FILA, (string) ($alu['inj'] ?? ''), 1, 0, 'C');
        $this->Cell(self::ANCHO_DIA, self::ALTO_FILA, (string) ($alu['ef'] ?? ''), 1, 0, 'C');
        $this->Cell(self::ANCHO_DIA, self::ALTO_FILA, (string) ($alu['acu'] ?? ''), 1, 0, 'C');
        $this->Cell(self::ANCHO_DIA, self::ALTO_FILA, (string) ($alu['aef'] ?? ''), 1, 1, 'C');
    }

    /**
     * Texto vertical en cada columna de día con feriado (coordenada X fija por día).
     *
     * @param  array<string, string>  $feriados  Y-m-d => nombre
     */
    private function dibujarEtiquetasFeriados(array $feriados, int $diasEnMes): void
    {
        if ($feriados === [] || $diasEnMes < 1) {
            return;
        }

        /** @var array<int, string> $porDia */
        $porDia = [];
        foreach ($feriados as $fecha => $nombre) {
            $nombre = trim((string) $nombre);
            if ($nombre === '') {
                continue;
            }
            if (! preg_match('/^\d{4}-\d{2}-(\d{2})/', (string) $fecha, $m)) {
                continue;
            }
            $dia = (int) $m[1];
            if ($dia < 1 || $dia > $diasEnMes) {
                continue;
            }
            $porDia[$dia] = $nombre;
        }

        ksort($porDia, SORT_NUMERIC);

        foreach ($porDia as $dia => $nombre) {
            $xColIzq = self::MARGEN_IZQ
                + self::ANCHO_NRO
                + self::ANCHO_NOMBRE
                + (($dia - 1) * self::ANCHO_DIA);
            $xCentro = $xColIzq + (self::ANCHO_DIA / 2);
            $this->textoVerticalFeriado($xCentro, $nombre);
        }
    }

    /**
     * Texto vertical centrado en la columna (mismo patrón que planilla IPE / calificaciones).
     */
    private function textoVerticalFeriado(float $xCentro, string $texto): void
    {
        if ($texto === '') {
            return;
        }

        $x = $this->GetX();
        $y = $this->GetY();
        $yInicio = 43.0;

        TcpdfFuenteArial::aplicar($this, 'I', 6);
        $longitud = $this->GetStringWidth($texto);
        if ($longitud <= 0) {
            $this->SetXY($x, $y);

            return;
        }

        // Rotate(-90): el texto crece hacia abajo y se lee de arriba hacia abajo
        // (sin invertir el string). Centrado horizontal: −alto/2 (igual que con +90).
        $yAncla = $yInicio;
        $altoGlifoMm = (float) $this->getFontSize();

        $this->StartTransform();
        $this->Rotate(-90, $xCentro, $yAncla);
        $this->Text($xCentro, $yAncla - ($altoGlifoMm / 2), $texto);
        $this->StopTransform();

        $this->SetXY($x, $y);
    }

    /**
     * @param  array<int, string>  $totales
     */
    private function dibujarTotalesPorDia(array $totales, int $diasEnMes, float $y): void
    {
        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(self::ANCHO_NRO + self::ANCHO_NOMBRE, self::ALTO_FILA, 'Totales por Día:', 1, 0, 'C');
        for ($d = 1; $d <= $diasEnMes; $d++) {
            $this->Cell(self::ANCHO_DIA, self::ALTO_FILA, (string) ($totales[$d] ?? ''), 1, 0, 'C');
        }
        if ($diasEnMes < 31) {
            $this->Cell(self::ANCHO_DIA, self::ALTO_FILA, (string) ($totales[$diasEnMes + 1] ?? '/'), 1, 0, 'C');
        }
        $this->Ln();
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function dibujarCuadrosEstadistica(array $stats, float $yBase): void
    {
        $mostrar = (bool) ($stats['mostrar_valores'] ?? false);

        // Días hábiles (izquierda)
        $this->SetXY(self::MARGEN_IZQ, $yBase);
        TcpdfFuenteArial::aplicar($this, '', 7);
        $dh = $mostrar ? (string) ($stats['dias_habiles'] ?? '') : '';
        $this->Cell(40, self::ALTO_FILA, '      Cantidad de días Hábiles: '.$dh, 1, 0, 'L');

        // Entrados / salidos (centro)
        $this->SetLeftMargin(60);
        $this->SetXY(60, $yBase);
        $this->filaCuadro('', 'Varones', 'Mujeres', 'Total');
        $this->filaCuadro('Al día 1º del Mes:', $stats['al_dia_1'] ?? []);
        $this->filaCuadro('Entrados:', $stats['entrados'] ?? []);
        $this->filaCuadro('Salidos:', $stats['salidos'] ?? []);
        $this->filaCuadro('Quedan al último día:', $stats['quedan'] ?? []);

        // Cálculos (derecha)
        $this->SetLeftMargin(135);
        $this->SetXY(135, $yBase);
        $this->filaCuadro('', 'Varones', 'Mujeres', 'Total');
        $this->filaCuadro('Total Asistencia:', $stats['tot_asist'] ?? []);
        $this->filaCuadro('Total Inasistencia:', $stats['tot_inas'] ?? []);
        $this->filaCuadro('Asistencia Media:', $stats['asist_media'] ?? []);
        $this->filaCuadro('% de Asistencia:', $stats['pct_asist'] ?? []);

        $this->SetLeftMargin(self::MARGEN_IZQ);
    }

    /**
     * @param  array{v?: string, m?: string, t?: string}|string  $valores
     */
    private function filaCuadro(string $etiqueta, array|string $valores = [], string $m = '', string $t = ''): void
    {
        if (is_string($valores)) {
            // encabezado: etiqueta vacía + tres títulos
            $v = $valores;
            $this->Cell(30, self::ALTO_FILA, $etiqueta, 1, 0, 'C');
            $this->Cell(10, self::ALTO_FILA, $v, 1, 0, 'C');
            $this->Cell(10, self::ALTO_FILA, $m, 1, 0, 'C');
            $this->Cell(10, self::ALTO_FILA, $t, 1, 1, 'C');

            return;
        }

        $this->Cell(30, self::ALTO_FILA, $etiqueta, 1, 0, 'C');
        $this->Cell(10, self::ALTO_FILA, (string) ($valores['v'] ?? ''), 1, 0, 'C');
        $this->Cell(10, self::ALTO_FILA, (string) ($valores['m'] ?? ''), 1, 0, 'C');
        $this->Cell(10, self::ALTO_FILA, (string) ($valores['t'] ?? ''), 1, 1, 'C');
    }
}
