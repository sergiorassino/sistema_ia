<?php

namespace App\Support\Cuotas\Siro;

use App\Models\CuotaGenerada;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Consulta de cuotas adeudadas para la subida SIRO.
 */
final class SiroSubidaBaseDeudaConsulta
{
    /**
     * @param  array<string, mixed>  $filtros  Normalizados con {@see SiroSubidaBaseDeudaFiltros::normalizarDesdeLivewire}
     * @return Collection<int, CuotaGenerada>
     */
    public static function cuotasAdeudadas(array $filtros): Collection
    {
        return self::consultaBase($filtros)
            ->with([
                'legajo:id,apellido,nombre,dni,idFamilias',
                'matricula:id,idLegajos,idTerlec,bloqmatr,bloqadmi',
                'curso:Id,cursec,c,s,idCurPlan,idTurnoClase,idNivel',
                'curso.nivel:id,nivel',
                'cuota:id,nombre,idTerlec',
                'cuota.terlec:id,ano',
            ])
            ->orderBy('cuotasgeneradas.idLegajos')
            ->orderBy('cuotasgeneradas.venc1')
            ->orderBy('cuotasgeneradas.id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<CuotaGenerada>
     */
    public static function consultaBase(array $filtros): Builder
    {
        $query = CuotaGenerada::query()
            ->whereHas('legajo')
            ->whereHas('curso', function (Builder $curso): void {
                SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($curso, 'cursos.idNivel');
            });

        SiroSubidaBaseDeudaFiltros::aplicarAConsulta($query, $filtros);

        return $query;
    }
}
