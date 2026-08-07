<?php

namespace App\Support\Viajes;

use App\Models\SalidaViaje;
use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfMultiCellJustificado;
use TCPDF;

/**
 * Autorización de salida educativa — A4 vertical (plantilla legacy Scriptcase).
 */
final class SalidaViajeTcpdf extends TCPDF
{
    private const MARGEN_L = 20.0;

    private const MARGEN_T = 5.0;

    private const MARGEN_R = 20.0;

    /**
     * A4 = 297 mm; el pie institucional empieza en Y_PIE_IMAGEN.
     * El margen inferior debe llegar justo encima del pie (no reservar de más).
     */
    private const Y_PIE_IMAGEN = 280.0;

    private const ALTO_PIE_IMAGEN = 14.0;

    /** Límite inferior del contenido/firmas (mm desde el tope), sin pisar el pie. */
    private const Y_LIMITE_CONTENIDO = 278.0;

    /** 297 − 278: auto page-break alineado al pie. */
    private const MARGEN_B = 19.0;

    private const Y_INICIO_CONTENIDO = 45.0;

    /** Altura del bloque de firmas (5 líneas + espacio lugar/fecha→firma), mm. */
    private const ALTO_BLOQUE_FIRMAS = 31.0;

    private function __construct()
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Salidas Educativas');
        $this->SetSubject('Salidas Educativas');
        $this->setPrintHeader(true);
        $this->setPrintFooter(true);
        $this->SetHeaderMargin(0);
        $this->SetFooterMargin(self::MARGEN_B);
        $this->SetAutoPageBreak(true, self::MARGEN_B);
        // Margen superior uniforme: el membrete de salidas solo va en la 1.ª hoja del alumno.
        $this->SetMargins(self::MARGEN_L, self::Y_INICIO_CONTENIDO, self::MARGEN_R);
        // Menos aire entre <p>: TCPDF suma ~1 línea al cerrar cada párrafo y eso puede forzar hoja vacía.
        $this->setHtmlVSpace([
            'p' => [
                0 => ['h' => 0, 'n' => 0],
                1 => ['h' => 0.5, 'n' => 1],
            ],
            'div' => [
                0 => ['h' => 0, 'n' => 0],
                1 => ['h' => 0, 'n' => 0],
            ],
        ]);
    }

    /** Membrete institucional del gobierno — todas las páginas. */
    public function Header(): void
    {
        $gobierno = SalidaViajeDatos::rutaMembrete('membreteGobierno.jpg');
        if ($gobierno !== null) {
            $this->imagenAbsoluta($gobierno, 80, 5, 120, 0);
        }
    }

    /** Pie institucional del gobierno — todas las páginas. */
    public function Footer(): void
    {
        $pie = SalidaViajeDatos::rutaMembrete('piePaginaGobierno.jpg');
        if ($pie !== null) {
            $this->imagenAbsoluta($pie, 20, self::Y_PIE_IMAGEN, 100, self::ALTO_PIE_IMAGEN);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $alumnos
     */
    public static function generarLote(SalidaViaje $viaje, array $alumnos): self
    {
        $pdf = new self;
        $titulo = trim((string) ($viaje->titulo ?? ''));
        $htmlCuerpo = SalidaViajeHtmlParaPdf::preparar((string) ($viaje->texto ?? ''));

        foreach ($alumnos as $alumno) {
            $pdf->AddPage();
            $pdf->dibujarFondoHoja();
            $pdf->dibujarHoja($titulo, $htmlCuerpo, $alumno);
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
     * Membrete de salidas educativas solo en la primera hoja de cada alumno.
     */
    private function dibujarFondoHoja(): void
    {
        $salidas = SalidaViajeDatos::rutaMembrete('membreteSalidas.jpg');
        if ($salidas !== null) {
            $this->imagenFija($salidas, 20, 20, 100, 0);
        }

        $this->SetXY(self::MARGEN_L, self::Y_INICIO_CONTENIDO);
    }

    /**
     * Imagen en coordenadas absolutas (Header/Footer); sin restaurar cursor.
     */
    private function imagenAbsoluta(string $ruta, float $x, float $y, float $w, float $h = 0): void
    {
        $autoPageBreak = $this->getAutoPageBreak();
        $breakMargin = $this->getBreakMargin();
        $this->SetAutoPageBreak(false);

        $this->Image(
            $ruta,
            $x,
            $y,
            $w,
            $h,
            '',
            '',
            '',
            false,
            300,
            '',
            false,
            false,
            0,
            false,
            false,
            false
        );

        $this->SetAutoPageBreak($autoPageBreak, $breakMargin);
    }

    /**
     * Dibuja una imagen en X/Y absolutos sin alterar la posición actual del cursor.
     */
    private function imagenFija(string $ruta, float $x, float $y, float $w, float $h = 0): void
    {
        $pagina = $this->getPage();
        $xActual = $this->GetX();
        $yActual = $this->GetY();

        $this->imagenAbsoluta($ruta, $x, $y, $w, $h);

        $this->setPage($pagina);
        $this->SetXY($xActual, $yActual);
    }

    /**
     * @param  array<string, mixed>  $alumno
     */
    private function dibujarHoja(string $titulo, string $htmlCuerpo, array $alumno): void
    {
        $ancho = $this->getPageWidth() - self::MARGEN_L - self::MARGEN_R;

        $apellido = (string) ($alumno['apellido'] ?? '');
        $nombre = (string) ($alumno['nombre'] ?? '');
        $dni = (string) ($alumno['dni'] ?? '');
        $cursec = (string) ($alumno['cursec'] ?? '');
        $callenum = (string) ($alumno['callenum'] ?? '');
        $localidad = (string) ($alumno['localidad'] ?? '');
        $gruposang = trim((string) ($alumno['gruposang'] ?? ''));
        $grupoSanguineoTexto = $gruposang !== '' ? $gruposang : '.................';

        TcpdfFuenteArial::aplicar($this, 'B', 7);
        $this->SetXY(150, 25);
        $this->Cell(40, 4, $apellido.', '.$nombre, 0, 1, 'R');
        $this->SetXY(150, 30);
        $this->Cell(40, 4, $cursec, 0, 1, 'R');

        $this->SetXY(self::MARGEN_L, self::Y_INICIO_CONTENIDO);

        TcpdfFuenteArial::aplicar($this, 'B', 10);
        $this->MultiCell($ancho, 5, $titulo, 0, 'C', false, 1);
        $this->Ln(2);

        TcpdfFuenteArial::aplicar($this, '', 9);
        $intro = 'Por la presente AUTORIZO a mi hijo/a: '.$apellido.', '.$nombre
            .' DNI N° '.$dni
            .' Grupo y factor sanguíneo: '.$grupoSanguineoTexto.'. Alumno/a del curso: '.$cursec
            .' Con domicilio en calle '.$callenum.' de la localidad de '.$localidad.'.';
        TcpdfMultiCellJustificado::escribir($this, $ancho, 4, $intro);
        $this->Ln(2);

        if ($htmlCuerpo !== '') {
            $this->escribirCuerpoHtml($htmlCuerpo);
        }

        $this->dibujarBloqueFirmas($ancho);
    }

    /**
     * Escribe el HTML del viaje sin writeHTMLCell: ese camino (MultiCell+ln) puede
     * abrir una hoja vacía al calcular el alto del bloque o al hacer Ln final.
     */
    private function escribirCuerpoHtml(string $htmlCuerpo): void
    {
        TcpdfFuenteArial::aplicar($this, '', 9);

        $this->SetAutoPageBreak(true, self::MARGEN_B);
        $this->SetX(self::MARGEN_L);
        $this->writeHTML($htmlCuerpo, false, false, true, false, 'L');
    }

    private function dibujarBloqueFirmas(float $ancho): void
    {
        $y = $this->GetY();
        $necesita = 2 + self::ALTO_BLOQUE_FIRMAS;

        if ($y + $necesita > self::Y_LIMITE_CONTENIDO) {
            // Si aún no pisa el pie institucional, compactar en la misma hoja.
            if ($y + self::ALTO_BLOQUE_FIRMAS <= self::Y_PIE_IMAGEN - 1) {
                // sin Ln extra
            } else {
                $this->AddPage();
                $this->SetY(max(self::MARGEN_T + 18, 28));
            }
        } else {
            $this->Ln(2);
        }

        $this->SetAutoPageBreak(false);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->SetX(self::MARGEN_L);
        $this->Cell($ancho, 5, 'Lugar y fecha:  Córdoba, ........ de  .............................................. de ........................', 0, 1, 'L');

        TcpdfFuenteArial::aplicar($this, 'B', 7);
        $this->Ln(4);
        $this->SetX(self::MARGEN_L);
        $this->Cell($ancho, 5, 'Firma: ..........................................................................................................................', 0, 1, 'L');
        $this->SetX(self::MARGEN_L);
        $this->Cell($ancho, 5, 'D.N.I.: .........................................................................................................................', 0, 1, 'L');
        $this->SetX(self::MARGEN_L);
        $this->Cell($ancho, 5, 'Domicilio: .....................................................................................................................', 0, 1, 'L');
        $this->SetX(self::MARGEN_L);
        $this->Cell($ancho, 5, 'Teléfono: ......................................................................................................................', 0, 1, 'L');

        $this->SetAutoPageBreak(true, self::MARGEN_B);
    }
}
