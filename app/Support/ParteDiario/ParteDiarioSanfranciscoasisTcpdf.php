<?php

namespace App\Support\ParteDiario;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use TCPDF;

/**
 * Parte diario del preceptor — modelo San Francisco de Asís (Legal vertical, TCPDF).
 *
 * Replica el layout FPDF legacy: listado de regulares con columnas 1ºh–10ºh
 * y bloque de firmas docentes por hora del día.
 */
final class ParteDiarioSanfranciscoasisTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 20.0;

    private const MARGEN_DER = 15.0;

    private const MARGEN_SUP = 10.0;

    private const MARGEN_INF = 12.0;

    private const ANCHO_UTIL = 170.0;

    private const W_NRO = 5.0;

    private const W_LEGAJO = 15.0;

    private const W_NOMBRE = 100.0;

    private const W_HORA = 5.0;

    private const W_ETIQUETA = 22.0;

    private const W_ESPACIO = 88.0;

    private const W_FIRMA = 60.0;

    private const ALTURA_ALUMNO = 6.0;

    /** Dos líneas compactas: «1º HORA:» + horario reloj (centrado vertical). */
    private const ALTURA_FIRMA = 6.6;

    private const ALTURA_TH = 4.0;

    private const LOGO_ANCHO = 22.0;

    private const LOGO_ALTO = 22.0;

    private const GAP_POST_ENCABEZADO = 3.0;

    private const FIRMA_LINEA_ALTO = 2.2;

    /** @var array{insti: string, direccion: string, localidad: string, provincia?: string, cue: string, ee: string, logo_file: ?string} */
    private array $header;

    /**
     * @param  array{insti: string, direccion: string, localidad: string, provincia?: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    private function __construct(array $header)
    {
        parent::__construct('P', 'mm', 'LEGAL', true, 'UTF-8', false);
        $this->header = $header;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Parte diario del preceptor');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
        $this->SetFillColor(255, 255, 255);
    }

    /**
     * @param  list<array{
     *   cursoLabel: string,
     *   fechaTexto: string,
     *   alumnos: list<array{nro:int, legajo:string, nombre:string}>,
     *   filasFirma: list<array{etiqueta:string, espacio:string}>
     * }>  $paginas
     * @param  array{insti: string, direccion: string, localidad: string, provincia?: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generar(array $paginas, array $header): self
    {
        $pdf = new self($header);

        foreach ($paginas as $pagina) {
            $pdf->dibujarCurso($pagina);
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
     *   cursoLabel: string,
     *   fechaTexto: string,
     *   alumnos: list<array{nro:int, legajo:string, nombre:string}>,
     *   filasFirma: list<array{etiqueta:string, espacio:string}>
     * }  $pagina
     */
    private function dibujarCurso(array $pagina): void
    {
        $this->AddPage();
        $this->dibujarEncabezado(
            (string) ($pagina['cursoLabel'] ?? ''),
            (string) ($pagina['fechaTexto'] ?? ''),
        );

        $alumnos = is_array($pagina['alumnos'] ?? null) ? $pagina['alumnos'] : [];
        $this->dibujarTablaAlumnos($alumnos);

        $this->Ln(5.0);
        $this->asegurarEspacio(self::ALTURA_TH + (ParteDiarioSanfranciscoasisDatos::HORAS_MARCADO * self::ALTURA_FIRMA) + 2.0);

        $filasFirma = is_array($pagina['filasFirma'] ?? null) ? $pagina['filasFirma'] : [];
        $this->dibujarBloqueFirmas($filasFirma);
    }

    private function dibujarEncabezado(string $cursoLabel, string $fechaTexto): void
    {
        $x0 = self::MARGEN_IZQ;
        $y0 = self::MARGEN_SUP;
        $yFinLogo = $y0;

        $logo = $this->header['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $src = TcpdfImagenPng::fuenteTcpdf($logo);
            $this->Image($src, $x0, $y0, self::LOGO_ANCHO, self::LOGO_ALTO, '', '', '', false, 300);
            $yFinLogo = $y0 + self::LOGO_ALTO;
        }

        $insti = trim((string) ($this->header['insti'] ?? ''));
        if ($insti === '') {
            $insti = schoolNombre();
        }

        TcpdfFuenteArial::aplicar($this, 'B', 12);
        $this->SetXY($x0, $y0);
        $this->Cell(self::ANCHO_UTIL, 7.0, $insti, 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 10);
        $titulo = 'PARTE DIARIO DEL PRECEPTOR';
        if ($cursoLabel !== '') {
            $titulo .= ' - '.$cursoLabel;
        }
        $this->Cell(self::ANCHO_UTIL, 5.0, $titulo, 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, '', 8);
        $this->Ln(2.0);
        $meta = 'Fecha: '.$fechaTexto.'         Cantidad de Alumnos: .......    Ausentes: ..........';
        $this->Cell(self::ANCHO_UTIL, 5.0, $meta, 0, 2, 'C');

        // La grilla arranca debajo del logo y del texto (el emblema SFA trae leyenda bajo el sello).
        $yGrilla = max($this->GetY(), $yFinLogo) + self::GAP_POST_ENCABEZADO;
        $this->SetY($yGrilla);
        $this->SetX($x0);
    }

    /**
     * @param  list<array{nro:int, legajo:string, nombre:string}>  $alumnos
     */
    private function dibujarTablaAlumnos(array $alumnos): void
    {
        $this->dibujarCabeceraAlumnos();

        TcpdfFuenteArial::aplicar($this, '', 8);
        foreach ($alumnos as $alumno) {
            $this->asegurarEspacio(self::ALTURA_ALUMNO);
            if ($this->GetY() <= self::MARGEN_SUP + 1.0) {
                $this->dibujarCabeceraAlumnos();
                TcpdfFuenteArial::aplicar($this, '', 8);
            }

            $this->SetX(self::MARGEN_IZQ);
            $this->Cell(self::W_NRO, self::ALTURA_ALUMNO, (string) (int) ($alumno['nro'] ?? 0), 1, 0, 'C');
            $this->Cell(self::W_LEGAJO, self::ALTURA_ALUMNO, (string) ($alumno['legajo'] ?? ''), 1, 0, 'C');
            $this->Cell(self::W_NOMBRE, self::ALTURA_ALUMNO, (string) ($alumno['nombre'] ?? ''), 1, 0, 'L');
            for ($h = 1; $h <= ParteDiarioSanfranciscoasisDatos::HORAS_MARCADO; $h++) {
                $ln = $h === ParteDiarioSanfranciscoasisDatos::HORAS_MARCADO ? 1 : 0;
                $this->Cell(self::W_HORA, self::ALTURA_ALUMNO, '', 1, $ln, 'C');
            }
        }
    }

    private function dibujarCabeceraAlumnos(): void
    {
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->SetX(self::MARGEN_IZQ);
        $this->Cell(self::W_NRO, self::ALTURA_TH, 'Nº', 1, 0, 'C');
        $this->Cell(self::W_LEGAJO, self::ALTURA_TH, 'Legajo', 1, 0, 'C');
        $this->Cell(self::W_NOMBRE, self::ALTURA_TH, 'Apellidos y Nombres', 1, 0, 'C');
        for ($h = 1; $h <= ParteDiarioSanfranciscoasisDatos::HORAS_MARCADO; $h++) {
            $ln = $h === ParteDiarioSanfranciscoasisDatos::HORAS_MARCADO ? 1 : 0;
            $this->Cell(self::W_HORA, self::ALTURA_TH, $h.'ºh', 1, $ln, 'C');
        }
    }

    /**
     * @param  list<array{etiqueta:string, espacio:string}>  $filas
     */
    private function dibujarBloqueFirmas(array $filas): void
    {
        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->SetX(self::MARGEN_IZQ);
        $this->Cell(self::W_ETIQUETA, self::ALTURA_TH, '', 1, 0, 'C');
        $this->Cell(self::W_ESPACIO, self::ALTURA_TH, 'Espacios Curriculares', 1, 0, 'C');
        $this->Cell(self::W_FIRMA, self::ALTURA_TH, 'Firma del profesor', 1, 1, 'C');

        for ($i = 0; $i < ParteDiarioSanfranciscoasisDatos::HORAS_MARCADO; $i++) {
            $fila = $filas[$i] ?? ['etiqueta' => ($i + 1).'º HORA', 'espacio' => ''];
            $this->asegurarEspacio(self::ALTURA_FIRMA);
            if ($this->GetY() <= self::MARGEN_SUP + 1.0) {
                TcpdfFuenteArial::aplicar($this, '', 6);
                $this->SetX(self::MARGEN_IZQ);
                $this->Cell(self::W_ETIQUETA, self::ALTURA_TH, '', 1, 0, 'C');
                $this->Cell(self::W_ESPACIO, self::ALTURA_TH, 'Espacios Curriculares', 1, 0, 'C');
                $this->Cell(self::W_FIRMA, self::ALTURA_TH, 'Firma del profesor', 1, 1, 'C');
            }

            $x = self::MARGEN_IZQ;
            $y = $this->GetY();
            [$lineaHora, $lineaReloj] = self::partirEtiquetaHora((string) ($fila['etiqueta'] ?? ''), $i + 1);

            $this->Rect($x, $y, self::W_ETIQUETA, self::ALTURA_FIRMA);
            $this->Rect($x + self::W_ETIQUETA, $y, self::W_ESPACIO, self::ALTURA_FIRMA);
            $this->Rect($x + self::W_ETIQUETA + self::W_ESPACIO, $y, self::W_FIRMA, self::ALTURA_FIRMA);

            $lineasEtiqueta = $lineaReloj !== '' ? 2 : 1;
            $altoTextoEtiqueta = $lineasEtiqueta * self::FIRMA_LINEA_ALTO;
            $yEtiqueta = $y + max(0.15, (self::ALTURA_FIRMA - $altoTextoEtiqueta) / 2);

            TcpdfFuenteArial::aplicar($this, '', 5);
            $this->SetXY($x + 0.4, $yEtiqueta);
            $textoEtiqueta = $lineaReloj !== ''
                ? $lineaHora."\n".$lineaReloj
                : $lineaHora;
            $this->MultiCell(self::W_ETIQUETA - 0.8, self::FIRMA_LINEA_ALTO, $textoEtiqueta, 0, 'C', false, 0);

            $espacio = trim((string) ($fila['espacio'] ?? ''));
            if ($espacio !== '') {
                $lineasEspacio = max(1, substr_count($espacio, "\n") + 1);
                $altoTextoEspacio = min(self::ALTURA_FIRMA - 0.4, $lineasEspacio * 2.0);
                $yEspacio = $y + max(0.15, (self::ALTURA_FIRMA - $altoTextoEspacio) / 2);

                TcpdfFuenteArial::aplicar($this, '', 5.5);
                $this->SetXY($x + self::W_ETIQUETA + 0.8, $yEspacio);
                $this->MultiCell(self::W_ESPACIO - 1.6, 2.0, $espacio, 0, 'L', false, 0);
            }

            $this->SetXY($x, $y + self::ALTURA_FIRMA);
        }
    }

    /**
     * @return array{0: string, 1: string}  [«nº HORA:», horario reloj o '']
     */
    private static function partirEtiquetaHora(string $etiqueta, int $nroHora): array
    {
        $etiqueta = trim($etiqueta);
        if ($etiqueta === '') {
            return [$nroHora.'º HORA:', ''];
        }

        if (preg_match('/^(\d+º\s*HORA)\s*:\s*(.+)$/iu', $etiqueta, $m) === 1) {
            return [trim($m[1]).':', trim($m[2])];
        }

        $pos = mb_strpos($etiqueta, ':');
        if ($pos !== false) {
            $izq = trim(mb_substr($etiqueta, 0, $pos + 1));
            $der = trim(mb_substr($etiqueta, $pos + 1));

            return [$izq !== '' ? $izq : $nroHora.'º HORA:', $der];
        }

        return [$etiqueta, ''];
    }

    private function asegurarEspacio(float $altoNecesario): void
    {
        $limite = $this->getPageHeight() - self::MARGEN_INF;
        if ($this->GetY() + $altoNecesario <= $limite) {
            return;
        }

        $this->AddPage();
        $this->SetY(self::MARGEN_SUP);
        $this->SetX(self::MARGEN_IZQ);
    }
}
