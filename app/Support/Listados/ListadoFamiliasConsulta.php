<?php

namespace App\Support\Listados;

use App\Livewire\Abm\Legajos\LegajoFamilia;
use App\Models\Familia;
use App\Models\Legajo;
use App\Support\NivelSistema;
use App\Support\OrdenAlfabeticoEstudiante;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Familias con estudiantes matriculados en el ciclo lectivo activo.
 */
final class ListadoFamiliasConsulta
{
    public const POR_PAGINA = 50;

    /**
     * @return Collection<int, \App\Models\Nivel>
     */
    public static function nivelesParaSelector(): Collection
    {
        return NivelSistema::nivelesPedagogicosParaSelector();
    }

    /**
     * @return Builder<Familia>
     */
    public static function consultar(string $termino = '', int $idNivel = 0): Builder
    {
        $idNivel = self::idNivelEfectivo($idNivel);

        $query = Familia::query()
            ->whereKeyNot(LegajoFamilia::ID_FAMILIA_SIN_ASIGNAR)
            ->whereHas('legajos', function ($q) use ($idNivel) {
                self::aplicarFiltroLegajosMatriculados($q, $idNivel);
            })
            ->with(['legajos' => function ($q) use ($idNivel) {
                $idTerlec = (int) schoolCtx()->idTerlec;

                self::aplicarFiltroLegajosMatriculados($q, $idNivel);
                OrdenAlfabeticoEstudiante::orderBy($q, 'apellido', 'nombre');
                $q->orderBy('id')
                    ->select(['id', 'apellido', 'nombre', 'dni', 'idFamilias'])
                    ->with(['matriculas' => function ($m) use ($idTerlec, $idNivel) {
                        $m->where('idTerlec', $idTerlec);
                        self::aplicarFiltroNivelMatricula($m, $idNivel);
                        $m->with([
                            'curso:Id,cursec,c,s,idNivel',
                        ]);
                    }]);
            }]);

        $termino = trim($termino);
        if ($termino !== '') {
            $query->where(function (Builder $sub) use ($termino, $idNivel) {
                $sub->where('apellido', 'like', '%'.$termino.'%')
                    ->orWhere('responsable', 'like', '%'.$termino.'%')
                    ->orWhere('email', 'like', '%'.$termino.'%');

                if (self::tieneDniResp()) {
                    $sub->orWhere('dniResp', 'like', '%'.$termino.'%');
                }

                $sub->orWhereHas('legajos', function ($leg) use ($termino, $idNivel) {
                    self::aplicarFiltroLegajosMatriculados($leg, $idNivel);
                    $leg->buscar($termino);
                });
            });
        }

        return $query
            ->orderByRaw(OrdenAlfabeticoEstudiante::sql('familias.apellido'))
            ->orderByRaw(OrdenAlfabeticoEstudiante::sql('familias.responsable'))
            ->orderBy('familias.id');
    }

    /**
     * @return LengthAwarePaginator<int, Familia>
     */
    public static function listar(string $termino = '', int $idNivel = 0, int $porPagina = self::POR_PAGINA): LengthAwarePaginator
    {
        return self::consultar($termino, $idNivel)
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * @return Collection<int, Familia>
     */
    public static function coleccion(string $termino = '', int $idNivel = 0): Collection
    {
        return self::consultar($termino, $idNivel)->get();
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
     * En Secretaría el nivel lo fija la sesión; el filtro extra solo aplica en Administración.
     */
    public static function idNivelEfectivo(int $idNivel): int
    {
        if (! schoolEsAdministracion()) {
            return 0;
        }

        return self::normalizarIdNivel($idNivel);
    }

    public static function tieneDniResp(): bool
    {
        static $cache = null;

        if ($cache === null) {
            $cache = Schema::hasTable('familias') && Schema::hasColumn('familias', 'dniResp');
        }

        return $cache;
    }

    /**
     * Curso y sección del ciclo activo (`cursos.c` / `cursos.s`).
     *
     * @return array{curso: string, seccion: string}
     */
    public static function cursoYSeccion(?object $curso): array
    {
        if ($curso === null) {
            return ['curso' => '', 'seccion' => ''];
        }

        $anio = trim((string) ($curso->c ?? ''));
        $seccion = trim((string) ($curso->s ?? ''));

        if ($anio === '' && $seccion === '') {
            $anio = trim((string) ($curso->cursec ?? ''));
        }

        return [
            'curso' => $anio,
            'seccion' => $seccion,
        ];
    }

    /**
     * @return array{curso: string, seccion: string}
     */
    public static function cursoYSeccionDeLegajo(Legajo $legajo): array
    {
        $matricula = $legajo->relationLoaded('matriculas')
            ? $legajo->matriculas->first()
            : null;

        return self::cursoYSeccion($matricula?->curso);
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
}
