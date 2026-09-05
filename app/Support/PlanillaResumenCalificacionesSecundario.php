<?php

namespace App\Support;

use App\Models\Curso;
use App\Models\Inasistencia;
use App\Models\Matricula;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Planilla resumen por curso (todas las materias, nivel secundario, impresión PDF).
 */
final class PlanillaResumenCalificacionesSecundario
{
    /** Filas visuales por estudiante (módulos 1-4, 5-8, JIS/prom, dic/feb, pie). */
    public const FILAS_POR_ESTUDIANTE = 5;

    public const FILAS_REFERENCIA_DISENO = 35;

    public const FACTOR_ALTO_FILA = 1.2;

    /**
     * @return array{
     *     cursoLabel: string,
     *     ano: int|null,
     *     materias: list<array{id: int, abrev: string}>,
     *     estudiantes: list<array<string, mixed>>
     * }
     */
    public static function build(int $cursoId): array
    {
        $secciones = self::buildSecciones([$cursoId]);
        if ($secciones === []) {
            abort(404);
        }

        $sec = $secciones[0];
        unset($sec['layout']);

        return $sec;
    }

    /**
     * Varias planillas en orden de curso (misma estructura que {@see build()} + layout por sección).
     *
     * Prefetch de previas e ítems de boletín para todos los alumnos de todos los cursos
     * (evita N+1 al imprimir "Todos").
     *
     * @param  list<int>  $cursoIds
     * @return list<array{
     *     cursoLabel: string,
     *     ano: int|null,
     *     materias: list<array{id: int, abrev: string}>,
     *     estudiantes: list<array<string, mixed>>,
     *     layout: array{cantidad: int, fontPt: float, paddingPx: float, lineHeight: float}
     * }>
     */
    public static function buildSecciones(array $cursoIds): array
    {
        $ctx = schoolCtx();
        $idTerlec = (int) $ctx->idTerlec;
        $idNivel = (int) $ctx->idNivel;
        $ano = $ctx->terlecAno();
        $anoInt = $ano !== null ? (int) $ano : null;

        $preparados = [];
        $allLegajos = [];
        $allMatriculas = [];

        foreach ($cursoIds as $cursoId) {
            $cursoId = (int) $cursoId;
            if ($cursoId < 1) {
                continue;
            }
            $prep = self::prepararCurso($cursoId, $idTerlec, $idNivel, $anoInt);
            if ($prep === null) {
                continue;
            }
            $preparados[] = $prep;
            foreach ($prep['idLegajos'] as $idLeg) {
                $allLegajos[$idLeg] = $idLeg;
            }
            foreach ($prep['idMatriculas'] as $idMat) {
                $allMatriculas[$idMat] = $idMat;
            }
        }

        if ($preparados === []) {
            return [];
        }

        $previasPorLegajo = ConsultaCalificacionesAlumno::materiasAdeudadasTextoPorLegajos(
            array_values($allLegajos),
            $idTerlec,
            $idNivel
        );
        $itemsPorMatricula = ConsultaCalificacionesAlumno::itemsBoletinPorMatriculas(
            array_values($allMatriculas),
            $idTerlec
        );

        $secciones = [];
        foreach ($preparados as $prep) {
            $data = self::armarSeccion($prep, $previasPorLegajo, $itemsPorMatricula, $ano);
            $data['layout'] = self::metricasLayout(count($data['estudiantes']));
            $secciones[] = $data;
        }

        return $secciones;
    }

    /**
     * @return array{
     *     cursoLabel: string,
     *     materiasLista: list<array{id: int, abrev: string}>,
     *     matriculas: Collection<int, Matricula>,
     *     califsPorLegajo: array<int, array<int, array<string, string>>>,
     *     inasPorMatricula: array<int, InasistenciasResumen>,
     *     idLegajos: list<int>,
     *     idMatriculas: list<int>
     * }|null
     */
    private static function prepararCurso(int $cursoId, int $idTerlec, int $idNivel, ?int $ano): ?array
    {
        $curso = Curso::query()
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->where('Id', $cursoId)
            ->first(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);

        if (! $curso) {
            return null;
        }

        $materias = DB::table('materias')
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->where('idCursos', $cursoId)
            ->orderBy('ord')
            ->orderBy('id')
            ->get(['id', 'materia', 'abrev']);

        $materiasLista = $materias
            ->map(fn (object $m) => [
                'id' => (int) $m->id,
                'abrev' => self::abrevMateria($m),
            ])
            ->values()
            ->all();

        $idsCondicionesRegulares = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES
        );

        $matriculas = Matricula::query()
            ->with('legajo')
            ->join('legajos as l', 'l.id', '=', 'matricula.idLegajos')
            ->where('matricula.idCursos', $cursoId)
            ->where('matricula.idTerlec', $idTerlec)
            ->where('matricula.idNivel', $idNivel)
            ->whereIn('matricula.idCondiciones', $idsCondicionesRegulares)
            ->whereNull('matricula.fechaBaja')
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.nombre'))
            ->select('matricula.*')
            ->get();

        $idMaterias = array_column($materiasLista, 'id');
        $califsPorLegajo = self::calificacionesPorLegajo($cursoId, $idMaterias, $idTerlec);

        $idMatriculas = $matriculas->map(fn (Matricula $m) => (int) $m->id)->all();
        $idLegajos = $matriculas->map(fn (Matricula $m) => (int) $m->idLegajos)->unique()->values()->all();
        $inasPorMatricula = self::resumenesInasistenciasPorMatricula($idMatriculas, $ano);

        return [
            'cursoLabel' => $curso->nombreParaListado(),
            'materiasLista' => $materiasLista,
            'matriculas' => $matriculas,
            'califsPorLegajo' => $califsPorLegajo,
            'inasPorMatricula' => $inasPorMatricula,
            'idLegajos' => $idLegajos,
            'idMatriculas' => $idMatriculas,
        ];
    }

    /**
     * @param  array{
     *     cursoLabel: string,
     *     materiasLista: list<array{id: int, abrev: string}>,
     *     matriculas: Collection<int, Matricula>,
     *     califsPorLegajo: array<int, array<int, array<string, string>>>,
     *     inasPorMatricula: array<int, InasistenciasResumen>
     * }  $prep
     * @param  array<int, string>  $previasPorLegajo
     * @param  array<int, list<object{etiqueta: string, fuente: string, total: float}>>  $itemsPorMatricula
     * @return array{
     *     cursoLabel: string,
     *     ano: int|null,
     *     materias: list<array{id: int, abrev: string}>,
     *     estudiantes: list<array<string, mixed>>
     * }
     */
    private static function armarSeccion(
        array $prep,
        array $previasPorLegajo,
        array $itemsPorMatricula,
        ?int $ano,
    ): array {
        $materiasLista = $prep['materiasLista'];
        $estudiantes = [];
        $ord = 0;

        foreach ($prep['matriculas'] as $mat) {
            $ord++;
            $legajo = $mat->legajo;
            $idLegajo = (int) $mat->idLegajos;
            $idMatricula = (int) $mat->id;

            $porMateria = [];
            $promediosMateria = [];
            $filasMateria = [];

            foreach ($materiasLista as $matDef) {
                $idMateria = (int) $matDef['id'];
                $row = $prep['califsPorLegajo'][$idLegajo][$idMateria] ?? self::filaCalificacionVacia();
                $porMateria[$idMateria] = self::celdasMateria($row);
                $promediosMateria[] = trim((string) ($row['calif'] ?? ''));
                $filasMateria[] = $row;
            }

            $estudiantes[] = [
                'ord' => $ord,
                'alumno' => trim(((string) ($legajo?->apellido ?? '')).', '.((string) ($legajo?->nombre ?? ''))),
                'materias' => $porMateria,
                'resumen' => self::resumenEstudiante(
                    $filasMateria,
                    $promediosMateria,
                    count($materiasLista),
                    $prep['inasPorMatricula'][$idMatricula] ?? null,
                    $previasPorLegajo[$idLegajo] ?? '',
                    $itemsPorMatricula[$idMatricula] ?? [],
                ),
            ];
        }

        return [
            'cursoLabel' => $prep['cursoLabel'],
            'ano' => $ano,
            'materias' => $materiasLista,
            'estudiantes' => $estudiantes,
        ];
    }

    /**
     * @param  list<int>  $idMaterias
     * @return array<int, array<int, array<string, string>>>
     */
    private static function calificacionesPorLegajo(int $cursoId, array $idMaterias, int $idTerlec): array
    {
        if ($idMaterias === []) {
            return [];
        }

        $cols = [
            'c.idLegajos', 'c.idMaterias',
            'c.ic01', 'c.ic02', 'c.ic03', 'c.ic04', 'c.ic05', 'c.ic06', 'c.ic07', 'c.ic08', 'c.ic09', 'c.ic10',
            'c.ic11', 'c.ic12', 'c.ic13', 'c.ic14', 'c.ic15', 'c.ic16', 'c.ic17', 'c.ic18', 'c.ic19', 'c.ic20',
            'c.ic21', 'c.ic22', 'c.ic23', 'c.ic24', 'c.ic25', 'c.ic26', 'c.ic27', 'c.ic28',
            'c.dic', 'c.feb', 'c.calif',
        ];

        $rows = DB::table('calificaciones as c')
            ->where('c.idTerlec', $idTerlec)
            ->where('c.idCursos', $cursoId)
            ->whereIn('c.idMaterias', $idMaterias)
            ->get($cols);

        $out = [];
        foreach ($rows as $r) {
            $idLeg = (int) $r->idLegajos;
            $idMat = (int) $r->idMaterias;
            $fila = self::filaCalificacionVacia();
            foreach (array_keys($fila) as $k) {
                if ($k === 'calif' || str_starts_with($k, 'ic') || in_array($k, ['dic', 'feb'], true)) {
                    $fila[$k] = (string) ($r->{$k} ?? '');
                }
            }
            $out[$idLeg][$idMat] = $fila;
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private static function filaCalificacionVacia(): array
    {
        $fila = ['dic' => '', 'feb' => '', 'calif' => ''];
        for ($i = 1; $i <= 28; $i++) {
            $fila[sprintf('ic%02d', $i)] = '';
        }

        return $fila;
    }

    /**
     * @param  array<string, string>  $row
     * @return array{
     *     modulos: list<array{texto: string, rojo: bool, gris: bool}>,
     *     jis1: array{texto: string, rojo: bool, gris: bool},
     *     jis2: array{texto: string, rojo: bool, gris: bool},
     *     promAnual: string,
     *     dic: string,
     *     feb: string
     * }
     */
    private static function celdasMateria(array $row): array
    {
        $modulos = [];
        for ($n = 1; $n <= 8; $n++) {
            $base = ($n - 1) * 3 + 1;
            $campos = [
                sprintf('ic%02d', $base),
                sprintf('ic%02d', $base + 1),
                sprintf('ic%02d', $base + 2),
            ];
            $modulos[] = PromedioAnualCalificacionesSecundario::celdaMejorNotaModulo($campos, $row);
        }

        return [
            'modulos' => $modulos,
            'jis1' => PromedioAnualCalificacionesSecundario::celdaMejorNotaModulo(['ic25', 'ic26'], $row),
            'jis2' => PromedioAnualCalificacionesSecundario::celdaMejorNotaModulo(['ic27', 'ic28'], $row),
            'promAnual' => PromedioAnualCalificacionesSecundario::formatPromedioDisplay($row['calif'] ?? ''),
            'dic' => trim((string) ($row['dic'] ?? '')),
            'feb' => trim((string) ($row['feb'] ?? '')),
        ];
    }

    /**
     * @param  list<array<string, string>>  $filasMateria  Una fila `ic01`…`ic24` por materia del curso
     * @param  list<string>  $promediosMateria
     * @param  list<object{etiqueta: string, fuente: string, total: float}>  $items
     * @return array{
     *     numRep: int,
     *     inas: string,
     *     amon: string,
     *     edFi: string,
     *     promGral: string,
     *     previas: string
     * }
     */
    private static function resumenEstudiante(
        array $filasMateria,
        array $promediosMateria,
        int $totalMateriasAnio,
        ?InasistenciasResumen $inasResumen,
        string $previas,
        array $items,
    ): array {
        $inas = self::valorItemBoletin($items, 'inasistencias');
        $amon = self::valorItemBoletin($items, 'sanciones');

        if ($inas === '' && $inasResumen !== null) {
            $total = $inasResumen->totalClase() + $inasResumen->educacionFisica;
            if ($total >= 0.005) {
                $inas = number_format($total, 2, '.', '');
            }
        }

        $edFi = '';
        if ($inasResumen !== null && $inasResumen->educacionFisica >= 0.005) {
            $edFi = number_format($inasResumen->educacionFisica, 2, '.', '');
        }

        return [
            'numRep' => self::contarReprobadas($filasMateria),
            'inas' => $inas,
            'amon' => $amon,
            'edFi' => $edFi,
            'promGral' => self::promedioGeneral($promediosMateria, $totalMateriasAnio),
            'previas' => $previas,
        ];
    }

    /**
     * @param  list<int>  $idMatriculas
     * @return array<int, InasistenciasResumen>
     */
    private static function resumenesInasistenciasPorMatricula(array $idMatriculas, ?int $ano): array
    {
        if ($idMatriculas === [] || $ano === null) {
            return [];
        }

        $rango = InformeInasistencias::rangoFechasConFiltro(null, null, $ano);
        $filas = Inasistencia::query()
            ->whereIn('idMatricula', $idMatriculas)
            ->whereBetween('fecha', [
                $rango['desde']->toDateString(),
                $rango['hasta']->toDateString(),
            ])
            ->get();

        /** @var Collection<int, Collection<int, Inasistencia>> $porMatricula */
        $porMatricula = $filas->groupBy(fn (Inasistencia $i) => (int) $i->idMatricula);

        $out = [];
        foreach ($idMatriculas as $idMat) {
            /** @var Collection<int, Inasistencia> $coleccion */
            $coleccion = $porMatricula->get($idMat, collect());
            $out[$idMat] = InasistenciasResumen::desdeColeccion($coleccion);
        }

        return $out;
    }

    /**
     * @param  list<object{etiqueta: string, fuente: string, total: float}>  $items
     */
    private static function valorItemBoletin(array $items, string $fuente): string
    {
        foreach ($items as $it) {
            if ((string) ($it->fuente ?? '') !== $fuente) {
                continue;
            }
            $t = (float) ($it->total ?? 0);
            if ($fuente === 'inasistencias') {
                return number_format($t, 2, '.', '');
            }

            return (string) (int) round($t);
        }

        return '';
    }

    /**
     * Materias con al menos un módulo 1–8 cuya mejor nota (incl. recuperatorio) es inferior a 7.
     * Igual criterio que el texto rojo de la grilla; no usa el promedio anual (`calif`).
     *
     * @param  list<array<string, string>>  $filasMateria
     */
    public static function contarReprobadas(array $filasMateria): int
    {
        $n = 0;
        foreach ($filasMateria as $row) {
            if (self::materiaTieneModuloDesaprobado($row)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @param  array<string, string>  $row
     */
    private static function materiaTieneModuloDesaprobado(array $row): bool
    {
        for ($n = 1; $n <= 8; $n++) {
            $base = ($n - 1) * 3 + 1;
            $campos = [
                sprintf('ic%02d', $base),
                sprintf('ic%02d', $base + 1),
                sprintf('ic%02d', $base + 2),
            ];
            if (PromedioAnualCalificacionesSecundario::bloqueDesaprobado($campos, $row)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Promedio general del año: suma de promedios anuales (`calif`) / cantidad de materias del curso.
     * Solo se calcula si TODAS las materias del año lectivo tienen `calif` numérico (las previas no entran).
     *
     * @param  list<string>  $promediosAnualesPorMateria  Un valor por cada materia del curso (`calif` trimado)
     */
    private static function promedioGeneral(array $promediosAnualesPorMateria, int $totalMateriasAnio): string
    {
        if ($totalMateriasAnio <= 0 || count($promediosAnualesPorMateria) !== $totalMateriasAnio) {
            return '';
        }

        $suma = 0.0;
        foreach ($promediosAnualesPorMateria as $calif) {
            $valor = self::parsePromedioAnualCalif($calif);
            if ($valor === null) {
                return '';
            }
            $suma += $valor;
        }

        return PromedioAnualCalificacionesSecundario::formatPromedioDisplay($suma / $totalMateriasAnio);
    }

    /**
     * Promedio anual persistido en `calificaciones.calif` (no se recalcula desde módulos).
     */
    private static function parsePromedioAnualCalif(mixed $calif): ?float
    {
        $s = trim((string) ($calif ?? ''));
        if ($s === '') {
            return null;
        }

        $n = str_replace(',', '.', $s);
        if (! is_numeric($n)) {
            return null;
        }

        return (float) $n;
    }

    private static function abrevMateria(object $m): string
    {
        $abrev = trim((string) ($m->abrev ?? ''));
        if ($abrev !== '') {
            return mb_strtoupper($abrev, 'UTF-8');
        }

        $nombre = trim((string) ($m->materia ?? ''));
        if ($nombre === '') {
            return '—';
        }

        $palabras = preg_split('/\s+/u', $nombre, -1, PREG_SPLIT_NO_EMPTY);
        if ($palabras === false || $palabras === []) {
            return mb_strtoupper(mb_substr($nombre, 0, 6, 'UTF-8'), 'UTF-8');
        }

        $ini = '';
        foreach ($palabras as $w) {
            $ini .= mb_substr($w, 0, 1, 'UTF-8');
        }

        return mb_strtoupper(mb_substr($ini, 0, 8, 'UTF-8'), 'UTF-8');
    }

    /**
     * @return array{
     *     cantidad: int,
     *     fontPt: float,
     *     paddingPx: float,
     *     lineHeight: float
     * }
     */
    public static function metricasLayout(int $cantidadEstudiantes): array
    {
        $filasVisuales = max(1, $cantidadEstudiantes) * self::FILAS_POR_ESTUDIANTE;
        $ratio = min(1.0, self::FILAS_REFERENCIA_DISENO / $filasVisuales);
        $f = self::FACTOR_ALTO_FILA;

        return [
            'cantidad' => $cantidadEstudiantes,
            'fontPt' => round(max(3.8, 5.0 * $ratio) + 0.5, 1),
            'paddingPx' => round(0.6 * $f * max(0.55, $ratio), 2),
            'lineHeight' => round(1.0 * $f, 2),
        ];
    }
}
