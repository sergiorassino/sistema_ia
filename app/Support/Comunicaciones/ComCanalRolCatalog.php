<?php

namespace App\Support\Comunicaciones;

use App\Models\ProfesorTipo;

/**
 * Claves de rol en `com_canales`: `familia` o `tipo:{id}` (profesortipo).
 */
final class ComCanalRolCatalog
{
    public const CLAVE_FAMILIA = 'familia';

    public const LABEL_FAMILIA = 'Estudiantes (Familias)';

    public const PREFIJO_TIPO = 'tipo:';

    /** Id en `profesortipo` para «Sin Rol» — no aparece en selectores de canales. */
    public const ID_TIPO_SIN_ROL = 1;

    public static function claveTipoProf(int $idTipoProf): string
    {
        return self::PREFIJO_TIPO . $idTipoProf;
    }

    public static function esClaveFamilia(string $clave): bool
    {
        return $clave === self::CLAVE_FAMILIA;
    }

    public static function esSinRolId(int $idTipoProf): bool
    {
        if ($idTipoProf <= 0 || $idTipoProf === self::ID_TIPO_SIN_ROL) {
            return true;
        }

        $tipo = ProfesorTipo::query()->whereKey($idTipoProf)->value('tipo');

        return $tipo !== null && self::esSinRolPorNombre((string) $tipo);
    }

    public static function esSinRolPorNombre(string $tipo): bool
    {
        return str_contains(mb_strtolower(trim($tipo)), 'sin rol');
    }

    /**
     * @return array{familia:bool,id_tipo_prof:?int,legacy:?string}
     */
    public static function parseClave(string $clave): array
    {
        if ($clave === self::CLAVE_FAMILIA) {
            return ['familia' => true, 'id_tipo_prof' => null, 'legacy' => null];
        }

        if (str_starts_with($clave, self::PREFIJO_TIPO)) {
            $id = (int) substr($clave, strlen(self::PREFIJO_TIPO));

            return ['familia' => false, 'id_tipo_prof' => $id > 0 ? $id : null, 'legacy' => null];
        }

        if (in_array($clave, ['directivo', 'preceptor', 'profesor', 'familia'], true)) {
            return ['familia' => $clave === 'familia', 'id_tipo_prof' => null, 'legacy' => $clave];
        }

        return ['familia' => false, 'id_tipo_prof' => null, 'legacy' => null];
    }

    /**
     * Catálogo para selectores (configuración de canales, validaciones, etiquetas).
     * Siempre lee `profesortipo` en vivo: un rol nuevo aparece al recargar la pantalla.
     *
     * @return array<string, string>  clave => etiqueta
     */
    public static function catalogo(): array
    {
        $out = [self::CLAVE_FAMILIA => self::LABEL_FAMILIA];

        foreach (ProfesorTipo::query()->orderBy('tipo')->get(['id', 'tipo']) as $pt) {
            $id = (int) $pt->id;
            $tipoStr = trim((string) $pt->tipo);
            if ($tipoStr === '' || $id === self::ID_TIPO_SIN_ROL || self::esSinRolPorNombre($tipoStr)) {
                continue;
            }
            $out[self::claveTipoProf($id)] = $tipoStr;
        }

        return $out;
    }

    /** Reservado por compatibilidad (migraciones); el catálogo ya no usa caché. */
    public static function invalidarCache(): void
    {
        // sin caché persistente
    }

    /** @return list<string> */
    public static function claves(): array
    {
        return array_keys(static::catalogo());
    }

    public static function etiqueta(string $clave): string
    {
        return static::catalogo()[$clave] ?? $clave;
    }

    public static function claveDeIdTipoProf(int $idTipoProf): ?string
    {
        if ($idTipoProf <= 0 || self::esSinRolId($idTipoProf)) {
            return null;
        }

        return self::claveTipoProf($idTipoProf);
    }

    /**
     * Rol canónico legacy (directivo|preceptor|profesor|familia) para filas no migradas.
     */
    public static function rolCanonicoLegacy(string $clave): ?string
    {
        $parsed = static::parseClave($clave);
        if ($parsed['legacy'] !== null) {
            return $parsed['legacy'];
        }
        if ($parsed['familia']) {
            return 'familia';
        }
        if ($parsed['id_tipo_prof'] !== null) {
            $tipo = ProfesorTipo::query()->whereKey($parsed['id_tipo_prof'])->value('tipo');

            return $tipo !== null
                ? \App\Comunicaciones\CanalesPolicy::normalizarRolProfesor((string) $tipo)
                : null;
        }

        return null;
    }

    /**
     * @return list<int>
     */
    public static function idsTipoProfConRolCanonicoLegacy(string $rolCanonico): array
    {
        if ($rolCanonico === 'familia') {
            return [];
        }

        $ids = [];
        foreach (ProfesorTipo::query()->get(['id', 'tipo']) as $pt) {
            $id = (int) $pt->id;
            if (self::esSinRolId($id)) {
                continue;
            }
            if (\App\Comunicaciones\CanalesPolicy::normalizarRolProfesor((string) $pt->tipo) === $rolCanonico) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
