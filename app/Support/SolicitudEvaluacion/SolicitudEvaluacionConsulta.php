<?php

namespace App\Support\SolicitudEvaluacion;

use App\Models\Curso;
use App\Models\Evaluac;
use App\Support\PortalDocente\CalificacionesDocenteSecundario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consultas y reglas de negocio — solicitud de evaluación (tabla evaluac).
 */
final class SolicitudEvaluacionConsulta
{
    public const MAX_EVALUACIONES_POR_DIA = 2;

    public static function abortSiNoHabilitadoEnTenant(): void
    {
        abort_unless(tenantSolicitudEvaluacionHabilitada(), 404);
    }

    public static function abortSiNoEsSecundario(): void
    {
        CalificacionesDocenteSecundario::abortSiNoEsSecundario();
    }

    /** @return Collection<int, Curso> */
    public static function cursosParaSelector(bool $soloCursosDelDocente = false): Collection
    {
        $ctx = schoolCtx();

        $q = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->orderBy('Id');

        if ($soloCursosDelDocente) {
            $idProfesor = (int) ($ctx->idProfesor ?? 0);
            if ($idProfesor < 1) {
                return collect();
            }

            $ids = DB::table('ppc')
                ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
                ->where('ppc.idProfesor', $idProfesor)
                ->where('m.idNivel', (int) $ctx->idNivel)
                ->where('m.idTerlec', (int) $ctx->idTerlec)
                ->distinct()
                ->pluck('m.idCursos')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all();

            if ($ids === []) {
                return collect();
            }

            $q->whereIn('Id', $ids);
        }

        return $q->get(['Id', 'cursec', 'orden', 'idTurnoClase', 'c', 's']);
    }

    public static function cursoEnContexto(int $idCurso, bool $soloCursosDelDocente = false): ?Curso
    {
        if ($idCurso < 1) {
            return null;
        }

        return self::cursosParaSelector($soloCursosDelDocente)
            ->first(fn (Curso $curso) => (int) $curso->getKey() === $idCurso);
    }

    /**
     * Materias del curso en el año lectivo activo.
     *
     * @return Collection<int, object{id: int, materia: string, abrev: string|null}>
     */
    public static function materiasDelCurso(int $idCurso): Collection
    {
        if ($idCurso < 1) {
            return collect();
        }

        $ctx = schoolCtx();

        return DB::table('materias')
            ->where('idCursos', $idCurso)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->orderBy('ord')
            ->orderBy('id')
            ->get(['id', 'materia', 'abrev']);
    }

    public static function materiaPerteneceAlCurso(int $idMateria, int $idCurso): bool
    {
        if ($idMateria < 1 || $idCurso < 1) {
            return false;
        }

        $ctx = schoolCtx();

        return DB::table('materias')
            ->where('id', $idMateria)
            ->where('idCursos', $idCurso)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->exists();
    }

    /** Normaliza fecha de input/HTML a Y-m-d; vacío si no es válida. */
    public static function normalizarFechaYmd(mixed $fecha): string
    {
        $raw = is_scalar($fecha) ? trim((string) $fecha) : '';
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw, $m) === 1) {
            $ymd = substr($m[0], 0, 10);
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $ymd);

            return ($dt && $dt->format('Y-m-d') === $ymd) ? $ymd : '';
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $raw, $m) === 1) {
            $ymd = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $ymd);

            return ($dt && $dt->format('Y-m-d') === $ymd) ? $ymd : '';
        }

        return '';
    }

    /** @return Collection<int, Evaluac> */
    public static function evaluacionesDelCursoEnFecha(int $idCurso, string $fechaYmd): Collection
    {
        $fechaYmd = self::normalizarFechaYmd($fechaYmd);
        if ($idCurso < 1 || $fechaYmd === '') {
            return collect();
        }

        $ctx = schoolCtx();

        return Evaluac::query()
            ->with('curso')
            ->join('cursos as c', 'c.Id', '=', 'evaluac.idCurso')
            ->where('evaluac.idCurso', $idCurso)
            ->where('c.idNivel', (int) $ctx->idNivel)
            ->where('c.idTerlec', (int) $ctx->idTerlec)
            ->where('evaluac.fecheval', $fechaYmd)
            ->orderBy('evaluac.Id')
            ->select('evaluac.*')
            ->get();
    }

    public static function cantidadEnFecha(int $idCurso, string $fechaYmd): int
    {
        $fechaYmd = self::normalizarFechaYmd($fechaYmd);
        if ($idCurso < 1 || $fechaYmd === '') {
            return 0;
        }

        $ctx = schoolCtx();

        return Evaluac::query()
            ->join('cursos as c', 'c.Id', '=', 'evaluac.idCurso')
            ->where('evaluac.idCurso', $idCurso)
            ->where('c.idNivel', (int) $ctx->idNivel)
            ->where('c.idTerlec', (int) $ctx->idTerlec)
            ->where('evaluac.fecheval', $fechaYmd)
            ->count();
    }

    public static function puedeSolicitarNueva(int $idCurso, string $fechaYmd): bool
    {
        return self::cantidadEnFecha($idCurso, $fechaYmd) < self::MAX_EVALUACIONES_POR_DIA;
    }

    /**
     * @return array<int, string> id evaluac => etiqueta materia
     */
    public static function etiquetasMateriaParaEvaluaciones(Collection $evaluaciones): array
    {
        $ids = $evaluaciones->pluck('idMateria')->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('materias')->whereIn('id', $ids)->get(['id', 'materia', 'abrev']);
        $out = [];
        foreach ($rows as $r) {
            $nombre = trim((string) ($r->materia ?? ''));
            $abrev = trim((string) ($r->abrev ?? ''));
            $out[(int) $r->id] = $abrev !== '' ? $abrev.' — '.$nombre : ($nombre !== '' ? $nombre : 'Materia #'.$r->id);
        }

        return $out;
    }

    /**
     * Materias para filtro de búsqueda, con datos del curso del año lectivo activo.
     *
     * @return Collection<int, object{id: int, materia: string, abrev: string|null, idCursos: int, cursec: string|null}>
     */
    public static function materiasParaSelectorGestion(?int $idCurso = null): Collection
    {
        $ctx = schoolCtx();

        $q = DB::table('materias as m')
            ->join('cursos as c', 'c.Id', '=', 'm.idCursos')
            ->where('m.idNivel', (int) $ctx->idNivel)
            ->where('m.idTerlec', (int) $ctx->idTerlec)
            ->orderBy('c.orden')
            ->orderBy('c.cursec')
            ->orderBy('m.ord')
            ->orderBy('m.id');

        if ($idCurso !== null && $idCurso > 0) {
            $q->where('m.idCursos', $idCurso);
        }

        return $q->get([
            'm.id as id',
            'm.materia as materia',
            'm.abrev as abrev',
            'm.idCursos as idCursos',
            'c.cursec as cursec',
        ]);
    }

    public static function etiquetaMateriaConCurso(object $filaMateria): string
    {
        $nombre = trim((string) ($filaMateria->materia ?? ''));
        $abrev = trim((string) ($filaMateria->abrev ?? ''));
        $materia = $abrev !== '' ? $abrev.' — '.$nombre : ($nombre !== '' ? $nombre : 'Materia #'.($filaMateria->id ?? ''));

        $curso = trim((string) ($filaMateria->cursec ?? ''));
        if ($curso === '') {
            $curso = 'Curso #'.(int) ($filaMateria->idCursos ?? 0);
        }

        return $materia.' · '.$curso;
    }

    /**
     * @param  array{fecha?: string, idCurso?: int|string, idMateria?: int|string}  $filtros
     * @return Collection<string, Collection<int, Evaluac>> clave Y-m-d
     */
    public static function evaluacionesAgrupadasPorFecha(?string $fechaDesdeYmd = null, array $filtros = []): Collection
    {
        $q = self::queryEvaluacionesEnContexto();
        self::aplicarFiltrosEvaluacion($q, $fechaDesdeYmd, $filtros);

        return self::agruparEvaluacionesPorFecha($q->get());
    }

    /**
     * @param  array{fecha?: string, idCurso?: int|string, idMateria?: int|string}  $filtros
     */
    public static function evaluacionesHistorialPaginadas(int $perPage = 25, array $filtros = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $q = self::queryEvaluacionesEnContexto();
        self::aplicarFiltrosEvaluacion($q, null, $filtros);

        return $q->paginate($perPage);
    }

    /**
     * @return Collection<string, Collection<int, Evaluac>> clave Y-m-d, fechas desc
     */
    public static function agruparEvaluacionesPorFecha(Collection $evaluaciones): Collection
    {
        $agrupadas = $evaluaciones->groupBy(fn (Evaluac $e) => $e->fecheval?->format('Y-m-d') ?? '');

        return $agrupadas
            ->sortKeysDesc()
            ->map(fn (Collection $grupo) => $grupo
                ->sortBy(fn (Evaluac $e) => sprintf(
                    '%05d|%s|%08d',
                    (int) ($e->curso?->orden ?? 0),
                    (string) ($e->curso?->cursec ?? ''),
                    (int) $e->Id,
                ))
                ->values());
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Evaluac>  $q
     * @param  array{fecha?: string, idCurso?: int|string, idMateria?: int|string}  $filtros
     */
    private static function aplicarFiltrosEvaluacion(\Illuminate\Database\Eloquent\Builder $q, ?string $fechaDesdeYmd, array $filtros): void
    {
        $fecha = self::normalizarFechaYmd($filtros['fecha'] ?? '');

        // Fecha exacta tiene prioridad: no combinar con "desde hoy" (anularía fechas pasadas).
        if ($fecha !== '') {
            $q->where('evaluac.fecheval', $fecha);
        } else {
            $fechaDesdeYmd = self::normalizarFechaYmd($fechaDesdeYmd);
            if ($fechaDesdeYmd !== '') {
                $q->where('evaluac.fecheval', '>=', $fechaDesdeYmd);
            }
        }

        $idCurso = (int) ($filtros['idCurso'] ?? 0);
        if ($idCurso > 0) {
            $q->where('evaluac.idCurso', $idCurso);
        }

        $idMateria = (int) ($filtros['idMateria'] ?? 0);
        if ($idMateria > 0) {
            $q->where('evaluac.idMateria', $idMateria);
        }
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Evaluac> */
    private static function queryEvaluacionesEnContexto(): \Illuminate\Database\Eloquent\Builder
    {
        $ctx = schoolCtx();

        return Evaluac::query()
            ->with('curso')
            ->join('cursos as c', 'c.Id', '=', 'evaluac.idCurso')
            ->where('c.idNivel', (int) $ctx->idNivel)
            ->where('c.idTerlec', (int) $ctx->idTerlec)
            ->orderByDesc('evaluac.fecheval')
            ->orderBy('c.orden')
            ->orderBy('c.cursec')
            ->orderBy('evaluac.Id')
            ->select('evaluac.*');
    }

    public static function evaluacionEnContexto(int $idEvaluacion): ?Evaluac
    {
        if ($idEvaluacion < 1) {
            return null;
        }

        $ctx = schoolCtx();

        return Evaluac::query()
            ->with('curso')
            ->join('cursos as c', 'c.Id', '=', 'evaluac.idCurso')
            ->where('evaluac.Id', $idEvaluacion)
            ->where('c.idNivel', (int) $ctx->idNivel)
            ->where('c.idTerlec', (int) $ctx->idTerlec)
            ->select('evaluac.*')
            ->first();
    }

    /**
     * Evaluaciones programadas desde hoy para el curso de una matrícula (consulta de calificaciones).
     *
     * @return list<object{fecha: string, materia: string, temas: string, obs: string, linea: string}>
     */
    public static function proximasEvaluacionesParaCursoMatricula(int $idCurso, int $idNivel, int $idTerlec): array
    {
        if ($idCurso < 1 || $idNivel < 1 || $idTerlec < 1) {
            return [];
        }

        $hoy = now()->toDateString();

        $rows = DB::table('evaluac as e')
            ->join('cursos as c', 'c.Id', '=', 'e.idCurso')
            ->join('materias as m', 'm.id', '=', 'e.idMateria')
            ->where('e.idCurso', $idCurso)
            ->where('c.idNivel', $idNivel)
            ->where('c.idTerlec', $idTerlec)
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->whereDate('e.fecheval', '>=', $hoy)
            ->orderBy('e.fecheval')
            ->orderBy('m.materia')
            ->orderBy('e.Id')
            ->get([
                'e.fecheval',
                'm.materia',
                'e.temas',
                'e.obs',
            ]);

        $out = [];
        foreach ($rows as $r) {
            $fechaRaw = $r->fecheval ?? '';
            $fecha = is_string($fechaRaw) && $fechaRaw !== ''
                ? substr($fechaRaw, 0, 10)
                : '';
            $materia = trim((string) ($r->materia ?? ''));
            $temas = trim((string) ($r->temas ?? ''));
            $obs = trim((string) ($r->obs ?? ''));

            $out[] = (object) [
                'fecha' => $fecha,
                'materia' => $materia,
                'temas' => $temas,
                'obs' => $obs,
                'linea' => self::lineaProximaEvaluacionConsulta($fecha, $materia, $temas, $obs),
            ];
        }

        return $out;
    }

    private static function lineaProximaEvaluacionConsulta(string $fecha, string $materia, string $temas, string $obs): string
    {
        $partes = array_filter([
            $fecha !== '' ? $fecha : null,
            $materia !== '' ? mb_strtoupper($materia, 'UTF-8') : null,
            $temas !== '' ? $temas : null,
        ], fn ($p) => $p !== null && $p !== '');

        $linea = implode(' ', $partes);
        if ($obs !== '') {
            $linea .= ($temas !== '' ? '. ' : ($linea !== '' ? ' ' : '')).$obs;
        }

        return $linea;
    }
}
