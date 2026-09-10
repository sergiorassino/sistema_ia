<?php

namespace App\Support\Configuracion;

use App\Models\Curso;
use App\Models\Nivel;
use App\Support\Database\PersistenciaColumnas;
use App\Support\PreceptoresPorCurso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Copia asignaciones de profesores (ppc) y preceptores por curso de un año lectivo a otro.
 *
 * Equivale al proceso ScriptCase de pasaje de ppc / preceptoresporcurso.
 * Idempotente: si la asignación ya existe en el destino, la omite.
 * No crea cursos ni materias: el destino debe existir (p. ej. tras copiar estructura).
 */
final class CopiarAsignacionesProfPrecepAnio
{
    /**
     * @param  list<int>  $idNiveles
     * @return array{
     *     ppc: array{origen: int, existentes: int, a_crear: int, sin_materia: int},
     *     preceptores: array{origen: int, existentes: int, a_crear: int, sin_curso: int},
     *     por_nivel: list<array{id: int, nombre: string, ppc: int, preceptores: int}>
     * }
     */
    public static function previsualizar(int $idTerlecOrigen, int $idTerlecDestino, array $idNiveles): array
    {
        return self::procesar($idTerlecOrigen, $idTerlecDestino, $idNiveles, dryRun: true);
    }

    /**
     * @param  list<int>  $idNiveles
     * @return array{
     *     ppc: array{origen: int, existentes: int, a_crear: int, sin_materia: int},
     *     preceptores: array{origen: int, existentes: int, a_crear: int, sin_curso: int},
     *     por_nivel: list<array{id: int, nombre: string, ppc: int, preceptores: int}>
     * }
     */
    public static function ejecutar(int $idTerlecOrigen, int $idTerlecDestino, array $idNiveles): array
    {
        return DB::transaction(function () use ($idTerlecOrigen, $idTerlecDestino, $idNiveles) {
            return self::procesar($idTerlecOrigen, $idTerlecDestino, $idNiveles, dryRun: false);
        });
    }

    /**
     * Clave para emparejar la materia de origen con la del año destino
     * (idMatPlan + división c/s + nivel, como el sistema anterior).
     */
    public static function claveMateriaDestino(int $idNivel, mixed $idMatPlan, mixed $c, mixed $s): string
    {
        return $idNivel.'|'.(int) $idMatPlan.'|'
            .trim((string) ($c ?? '')).'|'
            .trim((string) ($s ?? ''));
    }

    public static function clavePpc(int $idMateria, int $idProfesor): string
    {
        return $idMateria.'|'.$idProfesor;
    }

    public static function clavePreceptor(int $idCurso, int $idProfesor): string
    {
        return $idCurso.'|'.$idProfesor;
    }

    /**
     * @param  list<int>  $idNiveles
     * @return list<int>
     */
    public static function nivelesValidos(array $idNiveles): array
    {
        return CopiarCursosMateriasAnio::nivelesValidos($idNiveles);
    }

    /**
     * @param  list<int>  $idNiveles
     * @return array{
     *     ppc: array{origen: int, existentes: int, a_crear: int, sin_materia: int},
     *     preceptores: array{origen: int, existentes: int, a_crear: int, sin_curso: int},
     *     por_nivel: list<array{id: int, nombre: string, ppc: int, preceptores: int}>
     * }
     */
    private static function procesar(int $idTerlecOrigen, int $idTerlecDestino, array $idNiveles, bool $dryRun): array
    {
        $niveles = self::nivelesValidos($idNiveles);
        $informe = self::informeVacio($niveles);

        if ($idTerlecOrigen <= 0 || $idTerlecDestino <= 0 || $idTerlecOrigen === $idTerlecDestino || $niveles === []) {
            $informe['por_nivel'] = array_values($informe['por_nivel']);

            return $informe;
        }

        self::copiarPpc($idTerlecOrigen, $idTerlecDestino, $niveles, $dryRun, $informe);
        self::copiarPreceptores($idTerlecOrigen, $idTerlecDestino, $niveles, $dryRun, $informe);

        $informe['por_nivel'] = array_values($informe['por_nivel']);

        return $informe;
    }

    /**
     * @param  list<int>  $idNiveles
     * @return array{
     *     ppc: array{origen: int, existentes: int, a_crear: int, sin_materia: int},
     *     preceptores: array{origen: int, existentes: int, a_crear: int, sin_curso: int},
     *     por_nivel: array<int, array{id: int, nombre: string, ppc: int, preceptores: int}>
     * }
     */
    private static function informeVacio(array $idNiveles = []): array
    {
        $porNivel = [];
        if ($idNiveles !== []) {
            $nombres = Nivel::query()
                ->whereIn('id', $idNiveles)
                ->orderBy('id')
                ->get(['id', 'nivel'])
                ->keyBy('id');
            foreach ($idNiveles as $idNivel) {
                $nombre = trim((string) ($nombres->get($idNivel)?->nivel ?? ''));
                $porNivel[$idNivel] = [
                    'id' => $idNivel,
                    'nombre' => $nombre !== '' ? $nombre : 'Nivel '.$idNivel,
                    'ppc' => 0,
                    'preceptores' => 0,
                ];
            }
        }

        return [
            'ppc' => ['origen' => 0, 'existentes' => 0, 'a_crear' => 0, 'sin_materia' => 0],
            'preceptores' => ['origen' => 0, 'existentes' => 0, 'a_crear' => 0, 'sin_curso' => 0],
            'por_nivel' => $porNivel,
        ];
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private static function incPorNivel(array &$informe, int $idNivel, string $campo): void
    {
        if ($idNivel <= 0 || ! isset($informe['por_nivel'][$idNivel][$campo])) {
            return;
        }

        $informe['por_nivel'][$idNivel][$campo]++;
    }

    /**
     * @param  list<int>  $niveles
     * @param  array<string, mixed>  $informe
     */
    private static function copiarPpc(
        int $idTerlecOrigen,
        int $idTerlecDestino,
        array $niveles,
        bool $dryRun,
        array &$informe,
    ): void {
        if (! Schema::hasTable('ppc') || ! Schema::hasTable('materias') || ! Schema::hasTable('cursos')) {
            return;
        }

        $tieneSituRevis = Schema::hasColumn('ppc', 'idSituRevis');
        $selectPpc = [
            'ppc.idMateria',
            'ppc.idProfesor',
            'materias.idNivel',
            'materias.idMatPlan',
            'cursos.c',
            'cursos.s',
        ];
        if ($tieneSituRevis) {
            $selectPpc[] = 'ppc.idSituRevis';
        }

        $qPpc = DB::table('ppc')
            ->join('materias', 'ppc.idMateria', '=', 'materias.id')
            ->join('cursos', 'materias.idCursos', '=', 'cursos.Id')
            ->where('materias.idTerlec', $idTerlecOrigen)
            ->whereIn('materias.idNivel', $niveles)
            ->orderBy('materias.idNivel');
        if (Schema::hasColumn('ppc', 'id')) {
            $qPpc->orderBy('ppc.id');
        }

        $filas = $qPpc->get($selectPpc);

        $informe['ppc']['origen'] = $filas->count();
        if ($filas->isEmpty()) {
            return;
        }

        $matDestPorClave = [];
        foreach (
            DB::table('materias')
                ->join('cursos', 'materias.idCursos', '=', 'cursos.Id')
                ->where('materias.idTerlec', $idTerlecDestino)
                ->whereIn('materias.idNivel', $niveles)
                ->orderBy('materias.id')
                ->get([
                    'materias.id',
                    'materias.idNivel',
                    'materias.idMatPlan',
                    'cursos.c',
                    'cursos.s',
                ]) as $matDest
        ) {
            $clave = self::claveMateriaDestino(
                (int) $matDest->idNivel,
                $matDest->idMatPlan,
                $matDest->c,
                $matDest->s,
            );
            if (! isset($matDestPorClave[$clave])) {
                $matDestPorClave[$clave] = (int) $matDest->id;
            }
        }

        $idsDestino = array_values(array_unique(array_values($matDestPorClave)));
        $existentes = [];
        if ($idsDestino !== []) {
            foreach (
                DB::table('ppc')
                    ->whereIn('idMateria', $idsDestino)
                    ->get(['idMateria', 'idProfesor']) as $row
            ) {
                $existentes[self::clavePpc((int) $row->idMateria, (int) $row->idProfesor)] = true;
            }
        }

        foreach ($filas as $row) {
            $idProfesor = (int) ($row->idProfesor ?? 0);
            if ($idProfesor <= 0) {
                $informe['ppc']['sin_materia']++;

                continue;
            }

            $claveMat = self::claveMateriaDestino(
                (int) $row->idNivel,
                $row->idMatPlan,
                $row->c,
                $row->s,
            );
            $idMateriaDest = $matDestPorClave[$claveMat] ?? null;
            if ($idMateriaDest === null || $idMateriaDest <= 0) {
                $informe['ppc']['sin_materia']++;

                continue;
            }

            $clavePpc = self::clavePpc($idMateriaDest, $idProfesor);
            if (isset($existentes[$clavePpc])) {
                $informe['ppc']['existentes']++;

                continue;
            }

            $informe['ppc']['a_crear']++;
            self::incPorNivel($informe, (int) $row->idNivel, 'ppc');
            if ($dryRun) {
                $existentes[$clavePpc] = true;

                continue;
            }

            $idSituRevis = $tieneSituRevis && $row->idSituRevis !== null ? (int) $row->idSituRevis : 1;
            self::insertarPpc($idMateriaDest, $idProfesor, $idSituRevis, $tieneSituRevis);
            $existentes[$clavePpc] = true;
        }
    }

    private static function insertarPpc(int $idMateria, int $idProfesor, int $idSituRevis, bool $tieneSituRevis): void
    {
        $payload = [
            'idMateria' => $idMateria,
            'idProfesor' => $idProfesor,
        ];
        if ($tieneSituRevis) {
            $payload['idSituRevis'] = $idSituRevis;
        }

        $preparado = PersistenciaColumnas::prepararPayload('ppc', $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            throw new RuntimeException(
                PersistenciaColumnas::mensajeColumnasInexistentes('ppc', $preparado['columnas_con_valor_sin_columna'])
            );
        }

        $fila = PersistenciaColumnas::completarNotNullSinDefault(
            'ppc',
            PersistenciaColumnas::reemplazarNulosExplicitos('ppc', $preparado['payload']),
            ['id'],
        );

        DB::table('ppc')->insert($fila);
    }

    /**
     * @param  list<int>  $niveles
     * @param  array<string, mixed>  $informe
     */
    private static function copiarPreceptores(
        int $idTerlecOrigen,
        int $idTerlecDestino,
        array $niveles,
        bool $dryRun,
        array &$informe,
    ): void {
        if (! PreceptoresPorCurso::tablaDisponible() || ! Schema::hasTable('cursos')) {
            return;
        }

        $colProf = PreceptoresPorCurso::columnaProfesor();
        if ($colProf === null) {
            return;
        }

        $tabla = PreceptoresPorCurso::TABLA;
        $colNivel = Schema::hasColumn($tabla, 'idNivel')
            ? 'idNivel'
            : (Schema::hasColumn($tabla, 'idNiveles') ? 'idNiveles' : null);
        $tieneTerlec = Schema::hasColumn($tabla, 'idTerlec');

        $q = DB::table($tabla)
            ->join('cursos', $tabla.'.idCursos', '=', 'cursos.Id')
            ->whereIn('cursos.idNivel', $niveles);

        if ($tieneTerlec) {
            $q->where($tabla.'.idTerlec', $idTerlecOrigen);
        } else {
            $q->where('cursos.idTerlec', $idTerlecOrigen);
        }

        $select = [
            $tabla.'.idCursos',
            $tabla.'.'.$colProf.' as idProfesorAsig',
            'cursos.c',
            'cursos.s',
            'cursos.idNivel as cursoIdNivel',
        ];
        if ($colNivel !== null) {
            $select[] = $tabla.'.'.$colNivel.' as idNivelAsig';
        }

        $filas = $q->orderBy('cursos.idNivel');
        if (Schema::hasColumn($tabla, 'id')) {
            $filas->orderBy($tabla.'.id');
        }
        $filas = $filas->get($select);

        $informe['preceptores']['origen'] = $filas->count();
        if ($filas->isEmpty()) {
            return;
        }

        $cursoDestPorClave = [];
        foreach (
            Curso::query()
                ->where('idTerlec', $idTerlecDestino)
                ->whereIn('idNivel', $niveles)
                ->get(['Id', 'idNivel', 'c', 's']) as $cursoDest
        ) {
            $clave = CopiarCursosMateriasAnio::claveDivision((int) $cursoDest->idNivel, $cursoDest->c, $cursoDest->s);
            if (! isset($cursoDestPorClave[$clave])) {
                $cursoDestPorClave[$clave] = (int) ($cursoDest->Id ?? $cursoDest->getKey());
            }
        }

        $idsDestino = array_values(array_filter(array_unique(array_values($cursoDestPorClave))));
        $existentes = [];
        if ($idsDestino !== []) {
            foreach (
                DB::table($tabla)
                    ->whereIn('idCursos', $idsDestino)
                    ->get(['idCursos', $colProf]) as $row
            ) {
                $existentes[self::clavePreceptor((int) $row->idCursos, (int) ($row->{$colProf} ?? 0))] = true;
            }
        }

        foreach ($filas as $row) {
            $idProfesor = (int) ($row->idProfesorAsig ?? 0);
            $idNivel = (int) ($row->cursoIdNivel ?? $row->idNivelAsig ?? 0);
            if ($idProfesor <= 0 || $idNivel <= 0) {
                $informe['preceptores']['sin_curso']++;

                continue;
            }

            $claveCurso = CopiarCursosMateriasAnio::claveDivision($idNivel, $row->c, $row->s);
            $idCursoDest = $cursoDestPorClave[$claveCurso] ?? null;
            if ($idCursoDest === null || $idCursoDest <= 0) {
                $informe['preceptores']['sin_curso']++;

                continue;
            }

            $claveAsig = self::clavePreceptor($idCursoDest, $idProfesor);
            if (isset($existentes[$claveAsig])) {
                $informe['preceptores']['existentes']++;

                continue;
            }

            $informe['preceptores']['a_crear']++;
            self::incPorNivel($informe, $idNivel, 'preceptores');
            if ($dryRun) {
                $existentes[$claveAsig] = true;

                continue;
            }

            self::insertarPreceptor($idCursoDest, $idProfesor, $idNivel, $idTerlecDestino, $colProf, $colNivel, $tieneTerlec);
            $existentes[$claveAsig] = true;
        }
    }

    private static function insertarPreceptor(
        int $idCursoDestino,
        int $idProfesor,
        int $idNivel,
        int $idTerlecDestino,
        string $colProf,
        ?string $colNivel,
        bool $tieneTerlec,
    ): void {
        $tabla = PreceptoresPorCurso::TABLA;
        $payload = [
            'idCursos' => $idCursoDestino,
            $colProf => $idProfesor,
        ];
        if ($colNivel !== null && $idNivel > 0) {
            $payload[$colNivel] = $idNivel;
        }
        if ($tieneTerlec && $idTerlecDestino > 0) {
            $payload['idTerlec'] = $idTerlecDestino;
        }

        $preparado = PersistenciaColumnas::prepararPayload($tabla, $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            throw new RuntimeException(
                PersistenciaColumnas::mensajeColumnasInexistentes($tabla, $preparado['columnas_con_valor_sin_columna'])
            );
        }

        $fila = PersistenciaColumnas::completarNotNullSinDefault(
            $tabla,
            PersistenciaColumnas::reemplazarNulosExplicitos($tabla, $preparado['payload']),
            ['id'],
        );

        DB::table($tabla)->insert($fila);
    }
};
