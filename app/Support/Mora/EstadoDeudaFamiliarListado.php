<?php

namespace App\Support\Mora;

use App\Livewire\Abm\Legajos\LegajoFamilia;
use App\Models\Familia;
use App\Models\Legajo;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Listado de familias con estudiantes matriculados en el ciclo lectivo activo.
 */
final class EstadoDeudaFamiliarListado
{
    public const POR_PAGINA = 25;

    /**
     * @return LengthAwarePaginator<int, Familia>
     */
    public static function listarFamilias(string $termino = '', int $porPagina = self::POR_PAGINA): LengthAwarePaginator
    {
        $query = Familia::query()
            ->whereKeyNot(LegajoFamilia::ID_FAMILIA_SIN_ASIGNAR)
            ->whereHas('legajos', fn ($q) => self::aplicarFiltroLegajosMatriculados($q))
            ->with(['legajos' => function ($q) {
                $idTerlec = (int) schoolCtx()->idTerlec;

                self::aplicarFiltroLegajosMatriculados($q);
                $q->orderBy('apellido')
                    ->orderBy('nombre')
                    ->orderBy('id')
                    ->select(['id', 'apellido', 'nombre', 'dni', 'idFamilias'])
                    ->with(['matriculas' => function ($m) use ($idTerlec) {
                        $m->where('idTerlec', $idTerlec);
                        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($m, 'idNivel');
                        $m->with([
                            'curso:Id,cursec,c,s,idCurPlan,idTurnoClase,idNivel',
                            'curso.curplan:id,curPlanCurso',
                            'curso.turnoClase:id,nombre',
                        ]);
                    }]);
            }]);

        $termino = trim($termino);
        if ($termino !== '') {
            $query->where(function (Builder $sub) use ($termino) {
                $sub->where('apellido', 'like', "%{$termino}%")
                    ->orWhere('responsable', 'like', "%{$termino}%")
                    ->orWhereHas('legajos', function ($leg) use ($termino) {
                        self::aplicarFiltroLegajosMatriculados($leg);
                        $leg->buscar($termino);
                    });
            });
        }

        return $query
            ->orderBy('apellido')
            ->orderBy('id')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * @param  Builder<Legajo>|Relation  $query
     */
    private static function aplicarFiltroLegajosMatriculados(Builder|Relation $query): void
    {
        $idTerlec = (int) schoolCtx()->idTerlec;

        $query->whereHas('matriculas', function (Builder $mat) use ($idTerlec) {
            $mat->where('idTerlec', $idTerlec);
            SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($mat, 'idNivel');
        });
    }

    public static function apellidoNombre(Legajo $legajo): string
    {
        $apellido = trim((string) ($legajo->apellido ?? ''));
        $nombre = trim((string) ($legajo->nombre ?? ''));

        if ($apellido === '' && $nombre === '') {
            return '';
        }

        if ($apellido === '') {
            return mb_strtoupper($nombre);
        }

        if ($nombre === '') {
            return mb_strtoupper($apellido);
        }

        return mb_strtoupper($apellido.', '.$nombre);
    }

    public static function cursoCicloActivo(Legajo $legajo): string
    {
        $matricula = $legajo->matriculas->first();

        return mb_strtoupper(trim((string) ($matricula?->curso?->nombreParaListado() ?? '')));
    }
}
