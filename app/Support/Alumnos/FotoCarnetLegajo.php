<?php

namespace App\Support\Alumnos;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Foto carnet del estudiante (`legajos.fotoCarnet`).
 *
 * Disco `privado` → ento/foto-carnet/{tenantSlug}/{dni}.{ext}
 */
final class FotoCarnetLegajo
{
    public const DISK = 'privado';

    public const COLUMNA = 'fotoCarnet';

    /** @var list<string> */
    public const EXTENSIONES = ['jpg', 'jpeg', 'png'];

    public const MAX_KB = 2048;

    public static function columnaDisponible(): bool
    {
        return Schema::hasTable('legajos') && Schema::hasColumn('legajos', self::COLUMNA);
    }

    /** Solo dígitos, para nombre de archivo seguro. */
    public static function dniParaNombreArchivo(string|int|null $dni): string
    {
        return preg_replace('/\D+/', '', (string) $dni) ?? '';
    }

    public static function rutaAbsoluta(?string $pathRelativo): ?string
    {
        $path = trim((string) $pathRelativo);
        if ($path === '') {
            return null;
        }

        $disk = Storage::disk(self::DISK);
        if (! $disk->exists($path)) {
            return null;
        }

        return $disk->path($path);
    }

    public static function urlVer(?int $idLegajo, ?string $pathRelativo): ?string
    {
        if ($idLegajo === null || $idLegajo <= 0) {
            return null;
        }

        if (trim((string) $pathRelativo) === '' || self::rutaAbsoluta($pathRelativo) === null) {
            return null;
        }

        // Relativa al host actual; ID numérico (ABM secretaría con auth).
        $url = route('abm.legajos.foto-carnet', ['id' => $idLegajo], false);
        $mtime = @filemtime(self::rutaAbsoluta($pathRelativo) ?? '') ?: time();

        return $url.(str_contains($url, '?') ? '&' : '?').'v='.$mtime;
    }

    /**
     * Vista previa embebida (evita peticiones HTTP adicionales que fallen por host/sesión).
     */
    public static function dataUrlPreview(?string $pathRelativo): ?string
    {
        $abs = self::rutaAbsoluta($pathRelativo);
        if ($abs === null) {
            return null;
        }

        $bin = @file_get_contents($abs);
        if ($bin === false || $bin === '') {
            return null;
        }

        $mime = match (strtolower(pathinfo($abs, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            default => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.base64_encode($bin);
    }

    /**
     * @return array{ok: true, path: string}|array{ok: false, error: string}
     */
    public static function guardarDesdeUpload(
        int $idLegajo,
        string|int|null $dni,
        TemporaryUploadedFile $file,
        ?string $pathAnterior,
    ): array {
        if (! self::columnaDisponible()) {
            return [
                'ok' => false,
                'error' => 'La columna legajos.fotoCarnet no existe en esta base. Ejecute la migración o el SQL idempotente antes de subir fotos.',
            ];
        }

        $dniArchivo = self::dniParaNombreArchivo($dni);
        if ($dniArchivo === '') {
            return [
                'ok' => false,
                'error' => 'No se puede guardar la foto: el DNI del estudiante es obligatorio.',
            ];
        }

        $error = self::validarUpload($file);
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }

        $dir = 'ento/foto-carnet/'.tenantSlug();
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, self::EXTENSIONES, true)) {
            $ext = 'jpg';
        }
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $filename = $dniArchivo.'.'.$ext;

        $disk = Storage::disk(self::DISK);

        try {
            $disk->makeDirectory($dir);
            $newPath = $file->storeAs($dir, $filename, self::DISK);
        } catch (\Throwable $e) {
            Log::warning('foto-carnet-legajo: error al guardar', [
                'idLegajo' => $idLegajo,
                'dni' => $dniArchivo,
                'message' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'error' => 'No se pudo guardar la foto. Verifique permisos en storage/app/private.',
            ];
        }

        if (! is_string($newPath) || $newPath === '' || ! $disk->exists($newPath)) {
            return [
                'ok' => false,
                'error' => 'No se pudo guardar la foto. Verifique permisos en storage/app/private.',
            ];
        }

        $anterior = trim((string) $pathAnterior);
        if ($anterior !== '' && $anterior !== $newPath) {
            self::eliminarArchivo($anterior);
        }

        return ['ok' => true, 'path' => $newPath];
    }

    public static function eliminarArchivo(?string $pathRelativo): void
    {
        $path = trim((string) $pathRelativo);
        if ($path === '') {
            return;
        }

        try {
            Storage::disk(self::DISK)->delete($path);
        } catch (\Throwable $e) {
            Log::warning('foto-carnet-legajo: no se pudo eliminar archivo', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public static function validarUpload(TemporaryUploadedFile $file): ?string
    {
        $mime = strtolower((string) ($file->getMimeType() ?? ''));
        if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return 'La foto debe ser JPG o PNG.';
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > self::MAX_KB * 1024) {
            return 'La foto no puede superar los 2 MB.';
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, self::EXTENSIONES, true)) {
            return 'La foto debe ser JPG o PNG.';
        }

        return null;
    }
}
