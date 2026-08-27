<?php

namespace App\Support;

use App\Comunicaciones\CanalesPolicy;
use App\Support\Database\PersistenciaColumnas;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Preceptor(es) asignados a un curso (tabla legacy `preceptoresporcurso`).
 *
 * El esquema varía entre despliegues: puede usar `idNivel`, `idNiveles` o solo `idCursos`/`idTerlec`,
 * y `idProfesores` o `idProfesor`.
 */
final class PreceptoresPorCurso
{
    public const TABLA = 'preceptoresporcurso';

    /**
     * IDs de profesores preceptores del curso en el ciclo/nivel indicados.
     *
     * @return list<int>
     */
    public static function idsPreceptores(int $idCurso, int $idNivel, int $idTerlec): array
    {
        if ($idCurso < 1 || ! self::tablaDisponible()) {
            return [];
        }

        $colProf = self::columnaProfesor();
        if ($colProf === null) {
            return [];
        }

        $q = DB::table(self::TABLA)->where('idCursos', $idCurso);
        self::aplicarFiltroContexto($q, $idCurso, $idNivel, $idTerlec);

        $out = [];
        foreach ($q->pluck($colProf) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    public static function tablaDisponible(): bool
    {
        return Schema::hasTable(self::TABLA)
            && Schema::hasColumn(self::TABLA, 'idCursos')
            && self::columnaProfesor() !== null;
    }

    public static function mensajeSiTablaNoDisponible(): ?string
    {
        if (self::tablaDisponible()) {
            return null;
        }

        if (! Schema::hasTable(self::TABLA)) {
            return 'No está disponible la tabla preceptoresporcurso. Ejecute php artisan migrate (o se:migrate-legacy --force) en este colegio.';
        }

        return 'La tabla preceptoresporcurso no tiene las columnas necesarias (idCursos e idProfesores o idProfesor).';
    }

    /**
     * Asignaciones del ciclo/nivel, agrupadas por curso.
     *
     * @param  list<int>  $idsCursos
     * @return array<int, list<array{idProfesor: int, nombre: string}>>
     */
    public static function asignacionesPorCurso(array $idsCursos, int $idNivel, int $idTerlec): array
    {
        $idsCursos = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $idsCursos),
            static fn (int $id) => $id > 0
        )));

        if ($idsCursos === [] || ! self::tablaDisponible()) {
            return [];
        }

        $colProf = self::columnaProfesor();
        if ($colProf === null) {
            return [];
        }

        $q = DB::table(self::TABLA)
            ->whereIn('idCursos', $idsCursos);
        self::aplicarFiltroContexto($q, null, $idNivel, $idTerlec);

        $filas = $q->get(['idCursos', $colProf]);

        $idsProf = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila->{$colProf} ?? 0);
            if ($id > 0) {
                $idsProf[] = $id;
            }
        }
        $idsProf = array_values(array_unique($idsProf));

        $nombres = [];
        if ($idsProf !== []) {
            $nombres = DB::table('profesores')
                ->whereIn('id', $idsProf)
                ->get(['id', 'apellido', 'nombre'])
                ->mapWithKeys(static function ($p) {
                    $nombre = trim((string) ($p->apellido ?? '').', '.(string) ($p->nombre ?? ''));

                    return [(int) $p->id => $nombre !== ',' ? $nombre : ('Legajo #'.(int) $p->id)];
                })
                ->all();
        }

        $out = [];
        foreach ($filas as $fila) {
            $idCurso = (int) ($fila->idCursos ?? 0);
            $idProfesor = (int) ($fila->{$colProf} ?? 0);
            if ($idCurso < 1 || $idProfesor < 1) {
                continue;
            }
            $out[$idCurso] ??= [];
            foreach ($out[$idCurso] as $ya) {
                if ((int) $ya['idProfesor'] === $idProfesor) {
                    continue 2;
                }
            }
            $out[$idCurso][] = [
                'idProfesor' => $idProfesor,
                'nombre' => (string) ($nombres[$idProfesor] ?? ('Legajo #'.$idProfesor)),
            ];
        }

        return $out;
    }

    /**
     * Personal del nivel con rol preceptor (`profesortipo.tipo`).
     *
     * @return Collection<int, object{id: int, apellido: string, nombre: string}>
     */
    public static function preceptoresElegibles(int $idNivel): Collection
    {
        $q = DB::table('profesores as p')
            ->join('profesortipo as pt', 'pt.id', '=', 'p.IdTipoProf')
            ->orderBy('p.apellido')
            ->orderBy('p.nombre');

        if ($idNivel > 0) {
            $q->where(function ($w) use ($idNivel) {
                $w->where('p.nivel', $idNivel)
                    ->orWhereNull('p.nivel')
                    ->orWhere('p.nivel', 0);
            });
        }

        return $q->get(['p.id', 'p.apellido', 'p.nombre', 'pt.tipo'])
            ->filter(static function ($r) {
                return CanalesPolicy::normalizarRolProfesor((string) ($r->tipo ?? '')) === 'preceptor';
            })
            ->values();
    }

    public static function profesorEsPreceptorElegible(int $idProfesor, int $idNivel): bool
    {
        if ($idProfesor < 1) {
            return false;
        }

        foreach (self::preceptoresElegibles($idNivel) as $p) {
            if ((int) $p->id === $idProfesor) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public static function asignar(int $idCurso, int $idProfesor, int $idNivel, int $idTerlec): array
    {
        $bloqueo = self::mensajeSiTablaNoDisponible();
        if ($bloqueo !== null) {
            return ['ok' => false, 'mensaje' => $bloqueo];
        }

        $colProf = self::columnaProfesor();
        if ($colProf === null) {
            return ['ok' => false, 'mensaje' => 'No se encontró la columna del preceptor en preceptoresporcurso.'];
        }

        $payload = [
            'idCursos' => $idCurso,
            $colProf => $idProfesor,
        ];

        $colNivel = self::columnaNivel();
        if ($colNivel !== null && $idNivel > 0) {
            $payload[$colNivel] = $idNivel;
        }
        if (Schema::hasColumn(self::TABLA, 'idTerlec') && $idTerlec > 0) {
            $payload['idTerlec'] = $idTerlec;
        }

        $preparado = PersistenciaColumnas::prepararPayload(self::TABLA, $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            return [
                'ok' => false,
                'mensaje' => PersistenciaColumnas::mensajeColumnasInexistentes(
                    self::TABLA,
                    $preparado['columnas_con_valor_sin_columna']
                ),
            ];
        }

        $ya = self::queryAlcance($idCurso, $idNivel, $idTerlec)
            ->where($colProf, $idProfesor)
            ->exists();
        if ($ya) {
            return ['ok' => false, 'mensaje' => 'Ese preceptor ya está asignado a este curso.'];
        }

        try {
            DB::table(self::TABLA)->insert($preparado['payload']);
        } catch (QueryException $e) {
            $desdeEx = PersistenciaColumnas::mensajeDesdeQueryException($e);

            return ['ok' => false, 'mensaje' => $desdeEx ?? 'No se pudo guardar la asignación.'];
        }

        $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
            self::TABLA,
            [
                'idCursos' => $idCurso,
                $colProf => $idProfesor,
            ] + self::whereContexto($idNivel, $idTerlec),
            $preparado['payload']
        );
        if ($noPersistidas !== []) {
            return [
                'ok' => false,
                'mensaje' => PersistenciaColumnas::mensajeColumnasNoPersistidas(self::TABLA, $noPersistidas),
            ];
        }

        return ['ok' => true, 'mensaje' => 'Preceptor asignado.'];
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public static function quitar(int $idCurso, int $idProfesor, int $idNivel, int $idTerlec): array
    {
        $bloqueo = self::mensajeSiTablaNoDisponible();
        if ($bloqueo !== null) {
            return ['ok' => false, 'mensaje' => $bloqueo];
        }

        $colProf = self::columnaProfesor();
        if ($colProf === null) {
            return ['ok' => false, 'mensaje' => 'No se encontró la columna del preceptor en preceptoresporcurso.'];
        }

        $q = self::queryAlcance($idCurso, $idNivel, $idTerlec)
            ->where($colProf, $idProfesor);

        if (! $q->exists()) {
            return ['ok' => false, 'mensaje' => 'No se encontró la asignación a quitar.'];
        }

        try {
            $borrados = (clone $q)->delete();
        } catch (QueryException $e) {
            $desdeEx = PersistenciaColumnas::mensajeDesdeQueryException($e);

            return ['ok' => false, 'mensaje' => $desdeEx ?? 'No se pudo quitar la asignación.'];
        }

        if ((int) $borrados < 1) {
            return ['ok' => false, 'mensaje' => 'No se pudo quitar la asignación.'];
        }

        return ['ok' => true, 'mensaje' => 'Asignación quitada.'];
    }

    public static function columnaProfesor(?string $tabla = null): ?string
    {
        $tabla ??= self::TABLA;
        if (Schema::hasColumn($tabla, 'idProfesores')) {
            return 'idProfesores';
        }
        if (Schema::hasColumn($tabla, 'idProfesor')) {
            return 'idProfesor';
        }

        return null;
    }

    private static function columnaNivel(): ?string
    {
        if (Schema::hasColumn(self::TABLA, 'idNivel')) {
            return 'idNivel';
        }
        if (Schema::hasColumn(self::TABLA, 'idNiveles')) {
            return 'idNiveles';
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    private static function whereContexto(int $idNivel, int $idTerlec): array
    {
        $where = [];
        if (Schema::hasColumn(self::TABLA, 'idTerlec') && $idTerlec > 0) {
            $where['idTerlec'] = $idTerlec;
        }
        $colNivel = self::columnaNivel();
        if ($colNivel !== null && $idNivel > 0) {
            $where[$colNivel] = $idNivel;
        }

        return $where;
    }

    private static function queryAlcance(int $idCurso, int $idNivel, int $idTerlec)
    {
        $q = DB::table(self::TABLA)->where('idCursos', $idCurso);
        self::aplicarFiltroContexto($q, $idCurso, $idNivel, $idTerlec);

        return $q;
    }

    private static function aplicarFiltroContexto($q, ?int $idCurso, int $idNivel, int $idTerlec): void
    {
        if (Schema::hasColumn(self::TABLA, 'idTerlec') && $idTerlec > 0) {
            $q->where('idTerlec', $idTerlec);
        }

        if (Schema::hasColumn(self::TABLA, 'idNivel') && $idNivel > 0) {
            $q->where('idNivel', $idNivel);
        } elseif (Schema::hasColumn(self::TABLA, 'idNiveles') && $idNivel > 0) {
            $q->where('idNiveles', $idNivel);
        } elseif ($idNivel > 0 && Schema::hasTable('cursos') && Schema::hasColumn('cursos', 'idNivel')) {
            $idCursoFiltro = (int) ($idCurso ?? 0);
            $q->whereExists(function ($sub) use ($idCursoFiltro, $idNivel) {
                $sub->from('cursos')
                    ->whereColumn('cursos.Id', 'preceptoresporcurso.idCursos')
                    ->where('cursos.idNivel', $idNivel);
                if ($idCursoFiltro > 0) {
                    $sub->where('cursos.Id', $idCursoFiltro);
                }
            });
        }
    }
}
