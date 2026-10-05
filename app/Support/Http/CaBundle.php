<?php

namespace App\Support\Http;

/**
 * Busca un cacert.pem que PHP pueda leer.
 * En hosting con open_basedir, curl.cainfo suele apuntar a /etc/ssl/certs/cacert.pem
 * y is_file() sobre esa ruta lanza ErrorException.
 */
final class CaBundle
{
    /**
     * @param  list<mixed>  $candidatos
     */
    public static function primeraRutaLegible(array $candidatos): string
    {
        foreach ($candidatos as $ruta) {
            if (! is_string($ruta)) {
                continue;
            }
            $ruta = trim($ruta);
            if ($ruta !== '' && self::esArchivoLegible($ruta)) {
                return $ruta;
            }
        }

        return '';
    }

    public static function esArchivoLegible(string $ruta): bool
    {
        $ruta = trim($ruta);
        if ($ruta === '' || ! self::dentroDeOpenBasedir($ruta)) {
            return false;
        }

        try {
            return is_file($ruta);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function dentroDeOpenBasedir(string $ruta): bool
    {
        $basedir = ini_get('open_basedir');
        if (! is_string($basedir) || trim($basedir) === '') {
            return true;
        }

        $rutaNorm = str_replace('\\', '/', $ruta);
        foreach (explode(PATH_SEPARATOR, $basedir) as $permitido) {
            $permitido = str_replace('\\', '/', trim($permitido));
            if ($permitido === '') {
                continue;
            }
            if (str_starts_with($rutaNorm, $permitido)) {
                return true;
            }
        }

        return false;
    }
}
