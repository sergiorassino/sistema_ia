<?php

namespace App\Support\Listados;

use App\Models\CampoLegajo;
use App\Models\Curso;
use Illuminate\Support\Collection;

/**
 * Resuelve cursos y columnas para exportaciones PDF/Excel del listado por curso.
 */
final class ListadoCursoExportParams
{
    /**
     * @param  Collection<int, Curso>  $allowedById
     * @return list<int>
     */
    public static function resolverIdsCursos(string $cursosParam, Collection $allowedById): array
    {
        $parsed = collect(explode(',', $cursosParam))
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

        if (count($out) > 200) {
            return [];
        }

        return $out;
    }

    /**
     * @param  list<string>  $pedidos  claves del catálogo desde query string
     * @return list<string>
     */
    public static function normalizarCamposSeleccion(array $pedidos, ?string $filtroCondicion): array
    {
        $campos = ListadoCursoPdfFieldCatalog::normalizeSelection($pedidos);
        $campos = CampoLegajo::aplicarVisibilidadListadoPdf($campos);

        $filtro = ListadoCursoCondicionFiltro::normalize($filtroCondicion);
        $claveCondicionCatalogo = 'condiciones.condicion';
        if (ListadoCursoCondicionFiltro::forzarColumnaCondicionEnPdf($filtro)) {
            $campos = array_values(array_filter(
                $campos,
                fn (string $c): bool => $c !== $claveCondicionCatalogo
            ));
            $campos[] = $claveCondicionCatalogo;
        }

        return $campos;
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
