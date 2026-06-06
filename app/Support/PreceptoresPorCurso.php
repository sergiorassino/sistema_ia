<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Preceptor(es) asignados a un curso (tabla legacy `preceptoresporcurso`).
 *
 * El esquema varía entre despliegues: puede usar `idNivel`, `idNiveles` o solo `idCursos`/`idTerlec`.
 */
final class PreceptoresPorCurso
{
    /**
     * IDs de profesores preceptores del curso en el ciclo/nivel indicados.
     *
     * @return list<int>
     */
    public static function idsPreceptores(int $idCurso, int $idNivel, int $idTerlec): array
    {
        if ($idCurso < 1 || ! Schema::hasTable('preceptoresporcurso')) {
            return [];
        }

        $tabla = 'preceptoresporcurso';
        $colProf = self::columnaProfesor($tabla);
        if ($colProf === null) {
            return [];
        }

        $q = DB::table($tabla)->where('idCursos', $idCurso);

        if (Schema::hasColumn($tabla, 'idTerlec') && $idTerlec > 0) {
            $q->where('idTerlec', $idTerlec);
        }

        if (Schema::hasColumn($tabla, 'idNivel') && $idNivel > 0) {
            $q->where('idNivel', $idNivel);
        } elseif (Schema::hasColumn($tabla, 'idNiveles') && $idNivel > 0) {
            $q->where('idNiveles', $idNivel);
        } elseif ($idNivel > 0 && Schema::hasTable('cursos') && Schema::hasColumn('cursos', 'idNivel')) {
            $q->whereExists(function ($sub) use ($idCurso, $idNivel) {
                $sub->from('cursos')
                    ->whereColumn('cursos.Id', 'preceptoresporcurso.idCursos')
                    ->where('cursos.Id', $idCurso)
                    ->where('cursos.idNivel', $idNivel);
            });
        }

        $rows = $q->pluck($colProf);

        $out = [];
        foreach ($rows as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    private static function columnaProfesor(string $tabla): ?string
    {
        if (Schema::hasColumn($tabla, 'idProfesores')) {
            return 'idProfesores';
        }
        if (Schema::hasColumn($tabla, 'idProfesor')) {
            return 'idProfesor';
        }

        return null;
    }
}
