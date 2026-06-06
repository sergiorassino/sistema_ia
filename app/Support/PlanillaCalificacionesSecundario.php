<?php

namespace App\Support;

use App\Models\Curso;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Planilla de calificaciones por curso y materia (nivel secundario, impresión PDF).
 */
final class PlanillaCalificacionesSecundario
{
    /** Cantidad de filas de referencia para calibrar el alto de celda en una hoja A4 vertical. */
    public const FILAS_REFERENCIA_DISENO = 35;

    /**
     * Cantidad objetivo de filas por hoja para dimensionar el alto de fila en la planilla TCPDF.
     * Si el curso tiene menos alumnos, las filas se calculan como si hubiese esta cantidad
     * (deja aire al pie); si tiene más, se sigue compactando dinámicamente.
     */
    public const FILAS_OBJETIVO_PDF = 42;

    /** Factor sobre padding / interlineado de filas de datos (1.44 ≈ +44 % sobre base compacta). */
    public const FACTOR_ALTO_FILA = 1.44;

    /** Puntos extra sumados a las fuentes de la grilla de datos. */
    public const EXTRA_FONT_PT = 1.0;

    /**
     * @return array{
     *     cursoLabel: string,
     *     materiaLabel: string,
     *     profesoresLinea: string,
     *     ano: int|null,
     *     filas: list<array<string, mixed>>
     * }
     */
    public static function build(int $cursoId, int $materiaId): array
    {
        $ctx = schoolCtx();

        $curso = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->where('Id', $cursoId)
            ->first(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);

        if (! $curso) {
            abort(404);
        }

        $materia = DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('idCursos', $cursoId)
            ->where('id', $materiaId)
            ->first(['id', 'materia']);

        if (! $materia) {
            abort(404);
        }

        $idsCondicionesRegulares = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES
        );

        $califs = DB::table('calificaciones as c')
            ->join('legajos as l', 'l.id', '=', 'c.idLegajos')
            ->join('matricula as m', 'm.idLegajos', '=', 'l.id')
            ->where('m.idCursos', $cursoId)
            ->where('m.idTerlec', (int) $ctx->idTerlec)
            ->where('m.idNivel', (int) $ctx->idNivel)
            ->whereIn('m.idCondiciones', $idsCondicionesRegulares)
            ->whereNull('m.fechaBaja')
            ->where('c.idTerlec', (int) $ctx->idTerlec)
            ->where('c.idCursos', $cursoId)
            ->where('c.idMaterias', $materiaId)
            ->orderByRaw('COALESCE(c.ord, 9999) asc')
            ->orderBy('l.apellido')
            ->orderBy('l.nombre')
            ->get([
                'c.ord',
                'l.apellido',
                'l.nombre',
                'c.ic01', 'c.ic02', 'c.ic03', 'c.ic04', 'c.ic05', 'c.ic06',
                'c.ic07', 'c.ic08', 'c.ic09', 'c.ic10', 'c.ic11', 'c.ic12',
                'c.ic13', 'c.ic14', 'c.ic15', 'c.ic16', 'c.ic17', 'c.ic18',
                'c.ic19', 'c.ic20', 'c.ic21', 'c.ic22', 'c.ic23', 'c.ic24',
                'c.ic25', 'c.ic26', 'c.ic27', 'c.ic28',
                'c.dic', 'c.feb', 'c.calif',
            ]);

        $filas = [];
        foreach ($califs as $r) {
            $row = [
                'ord' => $r->ord,
                'alumno' => trim(((string) $r->apellido).', '.((string) $r->nombre)),
            ];
            foreach ([
                'ic01', 'ic02', 'ic03', 'ic04', 'ic05', 'ic06', 'ic07', 'ic08', 'ic09', 'ic10',
                'ic11', 'ic12', 'ic13', 'ic14', 'ic15', 'ic16', 'ic17', 'ic18', 'ic19', 'ic20',
                'ic21', 'ic22', 'ic23', 'ic24', 'ic25', 'ic26', 'ic27', 'ic28',
                'dic', 'feb',
            ] as $c) {
                $row[$c] = (string) ($r->{$c} ?? '');
            }
            // Pr. final: solo valor persistido en `calif` (ver docs/05 §7 — no calcular promedios aquí).
            $row['prom'] = trim((string) ($r->calif ?? ''));
            $filas[] = $row;
        }

        return [
            'cursoLabel' => $curso->nombreParaListado(),
            'materiaLabel' => trim((string) ($materia->materia ?? '')),
            'profesoresLinea' => self::profesoresLinea($materiaId),
            'ano' => $ctx->terlecAno(),
            'filas' => $filas,
        ];
    }

    /**
     * Materias del curso en el ciclo activo (orden de planilla).
     *
     * @return Collection<int, object{id: int, materia: string|null, abrev: string|null, ord: mixed}>
     */
    public static function materiasDelCurso(int $cursoId): Collection
    {
        $ctx = schoolCtx();

        $cursoOk = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->where('Id', $cursoId)
            ->exists();

        if (! $cursoOk) {
            return collect();
        }

        return DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('idCursos', $cursoId)
            ->orderBy('ord')
            ->orderBy('id')
            ->get(['id', 'materia', 'abrev', 'ord']);
    }

    /**
     * @param  Collection<int, object{id: int}>  $materiasPermitidas  Orden de impresión
     * @return list<int>
     */
    public static function resolverIdsMaterias(string $materiasParam, Collection $materiasPermitidas): array
    {
        $allowedById = $materiasPermitidas->keyBy(fn (object $m) => (int) $m->id);

        $parsed = collect(explode(',', $materiasParam))
            ->map(fn ($v) => (int) trim((string) $v))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $out = [];
        foreach ($parsed as $id) {
            if ($allowedById->has($id) && ! in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        if (count($out) > 200) {
            return [];
        }

        $ordenados = [];
        foreach ($materiasPermitidas as $m) {
            $id = (int) $m->id;
            if (in_array($id, $out, true)) {
                $ordenados[] = $id;
            }
        }

        return $ordenados;
    }

    /**
     * Varias planillas (una hoja A4 por materia) en orden de materia del curso.
     *
     * @param  list<int>  $materiaIds
     * @return array{
     *     cursoLabel: string,
     *     ano: int|null,
     *     secciones: list<array{
     *         materiaLabel: string,
     *         profesoresLinea: string,
     *         filas: list<array<string, mixed>>,
     *         layoutFilas: array<string, float|int>
     *     }>
     * }
     */
    public static function buildSecciones(int $cursoId, array $materiaIds): array
    {
        if ($materiaIds === []) {
            abort(404);
        }

        $secciones = [];
        $cursoLabel = '';
        $ano = null;

        foreach ($materiaIds as $materiaId) {
            if ($materiaId < 1) {
                continue;
            }
            $data = self::build($cursoId, $materiaId);
            if ($cursoLabel === '') {
                $cursoLabel = $data['cursoLabel'];
                $ano = $data['ano'];
            }
            $filas = $data['filas'];
            $secciones[] = [
                'materiaLabel' => $data['materiaLabel'],
                'profesoresLinea' => $data['profesoresLinea'],
                'filas' => $filas,
                'layoutFilas' => self::metricasLayoutFilas(count($filas)),
            ];
        }

        if ($secciones === []) {
            abort(404);
        }

        return [
            'cursoLabel' => $cursoLabel,
            'ano' => $ano,
            'secciones' => $secciones,
        ];
    }

    public static function profesoresLinea(int $idMateria): string
    {
        $profes = DB::table('ppc as ppc')
            ->join('profesores as p', 'p.id', '=', 'ppc.idProfesor')
            ->where('ppc.idMateria', $idMateria)
            ->orderBy('p.apellido')
            ->orderBy('p.nombre')
            ->get(['p.apellido', 'p.nombre']);

        if ($profes->isEmpty()) {
            return '—';
        }

        return $profes
            ->map(fn ($p) => trim(mb_strtoupper((string) $p->apellido).' '.mb_strtoupper((string) $p->nombre)))
            ->filter(fn ($s) => $s !== '')
            ->implode(' — ');
    }

    /**
     * Calcula alto de fila y tamaños de fuente para que todos los alumnos entren en una sola hoja A4.
     * Con ~35 filas el alto coincide con el diseño de referencia; con más filas (p. ej. 38) se compacta.
     *
     * @return array{
     *     cantidad: int,
     *     fontDataPt: float,
     *     fontEcPt: float,
     *     fontColPt: float,
     *     espacioFilasPx: float,
     *     paddingCeldaVertPx: float,
     *     lineHeightFila: float
     * }
     */
    /**
     * Anchos de columna en % del ancho de tabla (misma fórmula que `planilla-calificaciones-hoja.blade.php`).
     *
     * @return array{
     *     ord: float,
     *     ec: float,
     *     eval: list<float>,
     *     jis: list<float>,
     *     dic: float,
     *     feb: float,
     *     prom: float
     * }
     */
    public static function anchosColumnasPorcentaje(): array
    {
        $factorAnchoNotas = 0.9;
        $wEvPctNarrow = 7.05;
        $wEvPctBase = 8.1 * 0.9;
        $wEvPct = $wEvPctNarrow * $factorAnchoNotas;
        $freedEval = 8 * 8.1 * 0.1;
        $addColoq = $freedEval / 3;
        $toPromOnly = 8 * ($wEvPctBase - $wEvPctNarrow);
        $wJisPct = 5.8 * $factorAnchoNotas;
        $wDicPct = (1.8 + $addColoq) * $factorAnchoNotas;
        $wFebPct = $wDicPct;
        $wPromPct = (1.26 + $addColoq + $toPromOnly) * $factorAnchoNotas;
        $ahorroNotasPct = (8 * $wEvPctNarrow) + (2 * 5.8) + (1.8 + $addColoq) * 2 + (1.26 + $addColoq + $toPromOnly);
        $ahorroNotasPct -= (8 * $wEvPct) + (2 * $wJisPct) + $wDicPct * 2 + $wPromPct;
        $wOrdPct = 2.5;
        $wEcPct = (19.54 - $wOrdPct) + $ahorroNotasPct;

        // Reducción adicional del 10 % sobre el ancho de cada celda de notas para
        // que la tabla no se salga del margen derecho de la hoja A4. Se aplica
        // después del cálculo de $ahorroNotasPct para NO redistribuir este recorte
        // al ancho de la columna del estudiante: el espacio liberado queda como
        // margen libre a la derecha de la planilla.
        $reduccionExtraNotas = 0.9;
        $wEvPct *= $reduccionExtraNotas;
        $wJisPct *= $reduccionExtraNotas;
        $wDicPct *= $reduccionExtraNotas;
        $wFebPct *= $reduccionExtraNotas;
        $wPromPct *= $reduccionExtraNotas;

        return [
            'ord' => $wOrdPct,
            'ec' => $wEcPct,
            'eval' => array_fill(0, 8, $wEvPct),
            'jis' => [$wJisPct, $wJisPct],
            'dic' => $wDicPct,
            'feb' => $wFebPct,
            'prom' => $wPromPct,
        ];
    }

    public static function metricasLayoutFilas(int $cantidadFilas): array
    {
        $n = max(1, $cantidadFilas);
        $ratio = min(1.0, self::FILAS_REFERENCIA_DISENO / $n);
        $f = self::FACTOR_ALTO_FILA;
        $espacioBase = $n > self::FILAS_REFERENCIA_DISENO ? 0.35 : 0.65;

        $extraFont = self::EXTRA_FONT_PT;

        return [
            'cantidad' => $cantidadFilas,
            'fontDataPt' => round(max(4.4, 5.2 * $ratio) + $extraFont, 1),
            'fontEcPt' => round(max(4.3, 5.1 * $ratio) + $extraFont, 1),
            'fontColPt' => round(max(4.4, 5.0 * $ratio) + $extraFont, 1),
            'espacioFilasPx' => round($espacioBase * $f, 2),
            'paddingCeldaVertPx' => round(1 * $f, 1),
            'lineHeightFila' => round(1 * $f, 2),
        ];
    }
}
