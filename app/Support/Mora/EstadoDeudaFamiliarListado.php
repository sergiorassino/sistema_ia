<?php

namespace App\Support\Mora;

use App\Livewire\Abm\Legajos\LegajoFamilia;
use App\Models\Familia;
use App\Models\Legajo;
use App\Support\NivelSistema;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Listado de familias con estudiantes matriculados en el ciclo lectivo activo.
 */
final class EstadoDeudaFamiliarListado
{
    public const POR_PAGINA = 25;

    /**
     * Niveles pedagógicos del selector (Inicial, Primario, Secundario, …).
     *
     * @return Collection<int, \App\Models\Nivel>
     */
    public static function nivelesParaSelector(): Collection
    {
        return NivelSistema::nivelesPedagogicosParaSelector();
    }

    /**
     * @param  Builder<Legajo>|Relation  $query
     */
    public static function aplicarFiltroCuotasAdeudadas(Builder|Relation $query): void
    {
        $query->whereHas('cuotasGeneradas', function (Builder $cuota) {
            $cuota->where('faltapa', '>', 0)
                ->where('importe', '>', 0);
        });
    }

    /**
     * @return LengthAwarePaginator<int, Familia>
     */
    public static function listarFamilias(string $termino = '', int $idNivel = 0, bool $soloConDeuda = false, int $porPagina = self::POR_PAGINA): LengthAwarePaginator
    {
        $idNivel = self::normalizarIdNivel($idNivel);

        $query = Familia::query()
            ->whereKeyNot(LegajoFamilia::ID_FAMILIA_SIN_ASIGNAR)
            ->whereHas('legajos', function ($q) use ($idNivel, $soloConDeuda) {
                self::aplicarFiltroLegajosMatriculados($q, $idNivel);
                if ($soloConDeuda) {
                    self::aplicarFiltroCuotasAdeudadas($q);
                }
            })
            ->with(['legajos' => function ($q) use ($idNivel, $soloConDeuda) {
                $idTerlec = (int) schoolCtx()->idTerlec;

                self::aplicarFiltroLegajosMatriculados($q, $idNivel);
                if ($soloConDeuda) {
                    self::aplicarFiltroCuotasAdeudadas($q);
                }
                $q->orderBy('apellido')
                    ->orderBy('nombre')
                    ->orderBy('id')
                    ->select(['id', 'apellido', 'nombre', 'dni', 'idFamilias'])
                    ->with(['matriculas' => function ($m) use ($idTerlec, $idNivel) {
                        $m->where('idTerlec', $idTerlec);
                        self::aplicarFiltroNivelMatricula($m, $idNivel);
                        $m->with([
                            'curso:Id,cursec,c,s,idCurPlan,idTurnoClase,idNivel',
                            'curso.curplan:id,curPlanCurso',
                            'curso.turnoClase:id,nombre',
                        ]);
                    }]);
            }]);

        $termino = trim($termino);
        if ($termino !== '') {
            $query->where(function (Builder $sub) use ($termino, $idNivel) {
                $sub->where('apellido', 'like', "%{$termino}%")
                    ->orWhere('responsable', 'like', "%{$termino}%")
                    ->orWhereHas('legajos', function ($leg) use ($termino, $idNivel) {
                        self::aplicarFiltroLegajosMatriculados($leg, $idNivel);
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

    public static function normalizarIdNivel(int $idNivel): int
    {
        if ($idNivel < 1) {
            return 0;
        }

        $ids = self::nivelesParaSelector()->pluck('id')->map(fn ($id) => (int) $id);

        return $ids->contains($idNivel) ? $idNivel : 0;
    }

    /**
     * @param  Builder<Legajo>|Relation  $query
     */
    private static function aplicarFiltroLegajosMatriculados(Builder|Relation $query, int $idNivel = 0): void
    {
        $idTerlec = (int) schoolCtx()->idTerlec;

        $query->whereHas('matriculas', function (Builder $mat) use ($idTerlec, $idNivel) {
            $mat->where('idTerlec', $idTerlec);
            self::aplicarFiltroNivelMatricula($mat, $idNivel);
        });
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
