<?php

namespace App\Support\Configuracion;

use App\Models\Curso;
use App\Models\Materia;
use App\Models\Nivel;
use App\Support\Database\PersistenciaColumnas;
use App\Support\NivelSistema;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Promueve alumnos regulares de un año lectivo a otro (matrícula + filas de calificaciones).
 *
 * Equivale al proceso ScriptCase de «promover a todos los alumnos».
 * No promociona el último año de secundario (6 por defecto, 5 en EPQ) ni el último de Adultos.
 */
final class PromoverAlumnosAnio
{
    public const ID_CONDICION_REGULAR = 1;

    public const ID_CUOTA_BECA_INICIAL = 1;

    /**
     * @param  list<int>  $idNiveles
     * @param  list<int>  $idCursos
     * @return array{
     *     matriculas: array{origen: int, a_crear: int, existentes: int, sin_curso: int, no_promovibles: int},
     *     calificaciones: array{a_crear: int},
     *     por_nivel: list<array{id: int, nombre: string, matriculas: int, calificaciones: int}>
     * }
     */
    public static function previsualizar(int $idTerlecOrigen, int $idTerlecDestino, array $idNiveles, array $idCursos): array
    {
        return self::procesar($idTerlecOrigen, $idTerlecDestino, $idNiveles, $idCursos, dryRun: true);
    }

    /**
     * @param  list<int>  $idNiveles
     * @param  list<int>  $idCursos
     * @return array{
     *     matriculas: array{origen: int, a_crear: int, existentes: int, sin_curso: int, no_promovibles: int},
     *     calificaciones: array{a_crear: int},
     *     por_nivel: list<array{id: int, nombre: string, matriculas: int, calificaciones: int}>
     * }
     */
    public static function ejecutar(int $idTerlecOrigen, int $idTerlecDestino, array $idNiveles, array $idCursos): array
    {
        return DB::transaction(function () use ($idTerlecOrigen, $idTerlecDestino, $idNiveles, $idCursos) {
            return self::procesar($idTerlecOrigen, $idTerlecDestino, $idNiveles, $idCursos, dryRun: false);
        });
    }

    /**
     * Cursos del origen que se pueden marcar (sin último año de secundario / adultos).
     *
     * @param  list<int>  $idNiveles
     * @return list<array{
     *     id: int,
     *     nombre: string,
     *     idNivel: int,
     *     nivel: string,
     *     c: string,
     *     s: string,
     *     regulares: int,
     *     destino_id: int|null,
     *     destino_nombre: string|null,
     *     destino_nivel: int|null,
     *     sin_destino: bool,
     *     existentes: int,
     *     a_crear: int
     * }>
     */
    public static function listarCursos(int $idTerlecOrigen, int $idTerlecDestino, array $idNiveles): array
    {
        $niveles = self::nivelesValidos($idNiveles);
        if ($idTerlecOrigen <= 0 || $niveles === []) {
            return [];
        }

        $cursos = self::cursosPromovibles($idTerlecOrigen, $niveles);
        if ($cursos === []) {
            return [];
        }

        $nombresNivel = self::nombresNivel($niveles);
        $idsCursos = array_map(fn (Curso $c): int => self::idCurso($c), $cursos);

        $regularesPorCurso = self::regularesPorCurso($idTerlecOrigen, $idsCursos);
        $destPorClave = $idTerlecDestino > 0 ? self::indiceCursosDestino($idTerlecDestino) : [];
        $nombresDestino = $idTerlecDestino > 0 ? self::nombresCursosDestino($destPorClave) : [];

        $legajosPorCurso = self::legajosRegularesPorCurso($idTerlecOrigen, $idsCursos);
        $yaEnDestino = $idTerlecDestino > 0
            ? self::legajosConMatriculaEnAnio($idTerlecDestino, self::aplanarLegajos($legajosPorCurso))
            : [];

        $filas = [];
        foreach ($cursos as $curso) {
            $id = self::idCurso($curso);
            $idNivel = (int) $curso->idNivel;
            $c = trim((string) ($curso->c ?? ''));
            $s = trim((string) ($curso->s ?? ''));
            $destino = self::destinoPedagogico($idNivel, self::cursoNumero($curso->c));
            $destinoId = null;
            $sinDestino = $destino === null;
            if ($destino !== null && $idTerlecDestino > 0) {
                $clave = self::claveDivision($destino['idNivel'], $destino['c'], $s);
                $destinoId = $destPorClave[$clave] ?? null;
                $sinDestino = $destinoId === null;
            } elseif ($idTerlecDestino <= 0) {
                $sinDestino = false;
            }

            $regulares = $regularesPorCurso[$id] ?? 0;
            $existentes = 0;
            foreach ($legajosPorCurso[$id] ?? [] as $idLegajo) {
                if (isset($yaEnDestino[$idLegajo])) {
                    $existentes++;
                }
            }

            $aCrear = ($sinDestino || $idTerlecDestino <= 0) ? 0 : max(0, $regulares - $existentes);

            $filas[] = [
                'id' => $id,
                'nombre' => self::nombreCurso($curso),
                'idNivel' => $idNivel,
                'nivel' => $nombresNivel[$idNivel] ?? ('Nivel '.$idNivel),
                'c' => $c,
                's' => $s,
                'regulares' => $regulares,
                'destino_id' => $destinoId,
                'destino_nombre' => $destinoId !== null ? ($nombresDestino[$destinoId] ?? null) : null,
                'destino_nivel' => $destino['idNivel'] ?? null,
                'sin_destino' => $sinDestino,
                'existentes' => $existentes,
                'a_crear' => $aCrear,
            ];
        }

        return $filas;
    }

    /**
     * Próximo nivel y `cursos.c`. Null = no se promociona (egreso).
     *
     * @return array{idNivel: int, c: int}|null
     */
    public static function destinoPedagogico(
        int $idNivel,
        int $c,
        ?int $ultimoSecundario = null,
        ?int $ultimoAdultos = null,
    ): ?array {
        $ultimoSec = $ultimoSecundario ?? self::ultimoCursoSecundario();
        $ultimoAdu = $ultimoAdultos ?? self::ultimoCursoAdultos();

        if ($idNivel === NivelSistema::SECUNDARIO && $c >= $ultimoSec) {
            return null;
        }
        if ($idNivel === NivelSistema::ADULTOS && $c >= $ultimoAdu) {
            return null;
        }
        if ($idNivel === NivelSistema::INICIAL && $c === 5) {
            return ['idNivel' => NivelSistema::PRIMARIO, 'c' => 1];
        }
        if ($idNivel === NivelSistema::PRIMARIO && $c === 6) {
            return ['idNivel' => NivelSistema::SECUNDARIO, 'c' => 1];
        }

        return ['idNivel' => $idNivel, 'c' => $c + 1];
    }

    public static function esCursoTerminal(
        int $idNivel,
        int $c,
        ?int $ultimoSecundario = null,
        ?int $ultimoAdultos = null,
    ): bool {
        return self::destinoPedagogico($idNivel, $c, $ultimoSecundario, $ultimoAdultos) === null;
    }

    public static function ultimoCursoSecundario(): int
    {
        $v = (int) config('tenant.promocion.ultimo_curso_secundario', 6);

        return $v > 0 ? $v : 6;
    }

    public static function ultimoCursoAdultos(): int
    {
        $v = (int) config('tenant.promocion.ultimo_curso_adultos', 3);

        return $v > 0 ? $v : 3;
    }

    public static function cursoNumero(mixed $c): int
    {
        return (int) trim((string) ($c ?? ''));
    }

    public static function claveDivision(int $idNivel, mixed $c, mixed $s): string
    {
        return $idNivel.'|'.trim((string) ($c ?? '')).'|'.trim((string) ($s ?? ''));
    }

    /**
     * @param  list<int>  $idNiveles
     * @return list<int>
     */
    public static function nivelesValidos(array $idNiveles): array
    {
        $ids = [];
        foreach ($idNiveles as $id) {
            $n = (int) $id;
            if ($n > 0 && NivelSistema::esNivelPedagogico($n)) {
                $ids[$n] = $n;
            }
        }

        return array_values($ids);
    }

    /**
     * @param  list<int>  $idNiveles
     * @param  list<int>  $idCursos
     * @return array{
     *     matriculas: array{origen: int, a_crear: int, existentes: int, sin_curso: int, no_promovibles: int},
     *     calificaciones: array{a_crear: int},
     *     por_nivel: list<array{id: int, nombre: string, matriculas: int, calificaciones: int}>
     * }
     */
    private static function procesar(
        int $idTerlecOrigen,
        int $idTerlecDestino,
        array $idNiveles,
        array $idCursos,
        bool $dryRun,
    ): array {
        $niveles = self::nivelesValidos($idNiveles);
        $informe = self::informeVacio($niveles);
        $idsElegidos = self::idsPositivos($idCursos);

        if ($idTerlecOrigen <= 0 || $idTerlecDestino <= 0 || $idTerlecOrigen === $idTerlecDestino || $niveles === [] || $idsElegidos === []) {
            $informe['por_nivel'] = array_values($informe['por_nivel']);

            return $informe;
        }

        $cursos = self::cursosPromovibles($idTerlecOrigen, $niveles);
        $porId = [];
        foreach ($cursos as $curso) {
            $porId[self::idCurso($curso)] = $curso;
        }

        $destPorClave = self::indiceCursosDestino($idTerlecDestino);
        $cursosDestinoUsados = [];
        foreach ($idsElegidos as $idCursoOrigen) {
            $curso = $porId[$idCursoOrigen] ?? null;
            if ($curso === null) {
                $informe['matriculas']['no_promovibles']++;

                continue;
            }

            $destino = self::destinoPedagogico((int) $curso->idNivel, self::cursoNumero($curso->c));
            if ($destino === null) {
                $informe['matriculas']['no_promovibles']++;

                continue;
            }

            $clave = self::claveDivision($destino['idNivel'], $destino['c'], $curso->s);
            $idCursoDest = $destPorClave[$clave] ?? null;
            if ($idCursoDest !== null) {
                $cursosDestinoUsados[$idCursoDest] = $idCursoDest;
            }
        }

        $materiasPorCurso = self::materiasPorCursoDestino(array_values($cursosDestinoUsados));
        $legajosYaDestino = self::legajosConMatriculaEnAnio($idTerlecDestino);

        $matriculasOrigen = DB::table('matricula')
            ->where('idTerlec', $idTerlecOrigen)
            ->where('idCondiciones', self::ID_CONDICION_REGULAR)
            ->whereIn('idCursos', $idsElegidos)
            ->orderBy('idCursos')
            ->orderBy('id')
            ->get(['id', 'idTerlec', 'idNivel', 'idCursos', 'idLegajos', 'idCondiciones']);

        $informe['matriculas']['origen'] = $matriculasOrigen->count();

        foreach ($matriculasOrigen as $mat) {
            $idCursoOrigen = (int) $mat->idCursos;
            $curso = $porId[$idCursoOrigen] ?? null;
            if ($curso === null) {
                $informe['matriculas']['no_promovibles']++;

                continue;
            }

            $idNivelOrigen = (int) $curso->idNivel;
            $destino = self::destinoPedagogico($idNivelOrigen, self::cursoNumero($curso->c));
            if ($destino === null) {
                $informe['matriculas']['no_promovibles']++;

                continue;
            }

            $clave = self::claveDivision($destino['idNivel'], $destino['c'], $curso->s);
            $idCursoDest = $destPorClave[$clave] ?? null;
            if ($idCursoDest === null) {
                $informe['matriculas']['sin_curso']++;

                continue;
            }

            $idLegajo = (int) $mat->idLegajos;
            if ($idLegajo <= 0) {
                continue;
            }

            if (isset($legajosYaDestino[$idLegajo])) {
                $informe['matriculas']['existentes']++;

                continue;
            }

            $materias = $materiasPorCurso[$idCursoDest] ?? [];
            $informe['matriculas']['a_crear']++;
            $informe['calificaciones']['a_crear'] += count($materias);
            self::incPorNivel($informe, $idNivelOrigen, 'matriculas');
            self::incPorNivel($informe, $idNivelOrigen, 'calificaciones', count($materias));

            if ($dryRun) {
                $legajosYaDestino[$idLegajo] = true;

                continue;
            }

            $idNueva = self::insertarMatricula(
                $idTerlecDestino,
                $destino['idNivel'],
                $idCursoDest,
                $idLegajo,
                (int) ($mat->idCondiciones ?: self::ID_CONDICION_REGULAR),
            );
            $legajosYaDestino[$idLegajo] = true;

            self::insertarCalificaciones($idLegajo, $idNueva, $idTerlecDestino, $idCursoDest, $materias);
        }

        $informe['por_nivel'] = array_values($informe['por_nivel']);

        return $informe;
    }

    /**
     * @param  list<int>  $niveles
     * @return list<Curso>
     */
    private static function cursosPromovibles(int $idTerlecOrigen, array $niveles): array
    {
        $cursos = Curso::query()
            ->where('idTerlec', $idTerlecOrigen)
            ->whereIn('idNivel', $niveles)
            ->get();

        $filtrados = $cursos->filter(function (Curso $curso): bool {
            return ! self::esCursoTerminal((int) $curso->idNivel, self::cursoNumero($curso->c));
        });

        return Curso::ordenarColeccion($filtrados)->all();
    }

    /**
     * @return array<string, int>
     */
    private static function indiceCursosDestino(int $idTerlecDestino): array
    {
        $indice = [];
        foreach (
            Curso::query()->where('idTerlec', $idTerlecDestino)->get() as $curso
        ) {
            $indice[self::claveDivision((int) $curso->idNivel, $curso->c, $curso->s)] = self::idCurso($curso);
        }

        return $indice;
    }

    /**
     * @param  array<string, int>  $destPorClave
     * @return array<int, string>
     */
    private static function nombresCursosDestino(array $destPorClave): array
    {
        $ids = array_values(array_unique(array_values($destPorClave)));
        if ($ids === []) {
            return [];
        }

        $nombres = [];
        foreach (Curso::query()->whereKey($ids)->get() as $curso) {
            $nombres[self::idCurso($curso)] = self::nombreCurso($curso);
        }

        return $nombres;
    }

    /**
     * @param  list<int>  $idsCursos
     * @return array<int, int>
     */
    private static function regularesPorCurso(int $idTerlec, array $idsCursos): array
    {
        if ($idsCursos === []) {
            return [];
        }

        $conteos = [];
        foreach (
            DB::table('matricula')
                ->select('idCursos', DB::raw('COUNT(*) as n'))
                ->where('idTerlec', $idTerlec)
                ->where('idCondiciones', self::ID_CONDICION_REGULAR)
                ->whereIn('idCursos', $idsCursos)
                ->groupBy('idCursos')
                ->get() as $row
        ) {
            $conteos[(int) $row->idCursos] = (int) $row->n;
        }

        return $conteos;
    }

    /**
     * @param  list<int>  $idsCursos
     * @return array<int, list<int>>
     */
    private static function legajosRegularesPorCurso(int $idTerlec, array $idsCursos): array
    {
        if ($idsCursos === []) {
            return [];
        }

        $porCurso = [];
        foreach (
            DB::table('matricula')
                ->where('idTerlec', $idTerlec)
                ->where('idCondiciones', self::ID_CONDICION_REGULAR)
                ->whereIn('idCursos', $idsCursos)
                ->get(['idCursos', 'idLegajos']) as $row
        ) {
            $porCurso[(int) $row->idCursos][] = (int) $row->idLegajos;
        }

        return $porCurso;
    }

    /**
     * @param  array<int, list<int>>  $legajosPorCurso
     * @return list<int>
     */
    private static function aplanarLegajos(array $legajosPorCurso): array
    {
        $ids = [];
        foreach ($legajosPorCurso as $lista) {
            foreach ($lista as $id) {
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * @param  list<int>|null  $idLegajos
     * @return array<int, true>
     */
    private static function legajosConMatriculaEnAnio(int $idTerlec, ?array $idLegajos = null): array
    {
        $q = DB::table('matricula')->where('idTerlec', $idTerlec);
        if ($idLegajos !== null) {
            if ($idLegajos === []) {
                return [];
            }
            $q->whereIn('idLegajos', $idLegajos);
        }

        $set = [];
        foreach ($q->get(['idLegajos']) as $row) {
            $id = (int) $row->idLegajos;
            if ($id > 0) {
                $set[$id] = true;
            }
        }

        return $set;
    }

    /**
     * @param  list<int>  $idsCursosDestino
     * @return array<int, list<object{id: int, ord: int, idMatPlan: int}>>
     */
    private static function materiasPorCursoDestino(array $idsCursosDestino): array
    {
        if ($idsCursosDestino === []) {
            return [];
        }

        $porCurso = [];
        foreach (
            Materia::query()
                ->whereIn('idCursos', $idsCursosDestino)
                ->orderBy('idCursos')
                ->orderBy('ord')
                ->orderBy('id')
                ->get(['id', 'idCursos', 'ord', 'idMatPlan']) as $materia
        ) {
            $porCurso[(int) $materia->idCursos][] = $materia;
        }

        return $porCurso;
    }

    private static function insertarMatricula(
        int $idTerlecDestino,
        int $idNivel,
        int $idCursos,
        int $idLegajos,
        int $idCondiciones,
    ): int {
        $payload = [
            'idTerlec' => $idTerlecDestino,
            'idNivel' => $idNivel,
            'idCursos' => $idCursos,
            'idLegajos' => $idLegajos,
            'idCondiciones' => $idCondiciones,
            'idCuotasbecas' => self::ID_CUOTA_BECA_INICIAL,
        ];

        $preparado = PersistenciaColumnas::prepararPayload('matricula', $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            throw new RuntimeException(
                PersistenciaColumnas::mensajeColumnasInexistentes('matricula', $preparado['columnas_con_valor_sin_columna'])
            );
        }

        $fila = PersistenciaColumnas::completarNotNullSinDefault(
            'matricula',
            PersistenciaColumnas::reemplazarNulosExplicitos('matricula', $preparado['payload']),
            ['id'],
        );

        $id = (int) DB::table('matricula')->insertGetId($fila);

        return $id > 0 ? $id : (int) DB::getPdo()->lastInsertId();
    }

    /**
     * @param  list<object{id: int, ord: int, idMatPlan: int}>  $materias
     */
    private static function insertarCalificaciones(
        int $idLegajos,
        int $idMatricula,
        int $idTerlec,
        int $idCursos,
        array $materias,
    ): void {
        if ($materias === []) {
            return;
        }

        $filas = [];
        foreach ($materias as $materia) {
            $payload = [
                'idLegajos' => $idLegajos,
                'idMatricula' => $idMatricula,
                'ord' => (int) ($materia->ord ?? 0),
                'idTerlec' => $idTerlec,
                'idCursos' => $idCursos,
                'idMaterias' => (int) $materia->id,
                'idMatPlan' => (int) ($materia->idMatPlan ?? 0),
            ];

            $preparado = PersistenciaColumnas::prepararPayload('calificaciones', $payload);
            if ($preparado['columnas_con_valor_sin_columna'] !== []) {
                throw new RuntimeException(
                    PersistenciaColumnas::mensajeColumnasInexistentes('calificaciones', $preparado['columnas_con_valor_sin_columna'])
                );
            }

            $filas[] = PersistenciaColumnas::completarNotNullSinDefault(
                'calificaciones',
                PersistenciaColumnas::reemplazarNulosExplicitos('calificaciones', $preparado['payload']),
                ['id'],
            );
        }

        foreach (array_chunk($filas, 50) as $lote) {
            DB::table('calificaciones')->insert($lote);
        }
    }

    /**
     * @param  list<int>  $idNiveles
     * @return array{
     *     matriculas: array{origen: int, a_crear: int, existentes: int, sin_curso: int, no_promovibles: int},
     *     calificaciones: array{a_crear: int},
     *     por_nivel: array<int, array{id: int, nombre: string, matriculas: int, calificaciones: int}>
     * }
     */
    private static function informeVacio(array $idNiveles = []): array
    {
        $porNivel = [];
        if ($idNiveles !== []) {
            $nombres = self::nombresNivel($idNiveles);
            foreach ($idNiveles as $idNivel) {
                $porNivel[$idNivel] = [
                    'id' => $idNivel,
                    'nombre' => $nombres[$idNivel] ?? ('Nivel '.$idNivel),
                    'matriculas' => 0,
                    'calificaciones' => 0,
                ];
            }
        }

        return [
            'matriculas' => ['origen' => 0, 'a_crear' => 0, 'existentes' => 0, 'sin_curso' => 0, 'no_promovibles' => 0],
            'calificaciones' => ['a_crear' => 0],
            'por_nivel' => $porNivel,
        ];
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private static function incPorNivel(array &$informe, int $idNivel, string $campo, int $cantidad = 1): void
    {
        if ($idNivel <= 0 || $cantidad === 0 || ! isset($informe['por_nivel'][$idNivel][$campo])) {
            return;
        }

        $informe['por_nivel'][$idNivel][$campo] += $cantidad;
    }

    /**
     * @param  list<int>  $idNiveles
     * @return array<int, string>
     */
    private static function nombresNivel(array $idNiveles): array
    {
        $nombres = [];
        foreach (
            Nivel::query()->whereIn('id', $idNiveles)->get(['id', 'nivel']) as $nivel
        ) {
            $nombre = trim((string) ($nivel->nivel ?? ''));
            $nombres[(int) $nivel->id] = $nombre !== '' ? $nombre : 'Nivel '.(int) $nivel->id;
        }

        return $nombres;
    }

    private static function nombreCurso(Curso $curso): string
    {
        $nombre = trim((string) $curso->nombreParaListado());

        return $nombre !== '' ? $nombre : ('Curso '.self::idCurso($curso));
    }

    private static function idCurso(Curso $curso): int
    {
        return (int) ($curso->Id ?? $curso->id ?? $curso->getKey());
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    private static function idsPositivos(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $out[$n] = $n;
            }
        }

        return array_values($out);
    }
}
