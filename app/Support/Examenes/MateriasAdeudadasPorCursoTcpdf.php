<?php

namespace App\Support\Examenes;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Listado de estudiantes con espacios curriculares adeudados, por curso (A4 vertical).
 * Maquetación alineada al PDF legacy ScriptCase / FPDF.
 */
final class MateriasAdeudadasPorCursoTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const MARGEN_DER = 20.0;

    private const MARGEN_SUP = 10.0;

    private const MARGEN_INF = 10.0;

    private const ANCHO_UTIL = 170.0;

    private const ALTURA_CABECERA = 22.0;

    private const ANCHO_MATERIA = 90.0;

    private const ANCHO_CURSO = 35.0;

    private const ANCHO_ANO = 20.0;

    private const ANCHO_COND = 25.0;

    private const ALTURA_FILA = 5.0;

    /** @var array{insti: string} */
    private array $meta;

    /**
     * @param  array{insti: string}  $meta
     */
    private function __construct(array $meta)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->meta = $meta;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Materias adeudadas por curso');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(true, self::MARGEN_INF);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
        $this->SetDisplayMode('real');
    }

    /**
     * @param  array{
     *     cursoLabel: string,
     *     estudiantes: list<array{
     *         apellido: string,
     *         nombre: string,
     *         adeudas: list<array{
     *             materia: string,
     *             curso: string,
     *             ano: int|string,
     *             condicion: string
     *         }>
     *     }>
     * }  $datos
     * @param  array{insti?: string}  $header
     */
    public static function generar(array $datos, array $header): self
    {
        return self::generarLote([$datos], $header);
    }

    /**
     * @param  list<array{
     *     cursoLabel: string,
     *     estudiantes: list<array{
     *         apellido: string,
     *         nombre: string,
     *         adeudas: list<array{
     *             materia: string,
     *             curso: string,
     *             ano: int|string,
     *             condicion: string
     *         }>
     *     }>
     * }>  $hojas
     * @param  array{insti?: string}  $header
     */
    public static function generarLote(array $hojas, array $header): self
    {
        $insti = trim((string) ($header['insti'] ?? ''));
        if ($insti === '') {
            $insti = 'Institución educativa';
        }

        $pdf = new self(['insti' => mb_strtoupper($insti, 'UTF-8')]);

        foreach ($hojas as $datos) {
            $pdf->AddPage();
            $pdf->dibujarListado($datos);
        }

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

    /**
     * @param  array{
     *     cursoLabel: string,
     *     estudiantes: list<array{
     *         apellido: string,
     *         nombre: string,
     *         adeudas: list<array{
     *             materia: string,
     *             curso: string,
     *             ano: int|string,
     *             condicion: string
     *         }>
     *     }>
     * }  $datos
     */
    private function dibujarListado(array $datos): void
    {
        $this->dibujarEncabezado(trim((string) ($datos['cursoLabel'] ?? '')));

        $estudiantes = $datos['estudiantes'] ?? [];
        if ($estudiantes === []) {
            TcpdfFuenteArial::aplicar($this, '', 8);
            $this->SetXY(self::MARGEN_IZQ, $this->GetY() + 4);
            $this->Cell(self::ANCHO_UTIL, 5, 'No hay estudiantes con materias adeudadas en este curso.', 0, 1, 'L');

            return;
        }

        foreach ($estudiantes as $est) {
            $apellido = trim((string) ($est['apellido'] ?? ''));
            $nombre = trim((string) ($est['nombre'] ?? ''));
            $nombreCompleto = trim($apellido.' '.$nombre);

            $this->Ln(self::ALTURA_FILA);
            $this->asegurarEspacio(self::ALTURA_FILA * 2);

            TcpdfFuenteArial::aplicar($this, 'B', 7);
            $this->SetX(self::MARGEN_IZQ);
            $this->Cell(self::ANCHO_UTIL, self::ALTURA_FILA, $nombreCompleto, 0, 1, 'L');

            TcpdfFuenteArial::aplicar($this, '', 7);
            foreach ($est['adeudas'] as $fila) {
                $this->asegurarEspacio(self::ALTURA_FILA);
                $this->SetX(self::MARGEN_IZQ);
                $this->Cell(self::ANCHO_MATERIA, self::ALTURA_FILA, (string) ($fila['materia'] ?? ''), 0, 0, 'L');
                $this->Cell(self::ANCHO_CURSO, self::ALTURA_FILA, (string) ($fila['curso'] ?? ''), 0, 0, 'L');
                $this->Cell(self::ANCHO_ANO, self::ALTURA_FILA, (string) ($fila['ano'] ?? ''), 0, 0, 'L');
                $this->Cell(self::ANCHO_COND, self::ALTURA_FILA, (string) ($fila['condicion'] ?? ''), 0, 1, 'L');
            }
        }
    }

    private function dibujarEncabezado(string $cursoLabel): void
    {
        $x = self::MARGEN_IZQ;
        $y = self::MARGEN_SUP;
        $w = self::ANCHO_UTIL;

        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->SetXY(140, 5);
        $this->Cell(40, 3, now()->format('d/m/Y H:i'), 0, 0, 'R');

        $this->SetDrawColor(80, 80, 80);
        $this->Rect($x, $y, $w, self::ALTURA_CABECERA);

        $this->SetXY($x, $y);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell($w, 7, $this->meta['insti'], 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell($w, 5, 'LISTADO DE ESTUDIANTES CON ESPACIOS CURRICULARES ADEUDADOS', 0, 2, 'C');
        $this->Cell($w, 5, $cursoLabel !== '' ? $cursoLabel : 'Curso', 0, 2, 'C');

        $this->SetY($y + self::ALTURA_CABECERA + 2);
    }

    private function asegurarEspacio(float $alturaNecesaria): void
    {
        $limite = $this->getPageHeight() - self::MARGEN_INF;
        if ($this->GetY() + $alturaNecesaria > $limite) {
            $this->AddPage();
            $this->SetY(self::MARGEN_SUP);
        }
    }
}
