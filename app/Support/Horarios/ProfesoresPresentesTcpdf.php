<?php

namespace App\Support\Horarios;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use TCPDF;

/**
 * Listado de profesores presentes (A4 vertical, TCPDF, Arial).
 */
final class ProfesoresPresentesTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 12.0;

    private const MARGEN_DER = 12.0;

    private const MARGEN_SUP = 10.0;

    private const MARGEN_INF = 12.0;

    private const ANCHO_UTIL = 186.0;

    private const ALTURA_CABECERA_INST = 16.0;

    private const W_DOCENTE = 72.0;

    private const W_CURSO = 42.0;

    private const W_HORARIO = 72.0;

    /** @var array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string} */
    private array $header;

    /**
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    private function __construct(array $header)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->header = $header;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Profesores presentes');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
    }

    /**
     * @param  array<string, mixed>  $datos  Salida de {@see ProfesoresPresentesConsulta::consultar()}
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generar(array $datos, array $header, string $subtituloNivelCiclo): self
    {
        $pdf = new self($header);
        $pdf->AddPage();
        $pdf->dibujarListado($datos, $subtituloNivelCiclo);

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
     * @param  array<string, mixed>  $datos
     */
    private function dibujarListado(array $datos, string $subtituloNivelCiclo): void
    {
        $y = $this->dibujarMarcoCabecera(self::MARGEN_SUP);
        $y = $this->dibujarTitulo($y, $datos, $subtituloNivelCiclo);
        $this->dibujarTabla($y, $datos);
    }

    private function dibujarMarcoCabecera(float $y): float
    {
        $x = self::MARGEN_IZQ;
        $w = self::ANCHO_UTIL;
        $h = self::ALTURA_CABECERA_INST;

        $this->SetDrawColor(17, 17, 17);
        $this->RoundedRect($x, $y, $w, $h, 2.0, '1111', 'D');

        $logo = $this->header['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), $x + 2, $y + 1.5, 11, 13, '', '', '', false, 300);
        }

        $insti = trim((string) ($this->header['insti'] ?? ''));
        $direccion = trim((string) ($this->header['direccion'] ?? ''));
        $localidad = trim((string) ($this->header['localidad'] ?? ''));
        $lineaDir = trim($direccion.($direccion !== '' && $localidad !== '' ? ' — ' : '').$localidad);
        $cue = trim((string) ($this->header['cue'] ?? ''));
        $ee = trim((string) ($this->header['ee'] ?? ''));
        $lineaIds = trim(($cue !== '' ? 'CUE: '.$cue : '').(($cue !== '' && $ee !== '') ? '   ' : '').($ee !== '' ? 'EE: '.$ee : ''));

        $this->SetXY($x, $y + 2);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell($w, 4, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        if ($lineaDir !== '') {
            TcpdfFuenteArial::aplicar($this, '', 6.5);
            $this->Cell($w, 3, $lineaDir, 0, 2, 'C');
        }
        if ($lineaIds !== '') {
            TcpdfFuenteArial::aplicar($this, '', 5.5);
            $this->Cell($w, 3, $lineaIds, 0, 2, 'C');
        }

        return $y + $h + 2.5;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarTitulo(float $y, array $datos, string $subtituloNivelCiclo): float
    {
        $x = self::MARGEN_IZQ;
        $w = self::ANCHO_UTIL;

        TcpdfFuenteArial::aplicar($this, 'B', 11);
        $this->SetXY($x, $y);
        $this->Cell($w, 5, 'PROFESORES PRESENTES', 0, 2, 'C');

        $dia = trim((string) ($datos['diaLabel'] ?? ''));
        $hi = trim((string) ($datos['horaInicio'] ?? ''));
        $hf = trim((string) ($datos['horaFin'] ?? ''));
        $franja = trim($dia.($dia !== '' && $hi !== '' ? ' · ' : '').$hi.($hi !== '' && $hf !== '' ? ' a '.$hf : ''));

        TcpdfFuenteArial::aplicar($this, '', 8);
        if ($franja !== '') {
            $this->Cell($w, 4, $franja, 0, 2, 'C');
        }
        if ($subtituloNivelCiclo !== '') {
            $this->Cell($w, 3.5, $subtituloNivelCiclo, 0, 2, 'C');
        }

        $cursos = trim((string) ($datos['cursosResumen'] ?? ''));
        if ($cursos !== '') {
            TcpdfFuenteArial::aplicar($this, '', 7);
            $this->MultiCell($w, 3.4, 'Cursos: '.$cursos, 0, 'C', false, 1);
        }

        $n = (int) ($datos['cantidadDocentes'] ?? 0);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell($w, 4.5, $n === 1 ? '1 docente' : $n.' docentes', 0, 2, 'C');

        return $this->GetY() + 1.5;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarTabla(float $y, array $datos): void
    {
        /** @var list<array{idProfesor: int, docente: string, curso: string, horario: string}> $filas */
        $filas = $datos['filas'] ?? [];

        $this->dibujarEncabezadoTabla($y);

        if ($filas === []) {
            TcpdfFuenteArial::aplicar($this, '', 8);
            $this->SetXY(self::MARGEN_IZQ, $this->GetY() + 2);
            $this->Cell(self::ANCHO_UTIL, 6, 'No hay docentes con clase en ese día, horario y cursos.', 0, 1, 'C');

            return;
        }

        $this->SetDrawColor(193, 215, 218);
        $this->SetFillColor(255, 255, 255);
        $yMax = 297.0 - self::MARGEN_INF;
        $pad = 4.2;

        foreach ($filas as $fila) {
            $docente = trim((string) ($fila['docente'] ?? ''));
            $curso = trim((string) ($fila['curso'] ?? ''));
            $horario = trim((string) ($fila['horario'] ?? ''));
            if ($docente === '') {
                $docente = '—';
            }
            if ($curso === '') {
                $curso = '—';
            }
            if ($horario === '') {
                $horario = '—';
            }

            TcpdfFuenteArial::aplicar($this, '', 8);
            $lineas = max(
                1,
                $this->getNumLines($docente, self::W_DOCENTE - 1.5),
                $this->getNumLines($curso, self::W_CURSO - 1.5),
                $this->getNumLines($horario, self::W_HORARIO - 1.5),
            );
            $alt = max(6.0, $lineas * $pad);

            if ($this->GetY() + $alt > $yMax) {
                $this->AddPage();
                $this->dibujarEncabezadoTabla(self::MARGEN_SUP);
            }

            $x = self::MARGEN_IZQ;
            $yFila = $this->GetY();
            TcpdfFuenteArial::aplicar($this, '', 8);
            $this->SetXY($x, $yFila);
            $this->MultiCell(self::W_DOCENTE, $alt, $docente, 1, 'L', true, 0, $x, $yFila, true, 0, false, true, $alt, 'M');
            $this->MultiCell(self::W_CURSO, $alt, $curso, 1, 'L', true, 0, $x + self::W_DOCENTE, $yFila, true, 0, false, true, $alt, 'M');
            $this->MultiCell(self::W_HORARIO, $alt, $horario, 1, 'L', true, 1, $x + self::W_DOCENTE + self::W_CURSO, $yFila, true, 0, false, true, $alt, 'M');
        }
    }

    private function dibujarEncabezadoTabla(float $y): void
    {
        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->SetFillColor(244, 248, 249);
        $this->SetDrawColor(193, 215, 218);
        $this->SetTextColor(51, 51, 51);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $h = 6.5;
        $this->Cell(self::W_DOCENTE, $h, 'DOCENTE', 1, 0, 'L', true);
        $this->Cell(self::W_CURSO, $h, 'CURSO', 1, 0, 'L', true);
        $this->Cell(self::W_HORARIO, $h, 'HORARIO PRESENTE', 1, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
    }
}
