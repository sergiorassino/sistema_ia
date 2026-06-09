<?php

namespace App\Support\Listados;

use App\Models\CampoProfesor;
use App\Models\ProfesorTipo;
use Illuminate\Support\Collection;

/**
 * Resuelve roles y columnas para exportaciones PDF/Excel del listado de docentes.
 */
final class ListadoDocentesExportParams
{
    /**
     * @param  Collection<int, ProfesorTipo>  $allowedById
     * @return list<int>
     */
    public static function resolverIdsRoles(string $rolesParam, Collection $allowedById): array
    {
        return ListadoDocentesConsulta::resolverIdsRoles($rolesParam, $allowedById);
    }

    /**
     * @param  list<string>  $pedidos
     * @return list<string>
     */
    public static function normalizarCamposSeleccion(array $pedidos): array
    {
        $campos = ListadoDocentesPdfFieldCatalog::normalizeSelection($pedidos);

        return CampoProfesor::aplicarVisibilidadListadoPdf($campos);
    }

    public static function normalizarSubtitulo(?string $valor): string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return '';
        }

        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto) ?? '';

        return mb_substr($texto, 0, 200);
    }
}
