<?php

namespace App\Support\DocPp;

use App\Models\Ento;
use App\Support\NivelSistema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Repositorio de PDF de planificaciones y programas (módulo doc_pp).
 *
 * Nombre canónico fijo:
 * {codCol}_{anio}_{nivel}_{cursec}_{materia}_{Plan|Prog}.pdf
 */
final class DocPpStorage
{
    public const TIPO_PLAN = 'plan';

    public const TIPO_PROG = 'prog';

    public const CARPETA_PLANIFICACIONES = 'planificaciones';

    public const CARPETA_PROGRAMAS = 'programas';

    public const DISK = 'archivos';

    /** @return list<string> */
    public static function tiposValidos(): array
    {
        return [self::TIPO_PLAN, self::TIPO_PROG];
    }

    public static function tipoValido(string $tipo): bool
    {
        return in_array($tipo, self::tiposValidos(), true);
    }

    public static function carpetaPorTipo(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_PLAN => self::CARPETA_PLANIFICACIONES,
            self::TIPO_PROG => self::CARPETA_PROGRAMAS,
            default => throw new \InvalidArgumentException('Tipo de documento inválido.'),
        };
    }

    public static function sufijoTipo(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_PLAN => 'Plan',
            self::TIPO_PROG => 'Prog',
            default => throw new \InvalidArgumentException('Tipo de documento inválido.'),
        };
    }

    public static function codCol(int $idNivel): string
    {
        if ($idNivel < 1 || ! Schema::hasColumn('ento', 'codCol')) {
            return '';
        }

        return trim((string) (Ento::query()->where('idNivel', $idNivel)->value('codCol') ?? ''));
    }

    public static function segmentoNivel(int $idNivel): string
    {
        return NivelSistema::segmentoArchivos($idNivel);
    }

    public static function directorioRelativo(int $anio, string $tipo, int $idNivel): string
    {
        $codCol = self::codCol($idNivel);
        if ($codCol === '') {
            throw new \RuntimeException('No está configurado ento.codCol para el nivel pedagógico activo.');
        }

        return $codCol
            .'/'.self::segmentoNivel($idNivel)
            .'/'.$anio
            .'/'.self::carpetaPorTipo($tipo);
    }

    public static function rutaRelativaArchivo(int $anio, string $tipo, int $idNivel, string $nombreArchivo): string
    {
        return self::directorioRelativo($anio, $tipo, $idNivel).'/'.ltrim($nombreArchivo, '/');
    }

    /**
     * {codCol}_{anio}_{nivel}_{cursec}_{materia}_{Plan|Prog}.pdf
     */
    public static function generarNombreArchivo(
        int $anio,
        int $idNivel,
        string $tipo,
        string $cursec,
        string $materia,
    ): string {
        $codCol = self::codCol($idNivel);
        if ($codCol === '') {
            throw new \RuntimeException('No está configurado ento.codCol para el nivel pedagógico activo.');
        }

        $partes = [
            self::sanitizarSegmentoNombre($codCol, 'COL'),
            (string) $anio,
            self::segmentoNivel($idNivel),
            self::sanitizarSegmentoNombre($cursec, 'curso'),
            self::sanitizarSegmentoNombre($materia, 'materia'),
            self::sufijoTipo($tipo),
        ];

        return implode('_', $partes).'.pdf';
    }

    private static function sanitizarSegmentoNombre(string $texto, string $fallback): string
    {
        $limpio = mb_strtoupper(trim($texto), 'UTF-8');
        $limpio = strtr($limpio, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O',
            'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U', 'ü' => 'U', 'ñ' => 'N',
        ]);
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $limpio);
        if (is_string($converted) && $converted !== '') {
            $limpio = $converted;
        }
        $limpio = preg_replace('/\s+/', ' ', $limpio) ?? '';
        $limpio = str_replace(' ', '_', $limpio);
        $limpio = preg_replace('/[^A-Za-z0-9_-]/', '_', $limpio) ?? $fallback;
        $limpio = preg_replace('/_+/', '_', $limpio) ?? $limpio;
        $limpio = trim($limpio, '_');

        return $limpio !== '' ? $limpio : $fallback;
    }

    /**
     * Base pública: tenant.programas_examen.base_url (legado) → disco archivos.url → {APP_URL}/archivos.
     * Disco físico: siempre public/archivos de este sistema.
     */
    public static function baseUrlPublica(): string
    {
        $tenant = config('tenant.programas_examen.base_url');
        if (is_string($tenant) && trim($tenant) !== '') {
            return rtrim(trim($tenant), '/');
        }

        $diskUrl = config('filesystems.disks.archivos.url');
        if (is_string($diskUrl) && trim($diskUrl) !== '') {
            return rtrim(trim($diskUrl), '/');
        }

        return rtrim((string) config('app.url', ''), '/').'/archivos';
    }

    public static function urlPublica(int $anio, string $tipo, int $idNivel, string $nombreArchivo): string
    {
        $ruta = self::rutaRelativaArchivo($anio, $tipo, $idNivel, $nombreArchivo);

        return self::baseUrlPublica().'/'.implode('/', array_map('rawurlencode', explode('/', $ruta)));
    }

    public static function guardarPdf(
        int $anio,
        string $tipo,
        int $idNivel,
        string $nombreArchivo,
        TemporaryUploadedFile|UploadedFile $archivo,
    ): void {
        $ruta = self::rutaRelativaArchivo($anio, $tipo, $idNivel, $nombreArchivo);
        Storage::disk(self::DISK)->makeDirectory(dirname($ruta));
        Storage::disk(self::DISK)->put($ruta, $archivo->get());
    }

    public static function eliminarArchivo(int $anio, string $tipo, int $idNivel, string $nombreArchivo): void
    {
        $nombre = trim($nombreArchivo);
        if ($nombre === '') {
            return;
        }

        $ruta = self::rutaRelativaArchivo($anio, $tipo, $idNivel, $nombre);
        $disk = Storage::disk(self::DISK);
        if ($disk->exists($ruta)) {
            $disk->delete($ruta);
        }
    }

    public static function existeArchivo(int $anio, string $tipo, int $idNivel, string $nombreArchivo): bool
    {
        $nombre = trim($nombreArchivo);
        if ($nombre === '') {
            return false;
        }

        return Storage::disk(self::DISK)->exists(self::rutaRelativaArchivo($anio, $tipo, $idNivel, $nombre));
    }

    public static function validarPdf(TemporaryUploadedFile|UploadedFile|null $archivo): ?string
    {
        if (! $archivo instanceof TemporaryUploadedFile && ! $archivo instanceof UploadedFile) {
            return 'Debe seleccionar un archivo PDF.';
        }

        if (! $archivo->isValid()) {
            return 'La subida del archivo no finalizó correctamente. Intente nuevamente.';
        }

        $mime = strtolower((string) $archivo->getMimeType());
        $extension = strtolower((string) $archivo->getClientOriginalExtension());

        if ($extension !== 'pdf' && ! in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
            return 'Solo se permiten archivos PDF.';
        }

        $maxKb = max(512, (int) config('doc_pp.max_kb', 20480));
        if ($archivo->getSize() > ($maxKb * 1024)) {
            return 'El archivo supera el tamaño máximo permitido ('.number_format($maxKb / 1024, 0).' MB).';
        }

        return null;
    }
}
