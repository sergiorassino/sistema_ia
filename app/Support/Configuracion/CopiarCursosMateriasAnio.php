<?php

namespace App\Support\Configuracion;

use App\Models\Curso;
use App\Models\Materia;
use App\Models\Nivel;
use App\Support\Database\PersistenciaColumnas;
use App\Support\NivelSistema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copia cursos, materias y horarios de un año lectivo a otro (misma lógica que ScriptCase).
 *
 * Idempotente: si el registro ya existe en el destino, lo omite.
 * Grilla Laravel: tabla `horarios26`. Horarios ScriptCase: tabla `horarios`, si existe.
 */
final class CopiarCursosMateriasAnio
{
    /**
     * @param  list<int>  $idNiveles
     * @return array{
     *     cursos: array{origen: int, existentes: int, a_crear: int},
     *     materias: array{origen: int, existentes: int, a_crear: int, sin_curso: int},
     *     horarios: array{origen: int, existentes: int, a_crear: int, sin_materia: int},
     *     horarios26: array{origen: int, existentes: int, a_crear: int, sin_materia: int},
     *     por_nivel: list<array{id: int, nombre: string, cursos: int, materias: int, horarios: int}>
     * }
     */
    public static function previsualizar(int $idTerlecOrigen, int $idTerlecDestino, array $idNiveles): array
    {
        return self::procesar($idTerlecOrigen, $idTerlecDestino, $idNiveles, dryRun: true);
    }

    /**
     * @param  list<int>  $idNiveles
     * @return array{
     *     cursos: array{origen: int, existentes: int, a_crear: int},
     *     materias: array{origen: int, existentes: int, a_crear: int, sin_curso: int},
     *     horarios: array{origen: int, existentes: int, a_crear: int, sin_materia: int},
     *     horarios26: array{origen: int, existentes: int, a_crear: int, sin_materia: int},
     *     por_nivel: list<array{id: int, nombre: string, cursos: int, materias: int, horarios: int}>
     * }
     */
    public static function ejecutar(int $idTerlecOrigen, int $idTerlecDestino, array $idNiveles): array
    {
        return DB::transaction(function () use ($idTerlecOrigen, $idTerlecDestino, $idNiveles) {
            return self::procesar($idTerlecOrigen, $idTerlecDestino, $idNiveles, dryRun: false);
        });
    }

    /**
     * Clave de unicidad de curso en un año: nivel + c + s (como el sistema anterior).
     */
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
     * @return array{
     *     cursos: array{origen: int, existentes: int, a_crear: int},
     *     materias: array{origen: int, existentes: int, a_crear: int, sin_curso: int},
     *     horarios: array{origen: int, existentes: int, a_crear: int, sin_materia: int},
     *     horarios26: array{origen: int, existentes: int, a_crear: int, sin_materia: int},
     *     por_nivel: list<array{id: int, nombre: string, cursos: int, materias: int, horarios: int}>
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

        $cursosOrigen = Curso::query()
            ->where('idTerlec', $idTerlecOrigen)
            ->whereIn('idNivel', $niveles)
            ->orderBy('idNivel')
            ->orderBy('orden')
            ->orderBy('Id')
            ->get();

        $informe['cursos']['origen'] = $cursosOrigen->count();

        $destPorClave = [];
        foreach (
            Curso::query()
                ->where('idTerlec', $idTerlecDestino)
                ->whereIn('idNivel', $niveles)
                ->get() as $cursoDest
        ) {
            $destPorClave[self::claveDivision((int) $cursoDest->idNivel, $cursoDest->c, $cursoDest->s)] = self::idCurso($cursoDest);
        }

        /** @var array<int, int> $mapaCursos origenId => destinoId */
        $mapaCursos = [];

        foreach ($cursosOrigen as $curso) {
            $clave = self::claveDivision((int) $curso->idNivel, $curso->c, $curso->s);
            if (isset($destPorClave[$clave])) {
                $mapaCursos[self::idCurso($curso)] = $destPorClave[$clave];
                $informe['cursos']['existentes']++;

                continue;
            }

            $informe['cursos']['a_crear']++;
            self::incPorNivel($informe, (int) $curso->idNivel, 'cursos');
            if ($dryRun) {
                $fakeId = -1 * self::idCurso($curso);
                $destPorClave[$clave] = $fakeId;
                $mapaCursos[self::idCurso($curso)] = $fakeId;

                continue;
            }

            $nuevoId = self::insertarCurso($curso, $idTerlecDestino);
            $destPorClave[$clave] = $nuevoId;
            $mapaCursos[self::idCurso($curso)] = $nuevoId;
        }

        $materiasOrigen = Materia::query()
            ->where('idTerlec', $idTerlecOrigen)
            ->whereIn('idNivel', $niveles)
            ->orderBy('idCursos')
            ->orderBy('ord')
            ->orderBy('id')
            ->get();

        $informe['materias']['origen'] = $materiasOrigen->count();

        $idsCursosDestino = array_values(array_unique(array_values($mapaCursos)));
        $matDestPorCursoOrd = [];
        if ($idsCursosDestino !== []) {
            foreach (
                Materia::query()
                    ->where('idTerlec', $idTerlecDestino)
                    ->whereIn('idCursos', $idsCursosDestino)
                    ->get(['id', 'idCursos', 'ord']) as $matDest
            ) {
                $matDestPorCursoOrd[self::claveMateria((int) $matDest->idCursos, (int) $matDest->ord)] = (int) $matDest->id;
            }
        }

        /** @var array<int, int> $mapaMaterias origenId => destinoId */
        $mapaMaterias = [];

        foreach ($materiasOrigen as $materia) {
            $idCursoDest = $mapaCursos[(int) $materia->idCursos] ?? null;
            if ($idCursoDest === null) {
                $informe['materias']['sin_curso']++;

                continue;
            }

            $claveMat = $idCursoDest > 0 ? self::claveMateria($idCursoDest, (int) $materia->ord) : '';
            if ($claveMat !== '' && isset($matDestPorCursoOrd[$claveMat])) {
                $mapaMaterias[(int) $materia->id] = $matDestPorCursoOrd[$claveMat];
                $informe['materias']['existentes']++;

                continue;
            }

            $informe['materias']['a_crear']++;
            self::incPorNivel($informe, (int) $materia->idNivel, 'materias');
            if ($dryRun) {
                $mapaMaterias[(int) $materia->id] = -1 * (int) $materia->id;

                continue;
            }

            $nuevoId = self::insertarMateria($materia, $idTerlecDestino, $idCursoDest);
            $matDestPorCursoOrd[$claveMat] = $nuevoId;
            $mapaMaterias[(int) $materia->id] = $nuevoId;
        }

        self::copiarHorariosLegacy($idTerlecOrigen, $niveles, $mapaMaterias, $dryRun, $informe);
        self::copiarHorarios26($idTerlecOrigen, $niveles, $mapaCursos, $mapaMaterias, $dryRun, $informe);

        $informe['por_nivel'] = array_values($informe['por_nivel']);

        return $informe;
    }

    /**
     * @param  list<int>  $idNiveles
     * @return array{
     *     cursos: array{origen: int, existentes: int, a_crear: int},
     *     materias: array{origen: int, existentes: int, a_crear: int, sin_curso: int},
     *     horarios: array{origen: int, existentes: int, a_crear: int, sin_materia: int},
     *     horarios26: array{origen: int, existentes: int, a_crear: int, sin_materia: int},
     *     por_nivel: array<int, array{id: int, nombre: string, cursos: int, materias: int, horarios: int}>
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
                    'cursos' => 0,
                    'materias' => 0,
                    'horarios' => 0,
                ];
            }
        }

        return [
            'cursos' => ['origen' => 0, 'existentes' => 0, 'a_crear' => 0],
            'materias' => ['origen' => 0, 'existentes' => 0, 'a_crear' => 0, 'sin_curso' => 0],
            'horarios' => ['origen' => 0, 'existentes' => 0, 'a_crear' => 0, 'sin_materia' => 0],
            'horarios26' => ['origen' => 0, 'existentes' => 0, 'a_crear' => 0, 'sin_materia' => 0],
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
     * @param  array<string, mixed>  $informe
     */
    private static function decPorNivel(array &$informe, int $idNivel, string $campo): void
    {
        if ($idNivel <= 0 || ! isset($informe['por_nivel'][$idNivel][$campo])) {
            return;
        }

        $informe['por_nivel'][$idNivel][$campo] = max(0, (int) $informe['por_nivel'][$idNivel][$campo] - 1);
    }

    private static function idCurso(Curso $curso): int
    {
        return (int) ($curso->Id ?? $curso->id ?? $curso->getKey());
    }

    private static function claveMateria(int $idCursos, int $ord): string
    {
        return $idCursos.'|'.$ord;
    }

    private static function insertarCurso(Curso $origen, int $idTerlecDestino): int
    {
        $payload = [
            'orden' => $origen->orden,
            'idCurPlan' => $origen->idCurPlan,
            'idTerlec' => $idTerlecDestino,
            'idNivel' => $origen->idNivel,
            'cursec' => $origen->cursec,
            'c' => $origen->c,
            's' => $origen->s,
            'idTurnoClase' => $origen->idTurnoClase,
        ];

        $preparado = PersistenciaColumnas::prepararPayload('cursos', $payload);
        $fila = PersistenciaColumnas::completarNotNullSinDefault(
            'cursos',
            PersistenciaColumnas::reemplazarNulosExplicitos('cursos', $preparado['payload']),
            ['id', 'Id'],
        );

        $cursoId = (int) DB::table('cursos')->insertGetId($fila);

        return $cursoId > 0 ? $cursoId : (int) DB::getPdo()->lastInsertId();
    }

    private static function insertarMateria(Materia $origen, int $idTerlecDestino, int $idCursoDestino): int
    {
        $payload = [
            'ord' => $origen->ord,
            'idCurPlan' => $origen->idCurPlan,
            'idMatPlan' => $origen->idMatPlan,
            'idNivel' => $origen->idNivel,
            'idCursos' => $idCursoDestino,
            'idTerlec' => $idTerlecDestino,
            'materia' => $origen->materia,
            'abrev' => $origen->abrev,
            'cierre1e' => 0,
            'cierre2e' => 0,
            'esInstitucional' => $origen->esInstitucional,
            'infoCalif' => $origen->infoCalif,
            'escala' => $origen->escala,
        ];

        $preparado = PersistenciaColumnas::prepararPayload('materias', $payload);
        $fila = PersistenciaColumnas::completarNotNullSinDefault(
            'materias',
            PersistenciaColumnas::reemplazarNulosExplicitos('materias', $preparado['payload']),
            ['id'],
        );

        return (int) DB::table('materias')->insertGetId($fila);
    }

    /**
     * @param  list<int>  $niveles
     * @param  array<int, int>  $mapaMaterias
     * @param  array<string, mixed>  $informe
     */
    private static function copiarHorariosLegacy(
        int $idTerlecOrigen,
        array $niveles,
        array $mapaMaterias,
        bool $dryRun,
        array &$informe,
    ): void {
        if (! Schema::hasTable('horarios')) {
            return;
        }

        $filas = DB::table('horarios as h')
            ->join('materias as m', 'h.idMaterias', '=', 'm.id')
            ->where('m.idTerlec', $idTerlecOrigen)
            ->whereIn('m.idNivel', $niveles)
            ->select(['h.idMaterias', 'h.idDia', 'h.idHora'])
            ->get();

        $informe['horarios']['origen'] = $filas->count();
        if ($filas->isEmpty()) {
            return;
        }

        $idsDestino = array_values(array_unique(array_values($mapaMaterias)));
        $existentes = [];
        if ($idsDestino !== []) {
            foreach (
                DB::table('horarios')
                    ->whereIn('idMaterias', $idsDestino)
                    ->get(['idMaterias', 'idDia', 'idHora']) as $row
            ) {
                $existentes[self::claveHorario((int) $row->idMaterias, $row->idDia, (int) $row->idHora)] = true;
            }
        }

        foreach ($filas as $row) {
            $idNueva = $mapaMaterias[(int) $row->idMaterias] ?? null;
            if ($idNueva === null) {
                $informe['horarios']['sin_materia']++;

                continue;
            }

            if ($idNueva < 0) {
                $informe['horarios']['a_crear']++;

                continue;
            }

            $clave = self::claveHorario($idNueva, $row->idDia, (int) $row->idHora);
            if (isset($existentes[$clave])) {
                $informe['horarios']['existentes']++;

                continue;
            }

            $informe['horarios']['a_crear']++;
            if ($dryRun) {
                continue;
            }

            self::insertarHorarioLegacy($idNueva, $row->idDia, (int) $row->idHora);
            $existentes[$clave] = true;
        }
    }

    /**
     * @param  list<int>  $niveles
     * @param  array<int, int>  $mapaCursos
     * @param  array<int, int>  $mapaMaterias
     * @param  array<string, mixed>  $informe
     */
    private static function copiarHorarios26(
        int $idTerlecOrigen,
        array $niveles,
        array $mapaCursos,
        array $mapaMaterias,
        bool $dryRun,
        array &$informe,
    ): void {
        if (! Schema::hasTable('horarios26')) {
            return;
        }

        $usaTurno = Schema::hasColumn('horarios26', 'idTurnoClase');
        $usaCursos = Schema::hasColumn('horarios26', 'idCursos');

        $select = ['h.idProfesores', 'h.idMaterias', 'h.idDia', 'h.idHora', 'm.idNivel'];
        if ($usaTurno) {
            $select[] = 'h.idTurnoClase';
        }
        if ($usaCursos) {
            $select[] = 'h.idCursos';
        }

        $filas = DB::table('horarios26 as h')
            ->join('materias as m', 'h.idMaterias', '=', 'm.id')
            ->where('m.idTerlec', $idTerlecOrigen)
            ->whereIn('m.idNivel', $niveles)
            ->select($select)
            ->get();

        $informe['horarios26']['origen'] = $filas->count();
        if ($filas->isEmpty()) {
            return;
        }

        $idsDestino = array_values(array_unique(array_values($mapaMaterias)));
        $existentes = [];
        if ($idsDestino !== []) {
            $q = DB::table('horarios26')->whereIn('idMaterias', $idsDestino);
            $cols = ['idProfesores', 'idMaterias', 'idDia', 'idHora'];
            foreach ($q->get($cols) as $row) {
                $existentes[self::claveHorario26((int) $row->idProfesores, (int) $row->idMaterias, $row->idDia, (int) $row->idHora)] = true;
            }
        }

        foreach ($filas as $row) {
            $idNuevaMateria = $mapaMaterias[(int) $row->idMaterias] ?? null;
            if ($idNuevaMateria === null) {
                $informe['horarios26']['sin_materia']++;

                continue;
            }

            if ($idNuevaMateria < 0) {
                $informe['horarios26']['a_crear']++;
                self::incPorNivel($informe, (int) ($row->idNivel ?? 0), 'horarios');

                continue;
            }

            $idProfesor = (int) ($row->idProfesores ?? 0);
            $clave = self::claveHorario26($idProfesor, $idNuevaMateria, $row->idDia, (int) $row->idHora);
            if (isset($existentes[$clave])) {
                $informe['horarios26']['existentes']++;

                continue;
            }

            $informe['horarios26']['a_crear']++;
            self::incPorNivel($informe, (int) ($row->idNivel ?? 0), 'horarios');
            if ($dryRun) {
                continue;
            }

            $idCursoDest = null;
            if ($usaCursos) {
                $idCursoOrigen = (int) ($row->idCursos ?? 0);
                $idCursoDest = $mapaCursos[$idCursoOrigen] ?? null;
                if ($idCursoDest === null && $idCursoOrigen > 0) {
                    $informe['horarios26']['sin_materia']++;
                    $informe['horarios26']['a_crear']--;
                    self::decPorNivel($informe, (int) ($row->idNivel ?? 0), 'horarios');

                    continue;
                }
            }

            self::insertarHorario26(
                $idProfesor,
                $idNuevaMateria,
                $row->idDia,
                (int) $row->idHora,
                $usaCursos ? (int) ($idCursoDest ?? 0) : null,
                $usaTurno ? ($row->idTurnoClase ?? null) : null,
            );
            $existentes[$clave] = true;
        }
    }

    private static function claveHorario(int $idMaterias, mixed $idDia, int $idHora): string
    {
        return $idMaterias.'|'.trim((string) $idDia).'|'.$idHora;
    }

    private static function claveHorario26(int $idProfesores, int $idMaterias, mixed $idDia, int $idHora): string
    {
        return $idProfesores.'|'.self::claveHorario($idMaterias, $idDia, $idHora);
    }

    private static function insertarHorarioLegacy(int $idMaterias, mixed $idDia, int $idHora): void
    {
        $payload = [
            'idMaterias' => $idMaterias,
            'idDia' => $idDia,
            'idHora' => $idHora,
        ];
        $preparado = PersistenciaColumnas::prepararPayload('horarios', $payload);
        $fila = PersistenciaColumnas::completarNotNullSinDefault(
            'horarios',
            PersistenciaColumnas::reemplazarNulosExplicitos('horarios', $preparado['payload']),
            ['id'],
        );

        DB::table('horarios')->insert($fila);
    }

    private static function insertarHorario26(
        int $idProfesores,
        int $idMaterias,
        mixed $idDia,
        int $idHora,
        ?int $idCursos,
        mixed $idTurnoClase,
    ): void {
        $payload = [
            'idProfesores' => $idProfesores,
            'idMaterias' => $idMaterias,
            'idDia' => $idDia,
            'idHora' => $idHora,
        ];
        if ($idCursos !== null && Schema::hasColumn('horarios26', 'idCursos')) {
            $payload['idCursos'] = $idCursos;
        }
        if (Schema::hasColumn('horarios26', 'idTurnoClase')) {
            $payload['idTurnoClase'] = $idTurnoClase;
        }

        $preparado = PersistenciaColumnas::prepararPayload('horarios26', $payload);
        $fila = PersistenciaColumnas::completarNotNullSinDefault(
            'horarios26',
            PersistenciaColumnas::reemplazarNulosExplicitos('horarios26', $preparado['payload']),
            ['id'],
        );

        DB::table('horarios26')->insert($fila);
    }
};
