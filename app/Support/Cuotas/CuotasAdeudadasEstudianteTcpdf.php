<?php

namespace App\Support\Cuotas;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Listado de cuotas adeudadas del estudiante — TCPDF A4 apaisado.
 */
final class CuotasAdeudadasEstudianteTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 15.0;

    private const MARGEN_DER = 15.0;

    private const MARGEN_SUP = 12.0;

    private const ANCHO_TABLA = 267.0;

    private const ALTURA_ENCABEZADO = 28.0;

    private const ALTURA_FILA = 5.0;

    private const Y_ENCABEZADO_TABLA = 42.0;

    private const Y_PRIMERA_FILA = 48.0;

    private const Y_MAX_FILA = 190.0;

    private const TAMANO_FUENTE = 6;

    /** @var list<float> */
    private const ANCHOS_ADMIN = [
        10, 18, 38, 48, 16, 16, 16, 16, 18, 15, 15, 15, 26,
    ];

    /** @var list<string> */
    private const TITULOS_ADMIN = [
        'Año', 'Nivel', 'Curso', 'Cuota', 'Beca',
        'Venc 1', 'Venc 2', 'Venc. act.', 'Importe', 'Bonif.', 'Interés', 'Pagado', 'Saldo',
    ];

    /** @var list<float> */
    private const ANCHOS_AUTOGESTION = [
        67, 22, 32, 22, 10, 46, 16, 16, 18, 18,
    ];

    /** @var list<string> */
    private const TITULOS_AUTOGESTION = [
        'Apellido y nombre', 'Dni', 'Sala/Grado/Curso', 'Nivel', 'Año', 'Cuota',
        'Venc 1', 'Venc 2', 'Actualizada al', 'Saldo',
    ];

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
        $this->SetTitle('Cuotas adeudadas');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetLeftMargin(self::MARGEN_IZQ);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
        $this->SetDrawColor(0, 0, 0);
        $this->setCellHeightRatio(1.05);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage('L', 'A4');
        $pdf->dibujarEncabezadoInstitucional();
        $pdf->dibujarEncabezadoTabla();
        $yFinFilas = $pdf->dibujarFilas();
        $pdf->dibujarTotales($yFinFilas);

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

    private function esAutogestion(): bool
    {
        return ($this->datos['modo'] ?? '') === CuotasAdeudadasEstudianteDatos::MODO_AUTOGESTION;
    }

    /**
     * @return list<float>
     */
    private function anchosColumnas(): array
    {
        return $this->esAutogestion() ? self::ANCHOS_AUTOGESTION : self::ANCHOS_ADMIN;
    }

    /**
     * @return list<string>
     */
    private function titulosColumnas(): array
    {
        return $this->esAutogestion() ? self::TITULOS_AUTOGESTION : self::TITULOS_ADMIN;
    }

    private function dibujarEncabezadoInstitucional(): void
    {
        /** @var array<string, mixed> $header */
        $header = $this->datos['pdfHeader'] ?? [];
        $insti = trim((string) ($header['insti'] ?? ''));

        $x = self::MARGEN_IZQ;
        $y = self::MARGEN_SUP;

        $this->Rect($x, $y, self::ANCHO_TABLA, self::ALTURA_ENCABEZADO);

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(self::ANCHO_TABLA, 6, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell(self::ANCHO_TABLA, 5, 'CUOTAS ADEUDADAS', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 6.5);
        $lineaEstudiante = trim((string) ($this->datos['apellidoNombre'] ?? ''));
        $dni = trim((string) ($this->datos['dni'] ?? ''));
        if ($dni !== '') {
            $lineaEstudiante .= ' — DNI: '.$dni;
        }
        $this->Cell(self::ANCHO_TABLA, 4, $lineaEstudiante, 0, 2, 'C');

        $curso = trim((string) ($this->datos['curso'] ?? ''));
        $nivel = trim((string) ($this->datos['nivel'] ?? ''));
        $terlecAno = trim((string) ($this->datos['terlecAno'] ?? ''));
        $lineaContexto = $curso;
        if ($nivel !== '') {
            $lineaContexto .= ($lineaContexto !== '' ? ' · ' : '').$nivel;
        }
        if ($terlecAno !== '') {
            $lineaContexto .= ($lineaContexto !== '' ? ' · ' : '').'Ciclo activo: '.$terlecAno;
        }
        if ($lineaContexto !== '') {
            $this->Cell(self::ANCHO_TABLA, 4, $lineaContexto, 0, 2, 'C');
        }

        $beca = trim((string) ($this->datos['becaResumen'] ?? ''));
        if ($beca !== '') {
            $this->Cell(self::ANCHO_TABLA, 4, 'Beca: '.$beca, 0, 2, 'C');
        }

        $codigo = trim((string) ($this->datos['codigoPagoElectronico'] ?? ''));
        if ($codigo !== '') {
            TcpdfFuenteArial::aplicar($this, 'B', 6.5);
            $this->Cell(self::ANCHO_TABLA, 4, 'Código de pago electrónico: '.$codigo, 0, 2, 'C');
        }

        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->Cell(self::ANCHO_TABLA, 4, 'Impreso: '.(string) ($this->datos['fechaImpresion'] ?? ''), 0, 2, 'C');
    }

    private function dibujarEncabezadoTabla(): void
    {
        $this->dibujarFilaTabla(self::Y_ENCABEZADO_TABLA, $this->titulosColumnas(), true, false, false, true);
    }

    private function dibujarFilas(): float
    {
        /** @var list<array<string, string>> $filas */
        $filas = $this->datos['filas'] ?? [];
        $y = self::Y_PRIMERA_FILA;
        $indice = 0;

        foreach ($filas as $fila) {
            if ($y + self::ALTURA_FILA > self::Y_MAX_FILA) {
                $this->AddPage('L', 'A4');
                $this->dibujarEncabezadoTabla();
                $y = self::Y_PRIMERA_FILA;
                $indice = 0;
            }

            $valores = $this->esAutogestion()
                ? [
                    (string) ($fila['apellidoNombre'] ?? ''),
                    (string) ($fila['dni'] ?? ''),
                    (string) ($fila['curso'] ?? ''),
                    (string) ($fila['nivel'] ?? ''),
                    (string) ($fila['ano'] ?? ''),
                    (string) ($fila['cuota'] ?? ''),
                    (string) ($fila['venc1'] ?? ''),
                    (string) ($fila['venc2'] ?? ''),
                    (string) ($fila['vencAct'] ?? ''),
                    (string) ($fila['saldo'] ?? ''),
                ]
                : [
                    (string) ($fila['ano'] ?? ''),
                    (string) ($fila['nivel'] ?? ''),
                    (string) ($fila['curso'] ?? ''),
                    (string) ($fila['cuota'] ?? ''),
                    (string) ($fila['beca'] ?? ''),
                    (string) ($fila['venc1'] ?? ''),
                    (string) ($fila['venc2'] ?? ''),
                    (string) ($fila['vencAct'] ?? ''),
                    (string) ($fila['importe'] ?? ''),
                    (string) ($fila['bonificacion'] ?? ''),
                    (string) ($fila['interes'] ?? ''),
                    (string) ($fila['pagado'] ?? ''),
                    (string) ($fila['saldo'] ?? ''),
                ];

            $this->dibujarFilaTabla($y, $valores, false, $indice % 2 === 1);
            $y += self::ALTURA_FILA;
            $indice++;
        }

        return $y;
    }

    private function dibujarTotales(float $yFinFilas): void
    {
        /** @var list<array<string, string>> $filas */
        $filas = $this->datos['filas'] ?? [];
        if ($filas === []) {
            return;
        }

        /** @var array{neto?: string, conIntereses?: string} $totales */
        $totales = $this->datos['totales'] ?? [];

        $y = $yFinFilas + 0.5;
        if ($y + (self::ALTURA_FILA * 2) > self::Y_MAX_FILA) {
            $this->AddPage('L', 'A4');
            $this->dibujarEncabezadoTabla();
            $y = self::Y_PRIMERA_FILA;
        }

        $this->dibujarFilaTotal($y, 'TOTAL NETO', (string) ($totales['neto'] ?? ''), false);
        $y += self::ALTURA_FILA;
        $this->dibujarFilaTotal($y, 'TOTAL CON INTERESES AL DÍA DE HOY', (string) ($totales['conIntereses'] ?? ''), true);
    }

    private function dibujarFilaTotal(float $y, string $etiqueta, string $importe, bool $destacar): void
    {
        $anchos = $this->anchosColumnas();
        $indiceEtiqueta = $this->esAutogestion() ? 5 : 3;
        $indiceImporte = count($anchos) - 1;

        $valores = array_fill(0, count($anchos), '');
        $valores[$indiceEtiqueta] = $etiqueta;
        $valores[$indiceImporte] = $importe;

        $this->dibujarFilaTabla($y, $valores, true, false, $destacar, false, true);
    }

    /**
     * @param  list<string>  $valores
     */
    private function dibujarFilaTabla(
        float $y,
        array $valores,
        bool $negrita,
        bool $fondoSuave,
        bool $importeDestacado = false,
        bool $esEncabezadoTabla = false,
        bool $esFilaTotales = false,
    ): void {
        $anchos = $this->anchosColumnas();
        $x = self::MARGEN_IZQ;
        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, $negrita ? 'B' : '', self::TAMANO_FUENTE);

        if ($esEncabezadoTabla) {
            $this->SetFillColor(220, 220, 220);
        } elseif ($esFilaTotales) {
            $this->SetFillColor(232, 240, 242);
        } elseif ($fondoSuave) {
            $this->SetFillColor(225, 237, 240);
        } else {
            $this->SetFillColor(255, 255, 255);
        }

        $ultimoIndice = count($anchos) - 1;
        $indicesImporte = $this->esAutogestion()
            ? [$ultimoIndice]
            : range(8, $ultimoIndice);

        foreach ($anchos as $i => $ancho) {
            $texto = self::recortarTexto($valores[$i] ?? '', $ancho);
            $align = match (true) {
                in_array($i, $indicesImporte, true) => 'R',
                $i === 0 || ($this->esAutogestion() && $i === 4) || (! $this->esAutogestion() && $i === 0) => 'C',
                ! $this->esAutogestion() && $i >= 5 && $i <= 7 => 'C',
                $this->esAutogestion() && $i >= 6 && $i <= 8 => 'C',
                default => 'L',
            };

            if ($importeDestacado && $i === $ultimoIndice) {
                $this->SetTextColor(185, 28, 28);
            } else {
                $this->SetTextColor(0, 0, 0);
            }

            $this->Cell($ancho, self::ALTURA_FILA, $texto, 1, 0, $align, true);
        }

        $this->SetTextColor(0, 0, 0);
    }

    private static function recortarTexto(string $texto, float $anchoMm): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }

        $maxChars = max(4, (int) floor($anchoMm * 1.35));

        if (mb_strlen($texto) <= $maxChars) {
            return $texto;
        }

        return mb_substr($texto, 0, max(1, $maxChars - 1)).'…';
    }
}
