<?php

namespace App\Support\Alumnos;

use App\Models\CampoLegajo;
use App\Models\Legajo;
use App\Support\Database\PersistenciaColumnas;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Foto carnet del estudiante (`legajos.fotoCarnet`).
 *
 * Disco `privado` → ento/foto-carnet/{tenantSlug}/{dni}.jpg
 * Al guardar se redimensiona y comprime (el tamaño en pantalla lo define el CSS).
 */
final class FotoCarnetLegajo
{
    public const DISK = 'privado';

    public const COLUMNA = 'fotoCarnet';

    /** @var list<string> */
    public const EXTENSIONES = ['jpg', 'jpeg', 'png'];

    /** Tope del archivo original subido (antes de comprimir). */
    public const MAX_KB_UPLOAD = 2048;

    /** Ancho máximo en píxeles del archivo guardado. */
    public const MAX_ANCHO_PX = 600;

    /** Alto máximo en píxeles del archivo guardado (proporción carnet ~3:4). */
    public const MAX_ALTO_PX = 800;

    /** Calidad JPEG 0–100. */
    public const JPEG_CALIDAD = 75;

    public static function columnaDisponible(): bool
    {
        return Schema::hasTable('legajos') && Schema::hasColumn('legajos', self::COLUMNA);
    }

    /**
     * True si la columna existe y `fotoCarnet` está asignada a alguna solapa del legajo
     * (parametrización secretaría: `campos_legajo.solapa_legajo_id`).
     */
    public static function habilitadaEnSolapasLegajo(): bool
    {
        if (! self::columnaDisponible()) {
            return false;
        }

        if (! Schema::hasTable('campos_legajo')) {
            return false;
        }

        return CampoLegajo::query()
            ->where('columna', self::COLUMNA)
            ->whereNotNull('solapa_legajo_id')
            ->exists();
    }

    /** Etiqueta configurada en solapas, o «Foto carnet». */
    public static function etiquetaDesdeSolapas(): string
    {
        if (! Schema::hasTable('campos_legajo')) {
            return 'Foto carnet';
        }

        $etiqueta = CampoLegajo::query()
            ->where('columna', self::COLUMNA)
            ->whereNotNull('solapa_legajo_id')
            ->whereNotNull('etiqueta')
            ->where('etiqueta', '!=', '')
            ->orderBy('orden_en_solapa')
            ->orderBy('id')
            ->value('etiqueta');

        $etiqueta = trim((string) ($etiqueta ?? ''));

        return $etiqueta !== '' ? $etiqueta : 'Foto carnet';
    }

    /**
     * Persiste subida o quitar foto (con PersistenciaColumnas).
     *
     * @return array{ok: true, path: string}|array{ok: false, error: string}
     */
    public static function persistirCambio(
        int $idLegajo,
        string|int|null $dni,
        ?string $pathAnterior,
        ?TemporaryUploadedFile $upload,
        bool $remove,
    ): array {
        $pathAnterior = trim((string) $pathAnterior);

        if ($remove && ! ($upload instanceof TemporaryUploadedFile)) {
            if (! self::columnaDisponible()) {
                return [
                    'ok' => false,
                    'error' => PersistenciaColumnas::mensajeColumnasInexistentes('legajos', [self::COLUMNA]),
                ];
            }

            try {
                Legajo::query()->where('id', $idLegajo)->update([self::COLUMNA => null]);
            } catch (QueryException $e) {
                return ['ok' => false, 'error' => PersistenciaColumnas::mensajeDesdeQueryException($e)];
            }

            self::eliminarArchivo($pathAnterior);

            return ['ok' => true, 'path' => ''];
        }

        if (! ($upload instanceof TemporaryUploadedFile)) {
            return ['ok' => true, 'path' => $pathAnterior];
        }

        $resultado = self::guardarDesdeUpload($idLegajo, $dni, $upload, $pathAnterior);
        if (! $resultado['ok']) {
            return $resultado;
        }

        $payload = [self::COLUMNA => $resultado['path']];
        $preparado = PersistenciaColumnas::prepararPayload('legajos', $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            self::eliminarArchivo($resultado['path']);

            return [
                'ok' => false,
                'error' => PersistenciaColumnas::mensajeColumnasInexistentes(
                    'legajos',
                    $preparado['columnas_con_valor_sin_columna']
                ),
            ];
        }

        try {
            Legajo::query()->where('id', $idLegajo)->update($preparado['payload']);
        } catch (QueryException $e) {
            self::eliminarArchivo($resultado['path']);

            return ['ok' => false, 'error' => PersistenciaColumnas::mensajeDesdeQueryException($e)];
        }

        $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
            'legajos',
            ['id' => $idLegajo],
            $preparado['payload']
        );
        if ($noPersistidas !== []) {
            self::eliminarArchivo($resultado['path']);

            return [
                'ok' => false,
                'error' => PersistenciaColumnas::mensajeColumnasNoPersistidas('legajos', $noPersistidas),
            ];
        }

        return ['ok' => true, 'path' => $resultado['path']];
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

        $origen = self::rutaTemporalUpload($file);
        if ($origen === null) {
            return [
                'ok' => false,
                'error' => 'No se pudo leer el archivo en el servidor. Verifique permisos en storage/app/livewire-tmp.',
            ];
        }

        $comprimido = self::comprimirAJpeg($origen);
        if ($comprimido === null) {
            return [
                'ok' => false,
                'error' => 'No se pudo procesar la imagen. Use JPG o PNG y compruebe que la extensión GD de PHP esté habilitada.',
            ];
        }

        $dir = 'ento/foto-carnet/'.tenantSlug();
        $filename = $dniArchivo.'.jpg';
        $newPath = $dir.'/'.$filename;
        $disk = Storage::disk(self::DISK);

        try {
            $disk->makeDirectory($dir);
            if (! $disk->put($newPath, $comprimido)) {
                return [
                    'ok' => false,
                    'error' => 'No se pudo guardar la foto. Verifique permisos en storage/app/private.',
                ];
            }
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

        if (! $disk->exists($newPath)) {
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
        if ($size <= 0 || $size > self::MAX_KB_UPLOAD * 1024) {
            return 'La foto no puede superar los 2 MB (se comprime al guardar).';
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, self::EXTENSIONES, true)) {
            return 'La foto debe ser JPG o PNG.';
        }

        $origen = self::rutaTemporalUpload($file);
        if ($origen === null) {
            return 'No se pudo leer el archivo en el servidor. Espere a que termine la subida e intente de nuevo.';
        }

        if (@getimagesize($origen) === false) {
            return 'El archivo seleccionado no es una imagen válida (JPG/PNG).';
        }

        return null;
    }

    private static function rutaTemporalUpload(TemporaryUploadedFile $file): ?string
    {
        $path = $file->getRealPath();
        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            $path = method_exists($file, 'path') ? $file->path() : null;
        }

        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            return null;
        }

        return $path;
    }

    /**
     * Redimensiona (máx. MAX_ANCHO × MAX_ALTO) y comprime a JPEG.
     */
    private static function comprimirAJpeg(string $rutaOrigen): ?string
    {
        if (! extension_loaded('gd')) {
            Log::warning('foto-carnet-legajo: extensión GD no disponible');

            return null;
        }

        $info = @getimagesize($rutaOrigen);
        if ($info === false) {
            return null;
        }

        $mime = strtolower((string) ($info['mime'] ?? ''));
        $origen = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($rutaOrigen),
            'image/png' => @imagecreatefrompng($rutaOrigen),
            default => false,
        };

        if ($origen === false) {
            return null;
        }

        $origen = self::aplicarOrientacionExif($origen, $rutaOrigen, $mime);

        $anchoOrig = imagesx($origen);
        $altoOrig = imagesy($origen);
        if ($anchoOrig < 1 || $altoOrig < 1) {
            imagedestroy($origen);

            return null;
        }

        $escala = min(
            self::MAX_ANCHO_PX / $anchoOrig,
            self::MAX_ALTO_PX / $altoOrig,
            1.0
        );
        $ancho = max(1, (int) round($anchoOrig * $escala));
        $alto = max(1, (int) round($altoOrig * $escala));

        $destino = imagecreatetruecolor($ancho, $alto);
        if ($destino === false) {
            imagedestroy($origen);

            return null;
        }

        $blanco = imagecolorallocate($destino, 255, 255, 255);
        if ($blanco !== false) {
            imagefilledrectangle($destino, 0, 0, $ancho, $alto, $blanco);
        }

        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $ancho, $alto, $anchoOrig, $altoOrig);
        imagedestroy($origen);

        ob_start();
        $ok = imagejpeg($destino, null, self::JPEG_CALIDAD);
        $bin = ob_get_clean();
        imagedestroy($destino);

        if (! $ok || ! is_string($bin) || $bin === '') {
            return null;
        }

        return $bin;
    }

    /**
     * @param  \GdImage  $imagen
     * @return \GdImage
     */
    private static function aplicarOrientacionExif($imagen, string $ruta, string $mime)
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $imagen;
        }

        $exif = @exif_read_data($ruta);
        $orientacion = (int) ($exif['Orientation'] ?? 1);
        if ($orientacion <= 1) {
            return $imagen;
        }

        $rotado = match ($orientacion) {
            3 => imagerotate($imagen, 180, 0),
            6 => imagerotate($imagen, -90, 0),
            8 => imagerotate($imagen, 90, 0),
            default => false,
        };

        if ($rotado === false) {
            return $imagen;
        }

        imagedestroy($imagen);

        return $rotado;
    }
}
