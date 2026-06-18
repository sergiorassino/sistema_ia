<?php

namespace App\Support\Mora;

use App\Models\CuotaGenerada;
use Illuminate\Database\Eloquent\Builder;

/**
 * Consulta base de cuotas adeudadas para PDFs de gestión de morosos.
 */
final class GestionMorososConsulta
{
    /**
     * Sin filtros opcionales activos: familias con saldo en cuotas vencidas al 2.º vencimiento.
     *
     * @param  array<string, mixed>  $filtros  Normalizados con {@see GestionMorososFiltros::normalizarDesdeLivewire}
     * @return Builder<CuotaGenerada>
     */
    public static function cuotasAdeudadas(array $filtros): Builder
    {
        $query = CuotaGenerada::query()->whereHas('legajo');

        GestionMorososFiltros::aplicarAConsulta($query, $filtros);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros  Normalizados con {@see GestionMorososFiltros::normalizarDesdeLivewire}
     */
    public static function tieneCuotasAdeudadas(array $filtros): bool
    {
        return self::cuotasAdeudadas($filtros)->exists();
    }
}
