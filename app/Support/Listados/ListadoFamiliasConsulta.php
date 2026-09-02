<?php

namespace App\Support\Listados;

use App\Livewire\Abm\Legajos\LegajoFamilia;
use App\Models\Familia;
use App\Models\Legajo;
use App\Support\NivelSistema;
use App\Support\OrdenAlfabeticoEstudiante;
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
                            'curso' => function ($c) {
                                $c->select(['Id', 'cursec', 'c', 's', 'idNivel'])
                                    ->with(['nivel:id,nivel,abrev']);
                            },
                            'nivel:id,nivel,abrev',
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
     * Filtro opcional de nivel. 0 = todos los pedagógicos (mismo listado en cualquier sesión).
     */
    public static function idNivelEfectivo(int $idNivel): int
    {
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
     * Familia del listado (ciclo activo y alcance pedagógico), nunca el placeholder id = 1.
     */
    public static function familiaEnAlcance(int $id, int $idNivel = 0): ?Familia
    {
        if ($id < 1 || $id === LegajoFamilia::ID_FAMILIA_SIN_ASIGNAR) {
            return null;
        }

        if (! self::consultar('', $idNivel)->whereKey($id)->exists()) {
            return null;
        }

        return Familia::query()->whereKey($id)->first();
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
     * Etiqueta compacta: `4A (P)` — curso+sección y letra de nivel (I/P/S).
     * La letra sale del nombre/id del **curso**, no de `niveles.abrev` ni del idNivel de matrícula
     * (ese último a menudo copia el nivel de sesión y deja todo en S).
     */
    public static function etiquetaCursoNivel(?object $curso, ?object $nivel = null, int $idNivel = 0): string
    {
        $partes = self::cursoYSeccion($curso);
        $bloque = $partes['curso'].$partes['seccion'];
        $abrev = self::letraNivel($idNivel, $nivel, $curso);

        if ($bloque === '' && $abrev === '') {
            return '';
        }
        if ($abrev === '') {
            return $bloque;
        }
        if ($bloque === '') {
            return '('.$abrev.')';
        }

        return $bloque.' ('.$abrev.')';
    }

    public static function etiquetaCursoNivelDeLegajo(Legajo $legajo): string
    {
        [$curso, $nivel, $idNivel] = self::cursoNivelDeLegajo($legajo);

        return self::etiquetaCursoNivel($curso, $nivel, $idNivel);
    }

    public static function nombreNivelDeLegajo(Legajo $legajo): string
    {
        [, $nivel, $idNivel] = self::cursoNivelDeLegajo($legajo);
        $nombre = trim((string) ($nivel?->nivel ?? ''));
        if ($nombre !== '') {
            return $nombre;
        }

        return match ($idNivel) {
            NivelSistema::INICIAL => 'Inicial',
            NivelSistema::PRIMARIO => 'Primario',
            NivelSistema::SECUNDARIO => 'Secundario',
            NivelSistema::TERCIARIO => 'Terciario',
            NivelSistema::ADULTOS => 'Adultos',
            default => '',
        };
    }

    /**
     * I inicial, P primario, S secundario. Primero el nombre del nivel del curso; el id es respaldo.
     */
    private static function letraNivel(int $idNivel, ?object $nivel, ?object $curso): string
    {
        $desdeNombre = self::letraDesdeNombreNivel((string) ($nivel?->nivel ?? ''));
        if ($desdeNombre !== '') {
            return $desdeNombre;
        }

        $id = $idNivel > 0 ? $idNivel : (int) ($nivel?->id ?? $curso?->idNivel ?? 0);

        return match ($id) {
            NivelSistema::INICIAL => 'I',
            NivelSistema::PRIMARIO => 'P',
            NivelSistema::SECUNDARIO => 'S',
            NivelSistema::TERCIARIO => 'T',
            NivelSistema::ADULTOS => 'A',
            default => '',
        };
    }

    private static function letraDesdeNombreNivel(string $nombre): string
    {
        $nombre = mb_strtolower(trim($nombre));
        if ($nombre === '') {
            return '';
        }
        if (str_contains($nombre, 'inicial')) {
            return 'I';
        }
        if (str_contains($nombre, 'primar')) {
            return 'P';
        }
        if (str_contains($nombre, 'secund') || str_contains($nombre, 'medio')) {
            return 'S';
        }
        if (str_contains($nombre, 'terciar')) {
            return 'T';
        }
        if (str_contains($nombre, 'adulto')) {
            return 'A';
        }

        return '';
    }

    /**
     * @return array{0: ?object, 1: ?object, 2: int}
     */
    private static function cursoNivelDeLegajo(Legajo $legajo): array
    {
        $matricula = $legajo->relationLoaded('matriculas')
            ? $legajo->matriculas->first()
            : null;

        $curso = $matricula?->curso;
        $nivel = $curso?->nivel ?? $matricula?->nivel;
        $idNivel = (int) ($curso?->idNivel ?? $matricula?->idNivel ?? $nivel?->id ?? 0);

        return [$curso, $nivel, $idNivel];
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

        $query->where(function ($q) {
            $q->where('idNivel', '<', NivelSistema::ADMINISTRACION)
                ->orWhereNull('idNivel');
        });
    }
}
