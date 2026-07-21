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

    /** Terciario (`niveles.id`). */
    public const TERCIARIO = 4;

    /** Nivel «Administración» (cuotas, gestión transversal). */
    public const ADMINISTRACION = 5;

    /** Adultos (`niveles.id`; solo algunos colegios). */
    public const ADULTOS = 6;

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

    /**
     * IDs configurados en `tenant.login.niveles_ids`, o `null` si no hay filtro (todos en BD).
     *
     * @return list<int>|null
     */
    public static function idsNivelesLoginConfigurados(): ?array
    {
        $ids = config('tenant.login.niveles_ids');

        if (! is_array($ids) || $ids === []) {
            return null;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** @return Collection<int, Nivel> */
    public static function nivelesParaLogin(): Collection
    {
        $query = Nivel::query()->orderBy('id');

        $ids = self::idsNivelesLoginConfigurados();
        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get(['id', 'nivel', 'abrev']);
    }

    public static function nivelPermitidoEnLogin(int $idNivel): bool
    {
        if ($idNivel <= 0) {
            return false;
        }

        return self::nivelesParaLogin()->contains('id', $idNivel);
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

    /**
     * Segmento de carpeta en el repositorio `archivos/{codCol}/{segmento}/…`.
     */
    public static function segmentoArchivos(int $idNivel): string
    {
        return match ($idNivel) {
            self::INICIAL => 'inic',
            self::PRIMARIO => 'prim',
            self::SECUNDARIO => 'secu',
            self::TERCIARIO => 'terc',
            default => 'secu',
        };
    }

    public static function resolverIdPedagogico(int $candidato): ?int
    {
        if (! self::esNivelPedagogico($candidato)) {
            return null;
        }

        return Nivel::query()->whereKey($candidato)->exists() ? $candidato : null;
    }
}
