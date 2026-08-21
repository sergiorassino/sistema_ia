<?php

namespace App\Support\Examenes;

use App\Models\Curso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Datos para el PDF «Adeudadas por curso» (legacy ScriptCase / FPDF).
 *
 * Alumnos del curso seleccionado (matrícula activa) y, por cada uno, todas las
 * materias con apro = 1 (incluye cursadas en años anteriores / otros cursos).
 */
final class MateriasAdeudadasPorCurso
{
    /**
     * Cursos del ciclo y nivel del contexto, para el selector del modal.
     *
     * @return Collection<int, Curso>
     */
    public static function cursosDelContexto(int $idNivel, int $idTerlec): Collection
    {
        if ($idNivel < 1 || $idTerlec < 1) {
            return collect();
        }

        return Curso::query()
            ->with(['curplan', 'turnoClase'])
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);
    }

    /**
     * @param  list<int|string>  $idsSolicitados
     * @return list<int> IDs válidos del contexto, en orden de la grilla de cursos
     */
    public static function filtrarIdsPermitidos(array $idsSolicitados, int $idNivel, int $idTerlec): array
    {
        $marcados = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $idsSolicitados),
            static fn (int $id) => $id > 0,
        )));

        if ($marcados === []) {
            return [];
        }

        $permitidos = self::cursosDelContexto($idNivel, $idTerlec)
            ->pluck('Id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $set = array_flip($marcados);

        return array_values(array_filter(
            $permitidos,
            static fn (int $id) => isset($set[$id]),
        ));
    }

    /**
     * @param  list<int>  $idsCursos
     * @return list<array{
     *     cursoLabel: string,
     *     estudiantes: list<array{
     *         apellido: string,
     *         nombre: string,
     *         adeudas: list<array{
     *             materia: string,
     *             curso: string,
     *             ano: int|string,
     *             condicion: string
     *         }>
     *     }>
     * }>
     */
    public static function datosPdfLote(array $idsCursos, int $idNivel, int $idTerlec): array
    {
        $hojas = [];
        foreach ($idsCursos as $idCurso) {
            $datos = self::datosPdf((int) $idCurso, $idNivel, $idTerlec);
            if ($datos !== null) {
                $hojas[] = $datos;
            }
        }

        return $hojas;
    }

    /**
     * @return array{
     *     cursoLabel: string,
     *     estudiantes: list<array{
     *         apellido: string,
     *         nombre: string,
     *         adeudas: list<array{
     *             materia: string,
     *             curso: string,
     *             ano: int|string,
     *             condicion: string
     *         }>
     *     }>
     * }|null
     */
    public static function datosPdf(int $idCurso, int $idNivel, int $idTerlec): ?array
    {
        if ($idCurso < 1 || $idNivel < 1 || $idTerlec < 1) {
            return null;
        }

        $curso = Curso::query()
            ->with(['curplan', 'turnoClase'])
            ->whereKey($idCurso)
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->first(['Id', 'cursec', 'idCurPlan', 'idTurnoClase', 'c', 's']);

        if ($curso === null) {
            return null;
        }

        $alumnos = DB::table('matricula as mat')
            ->join('legajos as l', 'l.id', '=', 'mat.idLegajos')
            ->where('mat.idCursos', $idCurso)
            ->where('mat.idCondiciones', '<', 5)
            ->orderBy('l.apellido')
            ->orderBy('l.nombre')
            ->get([
                'mat.idLegajos',
                'l.apellido',
                'l.nombre',
            ]);

        if ($alumnos->isEmpty()) {
            return [
                'cursoLabel' => $curso->nombreParaListado(),
                'estudiantes' => [],
            ];
        }

        $idsLegajos = $alumnos->pluck('idLegajos')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $adeudasPorLegajo = DB::table('calificaciones as c')
            ->join('materias as m', function ($join) {
                $join->on('m.id', '=', 'c.idMaterias')
                    ->on('m.idTerlec', '=', 'c.idTerlec');
            })
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->join('terlec as t', 't.id', '=', 'c.idTerlec')
            ->whereIn('c.idLegajos', $idsLegajos)
            ->where('c.apro', 1)
            ->where('cu.idNivel', $idNivel)
            ->orderBy('c.idLegajos')
            ->orderByDesc('t.ano')
            ->orderBy('m.materia')
            ->get([
                'c.idLegajos',
                'm.materia',
                'cu.cursec',
                't.ano',
                'c.condAdeuda',
            ])
            ->groupBy(fn ($r) => (int) $r->idLegajos);

        $estudiantes = [];
        foreach ($alumnos as $a) {
            $idLegajo = (int) $a->idLegajos;
            /** @var Collection<int, object> $grupo */
            $grupo = $adeudasPorLegajo->get($idLegajo, collect());
            if ($grupo->isEmpty()) {
                continue;
            }

            $adeudas = [];
            foreach ($grupo as $r) {
                $adeudas[] = [
                    'materia' => trim((string) ($r->materia ?? '')),
                    'curso' => trim((string) ($r->cursec ?? '')),
                    'ano' => $r->ano ?? '',
                    'condicion' => trim((string) ($r->condAdeuda ?? '')),
                ];
            }

            $estudiantes[] = [
                'apellido' => trim((string) ($a->apellido ?? '')),
                'nombre' => trim((string) ($a->nombre ?? '')),
                'adeudas' => $adeudas,
            ];
        }

        return [
            'cursoLabel' => $curso->nombreParaListado(),
            'estudiantes' => $estudiantes,
        ];
    }
}
