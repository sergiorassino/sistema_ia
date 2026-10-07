<?php

namespace App\Support\Comunicaciones;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Maneja el único adjunto opcional por mensaje de comunicación institucional.
 *
 * Disco: privado (storage/app/private) — nunca expuesto vía storage:link.
 * Archivos nuevos: ento/comunicaciones/{tenant}/{id_nivel}/{id_mensaje}/{nombre}
 * Los ya guardados conservan su ruta en com_mensajes.adjunto_ruta
 * (p. ej. comunicaciones/{tenant}/…); la descarga usa esa ruta y no los mueve.
 */
final class ComunicacionAdjuntoStorage
{
    public const DISK = 'privado';

    public const CARPETA = 'ento/comunicaciones';

    /**
     * Guarda el archivo temporario de Livewire en el disco privado y retorna
     * los metadatos del adjunto. Retorna null si el archivo es inválido.
     *
     * @return array{nombre:string, ruta:string, mime:string, bytes:int}|null
     */
    public static function guardar(
        TemporaryUploadedFile|UploadedFile $archivo,
        int $idNivel,
        int $idMensaje
    ): ?array {
        $nombre = self::nombreSeguro($archivo->getClientOriginalName());
        if ($nombre === '') {
            return null;
        }

        $dir  = self::directorio(tenantSlug(), $idNivel, $idMensaje);
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory($dir);

        $disk->putFileAs($dir, $archivo, $nombre);

        $ruta  = $dir . '/' . $nombre;
        $mime  = $archivo->getMimeType() ?? '';
        $bytes = (int) $archivo->getSize();

        return compact('nombre', 'ruta', 'mime', 'bytes');
    }

    /**
     * Devuelve la ruta relativa del adjunto de un mensaje si existe en disco.
     */
    public static function ruta(string $rutaRelativa): ?string
    {
        $disk = Storage::disk(self::DISK);

        return $disk->exists($rutaRelativa) ? $rutaRelativa : null;
    }

    /**
     * Entrega el archivo para verlo en el navegador (PDF en solapa, sin forzar descarga).
     */
    public static function download(string $rutaRelativa, string $nombre): StreamedResponse
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
        ];

        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $headers['Content-Type'] = 'application/pdf';
        }

        return Storage::disk(self::DISK)->response($rutaRelativa, $nombre, $headers, 'inline');
    }

    /**
     * Borra archivos de mensajes ya eliminados en la base.
     * Solo acepta rutas dentro de las carpetas del módulo.
     *
     * @param  iterable<string|null>  $rutasRelativas
     */
    public static function borrarRutas(iterable $rutasRelativas): void
    {
        foreach ($rutasRelativas as $ruta) {
            self::borrar((string) $ruta);
        }
    }

    /**
     * Borra un adjunto del disco privado. Si la carpeta es la del mensaje
     * (ento/comunicaciones/{tenant}/{nivel}/{mensaje}), también la elimina.
     */
    public static function borrar(string $rutaRelativa): void
    {
        $ruta = str_replace('\\', '/', trim($rutaRelativa));
        $ruta = ltrim($ruta, '/');
        if ($ruta === '' || str_contains($ruta, '..')) {
            return;
        }

        $enCarpetaNueva = str_starts_with($ruta, self::CARPETA.'/');
        $enCarpetaVieja = str_starts_with($ruta, 'comunicaciones/');
        if (! $enCarpetaNueva && ! $enCarpetaVieja) {
            return;
        }

        $disk = Storage::disk(self::DISK);
        if ($disk->exists($ruta)) {
            $disk->delete($ruta);
        }

        if (! $enCarpetaNueva) {
            return;
        }

        $dir = dirname($ruta);
        $resto = substr($dir, strlen(self::CARPETA) + 1);
        $partes = $resto === false ? [] : explode('/', $resto);
        if (count($partes) !== 3 || ! ctype_digit($partes[1]) || ! ctype_digit($partes[2])) {
            return;
        }

        $disk->deleteDirectory($dir);
    }

    /**
     * Valida un archivo antes del envío. Retorna el mensaje de error o null si es válido.
     */
    public static function validar(TemporaryUploadedFile|UploadedFile $archivo): ?string
    {
        $maxBytes = self::maxBytes();
        if ($archivo->getSize() > $maxBytes) {
            $mb = (int) config('comunicaciones.adjunto_max_mb', 8);

            return "El archivo no debe superar los {$mb} MB.";
        }

        $ext = strtolower((string) $archivo->getClientOriginalExtension());
        $extPermitidas = self::extensionesPermitidas();
        if (! in_array($ext, $extPermitidas, true)) {
            return 'Tipo de archivo no permitido. Use: ' . implode(', ', $extPermitidas) . '.';
        }

        // Verificación de MIME básica (evita ejecutables disfrazados)
        $mime = strtolower((string) $archivo->getMimeType());
        if (! self::mimePermitido($mime)) {
            return 'Tipo de archivo no permitido.';
        }

        return null;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    public static function maxBytes(): int
    {
        return (int) config('comunicaciones.adjunto_max_mb', 8) * 1024 * 1024;
    }

    /** @return list<string> */
    public static function extensionesPermitidas(): array
    {
        $mimes = (string) config('comunicaciones.adjunto_mimes', 'pdf,jpg,jpeg,png,doc,docx,xls,xlsx');

        return array_values(array_filter(explode(',', $mimes)));
    }

    private static function mimePermitido(string $mime): bool
    {
        $bloqueados = [
            'text/html', 'application/xhtml+xml', 'image/svg+xml',
            'application/x-msdownload', 'application/x-sh', 'application/x-php',
            'application/javascript', 'text/javascript',
        ];

        return ! in_array($mime, $bloqueados, true);
    }

    private static function nombreSeguro(string $original): string
    {
        $base = pathinfo($original, PATHINFO_FILENAME);
        $ext  = pathinfo($original, PATHINFO_EXTENSION);

        $base = preg_replace('/[^\p{L}\p{N}\-_. ]+/u', '', (string) $base) ?? 'adjunto';
        $base = trim(str_replace(' ', '_', $base), '._');
        if ($base === '') {
            $base = 'adjunto';
        }

        $ext    = preg_replace('/[^a-zA-Z0-9]+/', '', (string) $ext) ?? '';
        $nombre = $ext !== '' ? $base . '.' . strtolower($ext) : $base;

        // Limitar a 200 caracteres preservando extensión
        if (mb_strlen($nombre) > 200) {
            if ($ext !== '' && mb_strlen($ext) + 1 < 200) {
                $base   = mb_substr($base, 0, max(1, 200 - mb_strlen($ext) - 1));
                $nombre = $base . '.' . strtolower($ext);
            } else {
                $nombre = mb_substr($nombre, 0, 200);
            }
        }

        return $nombre;
    }

    private static function directorio(string $tenant, int $idNivel, int $idMensaje): string
    {
        return self::CARPETA.'/'.$tenant.'/'.$idNivel.'/'.$idMensaje;
    }
}
