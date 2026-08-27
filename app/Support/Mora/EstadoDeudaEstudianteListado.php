<?php

namespace App\Support\Mora;

use App\Livewire\Abm\Legajos\LegajoFamilia;
use App\Models\Familia;
use App\Models\Legajo;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Listado de estudiantes matriculados en el ciclo lectivo activo (con o sin familia).
 */
final class EstadoDeudaEstudianteListado
{
    public const POR_PAGINA = 50;

    /**
     * @return Collection<int, \App\Models\Nivel>
     */
    public static function nivelesParaSelector(): Collection
    {
        return EstadoDeudaFamiliarListado::nivelesParaSelector();
    }

    public static function normalizarIdNivel(int $idNivel): int
    {
        return EstadoDeudaFamiliarListado::normalizarIdNivel($idNivel);
    }

    /**
     * @return LengthAwarePaginator<int, Legajo>
     */
    public static function listarEstudiantes(string $termino = '', int $idNivel = 0, bool $soloConDeuda = false, int $porPagina = self::POR_PAGINA): LengthAwarePaginator
    {
        $idNivel = self::normalizarIdNivel($idNivel);
        $idTerlec = (int) schoolCtx()->idTerlec;

        $query = Legajo::query()
            ->select(['id', 'apellido', 'nombre', 'dni', 'idFamilias'])
            ->whereHas('matriculas', function (Builder $mat) use ($idTerlec, $idNivel) {
                $mat->where('idTerlec', $idTerlec);
                self::aplicarFiltroNivelMatricula($mat, $idNivel);
            });

        if ($soloConDeuda) {
            EstadoDeudaFamiliarListado::aplicarFiltroCuotasAdeudadas($query);
        }

        $query
            ->with([
                'familia:id,apellido,responsable',
                'matriculas' => function ($m) use ($idTerlec, $idNivel) {
                    $m->where('idTerlec', $idTerlec);
                    self::aplicarFiltroNivelMatricula($m, $idNivel);
                    $m->with([
                        'curso:Id,cursec,c,s,idCurPlan,idTurnoClase,idNivel',
                        'curso.curplan:id,curPlanCurso',
                        'curso.turnoClase:id,nombre',
                    ]);
                },
            ]);

        $termino = trim($termino);
        if ($termino !== '') {
            $query->where(function (Builder $sub) use ($termino) {
                $sub->buscar($termino)
                    ->orWhereHas('familia', function ($fam) use ($termino) {
                        $fam->whereKeyNot(LegajoFamilia::ID_FAMILIA_SIN_ASIGNAR)
                            ->where(function (Builder $f) use ($termino) {
                                $f->where('apellido', 'like', "%{$termino}%")
                                    ->orWhere('responsable', 'like', "%{$termino}%");
                            });
                    });
            });
        }

        return $query
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate($porPagina)
            ->withQueryString();
    }

    public static function estudianteEnAlcance(int $idLegajo): ?Legajo
    {
        if ($idLegajo <= 0) {
            return null;
        }

        $idTerlec = (int) schoolCtx()->idTerlec;

        return Legajo::query()
            ->whereKey($idLegajo)
            ->whereHas('matriculas', function (Builder $mat) use ($idTerlec) {
                $mat->where('idTerlec', $idTerlec);
                self::aplicarFiltroNivelMatricula($mat, 0);
            })
            ->with(['familia:id,apellido,responsable'])
            ->first(['id', 'apellido', 'nombre', 'dni', 'idFamilias']);
    }

    public static function familiaAsignada(?Familia $familia): ?Familia
    {
        if ($familia === null) {
            return null;
        }

        if ((int) $familia->id === LegajoFamilia::ID_FAMILIA_SIN_ASIGNAR) {
            return null;
        }

        return $familia;
    }

    public static function apellidoNombre(Legajo $legajo): string
    {
        return EstadoDeudaFamiliarListado::apellidoNombre($legajo);
    }

    public static function cursoCicloActivo(Legajo $legajo): string
    {
        return EstadoDeudaFamiliarListado::cursoCicloActivo($legajo);
    }

    /**
     * @param  Builder<\App\Models\Matricula>|Relation  $query
     */
    private static function aplicarFiltroNivelMatricula(Builder|Relation $query, int $idNivel): void
    {
        if ($idNivel > 0) {
            $query->where('idNivel', $idNivel);

            return;
        }

        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'idNivel');
    }
}
