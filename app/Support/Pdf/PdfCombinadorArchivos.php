<?php

namespace App\Support\Pdf;

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Combina imágenes JPG y PDFs en un único archivo PDF.
 */
final class PdfCombinadorArchivos
{
    private const MARGEN_MM = 10.0;

    /**
     * @param  list<string>  $rutasAbsolutas  Rutas absolutas a JPG/JPEG/PDF existentes.
     */
    public static function combinar(array $rutasAbsolutas, string $rutaSalidaAbsoluta): void
    {
        if ($rutasAbsolutas === []) {
            throw new \InvalidArgumentException('No hay archivos para combinar.');
        }

        $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);

        foreach ($rutasAbsolutas as $ruta) {
            if (! is_file($ruta)) {
                throw new \RuntimeException('Archivo no encontrado: '.$ruta);
            }

            $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg'], true)) {
                self::agregarImagenComoPagina($pdf, $ruta);
            } elseif ($ext === 'pdf') {
                self::importarPaginasPdf($pdf, $ruta);
            } else {
                throw new \InvalidArgumentException('Extensión no soportada: '.$ext);
            }
        }

        $dir = dirname($rutaSalidaAbsoluta);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear el directorio de destino.');
        }

        $pdf->Output($rutaSalidaAbsoluta, 'F');
    }

    private static function agregarImagenComoPagina(Fpdi $pdf, string $rutaImagen): void
    {
        $info = @getimagesize($rutaImagen);
        if ($info === false) {
            throw new \RuntimeException('La imagen no es válida.');
        }

        [$imgW, $imgH] = $info;
        if ($imgW < 1 || $imgH < 1) {
            throw new \RuntimeException('La imagen no tiene dimensiones válidas.');
        }

        $pdf->AddPage();
        $pageW = $pdf->getPageWidth() - (2 * self::MARGEN_MM);
        $pageH = $pdf->getPageHeight() - (2 * self::MARGEN_MM);

        $ratio = min($pageW / $imgW, $pageH / $imgH);
        $w = $imgW * $ratio;
        $h = $imgH * $ratio;
        $x = self::MARGEN_MM + (($pageW - $w) / 2);
        $y = self::MARGEN_MM + (($pageH - $h) / 2);

        $pdf->Image($rutaImagen, $x, $y, $w, $h, '', '', '', false, 300);
    }

    private static function importarPaginasPdf(Fpdi $pdf, string $rutaPdf): void
    {
        try {
            $pageCount = $pdf->setSourceFile($rutaPdf);
        } catch (\Throwable $e) {
            throw new \RuntimeException('El PDF no pudo leerse o está protegido.', 0, $e);
        }

        for ($i = 1; $i <= $pageCount; $i++) {
            $template = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($template);
            $orientation = ($size['width'] ?? 0) > ($size['height'] ?? 0) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
        }
    }
}
