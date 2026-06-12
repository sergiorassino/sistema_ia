<?php

namespace App\Support\CalificacionesPrimario;

use App\Models\Curso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de columnas (materias por `ord`) y reglas de visibilidad del formulario legacy de primario.
 */
final class CalificacionesPrimarioCatalogo
{
    /** Materias base (ord 1–14) para todos los grados. */
    public const ORD_BASE = 14;

    /** Ord extra si grado > 1. */
    public const ORD_CICLO_2 = 15;

    /** Ord extra si grado > 2. */
    public const ORD_CICLO_3 = 16;

    public const ORD_CICLO_4 = 17;

    private const GRADO_TEXTO = [
        'PRIMER GRADO' => 1,
        'SEGUNDO GRADO' => 2,
        'TERCER GRADO' => 3,
        'CUARTO GRADO' => 4,
        'QUINTO GRADO' => 5,
        'SEXTO GRADO' => 6,
        'PRIMERO' => 1,
        'SEGUNDO' => 2,
        'TERCERO' => 3,
        'CUARTO' => 4,
        'QUINTO' => 5,
        'SEXTO' => 6,
    ];

    /**
     * Grado numérico del plan (equivalente a `glo_ciclo` en ScriptCase).
     */
    public static function cicloDesdeCurso(Curso $curso): int
    {
        $curso->loadMissing('curplan');
        $texto = mb_strtoupper(trim((string) ($curso->curplan?->curPlanCurso ?? '')), 'UTF-8');

        if ($texto !== '' && isset(self::GRADO_TEXTO[$texto])) {
            return self::GRADO_TEXTO[$texto];
        }

        foreach (self::GRADO_TEXTO as $clave => $num) {
            if ($texto !== '' && str_contains($texto, $clave)) {
                return $num;
            }
        }

        if (preg_match('/\b([1-6])\s*(?:°|º|o)?\b/u', $texto, $m) === 1) {
            return max(1, min(6, (int) $m[1]));
        }

        return 1;
    }

    public static function maxOrdVisible(int $ciclo): int
    {
        if ($ciclo > 2) {
            return self::ORD_CICLO_4;
        }
        if ($ciclo > 1) {
            return self::ORD_CICLO_2;
        }

        return self::ORD_BASE;
    }

    /**
     * Materias del curso en el ciclo lectivo activo, ordenadas por `ord` (misma fuente que secundario).
     * La abreviatura del encabezado prioriza `materias.abrev` y, si falta, `matplan.abrev` (plan del curso).
     *
     * @return Collection<int, object{id: int, ord: int, abrev: string, materia: string, esInstitucional: int}>
     */
    public static function materiasParaCurso(int $idCurso, int $idNivel, int $idTerlec, int $ciclo): Collection
    {
        $maxOrd = self::maxOrdVisible($ciclo);
        $columnas = [
            'm.id',
            'm.ord',
            'm.abrev as m_abrev',
            'm.materia',
            'mp_id.abrev as mp_id_abrev',
            'mp_ord.abrev as mp_ord_abrev',
            'mp_ord.matPlanMateria as mp_ord_nombre',
        ];
        if (Schema::hasColumn('materias', 'esInstitucional')) {
            $columnas[] = 'm.esInstitucional';
        }

        $materias = DB::table('materias as m')
            ->join('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->leftJoin('matplan as mp_id', function ($join) {
                $join->on('mp_id.id', '=', 'm.idMatPlan')
                    ->where('m.idMatPlan', '>', 0);
            })
            ->leftJoin('matplan as mp_ord', function ($join) {
                $join->on('mp_ord.idCurPlan', '=', 'cu.idCurPlan')
                    ->on('mp_ord.ord', '=', 'm.ord');
            })
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->where('m.idCursos', $idCurso)
            ->where('m.ord', '<=', $maxOrd)
            ->orderBy('m.ord')
            ->orderBy('m.id')
            ->get($columnas)
            ->map(function ($r) {
                $materia = trim((string) ($r->materia ?? ''));
                if ($materia === '') {
                    $materia = trim((string) ($r->mp_ord_nombre ?? ''));
                }

                return (object) [
                    'id' => (int) $r->id,
                    'ord' => (int) $r->ord,
                    'abrev' => self::resolverAbrevEncabezado(
                        (string) ($r->m_abrev ?? ''),
                        (string) ($r->mp_id_abrev ?? ''),
                        (string) ($r->mp_ord_abrev ?? ''),
                    ),
                    'materia' => $materia,
                    'esInstitucional' => (int) ($r->esInstitucional ?? 0),
                ];
            });

        return self::ordenarMateriasParaColumnas($materias);
    }

    /**
     * Orden de columnas alineado al IPE: por `ord` y, si hay institucionales, al final del bloque curricular.
     *
     * @param  Collection<int, object{id: int, ord: int, abrev: string, materia: string, esInstitucional?: int}>  $materias
     * @return Collection<int, object{id: int, ord: int, abrev: string, materia: string, esInstitucional: int}>
     */
    public static function ordenarMateriasParaColumnas(Collection $materias): Collection
    {
        $ordenadas = $materias
            ->sortBy(fn (object $m) => [(int) $m->ord, (int) $m->id])
            ->values();

        $tieneInstitucional = $ordenadas->contains(
            fn (object $m): bool => (int) ($m->esInstitucional ?? 0) === 1,
        );

        if (! $tieneInstitucional) {
            return $ordenadas;
        }

        $curriculares = $ordenadas
            ->filter(fn (object $m): bool => (int) ($m->esInstitucional ?? 0) !== 1)
            ->values();

        $institucionales = $ordenadas
            ->filter(fn (object $m): bool => (int) ($m->esInstitucional ?? 0) === 1)
            ->values();

        return $curriculares->concat($institucionales);
    }

    /**
     * Abreviatura para el encabezado de columna (ORAL, LECT, MA.OP, etc.).
     */
    public static function resolverAbrevEncabezado(string ...$candidatos): string
    {
        foreach ($candidatos as $c) {
            $t = trim($c);
            if ($t !== '') {
                return $t;
            }
        }

        return '';
    }

    /**
     * Texto visible en el `<th>`: solo abreviatura (tooltip con nombre completo en la vista).
     *
     * @param  object|array{id?: int, ord?: int, abrev?: string, materia?: string}  $materia
     */
    public static function etiquetaEncabezadoColumna(object|array $materia): string
    {
        $abrev = is_array($materia)
            ? trim((string) ($materia['abrev'] ?? ''))
            : trim((string) ($materia->abrev ?? ''));

        return $abrev !== '' ? $abrev : '—';
    }

    /** @deprecated Use etiquetaEncabezadoColumna() */
    public static function etiquetaColumna(object $materia): string
    {
        return self::etiquetaEncabezadoColumna($materia);
    }

    /**
     * Celdas no editables (p. ej. Aprec. final deshabilitada en ORAL/ESCR en 1° grado).
     */
    public static function celdaInhabilitada(int $ciclo, int $ord, string $campo): bool
    {
        if ($campo !== 'ic03') {
            return false;
        }

        if ($ciclo > 1) {
            return false;
        }

        return in_array($ord, [1, 3], true);
    }

    /** @return list<string> */
    public static function camposNotaEditables(): array
    {
        return ['ic01', 'ic02', 'ic03'];
    }

    /** @return list<string> */
    public static function camposObservacionMatricula(): array
    {
        return ['obs1', 'obs2', 'obsAnual'];
    }
}
