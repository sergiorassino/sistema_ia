<?php

namespace App\Support\Seguimiento;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use TCPDF;

/**
 * Historial de seguimiento de gabinete del alumno (A4 vertical, TCPDF).
 *
 * Réplica del modelo FPDF ScriptCase: membrete, alumno/curso y un bloque por registro.
 */
final class GabineteHistorialTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const MARGEN_DER = 10.0;

    private const MARGEN_SUP = 10.0;

    private const MARGEN_INF = 12.0;

    private const ANCHO = 180.0;

    private const HEADER_Y = 10.0;

    /** Alto del recuadro: caben institución, título, alumno y curso sin cortar el texto. */
    private const HEADER_H = 28.0;

    private const CUERPO_Y = 42.0;

    private const TITULO = 'SEGUIMIENTO DE GABINETE';

    /** @var array<string, mixed> */
    private array $datos;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->datos = $datos;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle(self::TITULO);
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->tcpdflink = false;
        $this->SetAutoPageBreak(true, self::MARGEN_INF);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
        $this->SetDrawColor(0, 0, 0);
        $this->SetTextColor(0, 0, 0);
        $this->SetFillColor(255, 255, 255);
    }

    /**
     * @param  array{
     *   nombreInstitucion: string,
     *   alumnoNombre: string,
     *   cursoLabel: string,
     *   logo_file: ?string,
     *   impresoEn: string,
     *   colorSemaforo: int,
     *   registros: list<array{
     *     fecha: string,
     *     tipo: string,
     *     solipor: string,
     *     motivo: string,
     *     asistentes: string,
     *     conclusion: string
     *   }>
     * }  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage();
        $pdf->dibujarDocumento();

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

    private function dibujarDocumento(): void
    {
        $this->dibujarEncabezado();
        $this->SetXY(self::MARGEN_IZQ, self::CUERPO_Y);

        $registros = $this->datos['registros'] ?? [];
        if (! is_array($registros) || $registros === []) {
            $this->fuente('', 8);
            $this->SetX(self::MARGEN_IZQ);
            $this->Cell(self::ANCHO, 5, 'Sin registros de gabinete.', 0, 2, 'L');

            return;
        }

        foreach ($registros as $reg) {
            if (! is_array($reg)) {
                continue;
            }
            $this->dibujarRegistro($reg);
        }
    }

    private function dibujarEncabezado(): void
    {
        $x = self::MARGEN_IZQ;
        $y = self::HEADER_Y;

        $this->fuente('', 6);
        $this->SetXY(140, 5);
        $this->Cell(50, 3, (string) ($this->datos['impresoEn'] ?? ''), 0, 0, 'R');

        $this->Rect($x, $y, self::ANCHO, self::HEADER_H);

        $logo = $this->datos['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), 25, 11, 18, 18, '', '', '', false, 300);
        }

        $this->SetXY($x, $y + 3);
        $this->fuente('B', 10);
        $insti = trim((string) ($this->datos['nombreInstitucion'] ?? ''));
        $this->celda(6, $insti !== '' ? $insti : 'Institución', 'C');

        $this->fuente('', 8);
        $this->celda(5, self::TITULO, 'C');
        $this->celda(5, trim((string) ($this->datos['alumnoNombre'] ?? '')), 'C');
        $this->celda(5, trim((string) ($this->datos['cursoLabel'] ?? '')), 'C');
    }

    /**
     * @param  array<string, mixed>  $reg
     */
    private function dibujarRegistro(array $reg): void
    {
        if ($this->GetY() > 250) {
            $this->AddPage();
            $this->SetXY(self::MARGEN_IZQ, self::MARGEN_SUP);
        }

        $rgb = GabineteSemaforo::rgbFondoPdf((int) ($this->datos['colorSemaforo'] ?? 0));
        $this->SetFillColor($rgb[0], $rgb[1], $rgb[2]);

        $this->fuente('', 8);
        $this->SetX(self::MARGEN_IZQ);
        $this->Cell(self::ANCHO, 5, '  '.(string) ($reg['fecha'] ?? ''), 0, 2, 'L', true);
        $this->SetX(self::MARGEN_IZQ);
        $this->Cell(self::ANCHO, 5, '  '.(string) ($reg['tipo'] ?? ''), 0, 2, 'L', true);
        $this->SetFillColor(255, 255, 255);

        $this->bloqueCampo('Solicitud:   ', (string) ($reg['solipor'] ?? ''));
        $this->bloqueCampo('Motivo:   ', (string) ($reg['motivo'] ?? ''));
        $this->bloqueCampo('Asistentes:   ', (string) ($reg['asistentes'] ?? ''));
        $this->bloqueCampo('Conclusión:   ', (string) ($reg['conclusion'] ?? ''));

        $this->Ln(2);
        $y = $this->GetY();
        $this->SetDrawColor(120, 120, 120);
        $this->Line(self::MARGEN_IZQ, $y, self::MARGEN_IZQ + self::ANCHO, $y);
        $this->SetDrawColor(0, 0, 0);

        $this->SetXY(self::MARGEN_IZQ, $y + 4);
    }

    private function bloqueCampo(string $etiqueta, string $valor): void
    {
        $texto = $etiqueta.(trim($valor) !== '' ? trim($valor) : '—');
        $this->SetX(self::MARGEN_IZQ);
        $this->MultiCell(self::ANCHO, 4, $texto, 0, 'L');
    }

    private function celda(float $alto, string $texto, string $align): void
    {
        $this->SetX(self::MARGEN_IZQ);
        $this->Cell(self::ANCHO, $alto, $texto, 0, 2, $align);
    }

    private function fuente(string $style, float $size): void
    {
        TcpdfFuenteArial::aplicar($this, $style, $size);
    }
}
