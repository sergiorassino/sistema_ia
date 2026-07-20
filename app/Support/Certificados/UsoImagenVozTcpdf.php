<?php

namespace App\Support\Certificados;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfLogoInstitucional;
use TCPDF;

/**
 * Autorización de uso de imagen y voz — A4 vertical, plantilla de fondo.
 */
final class UsoImagenVozTcpdf extends TCPDF
{
    private const MARGEN_LATERAL = 29.0;

    private const FILL_GRIS = [232, 232, 232];

    /**
     * Celda izquierda del encabezado de la plantilla (logo fijo de Fader).
     * Medido sobre autorizacionImagen.jpg (1241×1755 → A4 210 mm).
     */
    private const LOGO_AREA_X = 31.0;

    private const LOGO_AREA_Y = 25.5;

    private const LOGO_AREA_ANCHO = 37.5;

    private const LOGO_AREA_ALTO = 25.0;

    /** Cubre el párrafo de la plantilla (incluye nombre de colegio hardcodeado). */
    private const TEXTO_AREA_Y = 55.0;

    private const TEXTO_AREA_ALTO = 82.0;

    private const ANCHO_TEXTO = 152.0;

    private ?string $plantilla;

    private string $insti;

    private function __construct(?string $plantilla, string $insti)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->plantilla = $plantilla;
        $this->insti = $insti;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Uso de imagen y voz');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(self::MARGEN_LATERAL, 0, self::MARGEN_LATERAL);
        $this->SetFillColor(...self::FILL_GRIS);
    }

    /**
     * @param  list<array<string, mixed>>  $alumnos
     */
    public static function generarLote(array $alumnos, string $insti): self
    {
        $pdf = new self(CusIsaVozImagenDatos::rutaPlantilla('autorizacionImagen.jpg'), $insti);

        foreach ($alumnos as $alumno) {
            $pdf->AddPage('P', 'A4');
            $pdf->dibujarHoja($alumno);
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
     * @param  array<string, mixed>  $alumno
     */
    private function dibujarHoja(array $alumno): void
    {
        if ($this->plantilla !== null && is_file($this->plantilla)) {
            $this->Image($this->plantilla, 0, 0, 210, 0, '', '', '', false, 300);
        }

        $this->dibujarLogoInstitucional();

        $apellido = trim((string) ($alumno['apellido'] ?? ''));
        $nombre = trim((string) ($alumno['nombre'] ?? ''));
        $dni = trim((string) ($alumno['dni'] ?? ''));
        $nombrepad = trim((string) ($alumno['nombrepad'] ?? ''));
        $dnipad = trim((string) ($alumno['dnipad'] ?? ''));
        $nombremad = trim((string) ($alumno['nombremad'] ?? ''));
        $dnimad = trim((string) ($alumno['dnimad'] ?? ''));

        $texto = self::textoAutorizacion(
            $apellido,
            $nombre,
            $dni,
            $nombrepad,
            $dnipad,
            $nombremad,
            $dnimad,
            $this->insti,
        );

        $this->SetFillColor(255, 255, 255);
        $this->Rect(
            self::MARGEN_LATERAL - 1.0,
            self::TEXTO_AREA_Y,
            self::ANCHO_TEXTO + 2.0,
            self::TEXTO_AREA_ALTO,
            'F',
        );

        $this->SetXY(self::MARGEN_LATERAL, self::TEXTO_AREA_Y + 2.0);
        TcpdfFuenteArial::aplicar($this, '', 11);
        $this->MultiCell(self::ANCHO_TEXTO, 6, $texto, 0, 'L', false, 1);
    }

    private function dibujarLogoInstitucional(): void
    {
        $this->SetFillColor(255, 255, 255);
        $this->Rect(
            self::LOGO_AREA_X,
            self::LOGO_AREA_Y,
            self::LOGO_AREA_ANCHO,
            self::LOGO_AREA_ALTO,
            'F',
        );

        $logoFile = pdfHeaderLogoAbsolutePath(schoolPdfHeaderData());
        TcpdfLogoInstitucional::dibujarAjustado(
            $this,
            self::LOGO_AREA_X,
            self::LOGO_AREA_Y,
            self::LOGO_AREA_ANCHO,
            self::LOGO_AREA_ALTO,
            $logoFile,
        );
    }

    private static function textoAutorizacion(
        string $apellido,
        string $nombre,
        string $dni,
        string $nombrepad,
        string $dnipad,
        string $nombremad,
        string $dnimad,
        string $insti,
    ): string {
        $escuela = $insti !== '' ? $insti : '..................................................';

        $cierre = ' autorizamos a la institución educativa a la que asiste nuestro/a hijo/a,'
            .' a tomar fotografías, videos, y grabar su voz, para ser utilizados en el ámbito educativo,'
            .' como parte de las actividades del '.$escuela
            .' del MINISTERIO DE EDUCACIÓN DE LA PROVINCIA DE CÓRDOBA.'
            .' La presente autorización tiene por objeto facilitar la difusión de actividades institucionales,'
            .' respetando siempre la dignidad e intimidad del/la estudiante, y sin fines comerciales.';

        if ($nombrepad === '' && $nombremad !== '') {
            return 'Quienes suscriben, (madre) '.$nombremad.', D.N.I. Nº '.$dnimad
                .' madre de '.$apellido.', '.$nombre.', D.N.I. Nº '.$dni.','.$cierre;
        }

        if ($nombremad === '' && $nombrepad !== '') {
            return 'Quienes suscriben, (padre) '.$nombrepad.', D.N.I. Nº '.$dnipad
                .' padre de '.$apellido.', '.$nombre.', D.N.I. Nº '.$dni.','.$cierre;
        }

        if ($nombremad === '' && $nombrepad === '') {
            return 'NO HAY DATOS DE PADRE Y MADRE CARGADOS';
        }

        return 'Quienes suscriben, (padre) '.$nombrepad.', D.N.I. Nº '.$dnipad
            .' y (madre) '.$nombremad.', D.N.I. Nº '.$dnimad
            .' padre y madre de '.$apellido.', '.$nombre.', D.N.I. Nº '.$dni.','.$cierre;
    }
}
