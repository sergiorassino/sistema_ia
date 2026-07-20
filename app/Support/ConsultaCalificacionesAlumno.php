<?php

namespace App\Support;

use App\Models\Matricula;
use App\Support\Examenes\TercerMateriaGestor;
use App\Support\SolicitudEvaluacion\SolicitudEvaluacionConsulta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Arma el dataset de consulta de calificaciones (nivel secundario, boletín PDF compartido).
 *
 * - Autogestión: {@see self::build()} con contexto alumno.
 * - Docentes/secretaría: {@see self::buildForMatriculaEnContextoEscolar()} con matrícula acotada al {@see schoolCtx()}.
 */
final class ConsultaCalificacionesAlumno
{
    /**
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     matricula?: Matricula,
     *     anoLectivo: ?int,
     *     alumnoLinea: string,
     *     dni: string,
     *     cursoLabel: string,
     *     rows: list<object>,
     *     materias_adeudadas: list<object{materia: string, curso: string, linea: string}>,
     *     tercer_materia: list<array{materia: string, curso: string, ano_lectivo: int|string, nombre_boletin: string, linea: string, tm1: string, tm2: string, tm3: string, tm4: string, tm5: string, tm6: string, tmNota: string}>,
     *     items_boletin: list<object{etiqueta: string, fuente: string, total: float}>,
     *     proximas_evaluaciones: list<object{fecha: string, materia: string, temas: string, obs: string, linea: string}>
     * }
     */
    public static function build(): array
    {
        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            return self::respuestaError('Sesión inválida.');
        }

        $idLegajo = (int) $ctx->idLegajo;
        $idNivel = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;

        /** @var Matricula|null $matricula */
        $matricula = Matricula::query()
            ->with(['legajo', 'curso.curplan', 'terlec'])
            ->where('idLegajos', $idLegajo)
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->orderByDesc('id')
            ->first();

        if (! $matricula) {
            return self::respuestaError(
                'No hay matrícula registrada para este ciclo lectivo. Contacte a secretaría.',
                $ctx->terlecAno(),
            );
        }

        return self::datasetDesdeMatricula($matricula);
    }

    /**
     * Misma salida que {@see build()} para una matrícula concreta, acotada al nivel y ciclo lectivo del contexto escolar actual.
     */
    public static function buildForMatriculaEnContextoEscolar(int $idMatricula): array
    {
        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            return self::respuestaError('Sesión inválida.');
        }

        if ($idMatricula <= 0) {
            return self::respuestaError('Solicitud inválida.', $ctx->terlecAno());
        }

        /** @var Matricula|null $matricula */
        $matricula = Matricula::query()
            ->with(['legajo', 'curso.curplan', 'terlec'])
            ->where('id', $idMatricula)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->first();

        if (! $matricula) {
            return [
                'ok' => false,
                'error' => 'No se encontró la matrícula en este nivel y ciclo lectivo.',
                'anoLectivo' => $ctx->terlecAno(),
                'alumnoLinea' => '',
                'dni' => '',
                'cursoLabel' => '',
                'rows' => [],
                'materias_adeudadas' => [],
                'tercer_materia' => [],
                'items_boletin' => [],
                'proximas_evaluaciones' => [],
            ];
        }

        return self::datasetDesdeMatricula($matricula);
    }

    /**
     * @return array{
     *     ok: false,
     *     error: string,
     *     anoLectivo: ?int,
     *     alumnoLinea: string,
     *     dni: string,
     *     cursoLabel: string,
     *     rows: list<object>,
     *     materias_adeudadas: list<object>,
     *     tercer_materia: list<array>,
     *     items_boletin: list<object>
     * }
     */
    private static function respuestaError(string $error, ?int $anoLectivo = null): array
    {
        return [
            'ok' => false,
            'error' => $error,
            'anoLectivo' => $anoLectivo,
            'alumnoLinea' => '',
            'dni' => '',
            'cursoLabel' => '',
            'rows' => [],
            'materias_adeudadas' => [],
            'tercer_materia' => [],
            'items_boletin' => [],
            'proximas_evaluaciones' => [],
        ];
    }

    /**
     * @return array{
     *     ok: true,
     *     matricula: Matricula,
     *     anoLectivo: ?int,
     *     alumnoLinea: string,
     *     dni: string,
     *     cursoLabel: string,
     *     rows: list<object>,
     *     materias_adeudadas: list<object{materia: string, curso: string, linea: string}>,
     *     tercer_materia: list<array{materia: string, curso: string, ano_lectivo: int|string, nombre_boletin: string, linea: string, tm1: string, tm2: string, tm3: string, tm4: string, tm5: string, tm6: string, tmNota: string}>,
     *     items_boletin: list<object{etiqueta: string, fuente: string, total: float}>,
     *     proximas_evaluaciones: list<object{fecha: string, materia: string, temas: string, obs: string, linea: string}>
     * }
     */
    private static function datasetDesdeMatricula(Matricula $matricula): array
    {
        $legajo = $matricula->legajo;
        $alumnoLinea = trim(((string) ($legajo?->apellido ?? '')).' '.((string) ($legajo?->nombre ?? '')));
        $dni = trim((string) ($legajo?->dni ?? ''));
        $cursoLabel = $matricula->curso?->nombreParaListado() ?? '';
        $anoLectivo = $matricula->terlec?->ano;

        $idMat = (int) $matricula->id;
        $idLegajo = (int) $matricula->idLegajos;
        $idCurso = (int) $matricula->idCursos;
        $idTerlec = (int) $matricula->idTerlec;

        $cols = [
            'c.ic01', 'c.ic02', 'c.ic03', 'c.ic04', 'c.ic05', 'c.ic06', 'c.ic07', 'c.ic08', 'c.ic09', 'c.ic10',
            'c.ic11', 'c.ic12', 'c.ic13', 'c.ic14', 'c.ic15', 'c.ic16', 'c.ic17', 'c.ic18', 'c.ic19', 'c.ic20',
            'c.ic21', 'c.ic22', 'c.ic23', 'c.ic24', 'c.ic25', 'c.ic26', 'c.ic27', 'c.ic28',
            'c.dic', 'c.feb', 'c.calif',
        ];

        $rows = DB::table('calificaciones as c')
            ->join('materias as m', function ($join) {
                $join->on('m.id', '=', 'c.idMaterias')
                    ->on('m.idTerlec', '=', 'c.idTerlec');
            })
            ->where('c.idTerlec', $idTerlec)
            ->where(function ($q) use ($idMat, $idLegajo, $idCurso) {
                $q->where('c.idMatricula', $idMat)
                    ->orWhere(function ($q2) use ($idLegajo, $idCurso) {
                        $q2->whereNull('c.idMatricula')
                            ->where('c.idLegajos', $idLegajo)
                            ->where('c.idCursos', $idCurso);
                    });
            })
            ->orderByRaw('COALESCE(c.ord, 9999) asc')
            ->orderBy('m.materia')
            ->get(array_merge(['m.materia as espacio_curricular'], $cols));

        $idNivel = (int) $matricula->idNivel;
        $materiasAdeudadas = self::materiasAdeudadasCiclosAnteriores($idLegajo, $idTerlec, $idNivel);
        $tercerMateria = self::tercerMateriaParaLegajo($idLegajo, $idNivel, $idTerlec);
        $proximasEvaluaciones = tenantSolicitudEvaluacionHabilitada()
            ? SolicitudEvaluacionConsulta::proximasEvaluacionesParaCursoMatricula($idCurso, $idNivel, $idTerlec)
            : [];

        return [
            'ok' => true,
            'matricula' => $matricula,
            'anoLectivo' => $anoLectivo !== null ? (int) $anoLectivo : null,
            'alumnoLinea' => $alumnoLinea,
            'dni' => $dni,
            'cursoLabel' => $cursoLabel,
            'rows' => $rows->all(),
            'materias_adeudadas' => $materiasAdeudadas,
            'tercer_materia' => $tercerMateria,
            'items_boletin' => self::itemsBoletinParaMatricula($idMat, $idTerlec),
            'proximas_evaluaciones' => $proximasEvaluaciones,
        ];
    }

    /**
     * @return list<array{materia: string, curso: string, ano_lectivo: int|string, linea: string, tm1: string, tm2: string, tm3: string, tm4: string, tm5: string, tm6: string, tmNota: string}>
     */
    private static function tercerMateriaParaLegajo(int $idLegajo, int $idNivel, int $idTerlec): array
    {
        if (! tenantBoletinMuestraTercerMateria()) {
            return [];
        }

        return TercerMateriaGestor::filasParaLegajo($idLegajo, $idNivel, $idTerlec);
    }

    /**
     * @return list<object{materia: string, curso: string, linea: string}>
     */
    public static function materiasAdeudadasParaLegajo(int $idLegajo, int $idTerlecActual, int $idNivel): array
    {
        return self::materiasAdeudadasCiclosAnteriores($idLegajo, $idTerlecActual, $idNivel);
    }

    /**
     * @return list<object{etiqueta: string, fuente: string, total: float}>
     */
    public static function itemsBoletinParaMatriculaPublic(int $idMatricula, int $idTerlec): array
    {
        return self::itemsBoletinParaMatricula($idMatricula, $idTerlec);
    }

    /**
     * Materias marcadas como adeudadas (`apro = 1`) en ciclos lectivos anteriores al de la matrícula consultada.
     *
     * @return list<object{materia: string, curso: string, linea: string}>
     */
    private static function materiasAdeudadasCiclosAnteriores(int $idLegajo, int $idTerlecActual, int $idNivel): array
    {
        if ($idLegajo <= 0 || $idTerlecActual <= 0) {
            return [];
        }

        $raw = DB::table('calificaciones as c')
            ->join('materias as m', function ($join) {
                $join->on('m.id', '=', 'c.idMaterias')
                    ->on('m.idTerlec', '=', 'c.idTerlec');
            })
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
            ->leftJoin('terlec as t', 't.id', '=', 'c.idTerlec')
            ->where('c.idLegajos', $idLegajo)
            ->where('c.apro', 1)
            ->where('c.idTerlec', '<>', $idTerlecActual)
            ->where('cu.idNivel', $idNivel)
            ->orderByDesc('t.ano')
            ->orderBy('m.materia')
            ->select([
                'm.materia',
                'cu.cursec',
                'cp.curPlanCurso',
                'tc.nombre as turnoClaseNombre',
                'cu.c',
                'cu.s',
            ])
            ->get();

        /** @var Collection<int, object{materia: string, curso: string, linea: string}> $out */
        $out = $raw
            ->map(function (object $r): object {
                $materia = trim((string) ($r->materia ?? ''));
                $cursoRaw = self::cursoLabelDesdeFilaCalificacion($r);
                $cursoFmt = self::cursoTituloPalabras($cursoRaw);
                $matFmt = mb_strtoupper($materia, 'UTF-8');

                return (object) [
                    'materia' => $materia,
                    'curso' => $cursoRaw,
                    'linea' => $matFmt.' ('.$cursoFmt.')',
                ];
            })
            ->filter(fn (object $o) => $o->materia !== '')
            ->values();

        return $out->all();
    }

    /**
     * Replica de la lógica de {@see \App\Models\Curso::nombreParaListado()} sobre filas del query de adeudadas.
     */
    private static function cursoLabelDesdeFilaCalificacion(object $r): string
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

        return 'Curso';
    }

    /**
     * Curso entre paréntesis: cada palabra con inicial mayúscula (ej. Quinto A, Plan Básico · Turno Mañana).
     */
    private static function cursoTituloPalabras(string $curso): string
    {
        $s = trim($curso);
        if ($s === '') {
            return '';
        }
        $lower = mb_strtolower($s, 'UTF-8');
        $pieces = explode(' · ', $lower);
        $titledPieces = array_map(
            fn (string $p): string => self::tituloPalabrasPorEspacios(trim($p)),
            $pieces
        );

        return implode(' · ', $titledPieces);
    }

    /**
     * @return string cadena vacía si $segment es vacío
     */
    private static function tituloPalabrasPorEspacios(string $segment): string
    {
        if ($segment === '') {
            return '';
        }
        $words = preg_split('/\s+/u', $segment, -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        foreach ($words as $w) {
            $out[] = mb_convert_case($w, MB_CASE_TITLE, 'UTF-8');
        }

        return implode(' ', $out);
    }

    private const ITEMS_BOLETIN_FUENTES = ['inasistencias', 'sanciones'];

    /**
     * @return list<object{etiqueta: string, fuente: string, condicion_where: string}>
     */
    private static function definicionesItemsBoletinActivas(int $idTerlec): array
    {
        if (! Schema::hasTable('itemsboletin')) {
            return [];
        }

        return DB::table('itemsboletin')
            ->where('activo', true)
            ->where(function ($q) use ($idTerlec) {
                $q->whereNull('idTerlec')
                    ->orWhere('idTerlec', $idTerlec);
            })
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['etiqueta', 'fuente', 'condicion_where'])
            ->all();
    }

    /**
     * Filas para el pie del PDF (inasistencias / sanciones) según {@see ItemBoletin}.
     *
     * @return list<object{etiqueta: string, fuente: string, total: float}>
     */
    private static function itemsBoletinParaMatricula(int $idMatricula, int $idTerlec): array
    {
        if ($idMatricula <= 0) {
            return [];
        }

        $definiciones = self::definicionesItemsBoletinActivas($idTerlec);

        $out = [];
        foreach ($definiciones as $def) {
            $etiqueta = trim((string) ($def->etiqueta ?? ''));
            $fuente = trim((string) ($def->fuente ?? ''));
            $cond = trim((string) ($def->condicion_where ?? ''));
            if ($etiqueta === '' || ! in_array($fuente, self::ITEMS_BOLETIN_FUENTES, true) || ! self::condicionWhereItemsBoletinSegura($cond)) {
                continue;
            }
            $total = self::sumarCantidadItemsBoletin($fuente, $cond, $idMatricula);
            $out[] = (object) ['etiqueta' => $etiqueta, 'fuente' => $fuente, 'total' => $total];
        }

        return $out;
    }

    /**
     * Evita concatenar SQL arbitrario no acotado (la condición sale de BD pero debe ser solo expresión AND).
     */
    private static function condicionWhereItemsBoletinSegura(string $condicion): bool
    {
        if ($condicion === '' || strlen($condicion) > 500) {
            return false;
        }
        if (preg_match('/;|--|\/\*|\*\/|[`"]|\bunion\b|\bselect\b|\binsert\b|\bupdate\b|\bdelete\b|\bdrop\b|\bexec\b|\binto\b|\boutfile\b|\bload\b|\bgrant\b/i', $condicion)) {
            return false;
        }

        return true;
    }

    private static function sumarCantidadItemsBoletin(string $tabla, string $condicionAnd, int $idMatricula): float
    {
        $sum = DB::table($tabla)
            ->where('idMatricula', $idMatricula)
            ->whereRaw('('.$condicionAnd.')')
            ->sum(DB::raw('COALESCE(cantidad, 0)'));

        $v = (float) $sum;

        return $tabla === 'inasistencias'
            ? round($v, 2)
            : round($v, 0);
    }
}
