<?php

namespace App\Support\Seguimiento;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Acta de un registro de seguimiento de gabinete (A4 vertical, TCPDF).
 *
 * Réplica del modelo FPDF ScriptCase: recuadro institucional, alumno/curso/legajo,
 * solicitud / motivo / asistentes / conclusión y tres firmas.
 */
final class GabineteActaTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const MARGEN_DER = 20.0;

    private const MARGEN_SUP = 20.0;

    private const MARGEN_INF = 12.0;

    private const ANCHO = 170.0;

    private const HEADER_Y = 20.0;

    private const HEADER_H = 22.0;

    private const ALUMNO_Y = 50.0;

    private const LINEA_TRAS_ALUMNO_Y = 60.0;

    private const FIRMA_W = 56.0;

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
    }

    /**
     * @param  array{
     *   nombreInstitucion: string,
     *   alumnoNombre: string,
     *   cursoLabel: string,
     *   nroLegajo: string,
     *   lineaLugarFecha: string,
     *   solipor: string,
     *   motivo: string,
     *   asistentes: string,
     *   conclusion: string
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
        $this->dibujarAlumnoCurso();
        $this->lineaEnY(self::LINEA_TRAS_ALUMNO_Y);

        $this->Ln(2);
        $this->fuente('', 8);
        $this->celda(5, (string) ($this->datos['lineaLugarFecha'] ?? ''), 'R');
        $this->Ln(3);

        $this->fuente('', 8);
        $this->bloqueCampo('Solicitud:   ', (string) ($this->datos['solipor'] ?? ''));
        $this->Ln(5);
        $this->bloqueCampo('Motivo:   ', (string) ($this->datos['motivo'] ?? ''));
        $this->Ln(5);
        $this->bloqueCampo('Asistentes:   ', (string) ($this->datos['asistentes'] ?? ''));
        $this->Ln(5);
        $this->bloqueCampo('Conclusión:   ', (string) ($this->datos['conclusion'] ?? ''));

        $this->dibujarFirmas();
    }

    private function dibujarEncabezado(): void
    {
        $x = self::MARGEN_IZQ;
        $y = self::HEADER_Y;
        $this->Rect($x, $y, self::ANCHO, self::HEADER_H);

        $this->SetXY($x, $y + 5);
        $this->fuente('B', 14);
        $insti = trim((string) ($this->datos['nombreInstitucion'] ?? ''));
        $this->celda(7, $insti !== '' ? $insti : 'Institución', 'C');

        $this->fuente('', 10);
        $this->celda(5, self::TITULO, 'C');
    }

    private function dibujarAlumnoCurso(): void
    {
        $nombre = trim((string) ($this->datos['alumnoNombre'] ?? ''));
        $curso = trim((string) ($this->datos['cursoLabel'] ?? ''));
        $legajo = trim((string) ($this->datos['nroLegajo'] ?? ''));
        $linea = $curso !== '' ? $nombre.' de '.$curso : $nombre;
        if ($legajo !== '') {
            $linea .= '                 Legajo: '.$legajo;
        }

        $this->SetXY(self::MARGEN_IZQ, self::ALUMNO_Y);
        $this->fuente('I', 8);
        $this->celda(5, $linea, 'L');
        $this->Ln(5);
    }

    private function bloqueCampo(string $etiqueta, string $valor): void
    {
        $texto = $etiqueta.(trim($valor) !== '' ? trim($valor) : '—');
        $this->SetX(self::MARGEN_IZQ);
        $this->MultiCell(self::ANCHO, 4, $texto, 0, 'L');
    }

    private function dibujarFirmas(): void
    {
        $this->Ln(20);
        $this->fuente('', 6);
        $this->SetX(self::MARGEN_IZQ);
        $etiquetas = [
            'Notificación del Alumno',
            'Notificación Padre/Madre/Responsable',
            'Directivo',
        ];
        foreach ($etiquetas as $etiqueta) {
            $this->Cell(self::FIRMA_W, 5, '.................................................................................', 0, 0, 'C');
        }
        $this->Ln(3);
        $this->SetX(self::MARGEN_IZQ);
        $this->fuente('', 6);
        foreach ($etiquetas as $etiqueta) {
            $this->Cell(self::FIRMA_W, 5, $etiqueta, 0, 0, 'C');
        }
        $this->Ln();
    }

    private function celda(float $alto, string $texto, string $align): void
    {
        $this->SetX(self::MARGEN_IZQ);
        $this->Cell(self::ANCHO, $alto, $texto, 0, 2, $align);
    }

    private function lineaEnY(float $y): void
    {
        $x = self::MARGEN_IZQ;
        $this->Line($x, $y, $x + self::ANCHO, $y);
        $this->SetY($y);
    }

    private function fuente(string $style, float $size): void
    {
        TcpdfFuenteArial::aplicar($this, $style, $size);
    }
}
