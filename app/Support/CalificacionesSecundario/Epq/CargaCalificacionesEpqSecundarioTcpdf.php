<?php

namespace App\Support\CalificacionesSecundario\Epq;

use App\Support\Pdf\TcpdfFuenteArial;
use Illuminate\Http\Response;
use TCPDF;

/**
 * Planilla de carga EPQ secundario — A4 vertical, layout alineado al formulario web.
 */
final class CargaCalificacionesEpqSecundarioTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 8.0;

    private const MARGEN_DER = 8.0;

    private const MARGEN_SUP = 10.0;

    private const ALTURA_ENC_INST = 24.0;

    private const ALTURA_META = 8.0;

    private const ALTURA_ENC_TABLA = 12.0;

    /** @var array<string, mixed> */
    private array $payload;

    /** @param array<string, mixed> $payload */
    private function __construct(array $payload)
    {
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8', false);
        $this->payload = $payload;
        $this->SetCreator('Sistema Escolar');
        $this->SetTitle('Planilla de calificaciones EPQ');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(true, 8);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
        $this->SetLineWidth(0.2);
    }

    /** @param array<string, mixed> $payload */
    public static function generar(array $payload): self
    {
        $pdf = new self($payload);
        $pdf->AddPage();
        $pdf->dibujarContenido();

        return $pdf;
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

    private function anchoUtil(): float
    {
        return $this->getPageWidth() - self::MARGEN_IZQ - self::MARGEN_DER;
    }

    /**
     * @return array{ord: float, ec: float, notas: list<float>}
     */
    private function anchosColumnas(): array
    {
        $total = $this->anchoUtil();
        $notas = array_fill(0, count(CalificacionesEpqSecundarioCatalogo::CAMPOS_NOTA), 11.5);
        $sumNotas = array_sum($notas);

        return [
            'ord' => 8.0,
            'ec' => max(48.0, $total - 8.0 - $sumNotas),
            'notas' => $notas,
        ];
    }

    private function dibujarContenido(): void
    {
        $y = self::MARGEN_SUP;
        $y = $this->dibujarEncabezadoInstitucional($y);
        $y = $this->dibujarMeta($y);
        $this->dibujarTabla($y);
    }

    private function dibujarEncabezadoInstitucional(float $y): float
    {
        $header = $this->payload['pdfHeader'] ?? [];
        $insti = (string) ($header['insti'] ?? '');
        $ano = (int) ($this->payload['ano'] ?? 0);

        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->Rect(self::MARGEN_IZQ, $y, $this->anchoUtil(), self::ALTURA_ENC_INST);

        TcpdfFuenteArial::aplicar($this, 'B', 11);
        $this->Cell($this->anchoUtil(), 7, $insti, 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 9);
        $this->Cell($this->anchoUtil(), 5, 'PLANILLA DE CALIFICACIONES — EPQ NIVEL SECUNDARIO'.($ano > 0 ? ' · '.$ano : ''), 0, 2, 'C');

        return $y + self::ALTURA_ENC_INST + 2;
    }

    private function dibujarMeta(float $y): float
    {
        $curso = (string) ($this->payload['cursoLabel'] ?? '');
        $materia = (string) ($this->payload['materiaLabel'] ?? '');
        $prof = (string) ($this->payload['profesoresLinea'] ?? '');

        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->Cell($this->anchoUtil() * 0.55, self::ALTURA_META, $materia.' — '.$curso, 0, 0, 'L');

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell($this->anchoUtil() * 0.45, self::ALTURA_META, $prof, 0, 1, 'R');

        return $y + self::ALTURA_META + 2;
    }

    private function dibujarTabla(float $yInicio): void
    {
        $anchos = $this->anchosColumnas();
        $wNota = $anchos['notas'][0];
        $x0 = self::MARGEN_IZQ;
        $y = $yInicio;
        $hSub = 6.0;
        $hSup = 6.0;

        TcpdfFuenteArial::aplicar($this, 'B', 7);

        $this->SetXY($x0, $y);
        $this->Cell($anchos['ord'], $hSup + $hSub, '#', 1, 0, 'C');
        $this->Cell($anchos['ec'], $hSup + $hSub, 'Estudiante', 1, 0, 'C');

        foreach (CalificacionesEpqSecundarioCatalogo::gruposCuatrimestre() as $grupo) {
            $this->Cell($wNota * count($grupo['cols']), $hSup, $grupo['label'], 1, 0, 'C');
        }

        foreach (CalificacionesEpqSecundarioCatalogo::columnasFinales() as $col) {
            $this->Cell($wNota, $hSup + $hSub, $col['label'], 1, 0, 'C');
        }

        $y += $hSup;
        $x = $x0 + $anchos['ord'] + $anchos['ec'];
        foreach (CalificacionesEpqSecundarioCatalogo::gruposCuatrimestre() as $grupo) {
            foreach ($grupo['cols'] as $col) {
                $this->SetXY($x, $y);
                $this->Cell($wNota, $hSub, $col['label'], 1, 0, 'C');
                $x += $wNota;
            }
        }

        $y += $hSub;
        $filas = $this->payload['filas'] ?? [];
        $hFila = max(4.8, min(6.5, ($this->getPageHeight() - 8 - $y) / max(count($filas), 1)));

        foreach ($filas as $fila) {
            if ($y + $hFila > $this->getPageHeight() - 8) {
                $this->AddPage();
                $y = self::MARGEN_SUP;
            }

            $x = $x0;
            $this->SetXY($x, $y);
            TcpdfFuenteArial::aplicar($this, '', 7);
            $this->SetTextColor(0, 0, 0);
            $this->Cell($anchos['ord'], $hFila, (string) ($fila['ord'] ?? ''), 1, 0, 'C');
            $x += $anchos['ord'];
            $this->SetXY($x, $y);
            $this->Cell($anchos['ec'], $hFila, (string) ($fila['alumno'] ?? ''), 1, 0, 'L');
            $x += $anchos['ec'];

            foreach (CalificacionesEpqSecundarioCatalogo::CAMPOS_NOTA as $campo) {
                $valor = (string) ($fila[$campo] ?? '');
                $notaNum = is_numeric($valor) && $valor !== '' ? (float) $valor : null;
                $this->SetXY($x, $y);
                if ($notaNum !== null && $notaNum < 6) {
                    TcpdfFuenteArial::aplicar($this, 'B', 7);
                    $this->SetTextColor(180, 0, 0);
                } else {
                    TcpdfFuenteArial::aplicar($this, '', 7);
                    $this->SetTextColor(0, 0, 0);
                }
                $this->Cell($wNota, $hFila, $valor, 1, 0, 'C');
                $x += $wNota;
            }

            $y += $hFila;
        }

        $this->SetTextColor(0, 0, 0);
    }
}
