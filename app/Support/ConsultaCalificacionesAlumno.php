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
     *     items_boletin: list<object{etiqueta: string, fuente: string, total: float, texto?: string}>,
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
     *     items_boletin: list<object{etiqueta: string, fuente: string, total: float, texto?: string}>,
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

    /** @var array<int, list<object{etiqueta: string, fuente: string, condicion_where: string}>> */
    private static array $cacheDefsItemsBoletin = [];

    /**
     * @return list<object{materia: string, curso: string, linea: string}>
     */
    public static function materiasAdeudadasParaLegajo(int $idLegajo, int $idTerlecActual, int $idNivel): array
    {
        return self::materiasAdeudadasCiclosAnteriores($idLegajo, $idTerlecActual, $idNivel);
    }

    /**
     * Texto de previas por legajo (planillas lote): una sola consulta agrupada.
     *
     * @param  list<int>  $idLegajos
     * @return array<int, string>
     */
    public static function materiasAdeudadasTextoPorLegajos(array $idLegajos, int $idTerlecActual, int $idNivel): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $idLegajos),
            fn (int $id) => $id > 0
        )));
        if ($ids === [] || $idTerlecActual <= 0 || $idNivel <= 0) {
            return [];
        }

        $lineasPorLegajo = [];
        foreach (array_chunk($ids, 200) as $chunk) {
            $raw = DB::table('calificaciones as c')
                ->join('materias as m', function ($join) {
                    $join->on('m.id', '=', 'c.idMaterias')
                        ->on('m.idTerlec', '=', 'c.idTerlec');
                })
                ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
                ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
                ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
                ->leftJoin('terlec as t', 't.id', '=', 'c.idTerlec')
                ->whereIn('c.idLegajos', $chunk)
                ->where('c.apro', 1)
                ->where('c.idTerlec', '<>', $idTerlecActual)
                ->where('cu.idNivel', $idNivel)
                ->orderBy('c.idLegajos')
                ->orderByDesc('t.ano')
                ->orderBy('m.materia')
                ->select([
                    'c.idLegajos',
                    'm.materia',
                    'cu.cursec',
                    'cp.curPlanCurso',
                    'tc.nombre as turnoClaseNombre',
                    'cu.c',
                    'cu.s',
                ])
                ->get();

            foreach ($raw as $r) {
                $materia = trim((string) ($r->materia ?? ''));
                if ($materia === '') {
                    continue;
                }
                $idLeg = (int) $r->idLegajos;
                $cursoRaw = self::cursoLabelDesdeFilaCalificacion($r);
                $cursoFmt = self::cursoTituloPalabras($cursoRaw);
                $lineasPorLegajo[$idLeg][] = mb_strtoupper($materia, 'UTF-8').' ('.$cursoFmt.')';
            }
        }

        $out = [];
        foreach ($ids as $idLeg) {
            $lineas = $lineasPorLegajo[$idLeg] ?? [];
            $out[$idLeg] = $lineas === [] ? '' : implode(' - ', $lineas);
        }

        return $out;
    }

    /**
     * @return list<object{etiqueta: string, fuente: string, total: float, texto?: string}>
     */
    public static function itemsBoletinParaMatriculaPublic(int $idMatricula, int $idTerlec): array
    {
        return self::itemsBoletinParaMatricula($idMatricula, $idTerlec);
    }

    /**
     * Presentación uniforme del valor de un ítem de pie (PDF / Blade).
     *
     * @return array{mostrar: bool, texto: string, tight: bool}
     */
    public static function presentacionItemBoletin(object $it): array
    {
        $fuente = (string) ($it->fuente ?? '');
        if (self::esFuenteConducta($fuente)) {
            $texto = trim((string) ($it->texto ?? ''));

            return [
                'mostrar' => $texto !== '',
                'texto' => $texto,
                'tight' => false,
            ];
        }

        $t = (float) ($it->total ?? 0);
        $esInas = $fuente === 'inasistencias';

        return [
            'mostrar' => $esInas ? (abs($t) >= 0.005) : ((int) round($t) !== 0),
            'texto' => $esInas
                ? number_format($t, 2, ',', '')
                : (string) (int) round($t),
            'tight' => in_array($fuente, ['inasistencias', 'sanciones'], true),
        ];
    }

    /**
     * Ítems de boletín para muchas matrículas (unas pocas consultas GROUP BY, no una por alumno).
     *
     * Fuentes numéricas: `inasistencias` / `sanciones` (SUM cantidad).
     * Fuentes de texto: `conducta1` / `conducta2` → columnas homónimas de `matricula`.
     * Si el tenant no tiene filas activas en `itemsboletin` para esas fuentes, no se emite nada.
     *
     * @param  list<int>  $idMatriculas
     * @return array<int, list<object{etiqueta: string, fuente: string, total: float, texto?: string}>>
     */
    public static function itemsBoletinPorMatriculas(array $idMatriculas, int $idTerlec): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $idMatriculas),
            fn (int $id) => $id > 0
        )));
        if ($ids === []) {
            return [];
        }

        $definiciones = self::definicionesItemsBoletinActivas($idTerlec);
        $defsValidas = [];
        foreach ($definiciones as $def) {
            $etiqueta = trim((string) ($def->etiqueta ?? ''));
            $fuente = trim((string) ($def->fuente ?? ''));
            $cond = trim((string) ($def->condicion_where ?? ''));
            if ($etiqueta === '' || ! in_array($fuente, self::ITEMS_BOLETIN_FUENTES, true)) {
                continue;
            }
            if (! self::esFuenteConducta($fuente) && ! self::condicionWhereItemsBoletinSegura($cond)) {
                continue;
            }
            $defsValidas[] = (object) [
                'etiqueta' => $etiqueta,
                'fuente' => $fuente,
                'condicion_where' => $cond,
            ];
        }

        /** @var array<int, list<object{etiqueta: string, fuente: string, total: float, texto?: string}>> $out */
        $out = [];
        foreach ($ids as $idMat) {
            $out[$idMat] = [];
        }

        if ($defsValidas === []) {
            return $out;
        }

        $conductasPorMatricula = self::conductasMatriculaPorIds($ids, $defsValidas);

        foreach ($defsValidas as $def) {
            if (self::esFuenteConducta($def->fuente)) {
                foreach ($ids as $idMat) {
                    $out[$idMat][] = (object) [
                        'etiqueta' => $def->etiqueta,
                        'fuente' => $def->fuente,
                        'total' => 0.0,
                        'texto' => $conductasPorMatricula[$idMat][$def->fuente] ?? '',
                    ];
                }

                continue;
            }

            $totales = self::sumarCantidadItemsBoletinPorMatriculas(
                $def->fuente,
                $def->condicion_where,
                $ids
            );
            foreach ($ids as $idMat) {
                $out[$idMat][] = (object) [
                    'etiqueta' => $def->etiqueta,
                    'fuente' => $def->fuente,
                    'total' => $totales[$idMat] ?? 0.0,
                ];
            }
        }

        return $out;
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

    private const ITEMS_BOLETIN_FUENTES = ['inasistencias', 'sanciones', 'conducta1', 'conducta2'];

    private const ITEMS_BOLETIN_FUENTES_CONDUCTA = ['conducta1', 'conducta2'];

    private static function esFuenteConducta(string $fuente): bool
    {
        return in_array($fuente, self::ITEMS_BOLETIN_FUENTES_CONDUCTA, true);
    }

    /**
     * @return list<object{etiqueta: string, fuente: string, condicion_where: string}>
     */
    private static function definicionesItemsBoletinActivas(int $idTerlec): array
    {
        if (array_key_exists($idTerlec, self::$cacheDefsItemsBoletin)) {
            return self::$cacheDefsItemsBoletin[$idTerlec];
        }

        if (! Schema::hasTable('itemsboletin')) {
            self::$cacheDefsItemsBoletin[$idTerlec] = [];

            return [];
        }

        self::$cacheDefsItemsBoletin[$idTerlec] = DB::table('itemsboletin')
            ->where('activo', true)
            ->where(function ($q) use ($idTerlec) {
                $q->whereNull('idTerlec')
                    ->orWhere('idTerlec', $idTerlec);
            })
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['etiqueta', 'fuente', 'condicion_where'])
            ->all();

        return self::$cacheDefsItemsBoletin[$idTerlec];
    }

    /**
     * Filas para el pie del PDF (inasistencias / sanciones / conducta) según {@see ItemBoletin}.
     *
     * @return list<object{etiqueta: string, fuente: string, total: float, texto?: string}>
     */
    private static function itemsBoletinParaMatricula(int $idMatricula, int $idTerlec): array
    {
        if ($idMatricula <= 0) {
            return [];
        }

        return self::itemsBoletinPorMatriculas([$idMatricula], $idTerlec)[$idMatricula] ?? [];
    }

    /**
     * @param  list<int>  $idMatriculas
     * @param  list<object{fuente: string}>  $defsValidas
     * @return array<int, array{conducta1?: string, conducta2?: string}>
     */
    private static function conductasMatriculaPorIds(array $idMatriculas, array $defsValidas): array
    {
        $columnas = [];
        foreach ($defsValidas as $def) {
            $fuente = (string) ($def->fuente ?? '');
            if (self::esFuenteConducta($fuente) && Schema::hasColumn('matricula', $fuente)) {
                $columnas[$fuente] = true;
            }
        }
        if ($columnas === [] || $idMatriculas === []) {
            return [];
        }

        $select = array_merge(['id'], array_keys($columnas));
        $out = [];
        foreach (array_chunk($idMatriculas, 200) as $chunk) {
            $rows = DB::table('matricula')
                ->whereIn('id', $chunk)
                ->get($select);

            foreach ($rows as $row) {
                $idMat = (int) $row->id;
                $vals = [];
                foreach (array_keys($columnas) as $col) {
                    $vals[$col] = trim((string) ($row->{$col} ?? ''));
                }
                $out[$idMat] = $vals;
            }
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
        $totales = self::sumarCantidadItemsBoletinPorMatriculas($tabla, $condicionAnd, [$idMatricula]);

        return $totales[$idMatricula] ?? 0.0;
    }

    /**
     * @param  list<int>  $idMatriculas
     * @return array<int, float>
     */
    private static function sumarCantidadItemsBoletinPorMatriculas(string $tabla, string $condicionAnd, array $idMatriculas): array
    {
        $out = array_fill_keys($idMatriculas, 0.0);
        if ($idMatriculas === []) {
            return $out;
        }

        foreach (array_chunk($idMatriculas, 200) as $chunk) {
            $rows = DB::table($tabla)
                ->whereIn('idMatricula', $chunk)
                ->whereRaw('('.$condicionAnd.')')
                ->groupBy('idMatricula')
                ->selectRaw('idMatricula, SUM(COALESCE(cantidad, 0)) as total')
                ->get();

            foreach ($rows as $row) {
                $idMat = (int) $row->idMatricula;
                $v = (float) ($row->total ?? 0);
                $out[$idMat] = $tabla === 'inasistencias'
                    ? round($v, 2)
                    : round($v, 0);
            }
        }

        return $out;
    }
}
