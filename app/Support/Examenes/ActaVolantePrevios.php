<?php

namespace App\Support\Examenes;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Actas volantes de examen (previas) — una hoja por combinación según modalidad del tenant.
 *
 * Modalidades (`tenant.examenes.acta_volante_previos_modalidad`):
 * - `curso_seccion`: idMatPlan + condAdeuda + sección estructural (letra/turno).
 * - `curso`: idMatPlan + condAdeuda (reúne secciones del mismo año de plan).
 *
 * El idMatPlan se resuelve como en listado/permiso: `materias.idMatPlan` si existe,
 * si no `calificaciones.idMatPlan`. Las deudas de años anteriores (p. ej. Matemática
 * de 4.º de una egresada de 6.º) suelen tener el plan solo en `materias`.
 */
final class ActaVolantePrevios
{
    /** Filas de la grilla (alumnos + vacías), como el diseño legacy en FPDF. */
    public const FILAS_POR_ACTA = 40;

    public const MODALIDAD_CURSO = 'curso';

    public const MODALIDAD_CURSO_SECCION = 'curso_seccion';

    public static function modalidad(): string
    {
        return function_exists('tenantExamenesActaVolantePreviosModalidad')
            ? tenantExamenesActaVolantePreviosModalidad()
            : self::MODALIDAD_CURSO_SECCION;
    }

    public static function esModalidadCursoSeccion(?string $modalidad = null): bool
    {
        return ($modalidad ?? self::modalidad()) === self::MODALIDAD_CURSO_SECCION;
    }

    /**
     * Actas pendientes de impresión para el nivel activo.
     *
     * @return Collection<int, object{
     *     clave: string,
     *     idMatPlan: int,
     *     idMaterias: int,
     *     condAdeuda: string,
     *     idCurPlan: int,
     *     seccionKey: string|null,
     *     materiaLabel: string,
     *     cursoLabel: string,
     *     condicionLabel: string,
     *     cantidadAlumnos: int
     * }>
     */
    public static function actasPendientes(int $idNivel): Collection
    {
        if ($idNivel < 1) {
            return collect();
        }

        $porSeccion = self::esModalidadCursoSeccion();
        $filas = self::filasAdeudadasInscriptas($idNivel, $porSeccion);

        $conteoPorClave = [];
        $metas = [];

        foreach ($filas as $r) {
            $agrupacion = self::agrupacionDesdeFila($r, $porSeccion);
            if ($agrupacion === null) {
                continue;
            }

            $clave = $agrupacion['clave'];
            $conteoPorClave[$clave] = ($conteoPorClave[$clave] ?? 0) + 1;

            if (isset($metas[$clave])) {
                continue;
            }

            $cursoLabel = $porSeccion
                ? mb_strtoupper(MateriasAdeudadasExporter::cursoLabelDesdeFila($r), 'UTF-8')
                : mb_strtoupper(trim((string) ($r->curPlanCurso ?? '')), 'UTF-8');

            if ($cursoLabel === '') {
                $cursoLabel = $porSeccion
                    ? 'CURSO'
                    : ('CURPLAN '.(int) ($r->idCurPlan ?? 0));
            }

            $materia = self::materiaLabelDesdeFila($r);
            $idMatPlan = $agrupacion['idMatPlan'];
            $idMaterias = $agrupacion['idMaterias'];

            $metas[$clave] = (object) [
                'clave' => $clave,
                'idMatPlan' => $idMatPlan,
                'idMaterias' => $idMaterias,
                'condAdeuda' => $agrupacion['condAdeuda'],
                'idCurPlan' => (int) ($r->idCurPlan ?? 0),
                'seccionKey' => $agrupacion['seccionKey'],
                'materiaLabel' => $materia !== ''
                    ? $materia
                    : ($idMatPlan > 0 ? ('MATPLAN '.$idMatPlan) : ('MATERIA '.$idMaterias)),
                'cursoLabel' => $cursoLabel,
                'condicionLabel' => MateriasAdeudadasFiltros::tituloCondicionActa($agrupacion['condAdeuda']),
                'cantidadAlumnos' => 0,
            ];
        }

        $out = collect();
        foreach ($metas as $clave => $meta) {
            $meta->cantidadAlumnos = (int) ($conteoPorClave[$clave] ?? 0);
            $out->push($meta);
        }

        return $out;
    }

    /**
     * @param  Collection<int, object{clave: string}>  $actasPermitidas
     * @return list<array{clave: string, idMatPlan: int, idMaterias: int, condAdeuda: string, seccionKey: string|null}>
     */
    public static function resolverClavesActas(string $actasParam, Collection $actasPermitidas): array
    {
        $allowed = $actasPermitidas->keyBy(fn (object $a) => (string) $a->clave);

        $parsed = collect(explode(',', $actasParam))
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values();

        $out = [];
        foreach ($parsed as $clave) {
            if ($allowed->has($clave) && ! in_array($clave, array_column($out, 'clave'), true)) {
                $meta = $allowed->get($clave);
                $out[] = [
                    'clave' => $clave,
                    'idMatPlan' => (int) $meta->idMatPlan,
                    'idMaterias' => (int) ($meta->idMaterias ?? 0),
                    'condAdeuda' => (string) $meta->condAdeuda,
                    'seccionKey' => isset($meta->seccionKey) && $meta->seccionKey !== null && $meta->seccionKey !== ''
                        ? (string) $meta->seccionKey
                        : null,
                ];
            }
        }

        if (count($out) > 200) {
            return [];
        }

        $ordenados = [];
        foreach ($actasPermitidas as $a) {
            $clave = (string) $a->clave;
            foreach ($out as $item) {
                if ($item['clave'] === $clave) {
                    $ordenados[] = $item;
                    break;
                }
            }
        }

        return $ordenados;
    }

    /**
     * @param  list<array{clave: string, idMatPlan: int, idMaterias?: int, condAdeuda: string, seccionKey?: string|null}>  $actasSeleccionadas
     * @return array{
     *     actas: list<array{
     *         cursoLabel: string,
     *         materiaLabel: string,
     *         condicionLabel: string,
     *         filas: list<array{nro: int, dni: string, nombre: string}>
     *     }>
     * }
     */
    public static function build(int $idNivel, array $actasSeleccionadas): array
    {
        if ($idNivel < 1 || $actasSeleccionadas === []) {
            return ['actas' => []];
        }

        $permitidas = self::actasPendientes($idNivel);
        $actasSeleccionadas = self::resolverClavesActas(
            implode(',', array_column($actasSeleccionadas, 'clave')),
            $permitidas,
        );

        if ($actasSeleccionadas === []) {
            return ['actas' => []];
        }

        $metaPorClave = $permitidas->keyBy(fn (object $a) => (string) $a->clave);
        $porSeccion = self::esModalidadCursoSeccion();
        $actas = [];

        foreach ($actasSeleccionadas as $sel) {
            $clave = $sel['clave'];
            $meta = $metaPorClave->get($clave);
            if (! $meta) {
                continue;
            }

            $idMatPlan = (int) $sel['idMatPlan'];
            $idMaterias = (int) ($sel['idMaterias'] ?? 0);
            $cond = strtoupper(trim((string) $sel['condAdeuda']));
            $seccionKey = isset($sel['seccionKey']) && $sel['seccionKey'] !== null && $sel['seccionKey'] !== ''
                ? (string) $sel['seccionKey']
                : null;

            $alumnosQuery = DB::table('calificaciones as c')
                ->join('legajos as l', 'l.id', '=', 'c.idLegajos');
            self::aplicarJoinsAdeudadasInscriptas($alumnosQuery, $idNivel, true);
            $alumnosQuery->whereRaw('UPPER(TRIM(COALESCE(c.condAdeuda, ""))) = ?', [$cond]);
            self::aplicarFiltroMateriaAgrupada($alumnosQuery, $idMatPlan, $idMaterias);

            if ($porSeccion) {
                if ($seccionKey === null || $seccionKey === '') {
                    continue;
                }

                $idsCursos = self::idsCursosDeSeccionKey($idNivel, $seccionKey);
                if ($idsCursos === []) {
                    continue;
                }
                $alumnosQuery->whereIn('c.idCursos', $idsCursos);
            }

            $alumnos = $alumnosQuery
                ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.apellido'))
                ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.nombre'))
                ->orderBy('l.id')
                ->get([
                    'l.apellido',
                    'l.nombre',
                    'l.dni',
                ]);

            if ($alumnos->isEmpty()) {
                continue;
            }

            $filas = [];
            $nro = 0;
            foreach ($alumnos as $r) {
                $nro++;
                $filas[] = [
                    'nro' => $nro,
                    'dni' => trim((string) ($r->dni ?? '')),
                    'nombre' => mb_strtoupper(trim(((string) $r->apellido).' '.((string) $r->nombre)), 'UTF-8'),
                ];
            }

            $actas[] = [
                'cursoLabel' => (string) $meta->cursoLabel,
                'materiaLabel' => (string) $meta->materiaLabel,
                'condicionLabel' => MateriasAdeudadasFiltros::tituloCondicionActa($cond),
                'filas' => $filas,
            ];
        }

        return ['actas' => $actas];
    }

    /**
     * Clave estructural de sección (letra/turno), independiente de `cursos.Id` y del texto
     * visible de `cursec`. El año de la materia ya lo fija `idMatPlan` → `matplan.idCurPlan`.
     */
    public static function seccionKeyDesdeFilaCurso(object $r): string
    {
        $s = mb_strtoupper(trim((string) ($r->s ?? '')), 'UTF-8');
        if ($s === '') {
            $cursec = mb_strtoupper(trim((string) ($r->cursec ?? '')), 'UTF-8');
            if ($cursec !== '' && preg_match('/([A-Z0-9]+)\s*$/u', $cursec, $m)) {
                $s = $m[1];
            }
        }
        if ($s === '') {
            $s = mb_strtoupper(trim((string) ($r->c ?? '')), 'UTF-8');
        }

        $turnoId = (int) ($r->idTurnoClase ?? 0);
        if ($s === '' && $turnoId < 1) {
            return '';
        }

        return strtoupper(($s !== '' ? $s : 'X').'-t'.$turnoId);
    }

    /**
     * @return list<int>
     */
    public static function idsCursosDeSeccionKey(int $idNivel, string $seccionKey): array
    {
        if ($idNivel < 1 || $seccionKey === '') {
            return [];
        }

        /** @var array<int, array<string, list<int>>> $cache */
        static $cache = [];

        if (! isset($cache[$idNivel])) {
            $porKey = [];
            $cursos = DB::table('cursos as cu')
                ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
                ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
                ->where('cu.idNivel', $idNivel)
                ->get([
                    'cu.Id',
                    'cu.cursec',
                    'cu.c',
                    'cu.s',
                    'cu.idTurnoClase',
                    'cu.idCurPlan as curso_idCurPlan',
                    'cp.curPlanCurso',
                    'tc.nombre as turnoClaseNombre',
                ]);

            foreach ($cursos as $cu) {
                $key = self::seccionKeyDesdeFilaCurso($cu);
                if ($key === '') {
                    continue;
                }
                $porKey[$key][] = (int) $cu->Id;
            }
            $cache[$idNivel] = $porKey;
        }

        return $cache[$idNivel][$seccionKey] ?? [];
    }

    public static function claveActa(
        int $idMatPlan,
        string $condAdeuda,
        ?string $seccionKey = null,
        int $idMaterias = 0,
    ): string {
        $cond = strtoupper(trim($condAdeuda));
        $base = $idMatPlan > 0
            ? $idMatPlan.':'.($cond !== '' ? $cond : '_')
            : 'm'.$idMaterias.':'.($cond !== '' ? $cond : '_');

        if ($seccionKey !== null && $seccionKey !== '') {
            return $base.':'.$seccionKey;
        }

        return $base;
    }

    /**
     * @return array{idMatPlan: int, idMaterias: int, condAdeuda: string, seccionKey: string|null}|null
     */
    public static function parseClaveActa(string $clave): ?array
    {
        $clave = trim($clave);
        if ($clave === '' || ! str_contains($clave, ':')) {
            return null;
        }

        $parts = explode(':', $clave);
        if (count($parts) < 2) {
            return null;
        }

        $idMatPlan = 0;
        $idMaterias = 0;
        $cabeza = trim((string) $parts[0]);
        if (preg_match('/^m(\d+)$/i', $cabeza, $m)) {
            $idMaterias = (int) $m[1];
            if ($idMaterias < 1) {
                return null;
            }
        } else {
            $idMatPlan = (int) $cabeza;
            if ($idMatPlan < 1) {
                return null;
            }
        }

        $cond = strtoupper(trim((string) $parts[1]));
        if ($cond === '_') {
            $cond = '';
        }

        if ($cond !== '' && MateriasAdeudadasFiltros::normalizeCondicion($cond) === null) {
            return null;
        }

        $seccionKey = null;
        if (isset($parts[2]) && trim((string) $parts[2]) !== '') {
            $seccionKey = strtoupper(trim((string) $parts[2]));
            if (! preg_match('/^[A-Z0-9]+-T\d+$/', $seccionKey)) {
                return null;
            }
        }

        return [
            'idMatPlan' => $idMatPlan,
            'idMaterias' => $idMaterias,
            'condAdeuda' => $cond,
            'seccionKey' => $seccionKey,
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private static function filasAdeudadasInscriptas(int $idNivel, bool $conTurnoClase): Collection
    {
        $query = DB::table('calificaciones as c');
        self::aplicarJoinsAdeudadasInscriptas($query, $idNivel, $conTurnoClase);

        $query
            ->orderBy('cp.id')
            ->orderBy('cu.orden')
            ->orderBy('cu.Id')
            ->orderBy('mp.ord')
            ->orderBy('mp.id')
            ->orderBy('m.ord')
            ->orderBy('c.condAdeuda');

        $columnas = [
            'c.idMatPlan',
            'c.idMaterias',
            'c.condAdeuda',
            'c.idCursos',
            'm.materia',
            'm.ord as materia_ord',
            'mp.id as matplan_id',
            'mp.matPlanMateria',
            'mp.ord as matplan_ord',
            'cp.curPlanCurso',
            'cp.id as curplan_id',
            'cu.idCurPlan as curso_idCurPlan',
            'cu.cursec',
            'cu.c',
            'cu.s',
            'cu.orden as curso_orden',
            'cu.idTurnoClase',
            DB::raw(self::sqlIdMatPlanResuelto().' as idMatPlanResuelto'),
            DB::raw('COALESCE(NULLIF(mp.idCurPlan, 0), NULLIF(m.idCurPlan, 0), NULLIF(cu.idCurPlan, 0), 0) as idCurPlan'),
        ];

        if ($conTurnoClase) {
            $columnas[] = 'tc.nombre as turnoClaseNombre';
        }

        return $query->get($columnas);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private static function aplicarJoinsAdeudadasInscriptas($query, int $idNivel, bool $conTurnoClase): void
    {
        $query
            ->join('materias as m', function ($join) {
                $join->on('m.id', '=', 'c.idMaterias')
                    ->on('m.idTerlec', '=', 'c.idTerlec');
            })
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->leftJoin('matplan as mp', function ($join) {
                $join->whereRaw(
                    'mp.id = IF(COALESCE(m.idMatPlan, 0) > 0, m.idMatPlan, NULLIF(COALESCE(c.idMatPlan, 0), 0))'
                );
            })
            ->leftJoin('curplan as cp', function ($join) {
                $join->whereRaw(
                    'cp.id = COALESCE(NULLIF(mp.idCurPlan, 0), NULLIF(m.idCurPlan, 0), NULLIF(cu.idCurPlan, 0))'
                );
            })
            ->where('c.inscri', 1)
            ->where('c.apro', 1)
            ->where('cu.idNivel', $idNivel);

        if ($conTurnoClase) {
            $query->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase');
        }
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private static function aplicarFiltroMateriaAgrupada($query, int $idMatPlan, int $idMaterias): void
    {
        if ($idMatPlan > 0) {
            $query->whereRaw(self::sqlIdMatPlanResuelto().' = ?', [$idMatPlan]);

            return;
        }

        $query->where('c.idMaterias', $idMaterias);
    }

    /**
     * @return array{clave: string, idMatPlan: int, idMaterias: int, condAdeuda: string, seccionKey: string|null}|null
     */
    private static function agrupacionDesdeFila(object $r, bool $porSeccion): ?array
    {
        $idMatPlan = self::idMatPlanDesdeFila($r);
        $idMaterias = (int) ($r->idMaterias ?? 0);
        if ($idMatPlan < 1 && $idMaterias < 1) {
            return null;
        }

        $cond = strtoupper(trim((string) ($r->condAdeuda ?? '')));
        $seccionKey = null;
        if ($porSeccion) {
            $seccionKey = self::seccionKeyDesdeFilaCurso($r);
            if ($seccionKey === '') {
                return null;
            }
        }

        return [
            'clave' => self::claveActa($idMatPlan, $cond, $seccionKey, $idMaterias),
            'idMatPlan' => $idMatPlan,
            'idMaterias' => $idMaterias,
            'condAdeuda' => $cond,
            'seccionKey' => $seccionKey,
        ];
    }

    public static function sqlIdMatPlanResuelto(string $aliasMateria = 'm', string $aliasCalif = 'c'): string
    {
        return "IF(COALESCE({$aliasMateria}.idMatPlan, 0) > 0, {$aliasMateria}.idMatPlan, COALESCE({$aliasCalif}.idMatPlan, 0))";
    }

    public static function idMatPlanDesdeFila(object $r): int
    {
        foreach ([$r->idMatPlanResuelto ?? 0, $r->matplan_id ?? 0] as $candidato) {
            $id = (int) $candidato;
            if ($id > 0) {
                return $id;
            }
        }

        return (int) ($r->idMatPlan ?? 0);
    }

    public static function materiaLabelDesdeFila(object $r): string
    {
        $plan = mb_strtoupper(trim((string) ($r->matPlanMateria ?? '')), 'UTF-8');
        if ($plan !== '') {
            return $plan;
        }

        return mb_strtoupper(trim((string) ($r->materia ?? '')), 'UTF-8');
    }
}
