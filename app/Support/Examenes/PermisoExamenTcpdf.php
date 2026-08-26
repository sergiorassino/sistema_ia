<?php

namespace App\Support\Examenes;

use Illuminate\Support\Facades\DB;
use TCPDF;

/**
 * Permiso de examen — generación incremental (TCPDF), una página por alumno.
 * Bucle de alumnos + consulta de materias por alumno (como el sistema anterior).
 */
final class PermisoExamenTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 15.0;

    private const ANCHO_UTIL = 180.0;

    private const FUENTE = 'dejavusans';

    /** @var array<float> */
    private const ANCHOS_COL = [7.0, 52.0, 26.0, 14.0, 11.0, 18.0, 20.0, 32.0];

    private const ALTURA_FILA = 5.5;

    private const ALTURA_ENCABEZADO = 6.0;

    /** @var array{instiNombre: string, etiquetaTurno: string, pieLugarFecha: string} */
    private array $meta;

    private int $paginasGeneradas = 0;

    /**
     * @param  array{instiNombre: string, etiquetaTurno: string, pieLugarFecha: string}  $meta
     */
    public function __construct(array $meta)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->meta = $meta;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Permiso de examen');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_IZQ, 12, 15);
    }

    /**
     * @param  list<int>  $idsAlumnos
     */
    public static function generar(int $idNivel, array $idsAlumnos, int $numeroPermisoInicio, array $meta): self
    {
        $pdf = new self($meta);
        $numero = max(1, $numeroPermisoInicio);

        foreach ($idsAlumnos as $idLegajos) {
            $alumno = DB::table('legajos')
                ->where('id', $idLegajos)
                ->first(['apellido', 'nombre', 'dni']);

            if ($alumno === null) {
                continue;
            }

            $materias = PermisoExamen::materiasAlumno($idNivel, $idLegajos);
            if ($materias === []) {
                continue;
            }

            $pdf->AddPage();
            $pdf->dibujarPermiso(
                $numero,
                mb_strtoupper(trim(((string) $alumno->apellido).' '.((string) $alumno->nombre)), 'UTF-8'),
                trim((string) ($alumno->dni ?? '')),
                PermisoExamen::filasTablaPermiso($materias),
            );
            $pdf->paginasGeneradas++;
            $numero++;
        }

        return $pdf;
    }

    public function paginasGeneradas(): int
    {
        return $this->paginasGeneradas;
    }

    /**
     * @param  list<array{nro: int, materia: string, curso: string, plan: string, condicion: string}>  $filas
     */
    private function dibujarPermiso(int $numero, string $nombreCompleto, string $dni, array $filas): void
    {
        $x0 = self::MARGEN_IZQ;
        $centro = $x0 + self::ANCHO_UTIL / 2;

        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);
        $this->SetTextColor(0, 0, 0);

        $this->SetY(12);
        $this->SetFont(self::FUENTE, '', 8.5);
        $titulo = 'Permiso de Examen';
        $tw = $this->GetStringWidth($titulo, self::FUENTE, '', 8.5) + 6;
        $this->Rect($centro - $tw / 2, 10.5, $tw, 5.8);
        $this->SetXY($centro - $tw / 2 + 3, 11.8);
        $this->Cell($tw - 6, 3.2, $titulo, 0, 1, 'C');

        $this->SetY(19);
        $this->SetFont(self::FUENTE, 'B', 10);
        $this->Cell(self::ANCHO_UTIL, 4.5, $this->meta['instiNombre'], 0, 1, 'C');

        $this->SetFont(self::FUENTE, '', 8);
        $this->Cell(self::ANCHO_UTIL * 0.55, 4, $this->meta['etiquetaTurno'], 0, 0, 'L');
        $this->SetFont(self::FUENTE, 'B', 8);
        $this->Cell(self::ANCHO_UTIL * 0.45, 4, 'Permiso Nº: '.$numero, 0, 1, 'R');

        $lineaAlumno = $nombreCompleto;
        if ($dni !== '') {
            $lineaAlumno .= ' — D.N.I.: '.$dni;
        }
        $introHtml = 'Conste por el presente que el Alumno/a: <b>'.$this->escapeHtml($lineaAlumno).'</b> '
            .'está habilitado para rendir las asignaturas correspondientes al año de estudio que indica a continuación, '
            .'lo que hizo en las fechas señaladas.';
        $this->SetFont(self::FUENTE, '', 8.5);
        $this->writeHTMLCell(self::ANCHO_UTIL, 0, $x0, $this->GetY() + 1.2, $introHtml, 0, 1, false, true, 'J');

        $yTabla = $this->GetY() + 1.2;
        $this->dibujarTabla($x0, $yTabla, $filas);

        $yPie = $yTabla + self::ALTURA_ENCABEZADO + (PermisoExamen::FILAS_POR_PERMISO * self::ALTURA_FILA) + 2.5;
        $this->SetXY($x0, $yPie);
        $this->SetFont(self::FUENTE, '', 8);
        $this->Cell(self::ANCHO_UTIL, 4, $this->meta['pieLugarFecha'], 0, 1, 'L');

        $yFirmas = $yPie + 14;
        $this->SetFont(self::FUENTE, '', 6);
        $this->SetXY($x0, $yFirmas);
        $this->Cell(self::ANCHO_UTIL / 2, 3.5, 'Sello', 0, 0, 'C');
        $this->Cell(self::ANCHO_UTIL / 2, 3.5, 'Firma manuscrita de la Secretaría', 0, 1, 'C');

        $this->SetFont(self::FUENTE, 'B', 7);
        $this->SetXY($x0, $yFirmas + 5);
        $this->Cell(self::ANCHO_UTIL, 3.5, 'Notas:', 0, 1, 'L');
        $this->SetFont(self::FUENTE, '', 7);
        $this->MultiCell(
            self::ANCHO_UTIL,
            3.5,
            "1) Para poder rendir examen, el alumno deberá presentar a la mesa examinadora, este permiso y sus documentos de identidad\n"
            .'2) Los exámenes deberán ser hechos con tinta.',
            0,
            'L',
        );
    }

    /**
     * @param  list<array{nro: int, materia: string, curso: string, plan: string, condicion: string}>  $filas
     */
    private function dibujarTabla(float $x, float $y, array $filas): void
    {
        $encabezados = [
            '',
            'Espacios Curriculares',
            'Curso',
            'Plan',
            'Con',
            'Fecha',
            'Calificación',
            'Firma Presidente Mesa',
        ];

        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);
        $this->SetTextColor(0, 0, 0);

        $this->SetXY($x, $y);
        $this->SetFont(self::FUENTE, '', 7);
        $this->filaTabla($encabezados, true);

        $this->SetFont(self::FUENTE, '', 6.5);
        foreach ($filas as $fila) {
            $this->filaTabla([
                (string) $fila['nro'],
                $fila['materia'],
                $fila['curso'],
                $fila['plan'],
                $fila['condicion'],
                '/ /',
                '',
                '',
            ], false);
        }

        $this->dibujarRejillaTabla($x, $y, 1 + count($filas));
    }

    /**
     * Dibuja la rejilla una sola vez (borde exterior + líneas H/V) para tonalidad uniforme.
     */
    private function dibujarRejillaTabla(float $x, float $y, int $cantidadFilas): void
    {
        $anchoTotal = array_sum(self::ANCHOS_COL);
        $altoTotal = self::ALTURA_ENCABEZADO + (($cantidadFilas - 1) * self::ALTURA_FILA);

        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);

        $this->Rect($x, $y, $anchoTotal, $altoTotal);

        $yLinea = $y + self::ALTURA_ENCABEZADO;
        for ($i = 1; $i < $cantidadFilas; $i++) {
            $this->Line($x, $yLinea, $x + $anchoTotal, $yLinea);
            $yLinea += self::ALTURA_FILA;
        }

        $xLinea = $x;
        $ultima = count(self::ANCHOS_COL) - 1;
        foreach (self::ANCHOS_COL as $i => $w) {
            if ($i === $ultima) {
                break;
            }
            $xLinea += $w;
            $this->Line($xLinea, $y, $xLinea, $y + $altoTotal);
        }
    }

    /**
     * @param  list<string>  $celdas
     */
    private function filaTabla(array $celdas, bool $esEncabezado): void
    {
        $altura = $esEncabezado ? self::ALTURA_ENCABEZADO : self::ALTURA_FILA;
        $x = self::MARGEN_IZQ;
        $y = $this->GetY();
        $aligns = ['C', 'L', 'C', 'C', 'C', 'C', 'C', 'C'];

        foreach ($celdas as $i => $texto) {
            $w = self::ANCHOS_COL[$i] ?? 10;
            if (! $esEncabezado && $i === 1 && mb_strlen($texto) > 42) {
                $texto = mb_substr($texto, 0, 41).'…';
            }
            $this->SetXY($x + 0.5, $y + ($altura - 3.5) / 2);
            $this->Cell($w - 1, 3.5, $texto, 0, 0, $aligns[$i] ?? 'L', false, '', 0, false, 'T', 'M');
            $x += $w;
        }

        $this->SetY($y + $altura);
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
