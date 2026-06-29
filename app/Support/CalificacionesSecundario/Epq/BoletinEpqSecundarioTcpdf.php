<?php

namespace App\Support\CalificacionesSecundario\Epq;

use App\Support\Pdf\TcpdfFuenteArial;
use Illuminate\Http\Response;
use TCPDF;

/**
 * Informe de calificaciones EPQ secundario — A4 vertical, dos informes por hoja (centrados en cada mitad para corte).
 */
final class BoletinEpqSecundarioTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    /** Mitad de hoja A4 (corte horizontal): cada informe se centra en su mitad. */
    private const ALTURA_PAGINA = 297.0;

    private const MEDIA_HOJA = self::ALTURA_PAGINA / 2;

    /** Margen superior mínimo si el informe supera la mitad de hoja. */
    private const MARGEN_MIN_MITAD = 6.0;

    private const ANCHO_BLOQUE = 172.0;

    /** Ancho útil de la grilla de calificaciones (asignatura + notas). */
    private const ANCHO_TABLA = 170.0;

    private const ALTURA_ENCABEZADO = 22.0;

    private const ANCHO_ASIGNATURA = 60.0;

    private const ALTURA_ENC_TABLA = 8.0;

    private const ALTURA_SUBENC_TABLA = 4.0;

    private const ALTURA_FILA = 5.0;

    /**
     * Anchos de columnas de notas (mm), alineados al cuerpo legacy ScriptCase.
     * 5×12 (cuatrimestres) + 5×10 (2.º N.Cuat + finales).
     *
     * @var list<float>
     */
    private const ANCHOS_COL_NOTA = [12.0, 12.0, 12.0, 12.0, 12.0, 10.0, 10.0, 10.0, 10.0, 10.0];

    /** @var list<string> */
    private const ETIQUETAS_COL_NOTA = [
        '1º INF', '2º INF', 'N.Cuat', '3º INF', '4º INF', 'N.Cuat',
        'EV.INT', 'NOT.FIN', 'DIC', 'FEB',
    ];

    /** Pie con firmas (legacy ScriptCase). */
    private const ALTURA_PIE_FIRMAS = 20.0;

    private const FUENTE_PIE_ITEMS = 6;

    private const ALTURA_LINEA_PIE_ITEM = 2.5;

    private const PIE_ITEMS_OFFSET_Y = 2.5;

    private const ANCHO_COL_PIE_ITEMS = 68.0;

    /** Campos con color rojo si numérico y &lt; 6. */
    private const CAMPOS_ROJO = ['ic32', 'ic33', 'ic34'];

    /** @param array<string, mixed> $datos */
    private function __construct(private array $datos)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->SetCreator('Sistema Escolar');
        $this->SetTitle('Informe de calificaciones EPQ');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_IZQ, 10, self::MARGEN_IZQ);
        $this->SetLineWidth(0.2);
    }

    /** @param array<string, mixed> $datos */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage();
        $pdf->dibujarBoletin(self::yInicioEnMitad(0, $datos));

        return $pdf;
    }

    /**
     * @param  list<array<string, mixed>>  $hojas
     */
    public static function generarLote(array $hojas): self
    {
        abort_unless($hojas !== [], 404);

        $pdf = null;
        $indice = 0;

        foreach ($hojas as $datos) {
            $indice++;
            if ($pdf === null) {
                $pdf = new self($datos);
            } else {
                $pdf->datos = $datos;
            }

            $mitad = ($indice % 2 === 1) ? 0 : 1;
            if ($mitad === 0) {
                $pdf->AddPage();
            }

            $pdf->dibujarBoletin(self::yInicioEnMitad($mitad, $datos));
        }

        return $pdf;
    }

    /**
     * Y de inicio del informe dentro de la mitad superior (0) o inferior (1) de la hoja.
     * Centra verticalmente para que, al cortar el A4, ambos tengan el mismo margen superior.
     *
     * @param  array<string, mixed>  $datos
     */
    private static function yInicioEnMitad(int $mitad, array $datos): float
    {
        $altura = self::estimarAlturaBoletin($datos);
        $margenSup = (self::MEDIA_HOJA - $altura) / 2;
        if ($margenSup < self::MARGEN_MIN_MITAD) {
            $margenSup = self::MARGEN_MIN_MITAD;
        }

        return ($mitad * self::MEDIA_HOJA) + $margenSup;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private static function estimarAlturaBoletin(array $datos): float
    {
        $materias = is_array($datos['calificaciones'] ?? null) ? count($datos['calificaciones']) : 0;

        $altura = self::ALTURA_ENCABEZADO
            + 2
            + self::ALTURA_ENC_TABLA
            + ($materias * self::ALTURA_FILA)
            + 1
            + 2
            + self::ALTURA_PIE_FIRMAS;

        $proximas = is_array($datos['proximas_evaluaciones'] ?? null) ? $datos['proximas_evaluaciones'] : [];
        if ($proximas !== []) {
            $altura += 5 + (count($proximas) * 4) + 2;
        }

        return $altura;
    }

    public static function respuestaHttp(self $pdf, string $nombreArchivo): Response
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

    private function dibujarBoletin(float $y0): void
    {
        $d = $this->datos;
        $x = self::MARGEN_IZQ;

        $this->Rect($x, $y0, self::ANCHO_BLOQUE, self::ALTURA_ENCABEZADO);

        $membrete = $d['membrete_file'] ?? null;
        if (is_string($membrete) && is_file($membrete)) {
            $this->Image($membrete, $x + 3, $y0 + 1, 17, 17);
        }

        $xTexto = $x + 23;
        $this->SetXY($xTexto, $y0 + 2);
        TcpdfFuenteArial::aplicar($this, 'B', 12);
        $this->Cell(100, 4, (string) ($d['insti'] ?? ''), 0, 2, 'L');

        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $subtitulo = trim((string) ($d['subtituloInstitucion'] ?? ''));
        if ($subtitulo !== '') {
            $this->Cell(100, 4, $subtitulo, 0, 2, 'L');
        }

        TcpdfFuenteArial::aplicar($this, '', 8);
        $contacto = trim((string) ($d['lineaContacto'] ?? ''));
        if ($contacto !== '') {
            $this->Cell(100, 4, $contacto, 0, 2, 'L');
        }

        $this->SetXY($xTexto, $y0 + 2);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(140, 4, 'INFORME DE CALIFICACIONES', 0, 2, 'R');
        $this->Cell(140, 4, 'CICLO LECTIVO '.($d['anoLectivo'] ?? ''), 0, 2, 'R');

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $alumnoLinea = trim(((string) ($d['apellido'] ?? '')).' '.((string) ($d['nombre'] ?? '')));
        $dni = trim((string) ($d['dni'] ?? ''));
        $cursec = trim((string) ($d['cursec'] ?? ''));
        $this->SetXY($xTexto, $y0 + 15);
        $this->Cell(140, 5, $alumnoLinea.' - '.$dni.' - '.$cursec, 0, 2, 'C');

        $y = $y0 + self::ALTURA_ENCABEZADO + 2;
        $y = $this->dibujarEncabezadoTabla($x, $y);
        $y = $this->dibujarFilasCalificaciones($x, $y, $d);
        $y = $this->dibujarProximasEvaluaciones($x, $y, $d);
        $yPie = $y + 2;
        $this->dibujarPieFirmas($x, $yPie);
        $this->dibujarInasistenciasYSanciones($x, $yPie, $d);
    }

    private function dibujarEncabezadoTabla(float $x, float $y): float
    {
        $anchoCuat1 = self::anchoGrupoNotas(0, 3);
        $anchoCuat2 = self::anchoGrupoNotas(3, 3);
        $anchoFinales = self::anchoGrupoNotas(6, 4);

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, '', 6);

        $this->Cell(self::ANCHO_ASIGNATURA, self::ALTURA_ENC_TABLA, 'Asignatura', 1, 0, 'C');
        $this->Cell($anchoCuat1, self::ALTURA_SUBENC_TABLA, '1º Cuat', 1, 0, 'C');
        $this->Cell($anchoCuat2, self::ALTURA_SUBENC_TABLA, '2º Cuat', 1, 0, 'C');
        $this->Cell($anchoFinales, self::ALTURA_SUBENC_TABLA, '', 'LTR', 0, 'C');

        $xNotas = $x + self::ANCHO_ASIGNATURA;
        $this->SetXY($xNotas, $y + self::ALTURA_SUBENC_TABLA);
        foreach (self::ETIQUETAS_COL_NOTA as $i => $etiqueta) {
            $this->Cell(self::ANCHOS_COL_NOTA[$i], self::ALTURA_SUBENC_TABLA, $etiqueta, 1, 0, 'C');
        }

        return $y + self::ALTURA_ENC_TABLA;
    }

    private static function anchoGrupoNotas(int $offset, int $cantidad): float
    {
        $slice = array_slice(self::ANCHOS_COL_NOTA, $offset, $cantidad);

        return array_sum($slice);
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private function dibujarFilasCalificaciones(float $x, float $y, array $d): float
    {
        $califs = is_array($d['calificaciones'] ?? null) ? $d['calificaciones'] : [];
        $campos = CalificacionesEpqSecundarioCatalogo::CAMPOS_NOTA;

        foreach ($califs as $row) {
            $this->SetXY($x, $y);
            TcpdfFuenteArial::aplicar($this, '', 7);

            $materia = (string) ($row['materia'] ?? '');
            if (mb_strlen($materia) > 39) {
                $materia = mb_substr($materia, 0, 39);
            }
            $this->Cell(self::ANCHO_ASIGNATURA, self::ALTURA_FILA, $materia, 1, 0, 'L');

            foreach ($campos as $i => $campo) {
                $valor = (string) ($row[$campo] ?? '');
                $ancho = self::ANCHOS_COL_NOTA[$i] ?? 10.0;

                $this->dibujarCeldaNota($valor, $ancho, self::ALTURA_FILA, in_array($campo, self::CAMPOS_ROJO, true));
            }

            $y += self::ALTURA_FILA;
        }

        return $y + 1;
    }

    private function dibujarCeldaNota(string $valor, float $ancho, float $alto, bool $puedeRojo): void
    {
        if ($puedeRojo && $valor !== '' && is_numeric(str_replace(',', '.', $valor))) {
            $num = (float) str_replace(',', '.', $valor);
            if ($num < 6) {
                $this->SetTextColor(255, 0, 0);
            }
        }

        $this->Cell($ancho, $alto, $valor, 1, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private function dibujarProximasEvaluaciones(float $x, float $y, array $d): float
    {
        $lineas = is_array($d['proximas_evaluaciones'] ?? null) ? $d['proximas_evaluaciones'] : [];
        if ($lineas === []) {
            return $y;
        }

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(40, 5, 'PRÓXIMAS EVALUACIONES:', 0, 1, 'L');

        TcpdfFuenteArial::aplicar($this, '', 8);
        $texto = implode("\n", array_map('strval', $lineas));
        $this->SetX($x);
        $this->MultiCell(self::ANCHO_TABLA, 4, $texto, 0, 'L');

        return $this->GetY() + 2;
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private function dibujarInasistenciasYSanciones(float $x, float $yPie, array $d): void
    {
        /** @var list<object{etiqueta: string, fuente: string, total: float}> $items */
        $items = is_array($d['items_boletin'] ?? null) ? $d['items_boletin'] : [];
        if ($items === []) {
            return;
        }

        $this->SetXY($x, $yPie + self::PIE_ITEMS_OFFSET_Y);
        TcpdfFuenteArial::aplicar($this, '', self::FUENTE_PIE_ITEMS);

        foreach ($items as $i => $item) {
            $etiqueta = trim((string) ($item->etiqueta ?? ''));
            if ($etiqueta === '') {
                continue;
            }

            $fuente = (string) ($item->fuente ?? '');
            $total = (float) ($item->total ?? 0);
            $valor = $fuente === 'inasistencias'
                ? self::fmtNum($total)
                : (string) (int) round($total);

            $this->SetX($x);
            $this->Cell(
                self::ANCHO_COL_PIE_ITEMS,
                self::ALTURA_LINEA_PIE_ITEM,
                $etiqueta.': '.$valor,
                0,
                $i === count($items) - 1 ? 0 : 1,
                'L',
            );
        }
    }

    private function dibujarPieFirmas(float $x, float $yPie): void
    {
        $this->Rect($x, $yPie, 170, self::ALTURA_PIE_FIRMAS);

        $xFirmas = $x + 70;
        $this->SetXY($xFirmas, $yPie + 12);
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(60, 5, '..........................................', 0, 0, 'C');
        $this->Cell(40, 5, '..........................................', 0, 0, 'C');

        $this->SetXY($xFirmas, $yPie + 15);
        $this->Cell(60, 5, 'Firma Padre / Madre / Tutor', 0, 0, 'C');
        $this->Cell(40, 5, 'Firma Directivo', 0, 0, 'C');
    }

    private static function fmtNum(float|int|string $valor): string
    {
        if (is_string($valor)) {
            $valor = (float) str_replace(',', '.', $valor);
        }
        $v = (float) $valor;
        if (abs($v - round($v)) < 0.001) {
            return (string) (int) round($v);
        }

        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
