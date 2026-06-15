<?php

namespace App\Support\Cooperadora;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfLogoInstitucional;
use TCPDF;

/**
 * Listado de pagos del estudiante — cooperadora — TCPDF apaisado.
 */
final class PagosEstudianteCooperadoraTcpdf extends TCPDF
{
    private const MARGEN = 10.0;

    private const ANCHO_UTIL = 277.0;

    private const LOGO_ANCHO = 16.0;

    private const LOGO_ALTO = 16.0;

    private const ALTURA_FILA = 4.2;

    private const ALTURA_ENC = 5.0;

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
        $this->SetTitle('Pagos del estudiante — Cooperadora');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(true, self::MARGEN);
        $this->SetMargins(self::MARGEN, self::MARGEN, self::MARGEN);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage();
        $pdf->dibujar();

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

    private function dibujar(): void
    {
        $header = (array) ($this->datos['header'] ?? []);
        $y = self::MARGEN;

        TcpdfLogoInstitucional::dibujar(
            $this,
            self::MARGEN,
            $y,
            self::LOGO_ANCHO,
            self::LOGO_ALTO,
            $header['logo_file'] ?? null,
        );

        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->SetXY(self::MARGEN + self::LOGO_ANCHO + 4, $y);
        $this->Cell(self::ANCHO_UTIL - self::LOGO_ANCHO - 4, 3, (string) ($this->datos['fechaImpresion'] ?? ''), 0, 1, 'R');

        $this->SetX(self::MARGEN);
        TcpdfFuenteArial::aplicar($this, 'B', 11);
        $this->Cell(self::ANCHO_UTIL, 5, (string) ($header['nombre'] ?? 'Cooperadora').' — Pagos del estudiante', 0, 1, 'C');

        TcpdfFuenteArial::aplicar($this, 'B', 8.5);
        $apellidoNombre = trim((string) ($this->datos['apellidoNombre'] ?? ''));
        $dni = trim((string) ($this->datos['dni'] ?? ''));
        $this->Cell(self::ANCHO_UTIL, 4.5, $apellidoNombre.($dni !== '' ? ' — DNI: '.$dni : ''), 0, 1, 'C');

        $curso = trim((string) ($this->datos['curso'] ?? ''));
        $terlecAno = trim((string) ($this->datos['terlecAno'] ?? ''));
        if ($curso !== '' || $terlecAno !== '') {
            TcpdfFuenteArial::aplicar($this, '', 7.5);
            $linea = trim($curso.($terlecAno !== '' ? ' — Ciclo lectivo: '.$terlecAno : ''));
            $this->Cell(self::ANCHO_UTIL, 3.5, $linea, 0, 1, 'C');
        }

        $this->Ln(2);
        $this->dibujarTabla();
    }

    private function dibujarTabla(): void
    {
        $cols = self::anchosColumnas();
        $x = self::MARGEN;

        $this->dibujarEncabezado($x, $cols);

        /** @var list<array<string, string>> $filas */
        $filas = $this->datos['filas'] ?? [];

        TcpdfFuenteArial::aplicar($this, '', 5.5);
        if ($filas === []) {
            $this->Cell(self::ANCHO_UTIL, self::ALTURA_FILA, 'Sin pagos registrados para este estudiante.', 1, 1, 'C');

            return;
        }

        foreach ($filas as $fila) {
            if ($this->GetY() + self::ALTURA_FILA > $this->getPageHeight() - self::MARGEN - 10) {
                $this->AddPage();
                $this->dibujarEncabezado($x, $cols);
            }

            $this->SetX($x);
            $this->Cell($cols['fecha'], self::ALTURA_FILA, (string) ($fila['fecha'] ?? ''), 1, 0, 'C');
            $this->Cell($cols['recibo'], self::ALTURA_FILA, $this->t((string) ($fila['reciboNumero'] ?? ''), 10), 1, 0, 'R');
            $this->Cell($cols['rubro'], self::ALTURA_FILA, $this->t((string) ($fila['rubro'] ?? ''), 14), 1, 0, 'L');
            $this->Cell($cols['item'], self::ALTURA_FILA, $this->t((string) ($fila['item'] ?? ''), 14), 1, 0, 'L');
            $this->Cell($cols['concepto'], self::ALTURA_FILA, $this->t((string) ($fila['concepto'] ?? ''), 22), 1, 0, 'L');
            $this->Cell($cols['pagador'], self::ALTURA_FILA, $this->t(self::textoPagadorFila($fila), 18), 1, 0, 'L');
            $this->Cell($cols['medio'], self::ALTURA_FILA, $this->t((string) ($fila['medioPago'] ?? ''), 10), 1, 0, 'C');
            $this->Cell($cols['bruto'], self::ALTURA_FILA, (string) ($fila['importeBruto'] ?? ''), 1, 0, 'R');
            $this->Cell($cols['dto'], self::ALTURA_FILA, (string) ($fila['descuentoPct'] ?? ''), 1, 0, 'R');
            $this->Cell($cols['importe'], self::ALTURA_FILA, (string) ($fila['importe'] ?? ''), 1, 0, 'R');
            $this->Cell($cols['dest'], self::ALTURA_FILA, $this->t((string) ($fila['reciboDestinatarioTexto'] ?? ''), 26), 1, 0, 'L');
            $this->Cell($cols['estado'], self::ALTURA_FILA, $this->t((string) ($fila['reciboEmailEstadoEtiqueta'] ?? ''), 8), 1, 0, 'C');
            $this->Cell($cols['envio'], self::ALTURA_FILA, $this->t((string) ($fila['reciboEmailEnviadoAt'] ?? ''), 14), 1, 1, 'C');
        }

        $this->dibujarTotales($x, $cols);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private static function textoPagadorFila(array $fila): string
    {
        $nombre = trim((string) ($fila['pagadorNombre'] ?? ''));
        $vinculo = trim((string) ($fila['pagadorVinculo'] ?? ''));
        if ($nombre !== '' && $vinculo !== '') {
            return $nombre.' ('.$vinculo.')';
        }

        return $nombre;
    }

    /**
     * @param  array<string, float>  $cols
     */
    private function dibujarEncabezado(float $x, array $cols): void
    {
        $this->SetX($x);
        $this->SetFillColor(193, 215, 218);
        TcpdfFuenteArial::aplicar($this, 'B', 5.5);
        $this->Cell($cols['fecha'], self::ALTURA_ENC, 'Fecha', 1, 0, 'C', true);
        $this->Cell($cols['recibo'], self::ALTURA_ENC, 'Recibo', 1, 0, 'C', true);
        $this->Cell($cols['rubro'], self::ALTURA_ENC, 'Rubro', 1, 0, 'C', true);
        $this->Cell($cols['item'], self::ALTURA_ENC, 'Ítem', 1, 0, 'C', true);
        $this->Cell($cols['concepto'], self::ALTURA_ENC, 'Concepto', 1, 0, 'C', true);
        $this->Cell($cols['pagador'], self::ALTURA_ENC, 'Pagador', 1, 0, 'C', true);
        $this->Cell($cols['medio'], self::ALTURA_ENC, 'Medio pago', 1, 0, 'C', true);
        $this->Cell($cols['bruto'], self::ALTURA_ENC, 'Imp. bruto', 1, 0, 'C', true);
        $this->Cell($cols['dto'], self::ALTURA_ENC, 'Dto.', 1, 0, 'C', true);
        $this->Cell($cols['importe'], self::ALTURA_ENC, 'Importe', 1, 0, 'C', true);
        $this->Cell($cols['dest'], self::ALTURA_ENC, 'Recibo enviado a', 1, 0, 'C', true);
        $this->Cell($cols['estado'], self::ALTURA_ENC, 'Estado', 1, 0, 'C', true);
        $this->Cell($cols['envio'], self::ALTURA_ENC, 'Fecha envío', 1, 1, 'C', true);
        TcpdfFuenteArial::aplicar($this, '', 5.5);
    }

    /**
     * @param  array<string, float>  $cols
     */
    private function dibujarTotales(float $x, array $cols): void
    {
        /** @var array{importe?: string, cantidad?: int} $totales */
        $totales = $this->datos['totales'] ?? [];
        $wAntes = $cols['fecha'] + $cols['recibo'] + $cols['rubro'] + $cols['item'] + $cols['concepto']
            + $cols['pagador'] + $cols['medio'] + $cols['bruto'] + $cols['dto'];
        $wDespues = $cols['dest'] + $cols['estado'] + $cols['envio'];

        $this->SetX($x);
        $this->SetFillColor(235, 235, 235);
        TcpdfFuenteArial::aplicar($this, 'B', 5.5);
        $cantidad = (int) ($totales['cantidad'] ?? 0);
        $this->Cell($wAntes, self::ALTURA_FILA, 'TOTALES ('.$cantidad.' '.($cantidad === 1 ? 'pago' : 'pagos').')', 1, 0, 'R', true);
        $this->Cell($cols['importe'], self::ALTURA_FILA, (string) ($totales['importe'] ?? ''), 1, 0, 'R', true);
        $this->Cell($wDespues, self::ALTURA_FILA, '', 1, 1, 'R', true);
    }

    /**
     * @return array<string, float>
     */
    private static function anchosColumnas(): array
    {
        return [
            'fecha' => 16.0,
            'recibo' => 14.0,
            'rubro' => 22.0,
            'item' => 22.0,
            'concepto' => 34.0,
            'pagador' => 28.0,
            'medio' => 16.0,
            'bruto' => 18.0,
            'dto' => 12.0,
            'importe' => 18.0,
            'dest' => 36.0,
            'estado' => 14.0,
            'envio' => 27.0,
        ];
    }

    private function t(string $texto, int $max): string
    {
        $texto = trim($texto);
        if ($texto === '—' || mb_strlen($texto) <= $max) {
            return $texto;
        }

        return mb_substr($texto, 0, max(1, $max - 1)).'…';
    }
}
