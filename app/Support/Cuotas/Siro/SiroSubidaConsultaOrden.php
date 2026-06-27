<?php

namespace App\Support\Cuotas\Siro;

use App\Models\CuotaGenerada;
use Illuminate\Database\Eloquent\Builder;

/**
 * Orden de grilla previa en módulos de subida SIRO: apellido, nombre, orden de cuota.
 */
final class SiroSubidaConsultaOrden
{
    /**
     * @param  Builder<CuotaGenerada>  $query
     * @return Builder<CuotaGenerada>
     */
    public static function aplicar(Builder $query): Builder
    {
        return $query
            ->join('legajos', 'legajos.id', '=', 'cuotasgeneradas.idLegajos')
            ->join('cuotas', 'cuotas.id', '=', 'cuotasgeneradas.idCuotas')
            ->select('cuotasgeneradas.*')
            ->orderBy('legajos.apellido')
            ->orderBy('legajos.nombre')
            ->orderBy('cuotas.orden')
            ->orderBy('cuotasgeneradas.id');
    }
}
