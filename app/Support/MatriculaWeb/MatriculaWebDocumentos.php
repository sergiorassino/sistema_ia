<?php

namespace App\Support\MatriculaWeb;

use App\Models\Ento;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * PDFs de aceptación para matrícula web (portal familia).
 *
 * En `ento`, el nombre del archivo vigente por documento se guarda en
 * `documAcept1` … `documAcept4` (puede cambiar cada año lectivo).
 * El binario se almacena en storage/public bajo el directorio del nivel.
 */
final class MatriculaWebDocumentos
{
    public const COMPROMISO = 'compromiso';

    public const AEC = 'aec';

    public const NORMAS = 'normas';

    public const TRASLADO = 'traslado';

    public const MAX_BYTES = 15 * 1024 * 1024;

    /**
     * @return array<string, array{
     *     label: string,
     *     titulo_corto: string,
     *     docum_column: string,
     *     acept_matricula: string
     * }>
     */
    public static function definiciones(): array
    {
        return [
            self::COMPROMISO => [
                'label' => 'Compromiso educativo',
                'titulo_corto' => 'Compromiso educativo',
                'docum_column' => 'documAcept1',
                'acept_matricula' => 'acept1',
            ],
            self::AEC => [
                'label' => 'AEC (Acuerdo escolar de convivencia)',
                'titulo_corto' => 'AEC',
                'docum_column' => 'documAcept2',
                'acept_matricula' => 'acept2',
            ],
            self::NORMAS => [
                'label' => 'Normativas del nivel',
                'titulo_corto' => 'Normas del nivel',
                'docum_column' => 'documAcept3',
                'acept_matricula' => 'acept3',
            ],
            self::TRASLADO => [
                'label' => 'Autorización para el traslado por espacios institucionales',
                'titulo_corto' => 'Autorización de traslado',
                'docum_column' => 'documAcept4',
                'acept_matricula' => 'acept4',
            ],
        ];
    }

    /** @return list<string> */
    public static function claves(): array
    {
        return array_keys(self::definiciones());
    }

    public static function definicion(string $clave): ?array
    {
        return self::definiciones()[$clave] ?? null;
    }

    public static function claveValida(string $clave): bool
    {
        return isset(self::definiciones()[$clave]);
    }

    public static function etiquetaAceptado(string $clave): string
    {
        return match ($clave) {
            self::COMPROMISO => 'COMPROMISO EDUCATIVO ACEPTADO',
            self::AEC => 'AEC (ACUERDO ESCOLAR DE CONVIVENCIA) ACEPTADO',
            self::NORMAS => 'NORMAS DE NIVEL ACEPTADAS',
            self::TRASLADO => 'AUTORIZACIÓN PARA TRASLADO POR LOS ESPACIOS INSTITUCIONALES ACEPTADA',
            default => 'DOCUMENTO ACEPTADO',
        };
    }

    /** Título en formulario de actualización de datos cuando el documento aún no está aceptado. */
    public static function etiquetaPendiente(string $clave): string
    {
        return match ($clave) {
            self::COMPROMISO => 'ACEPTO el COMPROMISO EDUCATIVO',
            self::AEC => 'ACEPTO el AEC (ACUERDO EDUCATIVO ESCOLAR)',
            self::NORMAS => 'ACEPTO las NORMATIVAS DEL NIVEL',
            self::TRASLADO => 'AUTORIZO PARA EL TRASLADO POR LOS ESPACIOS INSTITUCIONALES',
            default => 'ACEPTAR DOCUMENTO',
        };
    }

    public static function storageDir(int $idNivel): string
    {
        return 'ento/docum-acept/'.tenantSlug().'/nivel-'.$idNivel;
    }

    /**
     * Nombre de archivo registrado en `ento` (documAceptN).
     */
    public static function nombreRegistrado(string $clave, ?int $idNivel = null): ?string
    {
        $def = self::definicion($clave);
        if ($def === null) {
            return null;
        }

        $idNivel = $idNivel ?? self::resolverIdNivel();
        if ($idNivel <= 0) {
            return null;
        }

        $col = $def['docum_column'];
        if (! Schema::hasTable('ento') || ! Schema::hasColumn('ento', $col)) {
            return null;
        }

        $nombre = trim((string) Ento::query()->where('idNivel', $idNivel)->value($col));

        return $nombre !== '' ? $nombre : null;
    }

    /**
     * Ruta relativa al disco `public` del PDF, o null si no hay nombre o no existe el archivo.
     */
    public static function pathAlmacenado(string $clave, ?int $idNivel = null): ?string
    {
        $nombre = self::nombreRegistrado($clave, $idNivel);
        if ($nombre === null) {
            return null;
        }

        $idNivel = $idNivel ?? self::resolverIdNivel();
        if ($idNivel <= 0) {
            return null;
        }

        $path = self::storageDir($idNivel).'/'.self::nombreArchivoSeguro($nombre);
        $disk = Storage::disk('public');

        return $disk->exists($path) ? $path : null;
    }

    /**
     * @return array{nombre: ?string, path: ?string, existe: bool}
     */
    public static function estadoDocumento(string $clave, ?int $idNivel = null): array
    {
        $nombre = self::nombreRegistrado($clave, $idNivel);
        $path = self::pathAlmacenado($clave, $idNivel);

        return [
            'nombre' => $nombre,
            'path' => $path,
            'existe' => $path !== null,
        ];
    }

    /**
     * Guarda el nombre en disco y devuelve el valor para `documAceptN`.
     */
    public static function nombreArchivoSeguro(string $nombreOriginal): string
    {
        $base = basename(str_replace(['\\', '/'], '', $nombreOriginal));
        $base = preg_replace('/[^\pL\pN._\- ()]/u', '_', $base) ?? 'documento.pdf';
        $base = trim($base, "._ \t\n\r\0\x0B");
        if ($base === '') {
            $base = 'documento.pdf';
        }
        if (! str_ends_with(strtolower($base), '.pdf')) {
            $base .= '.pdf';
        }

        return Str::limit($base, 200, '');
    }

    public static function eliminarArchivoPorNombre(int $idNivel, ?string $nombreRegistrado): void
    {
        $nombre = trim((string) $nombreRegistrado);
        if ($nombre === '') {
            return;
        }

        $path = self::storageDir($idNivel).'/'.self::nombreArchivoSeguro($nombre);
        $disk = Storage::disk('public');
        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    private static function resolverIdNivel(): int
    {
        if (auth('alumno')->check()) {
            return (int) (studentCtx()->idNivel ?? 0);
        }

        return (int) (schoolCtx()->idNivel ?? 0);
    }
}
