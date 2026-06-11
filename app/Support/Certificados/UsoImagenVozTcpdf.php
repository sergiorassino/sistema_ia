<?php

namespace App\Support\Certificados;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Autorización de uso de imagen y voz — A4 vertical, plantilla de fondo.
 */
final class UsoImagenVozTcpdf extends TCPDF
{
    private const MARGEN_LATERAL = 29.0;

    private const FILL_GRIS = [232, 232, 232];

    private const ANCHO_TEXTO = 152.0;

    private ?string $plantilla;

    private function __construct(?string $plantilla)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->plantilla = $plantilla;
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
    public static function generarLote(array $alumnos): self
    {
        $pdf = new self(CusIsaVozImagenDatos::rutaPlantilla('autorizacionImagen.jpg'));

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

        $apellido = trim((string) ($alumno['apellido'] ?? ''));
        $nombre = trim((string) ($alumno['nombre'] ?? ''));
        $dni = trim((string) ($alumno['dni'] ?? ''));
        $nombrepad = trim((string) ($alumno['nombrepad'] ?? ''));
        $dnipad = trim((string) ($alumno['dnipad'] ?? ''));
        $nombremad = trim((string) ($alumno['nombremad'] ?? ''));
        $dnimad = trim((string) ($alumno['dnimad'] ?? ''));

        $texto = self::textoAutorizacion($apellido, $nombre, $dni, $nombrepad, $dnipad, $nombremad, $dnimad);

        $y = 60.0;
        $this->SetXY(self::MARGEN_LATERAL, $y);
        TcpdfFuenteArial::aplicar($this, '', 11);
        $this->MultiCell(self::ANCHO_TEXTO, 6, $texto, 0, 'L', false, 1);
    }

    private static function textoAutorizacion(
        string $apellido,
        string $nombre,
        string $dni,
        string $nombrepad,
        string $dnipad,
        string $nombremad,
        string $dnimad,
    ): string {
        if ($nombrepad === '' && $nombremad !== '') {
            return 'Quienes suscriben, (madre) '.$nombremad.', D.N.I. Nº '.$dnimad
                .' madre de '.$apellido.', '.$nombre.', D.N.I. Nº '.$dni.',';
        }

        if ($nombremad === '' && $nombrepad !== '') {
            return 'Quienes suscriben, (padre) '.$nombrepad.', D.N.I. Nº '.$dnipad
                .' padre de '.$apellido.', '.$nombre.', D.N.I. Nº '.$dni.',';
        }

        if ($nombremad === '' && $nombrepad === '') {
            return 'NO HAY DATOS DE PADRE Y MADRE CARGADOS';
        }

        return 'Quienes suscriben, (padre) '.$nombrepad.', D.N.I. Nº '.$dnipad
            .' y (madre) '.$nombremad.', D.N.I. Nº '.$dnimad
            .' padre y madre de '.$apellido.', '.$nombre.', D.N.I. Nº '.$dni.',';
    }
}
