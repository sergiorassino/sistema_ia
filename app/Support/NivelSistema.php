<?php

namespace App\Support;

use App\Models\Nivel;
use Illuminate\Support\Collection;

/**
 * Identificadores de nivel educativo en tabla `niveles` / `ento.idNivel`.
 */
final class NivelSistema
{
    /** Inicial (`niveles.id`). */
    public const INICIAL = 1;

    /** Primario (`niveles.id`). */
    public const PRIMARIO = 2;

    /** Secundario / medio (`niveles.id`). */
    public const SECUNDARIO = 3;

    /** Nivel «Administración» (cuotas, gestión transversal). */
    public const ADMINISTRACION = 5;

    public static function esInicial(int $idNivel): bool
    {
        return $idNivel === self::INICIAL;
    }

    public static function esPrimario(int $idNivel): bool
    {
        return $idNivel === self::PRIMARIO;
    }

    public static function esSecundario(int $idNivel): bool
    {
        return $idNivel === self::SECUNDARIO;
    }

    public static function esAdministracion(int $idNivel): bool
    {
        return $idNivel === self::ADMINISTRACION;
    }

    public static function esNivelPedagogico(int $idNivel): bool
    {
        return $idNivel > 0 && ! self::esAdministracion($idNivel);
    }

    /** @return Collection<int, Nivel> */
    public static function nivelesPedagogicosParaSelector(): Collection
    {
        return Nivel::query()
            ->where('id', '<', self::ADMINISTRACION)
            ->orderBy('id')
            ->get(['id', 'nivel', 'abrev']);
    }

    public static function primerIdPedagogico(): ?int
    {
        $id = Nivel::query()
            ->where('id', '<', self::ADMINISTRACION)
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public static function resolverIdPedagogico(int $candidato): ?int
    {
        if (! self::esNivelPedagogico($candidato)) {
            return null;
        }

        return Nivel::query()->whereKey($candidato)->exists() ? $candidato : null;
    }
}
