<?php

namespace App\Support\CalificacionesPrimario;

use App\Models\Curso;
use App\Models\Matricula;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lectura y persistencia del formulario manual de calificaciones (primario).
 */
final class CalificacionesPrimarioDatos
{
    /**
     * @return array{
     *     matricula: Matricula,
     *     ciclo: int,
     *     materias: Collection<int, object>,
     *     notas: array<int, array{id: ?int, ic01: string, ic02: string, ic03: string}>,
     *     obs1: string,
     *     obs2: string,
     *     obsAnual: string,
     *     alumnoLinea: string,
     *     cursoLabel: string
     * }
     */
    public static function cargarFormulario(Matricula $matricula): array
    {
        $matricula->loadMissing(['legajo', 'curso.curplan']);
        $idMatricula = (int) $matricula->id;
        $idCurso = (int) $matricula->idCursos;
        $ciclo = CalificacionesPrimarioCatalogo::cicloDesdeCurso($matricula->curso);

        $materias = CalificacionesPrimarioCatalogo::materiasParaCurso(
            $idCurso,
            (int) $matricula->idNivel,
            (int) $matricula->idTerlec,
            $ciclo,
        );

        $ords = $materias->pluck('ord')->map(fn ($o) => (int) $o)->all();
        $porOrd = self::calificacionesPorOrd($idMatricula, $ords);

        $notas = [];
        foreach ($materias as $m) {
            $ord = (int) $m->ord;
            $fila = $porOrd[$ord] ?? null;
            $notas[$ord] = [
                'id' => $fila !== null ? (int) $fila->id : null,
                'ic01' => (string) ($fila->ic01 ?? ''),
                'ic02' => (string) ($fila->ic02 ?? ''),
                'ic03' => (string) ($fila->ic03 ?? ''),
            ];
        }

        $obs = DB::table('matricula')
            ->where('id', $idMatricula)
            ->first(['obs1', 'obs2', 'obsAnual']);

        $legajo = $matricula->legajo;
        $alumnoLinea = trim(((string) ($legajo?->apellido ?? '')).' '.((string) ($legajo?->nombre ?? '')));

        return [
            'matricula' => $matricula,
            'ciclo' => $ciclo,
            'materias' => $materias,
            'notas' => $notas,
            'obs1' => (string) ($obs->obs1 ?? ''),
            'obs2' => (string) ($obs->obs2 ?? ''),
            'obsAnual' => (string) ($obs->obsAnual ?? ''),
            'alumnoLinea' => $alumnoLinea,
            'cursoLabel' => $matricula->curso?->nombreParaListado() ?? '—',
        ];
    }

    /**
     * Notas completas por `ord` (todos los campos del esquema primario).
     *
     * @param  list<int>  $ords
     * @return array<int, array<string, string>>
     */
    public static function calificacionesCompletasPorOrd(int $idMatricula, array $ords): array
    {
        if ($ords === []) {
            return [];
        }

        $campos = array_merge(
            CalificacionesPrimarioCatalogo::camposNotaTodos(),
            CalificacionesPrimarioCatalogo::camposObservacionCalificacion(),
            [CalificacionesPrimarioCatalogo::CAMPO_INTENSIFICACION],
        );
        $columnas = array_merge(['ord'], $campos);

        $filas = DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->whereIn('ord', $ords)
            ->get($columnas);

        $out = [];
        foreach ($filas as $r) {
            $nota = [];
            foreach ($campos as $campo) {
                $nota[$campo] = (string) ($r->{$campo} ?? '');
            }
            $out[(int) $r->ord] = $nota;
        }

        return $out;
    }

    /**
     * @param  list<int>  $ords
     * @return array<int, object{id: int, ic01: ?string, ic02: ?string, ic03: ?string}>
     */
    private static function calificacionesPorOrd(int $idMatricula, array $ords): array
    {
        if ($ords === []) {
            return [];
        }

        $filas = DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->whereIn('ord', $ords)
            ->get(['id', 'ord', 'ic01', 'ic02', 'ic03']);

        $out = [];
        foreach ($filas as $r) {
            $out[(int) $r->ord] = $r;
        }

        return $out;
    }

    public static function guardarNota(
        Matricula $matricula,
        int $ord,
        string $campo,
        string $valor,
        ?int $idMaterias = null,
    ): void {
        if (! CalificacionesPrimarioCatalogo::esCampoNotaGrillaPersistible($campo)) {
            abort(400);
        }

        $matricula->loadMissing('curso.curplan');
        $ciclo = CalificacionesPrimarioCatalogo::cicloDesdeCurso($matricula->curso);
        if (CalificacionesPrimarioCatalogo::celdaInhabilitada($ciclo, $ord, $campo)) {
            return;
        }

        $idMatricula = (int) $matricula->id;
        $existente = DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->where('ord', $ord)
            ->first(['id']);

        if ($existente) {
            DB::table('calificaciones')
                ->where('id', (int) $existente->id)
                ->update([$campo => $valor]);

            return;
        }

        DB::table('calificaciones')->insert([
            'idMatricula' => $idMatricula,
            'idLegajos' => (int) $matricula->idLegajos,
            'idTerlec' => (int) $matricula->idTerlec,
            'idCursos' => (int) $matricula->idCursos,
            'idMaterias' => $idMaterias,
            'ord' => $ord,
            'ic01' => $campo === 'ic01' ? $valor : '',
            'ic02' => $campo === 'ic02' ? $valor : '',
            'ic03' => $campo === 'ic03' ? $valor : '',
            'dic' => $campo === CalificacionesPrimarioCatalogo::CAMPO_INTENSIFICACION ? $valor : '',
        ]);
    }

    public static function guardarObservacionCalificacion(
        Matricula $matricula,
        int $ord,
        string $campo,
        string $valor,
        ?int $idMaterias = null,
    ): void {
        if (! CalificacionesPrimarioCatalogo::esCampoObservacionCalificacion($campo)) {
            abort(400);
        }

        $idMatricula = (int) $matricula->id;
        $existente = DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->where('ord', $ord)
            ->first(['id']);

        if ($existente) {
            DB::table('calificaciones')
                ->where('id', (int) $existente->id)
                ->update([$campo => $valor]);

            return;
        }

        DB::table('calificaciones')->insert([
            'idMatricula' => $idMatricula,
            'idLegajos' => (int) $matricula->idLegajos,
            'idTerlec' => (int) $matricula->idTerlec,
            'idCursos' => (int) $matricula->idCursos,
            'idMaterias' => $idMaterias,
            'ord' => $ord,
            'obs01' => $campo === CalificacionesPrimarioCatalogo::CAMPO_OBS_ETAPA_1 ? $valor : '',
            'obs02' => $campo === CalificacionesPrimarioCatalogo::CAMPO_OBS_ETAPA_2 ? $valor : '',
        ]);
    }

    public static function guardarObservacionMatricula(int $idMatricula, string $campo, string $valor): void
    {
        if (! in_array($campo, CalificacionesPrimarioCatalogo::camposObservacionMatricula(), true)) {
            abort(400);
        }

        DB::table('matricula')
            ->where('id', $idMatricula)
            ->update([$campo => $valor]);
    }

    public static function matriculaEnContexto(int $idMatricula): ?Matricula
    {
        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        return Matricula::query()
            ->with(['legajo', 'curso.curplan'])
            ->where('id', $idMatricula)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->first();
    }

    /**
     * Matrícula del estudiante autenticado en el ciclo de autogestión activo.
     */
    public static function matriculaAlumnoEnSesion(): ?Matricula
    {
        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        return Matricula::query()
            ->with(['legajo', 'curso.curplan', 'terlec'])
            ->where('idLegajos', (int) $ctx->idLegajo)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Materia del curso por `ord` (revalidación en cada guardado; no depender de estado Livewire).
     *
     * @return object{id: int, ord: int}|null
     */
    public static function materiaDelCursoPorOrd(int $idCurso, int $idNivel, int $idTerlec, int $ord, int $ciclo): ?object
    {
        if ($ord < 1 || $ord > CalificacionesPrimarioCatalogo::maxOrdVisible($ciclo)) {
            return null;
        }

        $fila = DB::table('materias')
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->where('idCursos', $idCurso)
            ->where('ord', $ord)
            ->first(['id', 'ord']);

        if ($fila === null) {
            return null;
        }

        return (object) [
            'id' => (int) $fila->id,
            'ord' => (int) $fila->ord,
        ];
    }

    /**
     * Grilla por materia: matrículas regulares del curso con notas de la materia elegida.
     *
     * @return array{
     *     ciclo: int,
     *     ord: int,
     *     materiaLabel: string,
     *     cursoLabel: string,
     *     columnas: array{
     *         parciales: list<array{campo: string, etiqueta: string}>,
     *         finalEtapa: array{campo: string, etiqueta: string},
     *         anual: ?array{campo: string, etiqueta: string},
     *         intensificacion: ?array{campo: string, etiqueta: string},
     *         obs: array{campo: string, etiqueta: string}
     *     },
     *     filas: list<array{
     *         idMatricula: int,
     *         idCalificacion: ?int,
     *         alumno: string,
     *         notas: array<string, string>
     *     }>
     * }
     */
    public static function cargarGrillaMateria(int $cursoId, int $materiaId, int $etapa): array
    {
        $ctx = schoolCtx();
        $etapa = CalificacionesPrimarioCatalogo::normalizarEtapaCargaMateria($etapa);
        $columnas = CalificacionesPrimarioCatalogo::columnasGrillaMateria($etapa);
        $campos = CalificacionesPrimarioCatalogo::camposGrillaMateriaEditables($etapa);

        $curso = Curso::query()
            ->with('curplan')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('Id', $cursoId)
            ->first();

        if ($curso === null) {
            return [
                'ciclo' => 1,
                'ord' => 0,
                'materiaLabel' => '—',
                'cursoLabel' => '—',
                'columnas' => $columnas,
                'filas' => [],
            ];
        }

        $materia = DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('idCursos', $cursoId)
            ->where('id', $materiaId)
            ->first(['id', 'ord', 'materia']);

        if ($materia === null) {
            return [
                'ciclo' => CalificacionesPrimarioCatalogo::cicloDesdeCurso($curso),
                'ord' => 0,
                'materiaLabel' => '—',
                'cursoLabel' => $curso->nombreParaListado(),
                'columnas' => $columnas,
                'filas' => [],
            ];
        }

        $ord = (int) $materia->ord;
        $ciclo = CalificacionesPrimarioCatalogo::cicloDesdeCurso($curso);
        $idsCondiciones = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES,
        );

        $matriculas = Matricula::query()
            ->with('legajo')
            ->join('legajos as l', 'l.id', '=', 'matricula.idLegajos')
            ->where('matricula.idCursos', $cursoId)
            ->where('matricula.idNivel', (int) $ctx->idNivel)
            ->where('matricula.idTerlec', (int) $ctx->idTerlec)
            ->whereIn('matricula.idCondiciones', $idsCondiciones)
            ->whereNull('matricula.fechaBaja')
            ->orderBy('l.apellido')
            ->orderBy('l.nombre')
            ->select('matricula.*')
            ->get();

        $idsMatricula = $matriculas->map(fn (Matricula $m) => (int) $m->id)->all();
        $porMatricula = self::calificacionesPorMatriculaOrd($idsMatricula, $ord);

        $filas = [];
        foreach ($matriculas as $mat) {
            $idMatricula = (int) $mat->id;
            $fila = $porMatricula[$idMatricula] ?? null;
            $notas = [];
            foreach ($campos as $campo) {
                $notas[$campo] = (string) ($fila?->{$campo} ?? '');
            }

            $legajo = $mat->legajo;
            $filas[] = [
                'idMatricula' => $idMatricula,
                'idCalificacion' => $fila !== null ? (int) $fila->id : null,
                'alumno' => trim(((string) ($legajo?->apellido ?? '')).', '.((string) ($legajo?->nombre ?? ''))),
                'notas' => $notas,
            ];
        }

        return [
            'ciclo' => $ciclo,
            'ord' => $ord,
            'materiaLabel' => trim((string) ($materia->materia ?? '')) !== '' ? (string) $materia->materia : '—',
            'cursoLabel' => $curso->nombreParaListado(),
            'columnas' => $columnas,
            'filas' => $filas,
        ];
    }

    /**
     * @param  list<int>  $idsMatricula
     * @return array<int, object{id: int, ic01: ?string, ic02: ?string, ic03: ?string, ic05: ?string, ic06: ?string, ic07: ?string, ic08: ?string, ic09: ?string, ic10: ?string, ic11: ?string, ic12: ?string, ic13: ?string, ic14: ?string, ic15: ?string, ic16: ?string}>
     */
    private static function calificacionesPorMatriculaOrd(array $idsMatricula, int $ord): array
    {
        if ($idsMatricula === []) {
            return [];
        }

        $filas = DB::table('calificaciones')
            ->whereIn('idMatricula', $idsMatricula)
            ->where('ord', $ord)
            ->get(array_merge(
                ['id', 'idMatricula', 'ord'],
                CalificacionesPrimarioCatalogo::camposNotaTodos(),
                CalificacionesPrimarioCatalogo::camposObservacionCalificacion(),
                [CalificacionesPrimarioCatalogo::CAMPO_INTENSIFICACION],
            ));

        $out = [];
        foreach ($filas as $r) {
            $out[(int) $r->idMatricula] = $r;
        }

        return $out;
    }
}
