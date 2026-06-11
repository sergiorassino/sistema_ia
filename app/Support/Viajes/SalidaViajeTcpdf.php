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

    private const MARGEN_B = 25.0;

    private const Y_INICIO_CONTENIDO = 45.0;

    private const Y_PIE_IMAGEN = 280.0;

    /** Altura aproximada del bloque de firmas (mm). */
    private const ALTO_BLOQUE_FIRMAS = 22.0;

    private const ALTO_PIE_IMAGEN = 14.0;

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
            TcpdfFuenteArial::aplicar($this, '', 9);
            $y = $this->GetY();
            $this->writeHTMLCell(
                $ancho,
                0,
                self::MARGEN_L,
                $y,
                $htmlCuerpo,
                0,
                1,
                false,
                true,
                'J',
                true
            );
        }

        $this->Ln(4);

        $limiteInferior = $this->getPageHeight() - self::MARGEN_B;
        if ($this->GetY() + self::ALTO_BLOQUE_FIRMAS > $limiteInferior) {
            $this->AddPage();
        }

        $this->SetX(self::MARGEN_L);

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->Cell($ancho, 6, 'Lugar y fecha:  Córdoba, ........ de  .............................................. de ........................', 0, 1, 'L');

        TcpdfFuenteArial::aplicar($this, 'B', 7);
        $this->Ln(1);
        $this->SetX(self::MARGEN_L);
        $this->Cell($ancho, 5, 'FIRMA: ..........................................................................................       D.N.I.: .............................................', 0, 1, 'C');
        $this->SetX(self::MARGEN_L);
        $this->Cell($ancho, 5, 'DOMICILIO: ..........................................................................................................................       TELÉFONO: ..........................................................................................', 0, 1, 'C');
    }
}
