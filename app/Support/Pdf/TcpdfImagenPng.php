<?php

namespace App\Support\Pdf;

/**
 * PNG compatible con GD/TCPDF: quita chunk iCCP (libpng: «known incorrect sRGB profile»).
 */
final class TcpdfImagenPng
{
    /** @var array<string, string> */
    private static array $cache = [];

    /**
     * Ruta absoluta o datos inline TCPDF (@binario) listos para Image().
     */
    public static function fuenteTcpdf(string $rutaAbsoluta): string
    {
        if (! is_file($rutaAbsoluta)) {
            return $rutaAbsoluta;
        }

        $mtime = filemtime($rutaAbsoluta) ?: 0;
        $cacheKey = $rutaAbsoluta.'|'.$mtime;
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $ext = strtolower(pathinfo($rutaAbsoluta, PATHINFO_EXTENSION));
        if ($ext !== 'png') {
            self::$cache[$cacheKey] = $rutaAbsoluta;

            return $rutaAbsoluta;
        }

        $bin = file_get_contents($rutaAbsoluta);
        if ($bin === false) {
            self::$cache[$cacheKey] = $rutaAbsoluta;

            return $rutaAbsoluta;
        }

        $limpio = self::quitarChunkiCCP($bin);
        $result = $limpio === $bin ? $rutaAbsoluta : '@'.$limpio;
        self::$cache[$cacheKey] = $result;

        return $result;
    }

    private static function quitarChunkiCCP(string $png): string
    {
        $firma = "\x89PNG\r\n\x1a\n";
        if (! str_starts_with($png, $firma)) {
            return $png;
        }

        $out = $firma;
        $offset = 8;
        $len = strlen($png);
        $changed = false;

        while ($offset + 12 <= $len) {
            $chunkLen = unpack('N', substr($png, $offset, 4))[1];
            $type = substr($png, $offset + 4, 4);
            $chunkEnd = $offset + 12 + $chunkLen;
            if ($chunkEnd > $len) {
                break;
            }

            if ($type !== 'iCCP') {
                $out .= substr($png, $offset, 12 + $chunkLen);
            } else {
                $changed = true;
            }

            $offset = $chunkEnd;
            if ($type === 'IEND') {
                break;
            }
        }

        return $changed ? $out : $png;
    }
}
