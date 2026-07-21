<?php

namespace App\Support\PlanificacionesProgramas;

use App\Models\Ento;
use App\Support\EntoTerlecVerNotas;
use App\Support\NivelSistema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Almacenamiento de PDF de planificaciones y programas en el repositorio `archivos/`.
 */
final class PlanificacionesProgramasStorage
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

    /**
     * @return array{
     *     flag: string,
     *     aprob: string,
     *     obs: string,
     *     nombre: string,
     *     carpeta: string
     * }
     */
    public static function columnasPorTipo(string $tipo): array
    {
        return match ($tipo) {
            self::TIPO_PLAN => [
                'flag' => 'pp_plan',
                'aprob' => 'pp_aprobPlan',
                'obs' => 'pp_obsPlan',
                'nombre' => 'pp_nombrePlan',
                'carpeta' => self::CARPETA_PLANIFICACIONES,
            ],
            self::TIPO_PROG => [
                'flag' => 'pp_prog',
                'aprob' => 'pp_aprobProg',
                'obs' => 'pp_obsProg',
                'nombre' => 'pp_nombreProg',
                'carpeta' => self::CARPETA_PROGRAMAS,
            ],
            default => throw new \InvalidArgumentException('Tipo de documento inválido.'),
        };
    }

    /**
     * Código de colegio desde `ento.codCol` (por nivel pedagógico).
     */
    public static function codCol(int $idNivel): string
    {
        if ($idNivel < 1) {
            return '';
        }

        if (! Schema::hasColumn('ento', 'codCol')) {
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
        $cols = self::columnasPorTipo($tipo);
        $codCol = self::codCol($idNivel);
        if ($codCol === '') {
            throw new \RuntimeException('No está configurado ento.codCol para el nivel pedagógico activo.');
        }

        return $codCol
            .'/'.self::segmentoNivel($idNivel)
            .'/'.$anio
            .'/'.$cols['carpeta'];
    }

    public static function rutaRelativaArchivo(int $anio, string $tipo, int $idNivel, string $nombreArchivo): string
    {
        return self::directorioRelativo($anio, $tipo, $idNivel).'/'.ltrim($nombreArchivo, '/');
    }

    /**
     * Nombre de archivo: {codCol}_{año}_{cursec}_{materia}.pdf
     */
    public static function generarNombreArchivo(int $anio, int $idNivel, string $cursec, string $materia): string
    {
        $codCol = self::codCol($idNivel);
        if ($codCol === '') {
            throw new \RuntimeException('No está configurado ento.codCol para el nivel pedagógico activo.');
        }

        $cursecLimpio = self::sanitizarSegmentoNombre($cursec, 'curso');
        $materiaLimpia = self::sanitizarSegmentoNombre($materia, 'materia');

        return $codCol.'_'.$anio.'_'.$cursecLimpio.'_'.$materiaLimpia.'.pdf';
    }

    private static function sanitizarSegmentoNombre(string $texto, string $fallback): string
    {
        $limpio = preg_replace('/\s+/', ' ', trim($texto)) ?? '';
        $limpio = str_replace(' ', '_', $limpio);
        $limpio = preg_replace('/[^A-Za-z0-9_-]/u', '_', $limpio) ?? $fallback;
        $limpio = trim($limpio, '_');

        return $limpio !== '' ? $limpio : $fallback;
    }

    public static function urlPublica(int $anio, string $tipo, int $idNivel, string $nombreArchivo): string
    {
        $baseConfig = config('tenant.programas_examen.base_url');
        $base = is_string($baseConfig) && trim($baseConfig) !== ''
            ? rtrim(trim($baseConfig), '/')
            : rtrim((string) config('app.url', ''), '/').'/archivos';

        $ruta = self::rutaRelativaArchivo($anio, $tipo, $idNivel, $nombreArchivo);

        return $base.'/'.implode('/', array_map('rawurlencode', explode('/', $ruta)));
    }

    public static function guardarPdf(int $anio, string $tipo, int $idNivel, string $nombreArchivo, TemporaryUploadedFile|UploadedFile $archivo): void
    {
        $ruta = self::rutaRelativaArchivo($anio, $tipo, $idNivel, $nombreArchivo);
        $directorio = dirname($ruta);

        Storage::disk(self::DISK)->makeDirectory($directorio);
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

        $maxKb = max(512, (int) config('planificaciones_programas.max_kb', 20480));
        if ($archivo->getSize() > ($maxKb * 1024)) {
            return 'El archivo supera el tamaño máximo permitido ('.number_format($maxKb / 1024, 0).' MB).';
        }

        return null;
    }
}
