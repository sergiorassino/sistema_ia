<?php

namespace App\Support\Seguimiento;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Comunicado de seguimiento disciplinario — modelo IESS (A4 vertical, TCPDF).
 *
 * Replica textos, recuadro de encabezado y disposición de firmas del PDF ScriptCase
 * (solicitud + cupón para devolver). Los totales «Hasta la fecha…» usan
 * {@see ResumenComunicadoSancion} (`sanciontipo.enResumenComunicado`), no nombres fijos.
 */
final class SancionComunicadoIessTcpdf extends TCPDF
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

    private const TITULO = 'COMUNICADO DE SEGUIMIENTO DISCIPLINARIO';

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
     *   lineaLugarFecha: string,
     *   motivo: string,
     *   solicitadaPor: string,
     *   cantidad: int,
     *   tipoSancion: string,
     *   tipoSancionNombre: string,
     *   lineasResumenSinActual: list<array{tipo: string, total: int}>,
     *   lineasResumenConActual: list<array{tipo: string, total: int}>,
     *   actaHtml: string
     * }  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);
        $pdf->AddPage();
        $pdf->dibujarDocumento();

        $actaHtml = trim((string) ($datos['actaHtml'] ?? ''));
        if ($actaHtml !== '') {
            $pdf->AddPage();
            $pdf->dibujarActa($actaHtml);
        }

        return $pdf;
    }

    public function Header()
    {
    }

    public function Footer()
    {
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

    /**
     * Variante de textos según el nombre del tipo (no por id de BD).
     *
     * @return 'medida'|'entrevista'|'situacion_aulica'
     */
    public static function varianteTexto(string $tipoNombre): string
    {
        $n = self::normalizarNombre($tipoNombre);

        if (str_contains($n, 'entrevista')) {
            return 'entrevista';
        }
        if (str_contains($n, 'situacion aulica')) {
            return 'situacion_aulica';
        }

        return 'medida';
    }

    private function dibujarDocumento(): void
    {
        $variante = self::varianteTexto((string) ($this->datos['tipoSancionNombre'] ?? ''));
        $esMedida = $variante === 'medida';

        $this->dibujarEncabezado();
        $this->dibujarAlumnoCurso();
        $this->lineaEnY(self::LINEA_TRAS_ALUMNO_Y);

        $this->Ln(2);
        $this->fuente('', 8);
        $this->celda(5, (string) ($this->datos['lineaLugarFecha'] ?? ''), 'R');
        $this->Ln(3);

        $this->fuente('', 8);
        $this->celda(7, $this->textoIntro($variante), 'L');
        $this->Ln(5);

        $this->dibujarMotivo();
        $this->dibujarSolicitadaPor();
        $this->lineaBajoYActual(5);

        if ($esMedida) {
            $this->Ln(10);
            $this->dibujarResumen((array) ($this->datos['lineasResumenSinActual'] ?? []));
        }

        $this->lineaBajoYActual(5);

        if ($esMedida) {
            $this->Ln(10);
            $this->fuente('', 8);
            $cantidad = (int) ($this->datos['cantidad'] ?? 1);
            $tipo = trim((string) ($this->datos['tipoSancion'] ?? ''));
            $this->celda(5, 'De acuerdo con las causas invocadas y antecedentes, aplíquese al alumno: '.$cantidad.' '.$tipo, 'L');
        }

        $this->dibujarFirmas([
            'Firma del Solicitante',
            'Directivo',
        ]);

        $this->dibujarCupon($esMedida);
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
        $linea = $curso !== '' ? $nombre.' de '.$curso : $nombre;

        $this->SetXY(self::MARGEN_IZQ, self::ALUMNO_Y);
        $this->fuente('I', 8);
        $this->celda(5, $linea, 'L');
        $this->Ln(5);
    }

    private function dibujarMotivo(): void
    {
        $this->fuente('BI', 7);
        $motivo = trim((string) ($this->datos['motivo'] ?? ''));
        $this->SetX(self::MARGEN_IZQ);
        $this->MultiCell(self::ANCHO, 5, $motivo !== '' ? $motivo : '—', 0, 'L');
    }

    private function dibujarSolicitadaPor(): void
    {
        $this->Ln(5);
        $this->fuente('', 8);
        $quien = trim((string) ($this->datos['solicitadaPor'] ?? ''));
        $this->celda(5, 'Solicitada por:  '.$quien, 'L');
    }

    /**
     * @param  list<array{tipo?: string, total?: int}>  $lineas
     */
    private function dibujarResumen(array $lineas): void
    {
        $this->fuente('', 8);
        $this->celda(5, 'Hasta la fecha registra un total de:  ', 'L');
        $this->fuente('', 7);
        foreach ($lineas as $linea) {
            $total = (int) ($linea['total'] ?? 0);
            $tipo = trim((string) ($linea['tipo'] ?? ''));
            if ($tipo === '') {
                continue;
            }
            $this->celda(5, $total.' '.$tipo, 'L');
        }
    }

    private function dibujarCupon(bool $esMedida): void
    {
        $this->lineaBajoYActual(5);

        $this->Ln(8);
        $this->fuente('B', 14);
        $insti = trim((string) ($this->datos['nombreInstitucion'] ?? ''));
        $this->celda(7, $insti !== '' ? $insti : 'Institución', 'C');
        $this->Ln(3);

        $this->lineaEnY($this->GetY());

        $this->Ln(2);
        $this->fuente('', 8);
        $this->SetX(self::MARGEN_IZQ);
        $this->Cell(70, 5, (string) ($this->datos['lineaLugarFecha'] ?? ''), 0, 2, 'L');

        if ($esMedida) {
            $nombre = trim((string) ($this->datos['alumnoNombre'] ?? ''));
            $curso = trim((string) ($this->datos['cursoLabel'] ?? ''));
            $deCurso = $curso !== '' ? ' de '.$curso : '';
            $cantidad = (int) ($this->datos['cantidad'] ?? 1);
            $tipo = trim((string) ($this->datos['tipoSancion'] ?? ''));
            $parrafo = 'Me dirijo a Uds. para comunicarles que el/la alumno/a '.$nombre.$deCurso
                .' ha sido sancionado con '.$cantidad.' '.$tipo.' por: ';
            $this->SetX(self::MARGEN_IZQ);
            $this->MultiCell(self::ANCHO, 5, $parrafo, 0, 'L');
        }

        $this->dibujarMotivo();
        $this->dibujarSolicitadaPor();

        if ($esMedida) {
            $this->Ln(3);
            $this->dibujarResumen((array) ($this->datos['lineasResumenConActual'] ?? []));
        }

        $this->dibujarFirmas([
            'Notificación del Estudiante',
            'Notificación Padre/Madre/Responsable',
            'Directivo',
        ]);
    }

    /**
     * @param  list<string>  $etiquetas
     */
    private function dibujarFirmas(array $etiquetas): void
    {
        $this->Ln(15);
        $this->fuente('', 6);
        $this->SetX(self::MARGEN_IZQ);
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

    private function dibujarActa(string $actaHtml): void
    {
        $this->fuente('B', 14);
        $insti = trim((string) ($this->datos['nombreInstitucion'] ?? ''));
        $this->celda(7, $insti !== '' ? $insti : 'Institución', 'C');
        $this->fuente('B', 11);
        $this->celda(7, 'Acta', 'C');
        $this->Ln(3);

        $this->fuente('', 10);
        $this->writeHTMLCell(self::ANCHO, 0, self::MARGEN_IZQ, $this->GetY(), $actaHtml, 0, 1, false, true, 'L', true);
    }

    private function textoIntro(string $variante): string
    {
        return match ($variante) {
            'entrevista' => 'Entrevista:',
            'situacion_aulica' => 'Registro de Situación Áulica:',
            default => 'Solicito que al mencionado alumno se le aplique una medida disciplinaria:',
        };
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

    private function lineaBajoYActual(float $extra): void
    {
        $this->lineaEnY($this->GetY() + $extra);
    }

    private function fuente(string $style, float $size): void
    {
        if ($style === 'BI' || $style === 'IB') {
            TcpdfFuenteArial::aplicar($this, 'B', $size);

            return;
        }

        TcpdfFuenteArial::aplicar($this, $style, $size);
    }

    private static function normalizarNombre(string $tipoNombre): string
    {
        $n = mb_strtolower(trim($tipoNombre), 'UTF-8');
        $n = strtr($n, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ñ' => 'n',
        ]);

        return $n;
    }
}
