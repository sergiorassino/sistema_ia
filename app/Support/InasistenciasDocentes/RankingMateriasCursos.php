<?php

namespace App\Support\InasistenciasDocentes;

use App\Models\Curso;
use App\Models\Terlec;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ranking / estadísticas de inasistencias por materia y curso (detalle inasdocentes_detalle).
 */
final class RankingMateriasCursos
{
    public static function tieneDetalle(): bool
    {
        return Schema::hasTable('inasdocentes_detalle');
    }

    /** @return Collection<int, int> */
    public static function aniosDisponibles(): Collection
    {
        $anios = Terlec::paraSelector()
            ->pluck('ano')
            ->map(fn ($a) => (int) $a)
            ->filter(fn ($a) => $a > 0)
            ->unique()
            ->values();

        if ($anios->isEmpty()) {
            $anio = (int) (schoolCtx()->terlecAno() ?? now()->year);
            if ($anio > 0) {
                return collect([$anio]);
            }
        }

        return $anios;
    }

    public static function bimestreDesdeMesSql(): string
    {
        return 'CASE
            WHEN MONTH(i.fecha) IN (1,2) THEN 1
            WHEN MONTH(i.fecha) IN (3,4) THEN 2
            WHEN MONTH(i.fecha) IN (5,6) THEN 3
            WHEN MONTH(i.fecha) IN (7,8) THEN 4
            WHEN MONTH(i.fecha) IN (9,10) THEN 5
            WHEN MONTH(i.fecha) IN (11,12) THEN 6
            ELSE NULL END';
    }

    /**
     * @return array{
     *   filas: list<array{curso: string, materia: string, total: float}>,
     *   chart: array{labels: list<string>, data: list<float>},
     *   tieneDetalle: bool
     * }
     */
    public static function datos(int $anio, int $periodo, string $sort, string $dir, int $idNivel, int $idTerlec): array
    {
        $vacío = ['filas' => [], 'chart' => ['labels' => [], 'data' => []], 'tieneDetalle' => self::tieneDetalle()];

        if (! self::tieneDetalle() || $anio <= 0) {
            return $vacío;
        }

        if ($periodo < 0 || $periodo > 6) {
            $periodo = 0;
        }

        $sortAllow = ['curso', 'materia', 'total'];
        if (! in_array($sort, $sortAllow, true)) {
            $sort = 'total';
        }
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $bimestreCase = self::bimestreDesdeMesSql();
        $where = 'YEAR(i.fecha) = ?';
        $params = [$anio];

        if ($periodo >= 1 && $periodo <= 6) {
            $where .= " AND ({$bimestreCase}) = ?";
            $params[] = $periodo;
        }

        if ($idTerlec > 0) {
            $where .= ' AND c.idTerlec = ? AND m.idTerlec = ?';
            $params[] = $idTerlec;
            $params[] = $idTerlec;
        }

        if ($idNivel > 0) {
            $where .= ' AND c.idNivel = ?';
            $params[] = $idNivel;
        }

        $orderCol = match ($sort) {
            'curso' => 'curso',
            'materia' => 'materia',
            default => 'total',
        };

        $sql = "SELECT c.Id AS idCursos, c.cursec AS curso, m.materia, SUM(d.cantidad) AS total
            FROM inasdocentes_detalle d
            INNER JOIN inasdocentes i ON i.id = d.idInasDocentes
            INNER JOIN cursos c ON c.Id = d.idCursos
            INNER JOIN materias m ON m.id = d.idMaterias
            WHERE {$where}
            GROUP BY c.Id, c.cursec, m.id, m.materia
            ORDER BY {$orderCol} {$dir}, curso ASC, materia ASC";

        $filas = DB::select($sql, $params);
        $filas = array_map(fn ($r) => [
            'curso' => (string) ($r->curso ?? ''),
            'materia' => (string) ($r->materia ?? ''),
            'total' => (float) ($r->total ?? 0),
        ], $filas);

        $chart = self::datosGraficoPorCurso($where, $params, $periodo, $idNivel, $idTerlec);

        return [
            'filas' => $filas,
            'chart' => $chart,
            'tieneDetalle' => true,
        ];
    }

    /**
     * @param  list<mixed>  $params
     * @return array{labels: list<string>, data: list<float>}
     */
    private static function datosGraficoPorCurso(string $where, array $params, int $periodo, int $idNivel, int $idTerlec): array
    {
        $todosLosCursos = self::listarCursos($idNivel, $idTerlec);
        $bimestreCase = self::bimestreDesdeMesSql();

        if ($periodo >= 1 && $periodo <= 6) {
            $sql = "SELECT {$bimestreCase} AS bimestre, c.cursec AS curso, SUM(d.cantidad) AS total
                FROM inasdocentes_detalle d
                INNER JOIN inasdocentes i ON i.id = d.idInasDocentes
                INNER JOIN cursos c ON c.Id = d.idCursos
                INNER JOIN materias m ON m.id = d.idMaterias
                WHERE {$where}
                GROUP BY bimestre, c.Id, c.cursec";
            $porCurso = [];
            foreach (DB::select($sql, $params) as $row) {
                if ((int) ($row->bimestre ?? 0) === $periodo) {
                    $porCurso[(string) $row->curso] = (float) ($row->total ?? 0);
                }
            }
        } else {
            $sql = "SELECT c.cursec AS curso, SUM(d.cantidad) AS total
                FROM inasdocentes_detalle d
                INNER JOIN inasdocentes i ON i.id = d.idInasDocentes
                INNER JOIN cursos c ON c.Id = d.idCursos
                INNER JOIN materias m ON m.id = d.idMaterias
                WHERE {$where}
                GROUP BY c.Id, c.cursec";
            $porCurso = [];
            foreach (DB::select($sql, $params) as $row) {
                $porCurso[(string) $row->curso] = (float) ($row->total ?? 0);
            }
        }

        $labels = [];
        $data = [];
        foreach ($todosLosCursos as $curso) {
            $labels[] = $curso;
            $data[] = $porCurso[$curso] ?? 0.0;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /** @return list<string> */
    private static function listarCursos(int $idNivel, int $idTerlec): array
    {
        $q = Curso::query();
        if ($idTerlec > 0) {
            $q->where('idTerlec', $idTerlec);
        }
        if ($idNivel > 0) {
            $q->where('idNivel', $idNivel);
        }

        return $q->get(['cursec', 'c', 'orden', 'Id', 'idNivel'])
            ->pluck('cursec')
            ->map(fn ($c) => (string) $c)
            ->all();
    }
}
