<?php

namespace App\Support\Examenes;

use App\Models\Legajo;
use App\Support\SchoolContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class MateriasAdeudadasAlumnosListado
{
    public const MIN_CHARS_BUSQUEDA = 3;

    public const POR_PAGINA = 50;

    private const SESSION_BUSCAR_LISTADO = 'materias_adeudadas_gestion_buscar';

    public static function esNivelSecundario(SchoolContext $ctx): bool
    {
        return str_contains(mb_strtolower($ctx->nivelNombre()), 'secundari');
    }

    /**
     * Búsqueda paginada de alumnos con historial en secundario. Sin término válido no consulta la BD.
     *
     * @return LengthAwarePaginator<int, array{
     *     idLegajos: int,
     *     idMatricula: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string
     * }>|null
     */
    public static function paginarAlumnos(
        int $idNivel,
        int $idTerlec,
        ?string $buscar,
        int $porPagina = self::POR_PAGINA,
    ): ?LengthAwarePaginator {
        if ($idNivel < 1) {
            return null;
        }

        $termino = self::normalizarBusqueda($buscar);
        if ($termino === '') {
            return null;
        }

        $paginator = Legajo::query()
            ->whereHas('matriculas', fn ($q) => $q->where('idNivel', $idNivel))
            ->buscar($termino)
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate(max(10, min(100, $porPagina)), ['id', 'apellido', 'nombre', 'dni']);

        $idsLegajos = $paginator->getCollection()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $matriculaPorLegajo = self::matriculaReferenciaPorLegajos($idsLegajos, $idNivel, $idTerlec);

        return $paginator->through(function (Legajo $legajo) use ($matriculaPorLegajo): array {
            $idLegajos = (int) $legajo->id;
            $matricula = $matriculaPorLegajo->get($idLegajos);

            if ($matricula !== null) {
                return self::filaAlumnoDesdeRegistro($matricula);
            }

            return [
                'idLegajos' => $idLegajos,
                'idMatricula' => 0,
                'apellido' => trim((string) ($legajo->apellido ?? '')),
                'nombre' => trim((string) ($legajo->nombre ?? '')),
                'dni' => trim((string) ($legajo->dni ?? '')),
                'curso' => '',
            ];
        });
    }

    /**
     * Datos del alumno si tuvo matrícula en secundario alguna vez.
     *
     * @return array{
     *     idLegajos: int,
     *     idMatricula: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string
     * }|null
     */
    public static function alumnoPorLegajo(int $idLegajos, int $idNivel, ?int $idTerlecContexto = null): ?array
    {
        if ($idLegajos < 1 || $idNivel < 1) {
            return null;
        }

        $matricula = self::matriculaReferenciaPorLegajos([$idLegajos], $idNivel, $idTerlecContexto)->get($idLegajos);
        if ($matricula === null) {
            return null;
        }

        return self::filaAlumnoDesdeRegistro($matricula);
    }

    /**
     * @param  list<int>  $idsLegajos
     * @return Collection<int, object>
     */
    private static function matriculaReferenciaPorLegajos(
        array $idsLegajos,
        int $idNivel,
        ?int $idTerlecContexto,
    ): Collection {
        $idsLegajos = array_values(array_filter(array_map('intval', $idsLegajos), fn (int $id) => $id > 0));
        if ($idsLegajos === []) {
            return collect();
        }

        $filas = DB::table('matricula as m')
            ->join('legajos as l', 'l.id', '=', 'm.idLegajos')
            ->join('terlec as t', 't.id', '=', 'm.idTerlec')
            ->leftJoin('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
            ->whereIn('m.idLegajos', $idsLegajos)
            ->where('m.idNivel', $idNivel)
            ->select([
                'm.id as idMatricula',
                'm.idLegajos',
                'm.idTerlec',
                't.ano as ano_lectivo',
                'l.apellido',
                'l.nombre',
                'l.dni',
                'cu.cursec',
                'cp.curPlanCurso',
                'tc.nombre as turnoClaseNombre',
                'cu.c',
                'cu.s',
            ])
            ->get()
            ->groupBy(fn (object $r) => (int) $r->idLegajos);

        $idTerlecPreferido = ($idTerlecContexto !== null && $idTerlecContexto > 0) ? $idTerlecContexto : 0;

        return $filas->map(function (Collection $grupo) use ($idTerlecPreferido): object {
            return $grupo
                ->sort(function (object $a, object $b) use ($idTerlecPreferido): int {
                    $prioA = $idTerlecPreferido > 0 && (int) $a->idTerlec === $idTerlecPreferido ? 0 : 1;
                    $prioB = $idTerlecPreferido > 0 && (int) $b->idTerlec === $idTerlecPreferido ? 0 : 1;
                    if ($prioA !== $prioB) {
                        return $prioA <=> $prioB;
                    }

                    $anoA = (int) ($a->ano_lectivo ?? 0);
                    $anoB = (int) ($b->ano_lectivo ?? 0);
                    if ($anoA !== $anoB) {
                        return $anoB <=> $anoA;
                    }

                    return ((int) $b->idMatricula) <=> ((int) $a->idMatricula);
                })
                ->first();
        });
    }

    /**
     * @return array{
     *     idLegajos: int,
     *     idMatricula: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string
     * }
     */
    private static function filaAlumnoDesdeRegistro(object $r): array
    {
        return [
            'idLegajos' => (int) $r->idLegajos,
            'idMatricula' => (int) $r->idMatricula,
            'apellido' => trim((string) ($r->apellido ?? '')),
            'nombre' => trim((string) ($r->nombre ?? '')),
            'dni' => trim((string) ($r->dni ?? '')),
            'curso' => self::cursoLabelDesdeFila($r),
        ];
    }

    public static function persistirBuscarListado(?string $buscar): void
    {
        session([self::SESSION_BUSCAR_LISTADO => trim((string) $buscar)]);
    }

    public static function buscarRetornoListado(): string
    {
        $desdeRequest = trim((string) request()->query('buscar', ''));
        if ($desdeRequest !== '') {
            return $desdeRequest;
        }

        return trim((string) session(self::SESSION_BUSCAR_LISTADO, ''));
    }

    public static function urlListadoGestion(?string $buscar = null): string
    {
        $t = trim((string) ($buscar ?? ''));

        return $t !== ''
            ? route('examenes.materias-adeudadas.gestion', ['buscar' => $t])
            : route('examenes.materias-adeudadas.gestion');
    }

    private static function normalizarBusqueda(?string $buscar): string
    {
        $t = trim((string) $buscar);

        return mb_strlen($t) >= self::MIN_CHARS_BUSQUEDA ? $t : '';
    }

    private static function cursoLabelDesdeFila(object $r): string
    {
        $sec = trim((string) ($r->cursec ?? ''));
        if ($sec !== '') {
            return $sec;
        }

        $nombrePlan = trim((string) ($r->curPlanCurso ?? ''));
        $extras = collect([$r->turnoClaseNombre ?? '', $r->c ?? '', $r->s ?? ''])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values();

        if ($nombrePlan !== '') {
            return $extras->isNotEmpty()
                ? $nombrePlan.' · '.$extras->implode(' · ')
                : $nombrePlan;
        }

        if ($extras->isNotEmpty()) {
            return $extras->implode(' · ');
        }

        return '';
    }
}
