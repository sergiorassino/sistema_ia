<?php

namespace App\Support\DataMigracion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copia observaciones docentes de `infoxobse` (legacy) a `calificaciones.obs01` / `obs02`.
 *
 * Es una migración de DATOS puntual: no es migración de esquema Laravel y no se ejecuta
 * con `php artisan migrate` ni `php artisan se:migrate-legacy`. Solo vía:
 *   php artisan se:migrar-infoxobse-a-calificaciones-obs
 *
 * Mapeo de etapas:
 *   infoxobse.etapa1 → calificaciones.obs01
 *   infoxobse.etapa2 → calificaciones.obs02
 *
 * Por defecto solo matrículas activas del ciclo lectivo con mayor `terlec.ano`.
 */
final class MigrarInfoxobseACalificacionesObs
{
    /** @var list<string> */
    private const COLUMNAS_MATERIA = ['idMaterias', 'idMateria', 'IdMaterias'];

    /**
     * IDs de `terlec` del año lectivo más reciente (puede haber más de uno si `orden` > 1).
     *
     * @return list<int>
     */
    public static function idsTerlecCicloActual(): array
    {
        $maxAno = DB::table('terlec')->max('ano');
        if ($maxAno === null || (int) $maxAno < 1) {
            return [];
        }

        return DB::table('terlec')
            ->where('ano', (int) $maxAno)
            ->orderByDesc('orden')
            ->orderByDesc('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function anoCicloActual(): ?int
    {
        $maxAno = DB::table('terlec')->max('ano');

        return $maxAno !== null && (int) $maxAno > 0 ? (int) $maxAno : null;
    }

    /**
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     ano_ciclo?: ?int,
     *     ids_terlec?: list<int>,
     *     filas_infoxobse?: int,
     *     actualizadas?: int,
     *     insertadas?: int,
     *     omitidas_vacio?: int,
     *     omitidas_destino?: int,
     *     omitidas_sin_matricula?: int,
     *     omitidas_sin_materia?: int,
     *     omitidas_ambiguo?: int,
     *     advertencias?: list<string>
     * }
     */
    public static function ejecutar(
        bool $dryRun = false,
        bool $force = false,
        ?int $idNivel = null,
        ?int $idTerlec = null,
        bool $soloCicloActual = true,
    ): array {
        $validacion = self::validarEsquema();
        if (! $validacion['ok']) {
            return $validacion;
        }

        $colMateriaInfox = $validacion['colMateriaInfox'];
        $idsTerlecFiltro = [];

        if ($idTerlec !== null && $idTerlec > 0) {
            $idsTerlecFiltro = [$idTerlec];
        } elseif ($soloCicloActual) {
            $idsTerlecFiltro = self::idsTerlecCicloActual();
            if ($idsTerlecFiltro === []) {
                return ['ok' => false, 'error' => 'No hay ciclos lectivos en terlec para filtrar el año actual.'];
            }
        }

        $query = DB::table('infoxobse as i')
            ->join('matricula as m', 'm.id', '=', 'i.idMatricula')
            ->whereNull('m.fechaBaja');

        if ($idsTerlecFiltro !== []) {
            $query->whereIn('m.idTerlec', $idsTerlecFiltro);
        }

        if ($idNivel !== null && $idNivel > 0) {
            $query->where('m.idNivel', $idNivel);
        }

        $select = ['i.idMatricula'];
        if ($colMateriaInfox !== null) {
            $select[] = 'i.'.$colMateriaInfox.' as idMateriaInfox';
        }
        if (Schema::hasColumn('infoxobse', 'ord')) {
            $select[] = 'i.ord as ordInfox';
        }
        $select[] = 'i.etapa1';
        $select[] = 'i.etapa2';
        $select[] = 'm.idLegajos';
        $select[] = 'm.idTerlec';
        $select[] = 'm.idCursos';
        $select[] = 'm.idNivel';

        $filas = $query->orderBy('i.idMatricula')->get($select);

        $stats = [
            'ok' => true,
            'ano_ciclo' => self::anoCicloActual(),
            'ids_terlec' => $idsTerlecFiltro,
            'filas_infoxobse' => $filas->count(),
            'actualizadas' => 0,
            'insertadas' => 0,
            'omitidas_vacio' => 0,
            'omitidas_destino' => 0,
            'omitidas_sin_matricula' => 0,
            'omitidas_sin_materia' => 0,
            'omitidas_ambiguo' => 0,
            'advertencias' => [],
        ];

        foreach ($filas as $fila) {
            $etapa1 = trim((string) ($fila->etapa1 ?? ''));
            $etapa2 = trim((string) ($fila->etapa2 ?? ''));

            if ($etapa1 === '' && $etapa2 === '') {
                $stats['omitidas_vacio']++;

                continue;
            }

            $idMatricula = (int) $fila->idMatricula;
            if ($idMatricula < 1) {
                $stats['omitidas_sin_matricula']++;

                continue;
            }

            $resolucion = self::resolverMateria(
                (int) ($fila->idMateriaInfox ?? 0),
                isset($fila->ordInfox) ? (int) $fila->ordInfox : 0,
                (int) $fila->idCursos,
                (int) $fila->idNivel,
                (int) $fila->idTerlec,
            );

            if (! $resolucion['ok']) {
                $stats['omitidas_sin_materia']++;
                if ($resolucion['motivo'] !== null) {
                    $stats['advertencias'][] = "Matrícula {$idMatricula}: {$resolucion['motivo']}";
                }

                continue;
            }

            $idMaterias = $resolucion['idMaterias'];
            $ordMateria = $resolucion['ord'];

            $calif = self::buscarCalificacion($idMatricula, $idMaterias, $ordMateria);
            if ($calif === false) {
                $stats['omitidas_ambiguo']++;
                $stats['advertencias'][] = "Matrícula {$idMatricula}, materia {$idMaterias}: varias filas en calificaciones; revisar manualmente.";

                continue;
            }

            $payload = [];
            if ($etapa1 !== '') {
                $actual = $calif !== null ? trim((string) ($calif->obs01 ?? '')) : '';
                if ($actual === '' || $force) {
                    $payload['obs01'] = $etapa1;
                } elseif ($actual !== $etapa1) {
                    $stats['omitidas_destino']++;
                }
            }

            if ($etapa2 !== '') {
                $actual = $calif !== null ? trim((string) ($calif->obs02 ?? '')) : '';
                if ($actual === '' || $force) {
                    $payload['obs02'] = $etapa2;
                } elseif ($actual !== $etapa2) {
                    $stats['omitidas_destino']++;
                }
            }

            if ($payload === []) {
                continue;
            }

            if ($dryRun) {
                if ($calif !== null) {
                    $stats['actualizadas']++;
                } else {
                    $stats['insertadas']++;
                }

                continue;
            }

            if ($calif !== null) {
                DB::table('calificaciones')
                    ->where('id', (int) $calif->id)
                    ->where('idMatricula', $idMatricula)
                    ->update($payload);
                $stats['actualizadas']++;

                continue;
            }

            DB::table('calificaciones')->insert(array_merge([
                'idMatricula' => $idMatricula,
                'idLegajos' => (int) $fila->idLegajos,
                'idTerlec' => (int) $fila->idTerlec,
                'idCursos' => (int) $fila->idCursos,
                'idMaterias' => $idMaterias,
                'ord' => $ordMateria,
                'obs01' => $payload['obs01'] ?? '',
                'obs02' => $payload['obs02'] ?? '',
            ], self::camposVaciosInsert()));
            $stats['insertadas']++;
        }

        if (count($stats['advertencias']) > 20) {
            $stats['advertencias'] = array_slice($stats['advertencias'], 0, 20);
            $stats['advertencias'][] = '… (más advertencias omitidas en el resumen)';
        }

        return $stats;
    }

    /**
     * @return array{ok: bool, error?: string, colMateriaInfox?: ?string}
     */
    public static function validarEsquema(): array
    {
        if (! Schema::hasTable('infoxobse')) {
            return ['ok' => false, 'error' => 'No existe la tabla infoxobse.'];
        }

        if (! Schema::hasTable('calificaciones') || ! Schema::hasTable('matricula') || ! Schema::hasTable('materias')) {
            return ['ok' => false, 'error' => 'Faltan tablas calificaciones, matricula o materias.'];
        }

        if (! Schema::hasTable('terlec')) {
            return ['ok' => false, 'error' => 'No existe la tabla terlec.'];
        }

        if (! Schema::hasColumn('infoxobse', 'idMatricula')) {
            return ['ok' => false, 'error' => 'infoxobse no tiene columna idMatricula.'];
        }

        if (! Schema::hasColumn('infoxobse', 'etapa1') || ! Schema::hasColumn('infoxobse', 'etapa2')) {
            return ['ok' => false, 'error' => 'infoxobse no tiene columnas etapa1/etapa2.'];
        }

        if (! Schema::hasColumn('calificaciones', 'obs01') || ! Schema::hasColumn('calificaciones', 'obs02')) {
            return ['ok' => false, 'error' => 'calificaciones no tiene columnas obs01/obs02.'];
        }

        $colMateria = self::columnaMateriaInfoxobse();
        if ($colMateria === null && ! Schema::hasColumn('infoxobse', 'ord')) {
            return [
                'ok' => false,
                'error' => 'infoxobse no tiene idMaterias/idMateria ni ord para identificar la materia.',
            ];
        }

        return ['ok' => true, 'colMateriaInfox' => $colMateria];
    }

    public static function columnaMateriaInfoxobse(): ?string
    {
        foreach (self::COLUMNAS_MATERIA as $col) {
            if (Schema::hasColumn('infoxobse', $col)) {
                return $col;
            }
        }

        return null;
    }

    /**
     * @return array{ok: bool, idMaterias?: int, ord?: int, motivo?: string}
     */
    private static function resolverMateria(
        int $idMateriaInfox,
        int $ordInfox,
        int $idCurso,
        int $idNivel,
        int $idTerlec,
    ): array {
        if ($idMateriaInfox > 0) {
            $materia = DB::table('materias')
                ->where('id', $idMateriaInfox)
                ->where('idCursos', $idCurso)
                ->where('idNivel', $idNivel)
                ->where('idTerlec', $idTerlec)
                ->first(['id', 'ord']);

            if ($materia === null) {
                return [
                    'ok' => false,
                    'motivo' => "idMaterias {$idMateriaInfox} no pertenece al curso/ciclo de la matrícula.",
                ];
            }

            return [
                'ok' => true,
                'idMaterias' => (int) $materia->id,
                'ord' => (int) $materia->ord,
            ];
        }

        if ($ordInfox > 0) {
            $materia = DB::table('materias')
                ->where('idCursos', $idCurso)
                ->where('idNivel', $idNivel)
                ->where('idTerlec', $idTerlec)
                ->where('ord', $ordInfox)
                ->orderBy('id')
                ->first(['id', 'ord']);

            if ($materia === null) {
                return [
                    'ok' => false,
                    'motivo' => "No hay materia con ord {$ordInfox} en el curso de la matrícula.",
                ];
            }

            return [
                'ok' => true,
                'idMaterias' => (int) $materia->id,
                'ord' => (int) $materia->ord,
            ];
        }

        return ['ok' => false, 'motivo' => 'Fila sin idMaterias ni ord.'];
    }

    /**
     * @return object{id: int, obs01: ?string, obs02: ?string}|null|false
     */
    private static function buscarCalificacion(int $idMatricula, int $idMaterias, int $ordMateria): object|null|false
    {
        if ($idMaterias > 0) {
            $porId = DB::table('calificaciones')
                ->where('idMatricula', $idMatricula)
                ->where('idMaterias', $idMaterias)
                ->get(['id', 'obs01', 'obs02']);

            if ($porId->count() > 1) {
                return false;
            }

            if ($porId->count() === 1) {
                return $porId->first();
            }
        }

        if ($ordMateria < 1) {
            return null;
        }

        $legacy = DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->where('ord', $ordMateria)
            ->where(function ($q): void {
                $q->whereNull('idMaterias')
                    ->orWhere('idMaterias', 0);
            })
            ->get(['id', 'obs01', 'obs02']);

        if ($legacy->count() > 1) {
            return false;
        }

        return $legacy->first();
    }

    /** @return array<string, string> */
    private static function camposVaciosInsert(): array
    {
        return [
            'ic01' => '',
            'ic02' => '',
            'ic03' => '',
        ];
    }
}
