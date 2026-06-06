<?php

namespace App\Support\Cuotas;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Libro de aranceles — TCPDF A4 apaisado (réplica legacy FPDF).
 */
final class LibroArancelesTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const MARGEN_DER = 10.0;

    private const MARGEN_SUP = 10.0;

    private const ANCHO_BLOQUE = 267.0;

    private const ALTURA_ENCABEZADO = 23.0;

    private const ALTURA_FILA = 8.0;

    private const Y_TABLA = 35.0;

    /** Primera hoja de cada curso (legacy: 19 filas antes del salto). */
    private const ALUMNOS_PRIMERA_PAGINA = 19;

    /** Hojas siguientes del mismo curso (legacy: 20 filas). */
    private const ALUMNOS_PAGINA_SIGUIENTE = 20;

    /** @var list<int> */
    private const MESES_CUOTA = [3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

    /** @var list<string> */
    private const ETIQUETAS_MESES = [
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Setiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    /** @var array<string, mixed> */
    private array $datos;

    private int $numeroPagina;

    /** @var array<string, mixed>|null */
    private ?array $seccionActual = null;

    private int $indiceFilaPagina = 0;

    private int $limiteFilasPagina = self::ALUMNOS_PRIMERA_PAGINA;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8', false);
        $this->datos = $datos;
        $this->numeroPagina = (int) ($datos['paginaInicial'] ?? 1) - 1;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Libro de aranceles');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false, 10);
        $this->SetLeftMargin(self::MARGEN_IZQ);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
        $this->SetDrawColor(0, 0, 0);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);

        /** @var list<array<string, mixed>> $secciones */
        $secciones = $datos['secciones'] ?? [];
        foreach ($secciones as $seccion) {
            $pdf->renderSeccionCurso($seccion);
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
     * @param  array<string, mixed>  $seccion
     */
    private function renderSeccionCurso(array $seccion): void
    {
        $this->seccionActual = $seccion;
        $this->indiceFilaPagina = 0;
        $this->limiteFilasPagina = self::ALUMNOS_PRIMERA_PAGINA;

        $this->nuevaPaginaConEncabezado();

        /** @var list<array<string, mixed>> $alumnos */
        $alumnos = $seccion['alumnos'] ?? [];
        $nro = 0;

        foreach ($alumnos as $alumno) {
            $nro++;

            if ($this->indiceFilaPagina >= $this->limiteFilasPagina) {
                $this->indiceFilaPagina = 0;
                $this->limiteFilasPagina = self::ALUMNOS_PAGINA_SIGUIENTE;
                $this->nuevaPaginaConEncabezado();
            }

            $this->indiceFilaPagina++;
            $this->dibujarFilaAlumno($nro, $alumno, $this->indiceFilaPagina);
        }
    }

    private function nuevaPaginaConEncabezado(): void
    {
        $this->AddPage('L', 'A4');
        $this->numeroPagina++;
        $this->dibujarEncabezadoInstitucional();
        $this->dibujarEncabezadoTabla();
    }

    private function dibujarEncabezadoInstitucional(): void
    {
        /** @var array<string, mixed> $header */
        $header = $this->seccionActual['header'] ?? [];
        $ano = (int) ($this->datos['ano'] ?? now()->year);
        $cursoLinea = trim((string) ($this->seccionActual['cursoLinea'] ?? ''));

        $x = self::MARGEN_IZQ;
        $y = 10.0;

        $this->Rect($x, $y, self::ANCHO_BLOQUE, self::ALTURA_ENCABEZADO);

        $logo = $header['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $this->Image($logo, $x + 5, $y + 1, 21, 21, '', '', '', false, 300);
        }

        $insti = trim((string) ($header['insti'] ?? ''));
        $direccion = trim((string) ($header['direccion'] ?? ''));
        $localidad = trim((string) ($header['localidad'] ?? ''));
        $departamento = trim((string) ($header['departamento'] ?? ''));
        $provincia = trim((string) ($header['provincia'] ?? ''));
        $cuit = trim((string) ($header['cuit'] ?? ''));
        $ee = trim((string) ($header['ee'] ?? ''));

        $lineaDir = trim(implode(' ', array_filter([$direccion, $localidad, $departamento, $provincia])));
        $lineaIds = trim(
            ($cuit !== '' ? 'CUIT: '.$cuit : '').
            (($cuit !== '' && $ee !== '') ? ' - EE:  ' : ($ee !== '' ? 'EE:  ' : '')).
            ($ee !== '' ? $ee : ''),
        );
        if ($lineaIds !== '' && $lineaDir !== '') {
            $lineaDir .= '    '.$lineaIds;
        } elseif ($lineaIds !== '') {
            $lineaDir = $lineaIds;
        }

        $this->SetXY($x, $y + 3);
        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->Cell(self::ANCHO_BLOQUE, 6, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        if ($lineaDir !== '') {
            TcpdfFuenteArial::aplicar($this, '', 8);
            $this->Cell(self::ANCHO_BLOQUE, 4, $lineaDir, 0, 2, 'C');
        }

        if ($cursoLinea !== '') {
            TcpdfFuenteArial::aplicar($this, '', 8);
            $this->Cell(self::ANCHO_BLOQUE, 4, $cursoLinea, 0, 2, 'C');
        }

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell(self::ANCHO_BLOQUE, 5, 'LIBRO DE ARANCELES '.$ano, 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->SetXY($x, $y + self::ALTURA_ENCABEZADO - 3);
        $this->Cell(self::ANCHO_BLOQUE - 10, 3, 'Pag.Nº: '.$this->numeroPagina, 0, 0, 'R');
    }

    private function dibujarEncabezadoTabla(): void
    {
        $this->SetXY(self::MARGEN_IZQ, self::Y_TABLA);

        $this->Cell(7, 6, '', 1, 0, 'C');
        $this->Cell(60, 6, '', 1, 0, 'L');
        $this->Cell(13, 6, '% Beca', 1, 0, 'C');
        $this->Cell(17, 6, 'Matrícula', 1, 0, 'C');

        foreach (self::MESES_CUOTA as $mes) {
            $this->Cell(17, 6, self::ETIQUETAS_MESES[$mes] ?? (string) $mes, 1, 0, 'C');
        }
    }

    /**
     * @param  array<string, mixed>  $alumno
     */
    private function dibujarFilaAlumno(int $nro, array $alumno, int $filaEnPagina): void
    {
        $y = self::Y_TABLA + ($filaEnPagina * self::ALTURA_FILA);

        $this->SetXY(self::MARGEN_IZQ, $y);
        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Cell(7, self::ALTURA_FILA, (string) $nro, 1, 0, 'C');
        $this->Cell(60, self::ALTURA_FILA, (string) ($alumno['nombre'] ?? ''), 1, 0, 'L');
        $this->Cell(13, self::ALTURA_FILA, ((int) ($alumno['porcBeca'] ?? 0)).' %', 1, 0, 'C');

        /** @var array{pagado: float, nroComp: int} $matricula */
        $matricula = $alumno['matricula'] ?? ['pagado' => 0.0, 'nroComp' => 0];
        $this->dibujarCeldaPago(17, $matricula);

        /** @var array<int, array{pagado: float, nroComp: int}> $meses */
        $meses = $alumno['meses'] ?? [];
        foreach (self::MESES_CUOTA as $mes) {
            $this->dibujarCeldaPago(17, $meses[$mes] ?? ['pagado' => 0.0, 'nroComp' => 0]);
        }
    }

    /**
     * @param  array{pagado: float, nroComp: int}  $celda
     */
    private function dibujarCeldaPago(float $ancho, array $celda): void
    {
        $pagado = (float) ($celda['pagado'] ?? 0);
        $nroComp = (int) ($celda['nroComp'] ?? 0);

        $x = $this->GetX();
        $y = $this->GetY();

        TcpdfFuenteArial::aplicar($this, '', 5);
        if ($pagado > 0 && $nroComp > 0) {
            $this->Cell($ancho, 4, 'Nº Recibo: '.$nroComp, 1, 2, 'R');
        } else {
            $this->Cell($ancho, 4, '', 1, 2, 'R');
        }

        TcpdfFuenteArial::aplicar($this, '', 7);
        $this->SetXY($x, $y + 4);
        $this->Cell($ancho, 4, $this->formatearImporte($pagado), 1, 0, 'R');
        $this->SetXY($x + $ancho, $y);
    }

    private function formatearImporte(float $valor): string
    {
        if ($valor <= 0) {
            return '0.00';
        }

        return number_format($valor, 2, '.', '');
    }
}
