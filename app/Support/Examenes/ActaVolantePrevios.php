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
 * La materia se agrupa siempre por `idMatPlan` (nunca por el texto de `matPlanMateria`,
 * que puede cambiar de sintaxis entre años). En `curso_seccion` tampoco se usa el Id
 * interno de `cursos`: se reúnen cursos de distintos años lectivos con la misma sección.
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

        $query = DB::table('calificaciones as c')
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->join('matplan as mp', 'mp.id', '=', 'c.idMatPlan')
            ->join('curplan as cp', 'cp.id', '=', 'mp.idCurPlan')
            ->join('planes as pl', 'pl.id', '=', 'cp.idPlan')
            ->where('c.inscri', 1)
            ->where('c.apro', 1)
            ->where('cu.idNivel', $idNivel)
            ->where('pl.idNivel', $idNivel)
            ->where('c.idMatPlan', '>', 0);

        if ($porSeccion) {
            $filas = $query
                ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
                ->orderBy('cp.id')
                ->orderBy('cu.orden')
                ->orderBy('cu.Id')
                ->orderBy('mp.ord')
                ->orderBy('mp.id')
                ->orderBy('c.condAdeuda')
                ->get([
                    'c.idMatPlan',
                    'c.condAdeuda',
                    'c.idCursos',
                    'mp.idCurPlan',
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
                    'tc.nombre as turnoClaseNombre',
                ]);
        } else {
            $filas = $query
                ->orderBy('cp.id')
                ->orderBy('mp.ord')
                ->orderBy('mp.id')
                ->orderBy('c.condAdeuda')
                ->select([
                    'c.idMatPlan',
                    'c.condAdeuda',
                    'mp.idCurPlan',
                    'mp.matPlanMateria',
                    'mp.ord as matplan_ord',
                    'cp.curPlanCurso',
                    'cp.id as curplan_id',
                ])
                ->distinct()
                ->get();
        }

        $conteoPorClave = [];
        if ($porSeccion) {
            foreach ($filas as $r) {
                $idMatPlan = (int) $r->idMatPlan;
                $cond = strtoupper(trim((string) ($r->condAdeuda ?? '')));
                $seccionKey = self::seccionKeyDesdeFilaCurso($r);
                if ($seccionKey === '') {
                    continue;
                }
                $clave = self::claveActa($idMatPlan, $cond, $seccionKey);
                $conteoPorClave[$clave] = ($conteoPorClave[$clave] ?? 0) + 1;
            }
        } else {
            $conteo = DB::table('calificaciones as c')
                ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
                ->where('c.inscri', 1)
                ->where('c.apro', 1)
                ->where('cu.idNivel', $idNivel)
                ->where('c.idMatPlan', '>', 0)
                ->groupBy('c.idMatPlan', 'c.condAdeuda')
                ->select([
                    'c.idMatPlan',
                    'c.condAdeuda',
                    DB::raw('COUNT(*) as total'),
                ])
                ->get()
                ->keyBy(fn (object $r) => self::claveActa(
                    (int) $r->idMatPlan,
                    (string) ($r->condAdeuda ?? ''),
                ));

            foreach ($conteo as $clave => $row) {
                $conteoPorClave[$clave] = (int) ($row->total ?? 0);
            }
        }

        $out = collect();
        $clavesVistas = [];

        foreach ($filas as $r) {
            $idMatPlan = (int) $r->idMatPlan;
            $cond = strtoupper(trim((string) ($r->condAdeuda ?? '')));
            $seccionKey = null;

            if ($porSeccion) {
                $seccionKey = self::seccionKeyDesdeFilaCurso($r);
                if ($seccionKey === '') {
                    continue;
                }
            }

            $clave = self::claveActa($idMatPlan, $cond, $seccionKey);
            if (isset($clavesVistas[$clave])) {
                continue;
            }
            $clavesVistas[$clave] = true;

            $materia = mb_strtoupper(trim((string) ($r->matPlanMateria ?? '')), 'UTF-8');
            $cursoLabel = $porSeccion
                ? mb_strtoupper(MateriasAdeudadasExporter::cursoLabelDesdeFila($r), 'UTF-8')
                : mb_strtoupper(trim((string) ($r->curPlanCurso ?? '')), 'UTF-8');

            if ($cursoLabel === '') {
                $cursoLabel = $porSeccion
                    ? 'CURSO'
                    : ('CURPLAN '.(int) $r->idCurPlan);
            }

            $out->push((object) [
                'clave' => $clave,
                'idMatPlan' => $idMatPlan,
                'condAdeuda' => $cond,
                'idCurPlan' => (int) $r->idCurPlan,
                'seccionKey' => $seccionKey,
                'materiaLabel' => $materia !== '' ? $materia : ('MATPLAN '.$idMatPlan),
                'cursoLabel' => $cursoLabel,
                'condicionLabel' => MateriasAdeudadasFiltros::tituloCondicionActa($cond),
                'cantidadAlumnos' => (int) ($conteoPorClave[$clave] ?? 0),
            ]);
        }

        return $out;
    }

    /**
     * @param  Collection<int, object{clave: string}>  $actasPermitidas
     * @return list<array{clave: string, idMatPlan: int, condAdeuda: string, seccionKey: string|null}>
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
     * @param  list<array{clave: string, idMatPlan: int, condAdeuda: string, seccionKey?: string|null}>  $actasSeleccionadas
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
            $cond = strtoupper(trim((string) $sel['condAdeuda']));
            $seccionKey = isset($sel['seccionKey']) && $sel['seccionKey'] !== null && $sel['seccionKey'] !== ''
                ? (string) $sel['seccionKey']
                : null;

            $alumnosQuery = DB::table('calificaciones as c')
                ->join('legajos as l', 'l.id', '=', 'c.idLegajos')
                ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
                ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
                ->where('c.idMatPlan', $idMatPlan)
                ->whereRaw('UPPER(TRIM(COALESCE(c.condAdeuda, ""))) = ?', [$cond])
                ->where('c.inscri', 1)
                ->where('c.apro', 1)
                ->where('cu.idNivel', $idNivel);

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
                ->orderBy('l.apellido')
                ->orderBy('l.nombre')
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

    public static function claveActa(int $idMatPlan, string $condAdeuda, ?string $seccionKey = null): string
    {
        $cond = strtoupper(trim($condAdeuda));
        $base = $idMatPlan.':'.($cond !== '' ? $cond : '_');

        if ($seccionKey !== null && $seccionKey !== '') {
            return $base.':'.$seccionKey;
        }

        return $base;
    }

    /**
     * @return array{idMatPlan: int, condAdeuda: string, seccionKey: string|null}|null
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

        $idMatPlan = (int) $parts[0];
        if ($idMatPlan < 1) {
            return null;
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
            'condAdeuda' => $cond,
            'seccionKey' => $seccionKey,
        ];
    }
}
