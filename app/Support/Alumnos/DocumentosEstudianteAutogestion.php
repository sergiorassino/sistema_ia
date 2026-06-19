<?php

namespace App\Support\Alumnos;

use App\Models\DocEstudianteTipo;
use App\Support\Pdf\PdfCombinadorArchivos;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Documentación del estudiante subida desde autogestión (actualización de datos).
 *
 * Tipos parametrizados en tabla `doc_estudiante_tipos` (solo registros activos).
 * Almacenamiento en carpeta única del tenant (sin subcarpeta por estudiante):
 * disco `privado` → ento/doc-estudiante/{tenantSlug}/{dni}_{clave}.pdf
 */
final class DocumentosEstudianteAutogestion
{
    public const DISK = 'privado';

    public const EXTENSIONES_PERMITIDAS = DocEstudianteTipo::EXTENSIONES_SOPORTADAS;

    /**
     * @return list<array{
     *     id: int,
     *     clave: string,
     *     label: string,
     *     extensiones: list<string>,
     *     max_archivos: int,
     *     max_mb: int,
     *     obligatorio: bool,
     *     explicacion: ?string
     * }>
     */
    public static function tiposConfigurados(): array
    {
        if (! DocEstudianteTipo::tablaDisponible()) {
            return [];
        }

        return DocEstudianteTipo::query()
            ->activos()
            ->ordenados()
            ->get()
            ->map(static fn (DocEstudianteTipo $tipo) => $tipo->toDefinicionAutogestion())
            ->filter(static fn (array $def) => $def['extensiones'] !== [] && $def['label'] !== '')
            ->values()
            ->all();
    }

    public static function habilitadoConTipos(): bool
    {
        return self::tiposConfigurados() !== [];
    }

    public static function claveValida(string $clave): bool
    {
        return self::definicion($clave) !== null;
    }

    /**
     * @return array{
     *     id: int,
     *     clave: string,
     *     label: string,
     *     extensiones: list<string>,
     *     max_archivos: int,
     *     max_mb: int,
     *     obligatorio: bool,
     *     explicacion: ?string
     * }|null
     */
    public static function definicion(string $clave): ?array
    {
        if (! DocEstudianteTipo::tablaDisponible()) {
            return null;
        }

        $clave = DocEstudianteTipo::normalizarClave($clave);
        if ($clave === '') {
            return null;
        }

        $tipo = DocEstudianteTipo::query()
            ->activos()
            ->where('clave', $clave)
            ->first();

        if ($tipo === null) {
            return null;
        }

        $def = $tipo->toDefinicionAutogestion();

        return $def['extensiones'] !== [] ? $def : null;
    }

    public static function maxBytesPorArchivo(?string $clave = null): int
    {
        if ($clave !== null) {
            $def = self::definicion($clave);
            if ($def !== null) {
                return $def['max_mb'] * 1024 * 1024;
            }
        }

        return DocEstudianteTipo::MAX_MB_DEFAULT * 1024 * 1024;
    }

    public static function dniSanitizado(string $dni): string
    {
        $soloDigitos = preg_replace('/\D/', '', trim($dni)) ?? '';

        return $soloDigitos !== '' ? $soloDigitos : 'sin-dni';
    }

    public static function storageDir(): string
    {
        return 'ento/doc-estudiante/'.tenantSlug();
    }

    public static function nombreArchivoPdf(string $dni, string $clave): string
    {
        return self::dniSanitizado($dni).'_'.DocEstudianteTipo::normalizarClave($clave).'.pdf';
    }

    public static function pathRelativo(string $dni, string $clave): string
    {
        return self::storageDir().'/'.self::nombreArchivoPdf($dni, $clave);
    }

    public static function pathRelativoLegacy(string $dni, string $clave): string
    {
        $dniSan = self::dniSanitizado($dni);

        return 'ento/doc-estudiante/'.tenantSlug().'/'.$dniSan.'/'.self::nombreArchivoPdf($dni, $clave);
    }

    /**
     * Ruta relativa al disco privado si el archivo existe (layout actual o legacy).
     */
    public static function pathAlmacenadoResuelto(string $dni, string $clave): ?string
    {
        $disk = Storage::disk(self::DISK);
        $path = self::pathRelativo($dni, $clave);
        if ($disk->exists($path)) {
            return $path;
        }

        $legacy = self::pathRelativoLegacy($dni, $clave);
        if ($disk->exists($legacy)) {
            return $legacy;
        }

        return null;
    }

    public static function urlVerDocumento(string $dni, string $clave, int $idLegajo): ?string
    {
        if ($idLegajo <= 0) {
            return null;
        }

        $def = self::definicion($clave);
        if ($def === null || self::pathAlmacenadoResuelto($dni, $clave) === null) {
            return null;
        }

        $ref = OpaqueRouteToken::forDocumentoEstudianteAutogestion((int) $def['id'], $idLegajo);

        return se_route_url('alumnos.actualizacion-datos.documento-estudiante', ['ref' => $ref]);
    }

    /**
     * @param  array<int|string, TemporaryUploadedFile|mixed>  $slots
     * @return list<TemporaryUploadedFile>
     */
    public static function archivosDesdeSlots(array $slots): array
    {
        ksort($slots, SORT_NUMERIC);
        $validos = [];
        foreach ($slots as $archivo) {
            if ($archivo instanceof TemporaryUploadedFile) {
                $validos[] = $archivo;
            }
        }

        return $validos;
    }

    public static function validarArchivoIndividual(string $clave, TemporaryUploadedFile $archivo): ?string
    {
        $def = self::definicion($clave);
        if ($def === null) {
            return 'Tipo de documento no válido.';
        }

        $maxBytes = self::maxBytesPorArchivo($clave);
        $extensiones = array_map('strtolower', $def['extensiones']);
        $ext = strtolower((string) $archivo->getClientOriginalExtension());

        if (! in_array($ext, $extensiones, true)) {
            return 'Formato no permitido. Extensiones aceptadas: '.implode(', ', $extensiones).'.';
        }

        $bytes = (int) ($archivo->getSize() ?? 0);
        if ($bytes < 1) {
            return 'El archivo está vacío o no terminó de subirse.';
        }
        if ($bytes > $maxBytes) {
            $mb = (int) round($maxBytes / 1024 / 1024);

            return 'El archivo no puede superar los '.$mb.' MB.';
        }

        if (! self::mimeAceptado($archivo, $ext)) {
            return 'El archivo no es un JPG o PDF válido.';
        }

        return null;
    }

    /**
     * @return array{existe: bool, path: ?string, nombre: string, actualizado_en: ?string, url_ver: ?string}
     */
    public static function estadoDocumento(string $dni, string $clave, ?int $idLegajo = null): array
    {
        $def = self::definicion($clave);
        if ($def === null) {
            return ['existe' => false, 'path' => null, 'nombre' => '', 'actualizado_en' => null, 'url_ver' => null];
        }

        $path = self::pathAlmacenadoResuelto($dni, $clave);
        $disk = Storage::disk(self::DISK);
        $existe = $path !== null;
        $actualizado = null;

        if ($existe && $path !== null) {
            $ts = $disk->lastModified($path);
            if ($ts > 0) {
                $actualizado = date('d/m/Y H:i', $ts);
            }
        }

        return [
            'existe' => $existe,
            'path' => $path,
            'nombre' => self::nombreArchivoPdf($dni, $clave),
            'actualizado_en' => $actualizado,
            'url_ver' => $existe && $idLegajo !== null
                ? self::urlVerDocumento($dni, $clave, $idLegajo)
                : null,
        ];
    }

    /**
     * @return array<string, array{existe: bool, path: ?string, nombre: string, actualizado_en: ?string, url_ver: ?string}>
     */
    public static function estadoTodos(string $dni, ?int $idLegajo = null): array
    {
        $estado = [];
        foreach (self::tiposConfigurados() as $tipo) {
            $estado[$tipo['clave']] = self::estadoDocumento($dni, $tipo['clave'], $idLegajo);
        }

        return $estado;
    }

    /**
     * @return list<array{clave: string, label: string}>
     */
    public static function obligatoriosPendientes(string $dni): array
    {
        $pendientes = [];
        foreach (self::tiposConfigurados() as $tipo) {
            if (! $tipo['obligatorio']) {
                continue;
            }
            if (! self::estadoDocumento($dni, $tipo['clave'])['existe']) {
                $pendientes[] = [
                    'clave' => $tipo['clave'],
                    'label' => $tipo['label'],
                ];
            }
        }

        return $pendientes;
    }

    /**
     * Elimina el PDF unificado del estudiante para el tipo indicado.
     */
    public static function eliminar(string $dni, string $clave): void
    {
        if (! self::claveValida($clave)) {
            throw new \InvalidArgumentException('Tipo de documento no válido.');
        }

        $dniSan = self::dniSanitizado($dni);
        if ($dniSan === 'sin-dni') {
            throw new \InvalidArgumentException('El estudiante no tiene DNI registrado.');
        }

        $disk = Storage::disk(self::DISK);
        $path = self::pathAlmacenadoResuelto($dni, $clave);

        if ($path === null) {
            throw new \RuntimeException('El documento no existe o ya fue eliminado.');
        }

        if (! $disk->delete($path)) {
            throw new \RuntimeException('No se pudo eliminar el documento.');
        }
    }

    /**
     * @param  list<TemporaryUploadedFile|mixed>  $archivos
     */
    public static function validarArchivos(string $clave, array $archivos): ?string
    {
        $def = self::definicion($clave);
        if ($def === null) {
            return 'Tipo de documento no válido.';
        }

        $validos = self::archivosDesdeSlots(is_array($archivos) ? $archivos : [$archivos]);

        if ($validos === []) {
            return 'Seleccione al menos un archivo (JPG o PDF).';
        }

        if (count($validos) > $def['max_archivos']) {
            return 'Puede subir como máximo '.$def['max_archivos'].' archivo(s) para este documento.';
        }

        $maxBytes = self::maxBytesPorArchivo($clave);
        $extensiones = array_map('strtolower', $def['extensiones']);

        foreach ($validos as $archivo) {
            $ext = strtolower((string) $archivo->getClientOriginalExtension());
            if (! in_array($ext, $extensiones, true)) {
                return 'Formato no permitido. Extensiones aceptadas: '.implode(', ', $extensiones).'.';
            }

            $bytes = (int) ($archivo->getSize() ?? 0);
            if ($bytes < 1) {
                return 'Uno de los archivos está vacío o no terminó de subirse.';
            }
            if ($bytes > $maxBytes) {
                $mb = (int) round($maxBytes / 1024 / 1024);

                return 'Cada archivo no puede superar los '.$mb.' MB.';
            }

            if (! self::mimeAceptado($archivo, $ext)) {
                return 'Uno de los archivos no es un JPG o PDF válido.';
            }
        }

        return null;
    }

    /**
     * @param  list<TemporaryUploadedFile|mixed>  $archivos
     */
    public static function guardarDesdeUploads(string $dni, string $clave, array $archivos): void
    {
        $error = self::validarArchivos($clave, $archivos);
        if ($error !== null) {
            throw new \InvalidArgumentException($error);
        }

        $def = self::definicion($clave);
        if ($def === null) {
            throw new \InvalidArgumentException('Tipo de documento no válido.');
        }

        $dniSan = self::dniSanitizado($dni);
        if ($dniSan === 'sin-dni') {
            throw new \InvalidArgumentException('El estudiante no tiene DNI registrado.');
        }

        $dirTemp = null;
        try {
            $tempPaths = [];
            foreach ($archivos as $archivo) {
                if ($archivo instanceof TemporaryUploadedFile) {
                    $tempPaths[] = $archivo->getRealPath();
                }
            }

            $dirTemp = storage_path('app/temp/doc-estudiante/'.uniqid('merge_', true));
            if (! is_dir($dirTemp) && ! mkdir($dirTemp, 0755, true) && ! is_dir($dirTemp)) {
                throw new \RuntimeException('No se pudo preparar el directorio temporal.');
            }

            $salidaTemp = $dirTemp.'/documento.pdf';
            PdfCombinadorArchivos::combinar($tempPaths, $salidaTemp);

            if (! is_file($salidaTemp) || filesize($salidaTemp) < 1) {
                throw new \RuntimeException('No se pudo generar el PDF final.');
            }

            $relDir = self::storageDir();
            $nombreFinal = self::nombreArchivoPdf($dniSan, $clave);
            $disk = Storage::disk(self::DISK);

            if (! $disk->exists($relDir)) {
                $disk->makeDirectory($relDir);
            }

            $contenido = file_get_contents($salidaTemp);
            if ($contenido === false) {
                throw new \RuntimeException('No se pudo leer el PDF generado.');
            }

            $ok = $disk->put($relDir.'/'.$nombreFinal, $contenido);
            if (! $ok) {
                throw new \RuntimeException('No se pudo guardar el documento en el servidor.');
            }
        } catch (\Throwable $e) {
            Log::warning('doc-estudiante-autogestion: error al guardar', [
                'dni' => $dniSan ?? null,
                'clave' => $clave,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            if ($dirTemp !== null && is_dir($dirTemp)) {
                foreach (glob($dirTemp.'/*') ?: [] as $f) {
                    @unlink($f);
                }
                @rmdir($dirTemp);
            }
        }
    }

    public static function acceptAttribute(array $extensiones): string
    {
        $parts = [];
        foreach ($extensiones as $ext) {
            $ext = strtolower(trim($ext));
            if ($ext === 'jpg' || $ext === 'jpeg') {
                $parts[] = 'image/jpeg';
            } elseif ($ext === 'pdf') {
                $parts[] = 'application/pdf';
            }
            if ($ext !== '') {
                $parts[] = '.'.$ext;
            }
        }

        return implode(',', array_unique($parts));
    }

    private static function mimeAceptado(TemporaryUploadedFile $archivo, string $ext): bool
    {
        $mime = strtolower((string) $archivo->getMimeType());
        if ($mime === '') {
            return true;
        }

        if (in_array($ext, ['jpg', 'jpeg'], true)) {
            return in_array($mime, ['image/jpeg', 'image/jpg', 'image/pjpeg'], true);
        }

        if ($ext === 'pdf') {
            return in_array($mime, ['application/pdf', 'application/x-pdf'], true);
        }

        return false;
    }
}
