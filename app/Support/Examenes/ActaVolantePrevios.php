<?php

namespace App\Support\Examenes;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Actas volantes de examen (previas) — una hoja por materia del plan + condición de adeudo.
 *
 * Listado legacy: calificaciones con inscri = 1 y apro = 1, agrupado por idMatPlan y condAdeuda.
 */
final class ActaVolantePrevios
{
    /** Filas de la grilla (alumnos + vacías), como el diseño legacy en FPDF. */
    public const FILAS_POR_ACTA = 40;

    /**
     * Actas pendientes de impresión para el nivel activo.
     *
     * @return Collection<int, object{
     *     clave: string,
     *     idMatPlan: int,
     *     condAdeuda: string,
     *     idCurPlan: int,
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

        $filas = DB::table('calificaciones as c')
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->join('matplan as mp', 'mp.id', '=', 'c.idMatPlan')
            ->join('curplan as cp', 'cp.id', '=', 'mp.idCurPlan')
            ->join('planes as pl', 'pl.id', '=', 'cp.idPlan')
            ->where('c.inscri', 1)
            ->where('c.apro', 1)
            ->where('cu.idNivel', $idNivel)
            ->where('pl.idNivel', $idNivel)
            ->where('c.idMatPlan', '>', 0)
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
            ->orderBy('cp.id')
            ->orderBy('mp.ord')
            ->orderBy('mp.id')
            ->orderBy('c.condAdeuda')
            ->get();

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
            ->keyBy(fn (object $r) => self::claveActa((int) $r->idMatPlan, (string) ($r->condAdeuda ?? '')));

        $out = collect();
        foreach ($filas as $r) {
            $idMatPlan = (int) $r->idMatPlan;
            $cond = strtoupper(trim((string) ($r->condAdeuda ?? '')));
            $clave = self::claveActa($idMatPlan, $cond);
            $materia = mb_strtoupper(trim((string) ($r->matPlanMateria ?? '')), 'UTF-8');
            $curso = mb_strtoupper(trim((string) ($r->curPlanCurso ?? '')), 'UTF-8');

            $out->push((object) [
                'clave' => $clave,
                'idMatPlan' => $idMatPlan,
                'condAdeuda' => $cond,
                'idCurPlan' => (int) $r->idCurPlan,
                'materiaLabel' => $materia !== '' ? $materia : ('MATPLAN '.$idMatPlan),
                'cursoLabel' => $curso !== '' ? $curso : ('CURPLAN '.(int) $r->idCurPlan),
                'condicionLabel' => MateriasAdeudadasFiltros::tituloCondicionActa($cond),
                'cantidadAlumnos' => (int) ($conteo->get($clave)?->total ?? 0),
            ]);
        }

        return $out;
    }

    /**
     * @param  Collection<int, object{clave: string}>  $actasPermitidas
     * @return list<array{clave: string, idMatPlan: int, condAdeuda: string}>
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
            if ($allowed->has($clave) && ! in_array($clave, $out, true)) {
                $out[] = [
                    'clave' => $clave,
                    'idMatPlan' => (int) $allowed->get($clave)->idMatPlan,
                    'condAdeuda' => (string) $allowed->get($clave)->condAdeuda,
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
     * @param  list<array{clave: string, idMatPlan: int, condAdeuda: string}>  $actasSeleccionadas
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
        $actas = [];

        foreach ($actasSeleccionadas as $sel) {
            $clave = $sel['clave'];
            $meta = $metaPorClave->get($clave);
            if (! $meta) {
                continue;
            }

            $idMatPlan = (int) $sel['idMatPlan'];
            $cond = strtoupper(trim((string) $sel['condAdeuda']));

            $alumnos = DB::table('calificaciones as c')
                ->join('legajos as l', 'l.id', '=', 'c.idLegajos')
                ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
                ->where('c.idMatPlan', $idMatPlan)
                ->whereRaw('UPPER(TRIM(COALESCE(c.condAdeuda, ""))) = ?', [$cond])
                ->where('c.inscri', 1)
                ->where('c.apro', 1)
                ->where('cu.idNivel', $idNivel)
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

    public static function claveActa(int $idMatPlan, string $condAdeuda): string
    {
        $cond = strtoupper(trim($condAdeuda));

        return $idMatPlan.':'.($cond !== '' ? $cond : '_');
    }

    /**
     * @return array{idMatPlan: int, condAdeuda: string}|null
     */
    public static function parseClaveActa(string $clave): ?array
    {
        $clave = trim($clave);
        if ($clave === '' || ! str_contains($clave, ':')) {
            return null;
        }

        [$idPart, $condPart] = explode(':', $clave, 2);
        $idMatPlan = (int) $idPart;
        if ($idMatPlan < 1) {
            return null;
        }

        $cond = strtoupper(trim($condPart));
        if ($cond === '_') {
            $cond = '';
        }

        if ($cond !== '' && MateriasAdeudadasFiltros::normalizeCondicion($cond) === null) {
            return null;
        }

        return [
            'idMatPlan' => $idMatPlan,
            'condAdeuda' => $cond,
        ];
    }
}
