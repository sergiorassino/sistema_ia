<?php

namespace App\Support\CalificacionesPrimario;

use App\Models\Matricula;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lectura y persistencia del formulario manual de calificaciones (primario).
 */
final class CalificacionesPrimarioDatos
{
    /**
     * @return array{
     *     matricula: Matricula,
     *     ciclo: int,
     *     materias: Collection<int, object>,
     *     notas: array<int, array{id: ?int, ic01: string, ic02: string, ic03: string}>,
     *     obs1: string,
     *     obs2: string,
     *     obsAnual: string,
     *     alumnoLinea: string,
     *     cursoLabel: string
     * }
     */
    public static function cargarFormulario(Matricula $matricula): array
    {
        $matricula->loadMissing(['legajo', 'curso.curplan']);
        $idMatricula = (int) $matricula->id;
        $idCurso = (int) $matricula->idCursos;
        $ciclo = CalificacionesPrimarioCatalogo::cicloDesdeCurso($matricula->curso);

        $materias = CalificacionesPrimarioCatalogo::materiasParaCurso(
            $idCurso,
            (int) $matricula->idNivel,
            (int) $matricula->idTerlec,
            $ciclo,
        );

        $ords = $materias->pluck('ord')->map(fn ($o) => (int) $o)->all();
        $porOrd = self::calificacionesPorOrd($idMatricula, $ords);

        $notas = [];
        foreach ($materias as $m) {
            $ord = (int) $m->ord;
            $fila = $porOrd[$ord] ?? null;
            $notas[$ord] = [
                'id' => $fila !== null ? (int) $fila->id : null,
                'ic01' => (string) ($fila->ic01 ?? ''),
                'ic02' => (string) ($fila->ic02 ?? ''),
                'ic03' => (string) ($fila->ic03 ?? ''),
            ];
        }

        $obs = DB::table('matricula')
            ->where('id', $idMatricula)
            ->first(['obs1', 'obs2', 'obsAnual']);

        $legajo = $matricula->legajo;
        $alumnoLinea = trim(((string) ($legajo?->apellido ?? '')).' '.((string) ($legajo?->nombre ?? '')));

        return [
            'matricula' => $matricula,
            'ciclo' => $ciclo,
            'materias' => $materias,
            'notas' => $notas,
            'obs1' => (string) ($obs->obs1 ?? ''),
            'obs2' => (string) ($obs->obs2 ?? ''),
            'obsAnual' => (string) ($obs->obsAnual ?? ''),
            'alumnoLinea' => $alumnoLinea,
            'cursoLabel' => $matricula->curso?->nombreParaListado() ?? '—',
        ];
    }

    /**
     * @param  list<int>  $ords
     * @return array<int, object{id: int, ic01: ?string, ic02: ?string, ic03: ?string}>
     */
    private static function calificacionesPorOrd(int $idMatricula, array $ords): array
    {
        if ($ords === []) {
            return [];
        }

        $filas = DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->whereIn('ord', $ords)
            ->get(['id', 'ord', 'ic01', 'ic02', 'ic03']);

        $out = [];
        foreach ($filas as $r) {
            $out[(int) $r->ord] = $r;
        }

        return $out;
    }

    public static function guardarNota(
        Matricula $matricula,
        int $ord,
        string $campo,
        string $valor,
        ?int $idMaterias = null,
    ): void {
        if (! in_array($campo, CalificacionesPrimarioCatalogo::camposNotaEditables(), true)) {
            abort(400);
        }

        $matricula->loadMissing('curso.curplan');
        $ciclo = CalificacionesPrimarioCatalogo::cicloDesdeCurso($matricula->curso);
        if (CalificacionesPrimarioCatalogo::celdaInhabilitada($ciclo, $ord, $campo)) {
            return;
        }

        $idMatricula = (int) $matricula->id;
        $existente = DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->where('ord', $ord)
            ->first(['id']);

        if ($existente) {
            DB::table('calificaciones')
                ->where('id', (int) $existente->id)
                ->update([$campo => $valor]);

            return;
        }

        DB::table('calificaciones')->insert([
            'idMatricula' => $idMatricula,
            'idLegajos' => (int) $matricula->idLegajos,
            'idTerlec' => (int) $matricula->idTerlec,
            'idCursos' => (int) $matricula->idCursos,
            'idMaterias' => $idMaterias,
            'ord' => $ord,
            'ic01' => $campo === 'ic01' ? $valor : '',
            'ic02' => $campo === 'ic02' ? $valor : '',
            'ic03' => $campo === 'ic03' ? $valor : '',
        ]);
    }

    public static function guardarObservacionMatricula(int $idMatricula, string $campo, string $valor): void
    {
        if (! in_array($campo, CalificacionesPrimarioCatalogo::camposObservacionMatricula(), true)) {
            abort(400);
        }

        DB::table('matricula')
            ->where('id', $idMatricula)
            ->update([$campo => $valor]);
    }

    public static function matriculaEnContexto(int $idMatricula): ?Matricula
    {
        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        return Matricula::query()
            ->with(['legajo', 'curso.curplan'])
            ->where('id', $idMatricula)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->first();
    }

    /**
     * Materia del curso por `ord` (revalidación en cada guardado; no depender de estado Livewire).
     *
     * @return object{id: int, ord: int}|null
     */
    public static function materiaDelCursoPorOrd(int $idCurso, int $idNivel, int $idTerlec, int $ord, int $ciclo): ?object
    {
        if ($ord < 1 || $ord > CalificacionesPrimarioCatalogo::maxOrdVisible($ciclo)) {
            return null;
        }

        $fila = DB::table('materias')
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->where('idCursos', $idCurso)
            ->where('ord', $ord)
            ->first(['id', 'ord']);

        if ($fila === null) {
            return null;
        }

        return (object) [
            'id' => (int) $fila->id,
            'ord' => (int) $fila->ord,
        ];
    }
}
