<?php

namespace App\Support\EmailsMasivos;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class EmailsMasivosAdjuntosStorage
{
    public const DISK = 'privado';

    /**
     * @param  list<TemporaryUploadedFile|UploadedFile>  $archivos
     * @return array{attached:string,nombres:list<string>,paths:list<string>}
     */
    public static function guardarParaCampana(int $idTerlec, int $idEmailEscrito, array $archivos): array
    {
        $dir = self::directorioCampana($idTerlec, $idEmailEscrito);
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory($dir);

        $nombres = [];
        $paths = [];

        foreach ($archivos as $archivo) {
            if (! $archivo instanceof TemporaryUploadedFile && ! $archivo instanceof UploadedFile) {
                continue;
            }
            $nombre = self::nombreSeguro($archivo->getClientOriginalName());
            $disk->putFileAs($dir, $archivo, $nombre);
            $nombres[] = $nombre;
            $paths[] = $dir . '/' . $nombre;
        }

        return [
            'attached' => implode('|', $nombres),
            'nombres' => $nombres,
            'paths' => $paths,
        ];
    }

    public static function rutaArchivo(int $idTerlec, int $idEmailEscrito, string $nombreArchivo): ?string
    {
        $nombre = self::sanearNombreExistente($nombreArchivo);
        if ($nombre === '') {
            return null;
        }

        $path = self::directorioCampana($idTerlec, $idEmailEscrito) . '/' . $nombre;
        $disk = Storage::disk(self::DISK);

        return $disk->exists($path) ? $path : null;
    }

    /**
     * @return list<string> rutas absolutas en disco para adjuntos de una campaña
     */
    public static function pathsAbsolutosCampana(int $idTerlec, int $idEmailEscrito, string $attached): array
    {
        $disk = Storage::disk(self::DISK);
        $paths = [];
        foreach (DestinatariosEmailsMasivos::parseAttached($attached) as $nombre) {
            $rel = self::rutaArchivo($idTerlec, $idEmailEscrito, $nombre);
            if ($rel !== null) {
                $paths[] = $disk->path($rel);
            }
        }

        return $paths;
    }

    public static function nombreSeguro(string $original): string
    {
        $base = pathinfo($original, PATHINFO_FILENAME);
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $base = preg_replace('/[^\p{L}\p{N}\-_. ]+/u', '', (string) $base) ?? 'adjunto';
        $base = trim(str_replace(' ', '_', $base), '._');
        if ($base === '') {
            $base = 'adjunto';
        }
        $ext = preg_replace('/[^a-zA-Z0-9]+/', '', (string) $ext) ?? '';
        $nombre = $ext !== '' ? $base . '.' . strtolower($ext) : $base;
        $max = EmailsMasivosConfig::adjuntoNombreMaxChars();
        if (mb_strlen($nombre) > $max) {
            if ($ext !== '' && mb_strlen($ext) + 1 < $max) {
                $limiteBase = $max - mb_strlen($ext) - 1;
                $nombre = mb_substr($base, 0, max(1, $limiteBase)) . '.' . strtolower($ext);
            } else {
                $nombre = mb_substr($nombre, 0, $max);
            }
        }

        return $nombre;
    }

    public static function validarListaNombres(array $nombres): ?string
    {
        if (count($nombres) > EmailsMasivosConfig::adjuntosMaxCount()) {
            return 'Demasiados adjuntos.';
        }
        $attached = implode('|', $nombres);
        if (mb_strlen($attached) > EmailsMasivosConfig::attachedFieldMaxChars()) {
            return 'Los nombres de adjuntos superan el límite del campo (150 caracteres). Acorte los nombres.';
        }
        foreach ($nombres as $n) {
            if (mb_strlen($n) > EmailsMasivosConfig::adjuntoNombreMaxChars()) {
                return 'Cada nombre de adjunto debe tener como máximo ' . EmailsMasivosConfig::adjuntoNombreMaxChars() . ' caracteres.';
            }
        }

        return null;
    }

    private static function directorioCampana(int $idTerlec, int $idEmailEscrito): string
    {
        return 'emails-masivos/' . tenantSlug() . '/' . $idTerlec . '/' . $idEmailEscrito;
    }

    private static function sanearNombreExistente(string $nombre): string
    {
        $nombre = basename(str_replace(['\\', '/'], '', trim($nombre)));

        return preg_match('/^[\\p{L}\\p{N}\\-_. ]+$/u', $nombre) ? $nombre : '';
    }
}
