<?php

namespace App\Support\Cuotas\Siro;

use App\Models\CuotaGenerada;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Consulta de cuotas vencidas (2.º venc.) para re-subida SIRO.
 */
final class SiroCuponesVencidosConsulta
{
    /**
     * @param  array<string, mixed>  $filtros  Normalizados con {@see SiroCuponesVencidosFiltros::normalizarDesdeLivewire}
     * @return Collection<int, CuotaGenerada>
     */
    public static function cuotasAdeudadas(array $filtros): Collection
    {
        $query = self::consultaBase($filtros)
            ->with([
                'legajo:id,apellido,nombre,dni,idFamilias',
                'matricula:id,idLegajos,idTerlec,bloqmatr,bloqadmi',
                'curso:Id,cursec,c,s,idCurPlan,idTurnoClase,idNivel',
                'curso.nivel:id,nivel',
                'cuota:id,nombre,idTerlec,orden',
                'cuota.terlec:id,ano',
            ]);

        return SiroSubidaConsultaOrden::aplicar($query)->get();
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

        SiroCuponesVencidosFiltros::aplicarAConsulta($query, $filtros);

        return $query;
    }
}
