<?php

namespace App\Support\Pdf;

use TCPDF;
use TCPDF_FONTS;

/**
 * Fuente Verdana para PDFs TCPDF (UTF-8).
 *
 * Uso: certificado de sexto grado (misma familia que el modelo Word provincial).
 * TTF: {@see storage_path('fonts/')} (`verdana.ttf`, `verdanab.ttf` opcional).
 * Respaldo: `resources/fonts/`. Sin TTF: `helvetica`.
 */
final class TcpdfFuenteVerdana
{
    private static bool $inicializado = false;

    private static string $regular = 'helvetica';

    private static ?string $bold = null;

    public static function aplicar(TCPDF $pdf, string $style = '', float $size = 10): void
    {
        self::boot();

        if ($style === 'B' && self::$bold !== null) {
            $pdf->SetFont(self::$bold, '', $size);

            return;
        }

        $pdf->SetFont(self::$regular, $style, $size);
    }

    private static function boot(): void
    {
        if (self::$inicializado) {
            return;
        }
        self::$inicializado = true;

        $dir = storage_path('fonts');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $regularPath = self::resolverRuta('verdana.ttf');
        if ($regularPath !== null) {
            $nombre = TCPDF_FONTS::addTTFfont($regularPath, 'TrueTypeUnicode', '', 32);
            if (is_string($nombre) && $nombre !== '') {
                self::$regular = $nombre;
            }
        }

        $boldPath = self::resolverRuta('verdanab.ttf');
        if ($boldPath !== null) {
            $nombre = TCPDF_FONTS::addTTFfont($boldPath, 'TrueTypeUnicode', '', 32);
            if (is_string($nombre) && $nombre !== '') {
                self::$bold = $nombre;
            }
        }
    }

    private static function resolverRuta(string $archivo): ?string
    {
        foreach ([storage_path('fonts'), base_path('resources/fonts')] as $directorio) {
            $ruta = self::buscarEnDirectorio($directorio, $archivo);
            if ($ruta !== null) {
                return $ruta;
            }
        }

        return null;
    }

    private static function buscarEnDirectorio(string $directorio, string $archivo): ?string
    {
        if (! is_dir($directorio)) {
            return null;
        }

        $rutaExacta = $directorio.DIRECTORY_SEPARATOR.$archivo;
        if (is_file($rutaExacta)) {
            return $rutaExacta;
        }

        $archivoLower = strtolower($archivo);
        $entradas = @scandir($directorio);
        if (! is_array($entradas)) {
            return null;
        }

        foreach ($entradas as $entrada) {
            if ($entrada === '.' || $entrada === '..') {
                continue;
            }
            if (strtolower($entrada) === $archivoLower) {
                $ruta = $directorio.DIRECTORY_SEPARATOR.$entrada;
                if (is_file($ruta)) {
                    return $ruta;
                }
            }
        }

        return null;
    }
}
