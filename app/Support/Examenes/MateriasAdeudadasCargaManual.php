<?php



namespace App\Support\Examenes;



use Illuminate\Support\Facades\DB;



final class MateriasAdeudadasCargaManual

{

    /**

     * Datos del alumno si tiene matrícula activa en el nivel y ciclo del contexto.

     *

     * @return array{

     *     idLegajos: int,

     *     idMatricula: int,

     *     apellido: string,

     *     nombre: string,

     *     dni: string,

     *     curso: string

     * }|null

     */

    public static function alumnoEnGestion(int $idLegajos, int $idNivel, int $idTerlec): ?array

    {

        if ($idLegajos < 1 || $idNivel < 1 || $idTerlec < 1) {

            return null;

        }



        $r = DB::table('matricula as m')

            ->join('legajos as l', 'l.id', '=', 'm.idLegajos')

            ->leftJoin('cursos as cu', 'cu.Id', '=', 'm.idCursos')

            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')

            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')

            ->where('m.idLegajos', $idLegajos)

            ->where('m.idNivel', $idNivel)

            ->where('m.idTerlec', $idTerlec)

            ->whereNull('m.fechaBaja')

            ->select([

                'm.id as idMatricula',

                'l.id as idLegajos',

                'l.apellido',

                'l.nombre',

                'l.dni',

                'cu.cursec',

                'cp.curPlanCurso',

                'tc.nombre as turnoClaseNombre',

                'cu.c',

                'cu.s',

            ])

            ->first();



        if ($r === null) {

            return null;

        }



        return [

            'idLegajos' => (int) $r->idLegajos,

            'idMatricula' => (int) $r->idMatricula,

            'apellido' => trim((string) ($r->apellido ?? '')),

            'nombre' => trim((string) ($r->nombre ?? '')),

            'dni' => trim((string) ($r->dni ?? '')),

            'curso' => self::cursoLabelDesdeFila($r),

        ];

    }



    /**

     * Materias con adeudo registrado (`apro = 1`) del alumno en el nivel.

     *

     * @return list<array{

     *     id: int,

     *     materia: string,

     *     curso: string,

     *     ano_lectivo: int|string,

     *     condicion: string,

     *     inscripto: string

     * }>

     */

    public static function materiasAdeudadas(int $idLegajos, int $idNivel): array

    {

        return array_values(array_filter(

            self::materiasDelAlumno($idLegajos, $idNivel),

            fn (array $f) => $f['esAdeudada'],

        ));

    }



    /**

     * Todas las filas de calificaciones del alumno en el nivel (cualquier ciclo lectivo).

     *

     * @return list<array{

     *     id: int,

     *     materia: string,

     *     curso: string,

     *     ano_lectivo: int|string,

     *     condicion: string,

     *     inscripto: string,

     *     esAdeudada: bool

     * }>

     */

    public static function materiasDelAlumno(int $idLegajos, int $idNivel): array

    {

        if ($idLegajos < 1 || $idNivel < 1) {

            return [];

        }



        $raw = DB::table('calificaciones as c')

            ->join('materias as m', function ($join) {

                $join->on('m.id', '=', 'c.idMaterias')

                    ->on('m.idTerlec', '=', 'c.idTerlec');

            })

            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')

            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')

            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')

            ->join('terlec as t', 't.id', '=', 'c.idTerlec')

            ->where('c.idLegajos', $idLegajos)

            ->where('cu.idNivel', $idNivel)

            ->orderByDesc('t.ano')

            ->orderBy('m.materia')

            ->select([

                'c.id',

                'c.apro',

                'c.condAdeuda',

                'c.inscri',

                'm.materia',

                't.ano as ano_lectivo',

                'cu.cursec',

                'cp.curPlanCurso',

                'tc.nombre as turnoClaseNombre',

                'cu.c',

                'cu.s',

            ])

            ->get();



        $out = [];

        foreach ($raw as $r) {

            $materia = trim((string) ($r->materia ?? ''));

            if ($materia === '') {

                continue;

            }



            $out[] = [

                'id' => (int) $r->id,

                'materia' => $materia,

                'curso' => self::cursoLabelDesdeFila($r),

                'ano_lectivo' => $r->ano_lectivo ?? '',

                'condicion' => trim((string) ($r->condAdeuda ?? '')),

                'inscripto' => MateriasAdeudadasFiltros::etiquetaInscri((int) ($r->inscri ?? 0)),

                'esAdeudada' => (int) ($r->apro ?? 0) === 1,

            ];

        }



        return $out;

    }



    /**

     * Marca una calificación como adeudada (`apro = 1`). Revalida legajo y nivel.

     *

     * @return 'ok'|'ya_adeudada'|'no_encontrada'

     */

    public static function registrarAdeudada(int $idCalificacion, int $idLegajos, int $idNivel): string

    {

        if ($idCalificacion < 1 || $idLegajos < 1 || $idNivel < 1) {

            return 'no_encontrada';

        }



        $row = DB::table('calificaciones as c')

            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')

            ->where('c.id', $idCalificacion)

            ->where('c.idLegajos', $idLegajos)

            ->where('cu.idNivel', $idNivel)

            ->select(['c.id', 'c.apro'])

            ->first();



        if ($row === null) {

            return 'no_encontrada';

        }



        if ((int) ($row->apro ?? 0) === 1) {

            return 'ya_adeudada';

        }



        DB::table('calificaciones')

            ->where('id', $idCalificacion)

            ->update(['apro' => 1]);



        return 'ok';

    }



    /**

     * Quita el adeudo (`apro = 0`). Revalida legajo y nivel.

     *

     * @return 'ok'|'no_adeudada'|'no_encontrada'

     */

    public static function quitarAdeudada(int $idCalificacion, int $idLegajos, int $idNivel): string

    {

        if ($idCalificacion < 1 || $idLegajos < 1 || $idNivel < 1) {

            return 'no_encontrada';

        }



        $row = DB::table('calificaciones as c')

            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')

            ->where('c.id', $idCalificacion)

            ->where('c.idLegajos', $idLegajos)

            ->where('cu.idNivel', $idNivel)

            ->select(['c.id', 'c.apro'])

            ->first();



        if ($row === null) {

            return 'no_encontrada';

        }



        if ((int) ($row->apro ?? 0) !== 1) {

            return 'no_adeudada';

        }



        DB::table('calificaciones')

            ->where('id', $idCalificacion)

            ->update(['apro' => 0]);



        return 'ok';

    }



    public static function cursoLabelDesdeFila(object $r): string

    {

        $sec = trim((string) ($r->cursec ?? ''));

        if ($sec !== '') {

            return $sec;

        }



        $nombrePlan = trim((string) ($r->curPlanCurso ?? ''));

        $extras = collect([$r->turnoClaseNombre ?? '', $r->c ?? '', $r->s ?? ''])

            ->map(fn ($v) => trim((string) $v))

            ->filter()

            ->values();



        if ($nombrePlan !== '') {

            return $extras->isNotEmpty()

                ? $nombrePlan.' · '.$extras->implode(' · ')

                : $nombrePlan;

        }



        if ($extras->isNotEmpty()) {

            return $extras->implode(' · ');

        }



        return '';

    }

}

