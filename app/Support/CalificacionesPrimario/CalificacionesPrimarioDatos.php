<?php

namespace App\Support\CalificacionesPrimario;

use App\Models\Curso;
use App\Models\Matricula;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lectura y persistencia del formulario manual de calificaciones (primario).
 *
 * Vínculo materia ↔ calificación: `calificaciones.idMaterias` (como secundario y Sincro GE).
 * `calificaciones.ord` se mantiene como copia desnormalizada de `materias.ord` al insertar;
 * en lectura legacy se acepta fallback por `ord` solo si `idMaterias` está vacío en la fila.
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

        $materias = CalificacionesPrimarioCatalogo::materiasParaSelectorAnio(
            $idCurso,
            (int) $matricula->idNivel,
            (int) $matricula->idTerlec,
        );

        $porMateria = self::calificacionesPorMaterias($idMatricula, $materias);

        $notas = [];
        foreach ($materias as $m) {
            $idMaterias = (int) $m->id;
            $fila = $porMateria[$idMaterias] ?? null;
            $notas[$idMaterias] = [
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
     * Notas completas por `idMaterias` (todos los campos del esquema primario).
     *
     * @param  list<int>  $idsMaterias
     * @return array<int, array<string, string>>
     */
    public static function calificacionesCompletasPorIdMaterias(int $idMatricula, array $idsMaterias): array
    {
        if ($idsMaterias === []) {
            return [];
        }

        $campos = array_merge(
            CalificacionesPrimarioCatalogo::camposNotaTodos(),
            CalificacionesPrimarioCatalogo::camposObservacionCalificacion(),
            [CalificacionesPrimarioCatalogo::CAMPO_INTENSIFICACION],
        );
        $columnas = array_merge(['id', 'idMaterias', 'ord'], $campos);

        $filas = DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->get($columnas);

        $porIdMaterias = [];
        $porOrdLegacy = [];
        foreach ($filas as $r) {
            $idMat = (int) ($r->idMaterias ?? 0);
            if ($idMat > 0) {
                $porIdMaterias[$idMat] = $r;

                continue;
            }
            $porOrdLegacy[(int) $r->ord] = $r;
        }

        $materias = DB::table('materias')
            ->whereIn('id', $idsMaterias)
            ->get(['id', 'ord']);

        $out = [];
        foreach ($materias as $m) {
            $idMaterias = (int) $m->id;
            $r = $porIdMaterias[$idMaterias] ?? $porOrdLegacy[(int) $m->ord] ?? null;
            if ($r === null) {
                continue;
            }

            $nota = [];
            foreach ($campos as $campo) {
                $nota[$campo] = (string) ($r->{$campo} ?? '');
            }
            $out[$idMaterias] = $nota;
        }

        return $out;
    }

    /**
     * @deprecated Use calificacionesCompletasPorIdMaterias()
     *
     * @param  list<int>  $ords
     * @return array<int, array<string, string>>
     */
    public static function calificacionesCompletasPorOrd(int $idMatricula, array $ords): array
    {
        if ($ords === []) {
            return [];
        }

        $idsMaterias = DB::table('materias')
            ->whereIn('ord', $ords)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $porId = self::calificacionesCompletasPorIdMaterias($idMatricula, $idsMaterias);
        $out = [];
        foreach ($idsMaterias as $i => $idMaterias) {
            if (isset($porId[$idMaterias], $ords[$i])) {
                $out[(int) $ords[$i]] = $porId[$idMaterias];
            }
        }

        return $out;
    }

    /**
     * @param  Collection<int, object{id: int, ord: int}>  $materias
     * @return array<int, object{id: int, ic01: ?string, ic02: ?string, ic03: ?string}>
     */
    private static function calificacionesPorMaterias(int $idMatricula, Collection $materias): array
    {
        if ($materias->isEmpty()) {
            return [];
        }

        $filas = DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->get(['id', 'idMaterias', 'ord', 'ic01', 'ic02', 'ic03']);

        $porIdMaterias = [];
        $porOrdLegacy = [];
        foreach ($filas as $r) {
            $idMat = (int) ($r->idMaterias ?? 0);
            if ($idMat > 0) {
                $porIdMaterias[$idMat] = $r;

                continue;
            }
            $porOrdLegacy[(int) $r->ord] = $r;
        }

        $out = [];
        foreach ($materias as $m) {
            $idMaterias = (int) $m->id;
            $out[$idMaterias] = $porIdMaterias[$idMaterias] ?? $porOrdLegacy[(int) $m->ord] ?? null;
        }

        return $out;
    }

    public static function guardarNota(
        Matricula $matricula,
        int $idMaterias,
        string $campo,
        string $valor,
    ): void {
        if (! CalificacionesPrimarioCatalogo::esCampoNotaGrillaPersistible($campo)) {
            abort(400);
        }

        $materia = self::materiaDelCursoPorId(
            (int) $matricula->idCursos,
            (int) $matricula->idNivel,
            (int) $matricula->idTerlec,
            $idMaterias,
        );
        if ($materia === null) {
            abort(404, 'Materia no encontrada para este curso.');
        }

        $matricula->loadMissing('curso.curplan');
        $ciclo = CalificacionesPrimarioCatalogo::cicloDesdeCurso($matricula->curso);
        if (CalificacionesPrimarioCatalogo::celdaInhabilitada($ciclo, (int) $materia->ord, $campo)) {
            return;
        }

        $idMatricula = (int) $matricula->id;
        $existente = self::buscarCalificacion($idMatricula, $idMaterias, (int) $materia->ord);

        if ($existente !== null) {
            $payload = [$campo => $valor];
            if ((int) ($existente->idMaterias ?? 0) !== $idMaterias) {
                $payload['idMaterias'] = $idMaterias;
            }

            DB::table('calificaciones')
                ->where('id', (int) $existente->id)
                ->update($payload);

            return;
        }

        DB::table('calificaciones')->insert([
            'idMatricula' => $idMatricula,
            'idLegajos' => (int) $matricula->idLegajos,
            'idTerlec' => (int) $matricula->idTerlec,
            'idCursos' => (int) $matricula->idCursos,
            'idMaterias' => $idMaterias,
            'ord' => (int) $materia->ord,
            'ic01' => $campo === 'ic01' ? $valor : '',
            'ic02' => $campo === 'ic02' ? $valor : '',
            'ic03' => $campo === 'ic03' ? $valor : '',
            'dic' => $campo === CalificacionesPrimarioCatalogo::CAMPO_INTENSIFICACION ? $valor : '',
        ]);
    }

    public static function guardarObservacionCalificacion(
        Matricula $matricula,
        int $idMaterias,
        string $campo,
        string $valor,
    ): void {
        if (! CalificacionesPrimarioCatalogo::esCampoObservacionCalificacion($campo)) {
            abort(400);
        }

        $materia = self::materiaDelCursoPorId(
            (int) $matricula->idCursos,
            (int) $matricula->idNivel,
            (int) $matricula->idTerlec,
            $idMaterias,
        );
        if ($materia === null) {
            abort(404, 'Materia no encontrada para este curso.');
        }

        $idMatricula = (int) $matricula->id;
        $existente = self::buscarCalificacion($idMatricula, $idMaterias, (int) $materia->ord);

        if ($existente !== null) {
            $payload = [$campo => $valor];
            if ((int) ($existente->idMaterias ?? 0) !== $idMaterias) {
                $payload['idMaterias'] = $idMaterias;
            }

            DB::table('calificaciones')
                ->where('id', (int) $existente->id)
                ->update($payload);

            return;
        }

        DB::table('calificaciones')->insert([
            'idMatricula' => $idMatricula,
            'idLegajos' => (int) $matricula->idLegajos,
            'idTerlec' => (int) $matricula->idTerlec,
            'idCursos' => (int) $matricula->idCursos,
            'idMaterias' => $idMaterias,
            'ord' => (int) $materia->ord,
            'obs01' => $campo === CalificacionesPrimarioCatalogo::CAMPO_OBS_ETAPA_1 ? $valor : '',
            'obs02' => $campo === CalificacionesPrimarioCatalogo::CAMPO_OBS_ETAPA_2 ? $valor : '',
        ]);
    }

    /**
     * Valor persistido de un campo en la fila materia × matrícula (con fallback legacy por `ord`).
     */
    public static function valorCampoCalificacion(
        int $idMatricula,
        int $idMaterias,
        int $ordMateria,
        string $campo,
    ): string {
        $fila = self::buscarCalificacion($idMatricula, $idMaterias, $ordMateria);
        if ($fila === null) {
            return '';
        }

        $valor = DB::table('calificaciones')
            ->where('id', (int) $fila->id)
            ->value($campo);

        return trim((string) ($valor ?? ''));
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
     * Materia del curso por `id` (revalidación en cada guardado; no depender de estado Livewire).
     *
     * @return object{id: int, ord: int}|null
     */
    public static function materiaDelCursoPorId(int $idCurso, int $idNivel, int $idTerlec, int $idMaterias): ?object
    {
        if ($idMaterias < 1) {
            return null;
        }

        $fila = DB::table('materias')
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->where('idCursos', $idCurso)
            ->where('id', $idMaterias)
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
     * @deprecated Use materiaDelCursoPorId()
     *
     * @return object{id: int, ord: int}|null
     */
    public static function materiaDelCursoPorOrd(int $idCurso, int $idNivel, int $idTerlec, int $ord): ?object
    {
        if ($ord < 1) {
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
            ->where('id', $materiaId)
            ->where('idCursos', $cursoId)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
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
        $porMatricula = self::calificacionesPorMatriculaIdMaterias($idsMatricula, $materiaId, $ord);

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
     * @return object{id: int, idMaterias: ?int, ord: ?int}|null
     */
    private static function buscarCalificacion(int $idMatricula, int $idMaterias, int $ordMateria): ?object
    {
        $columnas = ['id', 'idMaterias', 'ord'];

        if ($idMaterias > 0) {
            $fila = DB::table('calificaciones')
                ->where('idMatricula', $idMatricula)
                ->where('idMaterias', $idMaterias)
                ->first($columnas);

            if ($fila !== null) {
                return $fila;
            }
        }

        if ($ordMateria < 1) {
            return null;
        }

        return DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->where('ord', $ordMateria)
            ->where(function ($q): void {
                $q->whereNull('idMaterias')
                    ->orWhere('idMaterias', 0);
            })
            ->first($columnas);
    }

    /**
     * @param  list<int>  $idsMatricula
     * @return array<int, object{id: int, idMatricula: int, ic01: ?string, ic02: ?string, ic03: ?string, ic05: ?string, ic06: ?string, ic07: ?string, ic08: ?string, ic09: ?string, ic10: ?string, ic11: ?string, ic12: ?string, ic13: ?string, ic14: ?string, ic15: ?string, ic16: ?string}>
     */
    private static function calificacionesPorMatriculaIdMaterias(array $idsMatricula, int $idMaterias, int $ordMateria): array
    {
        if ($idsMatricula === [] || $idMaterias < 1) {
            return [];
        }

        $columnas = array_merge(
            ['id', 'idMatricula', 'idMaterias', 'ord'],
            CalificacionesPrimarioCatalogo::camposNotaTodos(),
            CalificacionesPrimarioCatalogo::camposObservacionCalificacion(),
            [CalificacionesPrimarioCatalogo::CAMPO_INTENSIFICACION],
        );

        $filas = DB::table('calificaciones')
            ->whereIn('idMatricula', $idsMatricula)
            ->where(function ($q) use ($idMaterias, $ordMateria): void {
                $q->where('idMaterias', $idMaterias);
                if ($ordMateria > 0) {
                    $q->orWhere(function ($q2) use ($ordMateria): void {
                        $q2->where('ord', $ordMateria)
                            ->where(function ($q3): void {
                                $q3->whereNull('idMaterias')
                                    ->orWhere('idMaterias', 0);
                            });
                    });
                }
            })
            ->get($columnas);

        $out = [];
        foreach ($filas as $r) {
            $idMatricula = (int) $r->idMatricula;
            $esPorId = (int) ($r->idMaterias ?? 0) === $idMaterias;

            if ($esPorId || ! isset($out[$idMatricula])) {
                $out[$idMatricula] = $r;
            }
        }

        return $out;
    }
}
