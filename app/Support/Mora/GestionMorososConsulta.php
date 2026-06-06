<?php

namespace App\Support\Mora;

use App\Livewire\Abm\Legajos\LegajoFamilia;
use App\Models\CuotaGenerada;
use Illuminate\Database\Eloquent\Builder;

/**
 * Consulta base de cuotas adeudadas para PDFs de gestión de morosos.
 */
final class GestionMorososConsulta
{
    /**
     * Sin filtros opcionales activos: todas las familias con saldo (mismo criterio que el listado legacy).
     *
     * @param  array<string, mixed>  $filtros  Normalizados con {@see GestionMorososFiltros::normalizarDesdeLivewire}
     * @return Builder<CuotaGenerada>
     */
    public static function cuotasAdeudadas(array $filtros): Builder
    {
        $query = CuotaGenerada::query()
            ->whereHas('legajo', fn (Builder $leg) => $leg->whereKeyNot(LegajoFamilia::ID_FAMILIA_SIN_ASIGNAR)
                ->where('idFamilias', '>', 0));

        GestionMorososFiltros::aplicarAConsulta($query, $filtros);

        return $query;
    }
}
