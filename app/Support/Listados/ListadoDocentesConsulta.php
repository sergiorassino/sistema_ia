<?php

namespace App\Support\Listados;

use App\Models\ProfesorTipo;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Support\Collection;

/**
 * Roles y docentes permitidos para listados de personal docente (PDF/Excel).
 */
final class ListadoDocentesConsulta
{
    /** IdTipoProf que identifica «Sin Rol». */
    public const ID_TIPO_SIN_ROL = 1;

    /** @return Collection<int, ProfesorTipo> */
    public static function rolesDisponibles(): Collection
    {
        return ProfesorTipo::query()
            ->orderBy('tipo')
            ->get(['id', 'tipo']);
    }

    /**
     * @param  Collection<int, ProfesorTipo>  $allowedById
     * @return list<int>
     */
    public static function resolverIdsRoles(string $rolesParam, Collection $allowedById): array
    {
        $parsed = collect(explode(',', $rolesParam))
            ->map(fn ($v) => (int) trim((string) $v))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $out = [];
        foreach ($parsed as $id) {
            if ($allowedById->has($id) && ! in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        if (count($out) > 50) {
            return [];
        }

        return $out;
    }

    public static function idNivelLegajos(): int
    {
        return (int) (SchoolAlcancePedagogico::idNivelLegajosDocente() ?? 0);
    }

    /**
     * @param  list<int>  $roleIds
     */
    public static function incluyeSinRolEnRoles(array $roleIds): bool
    {
        return in_array(self::ID_TIPO_SIN_ROL, $roleIds, true);
    }

    /**
     * Texto de cabecera PDF/Excel con los roles incluidos en el listado.
     *
     * @param  Collection<int, ProfesorTipo>  $rolesDisponibles
     * @param  list<int>  $roleIdsSeleccionados
     */
    public static function etiquetaRolesSeleccionados(Collection $rolesDisponibles, array $roleIdsSeleccionados): string
    {
        if ($roleIdsSeleccionados === []) {
            return '—';
        }

        $flip = array_flip($roleIdsSeleccionados);
        $nombres = $rolesDisponibles
            ->filter(fn (ProfesorTipo $r) => isset($flip[(int) $r->id]))
            ->map(fn (ProfesorTipo $r) => trim((string) $r->tipo))
            ->filter(fn (string $n) => $n !== '')
            ->values()
            ->all();

        if ($nombres === []) {
            return '—';
        }

        if (count($nombres) === $rolesDisponibles->count()) {
            return 'Todos';
        }

        return implode(', ', $nombres);
    }
}
