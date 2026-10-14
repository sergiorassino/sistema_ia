<?php

namespace App\Support\Horarios;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use Illuminate\Http\Response;
use TCPDF;

/**
 * Listado de profesores con horarios (A4 apaisado, TCPDF, Arial).
 * Columnas: docente y lunes a viernes.
 */
final class ListadoProfesoresHorariosTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 10.0;

    private const MARGEN_DER = 10.0;

    private const MARGEN_SUP = 8.0;

    private const MARGEN_INF = 10.0;

    private const ANCHO_UTIL = 277.0;

    private const ALTURA_CABECERA_INST = 14.0;

    private const W_DOCENTE = 47.0;

    private const W_DIA = 46.0;

    /** @var array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string} */
    private array $header;

    /**
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    private function __construct(array $header)
    {
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8', false);
        $this->header = $header;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Listado de Profesores con Horarios');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
    }

    /**
     * @param  array<string, mixed>  $datos  Salida de {@see ListadoProfesoresHorariosConsulta::consultar()}
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generar(array $datos, array $header, string $subtituloNivelCiclo): self
    {
        $pdf = new self($header);
        $pdf->AddPage();
        $pdf->dibujarListado($datos, $subtituloNivelCiclo);

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
            $this->Image(TcpdfImagenPng::fuenteTcpdf($logo), $x + 2, $y + 1.2, 10, 11.5, '', '', '', false, 300);
        }

        $insti = trim((string) ($this->header['insti'] ?? ''));
        $direccion = trim((string) ($this->header['direccion'] ?? ''));
        $localidad = trim((string) ($this->header['localidad'] ?? ''));
        $lineaDir = trim($direccion.($direccion !== '' && $localidad !== '' ? ' — ' : '').$localidad);
        $cue = trim((string) ($this->header['cue'] ?? ''));
        $ee = trim((string) ($this->header['ee'] ?? ''));
        $lineaIds = trim(($cue !== '' ? 'CUE: '.$cue : '').(($cue !== '' && $ee !== '') ? '   ' : '').($ee !== '' ? 'EE: '.$ee : ''));

        $this->SetXY($x, $y + 1.6);
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

        return $y + $h + 2.0;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarTitulo(float $y, array $datos, string $subtituloNivelCiclo): float
    {
        $x = self::MARGEN_IZQ;
        $w = self::ANCHO_UTIL;

        TcpdfFuenteArial::aplicar($this, 'B', 12);
        $this->SetXY($x, $y);
        $this->Cell($w, 5, 'LISTADO DE PROFESORES CON HORARIOS', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 8);
        if ($subtituloNivelCiclo !== '') {
            $this->Cell($w, 4, $subtituloNivelCiclo, 0, 2, 'C');
        }
        $this->Cell($w, 3.5, 'Emitido el '.now()->format('d/m/Y'), 0, 2, 'C');

        $n = (int) ($datos['cantidadDocentes'] ?? 0);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell($w, 4.5, $n === 1 ? '1 docente' : $n.' docentes', 0, 2, 'C');

        return $this->GetY() + 1.2;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarTabla(float $y, array $datos): void
    {
        /** @var list<array{docente: string, dias: array<int, string>}> $filas */
        $filas = $datos['filas'] ?? [];

        $this->dibujarEncabezadoTabla($y);

        if ($filas === []) {
            TcpdfFuenteArial::aplicar($this, '', 8);
            $this->SetXY(self::MARGEN_IZQ, $this->GetY() + 2);
            $this->Cell(self::ANCHO_UTIL, 6, 'No hay docentes con materia, curso y horario cargado en este ciclo.', 0, 1, 'C');

            return;
        }

        $this->SetDrawColor(193, 215, 218);
        $yMax = 210.0 - self::MARGEN_INF;
        $pad = 3.5;

        foreach ($filas as $fila) {
            $docente = trim((string) ($fila['docente'] ?? ''));
            if ($docente === '') {
                $docente = '—';
            }
            $textos = [$docente];
            foreach (ListadoProfesoresHorariosConsulta::DIAS as $dia) {
                $txt = trim((string) (($fila['dias'][$dia] ?? '') !== '' ? $fila['dias'][$dia] : '—'));
                $textos[] = $txt !== '' ? $txt : '—';
            }

            TcpdfFuenteArial::aplicar($this, '', 7);
            $lineas = 1;
            $anchos = [self::W_DOCENTE, self::W_DIA, self::W_DIA, self::W_DIA, self::W_DIA, self::W_DIA];
            foreach ($textos as $i => $txt) {
                $lineas = max($lineas, $this->getNumLines($txt, $anchos[$i] - 1.4));
            }
            $alt = max(6.2, $lineas * $pad);

            if ($this->GetY() + $alt > $yMax) {
                $this->AddPage();
                $this->dibujarEncabezadoTabla(self::MARGEN_SUP);
            }

            $x = self::MARGEN_IZQ;
            $yFila = $this->GetY();
            TcpdfFuenteArial::aplicar($this, '', 7);
            $this->SetFillColor(255, 255, 255);
            foreach ($textos as $i => $txt) {
                $align = $i === 0 ? 'L' : 'C';
                $this->MultiCell(
                    $anchos[$i],
                    $alt,
                    $txt,
                    1,
                    $align,
                    true,
                    0,
                    $x,
                    $yFila,
                    true,
                    0,
                    false,
                    true,
                    $alt,
                    'M',
                );
                $x += $anchos[$i];
            }
            $this->SetY($yFila + $alt);
        }
    }

    private function dibujarEncabezadoTabla(float $y): void
    {
        $this->SetXY(self::MARGEN_IZQ, $y);
        $this->SetFillColor(244, 248, 249);
        $this->SetDrawColor(193, 215, 218);
        $this->SetTextColor(51, 51, 51);
        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $h = 6.2;
        $this->Cell(self::W_DOCENTE, $h, 'DOCENTE', 1, 0, 'L', true);
        foreach (ListadoProfesoresHorariosConsulta::etiquetasDias() as $etiqueta) {
            $this->Cell(self::W_DIA, $h, mb_strtoupper($etiqueta), 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetTextColor(0, 0, 0);
    }
}
