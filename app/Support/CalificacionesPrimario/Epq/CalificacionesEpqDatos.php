<?php

namespace App\Support\CalificacionesPrimario\Epq;

use App\Models\Matricula;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioCatalogo;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioDatos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lectura y persistencia — implementación EPQ (primario).
 */
final class CalificacionesEpqDatos
{
    /**
     * @return array{
     *     materias: Collection<int, object>,
     *     notas: array<int, array{id: ?int, ic01: string, ic02: string, ic03: string, ic04: string, ic05: string, ic06: string, ic07: string}>,
     *     alumnoLinea: string,
     *     cursoLabel: string
     * }
     */
    public static function cargarCalificaciones(Matricula $matricula): array
    {
        $matricula->loadMissing(['legajo', 'curso']);
        $idMatricula = (int) $matricula->id;

        $materias = CalificacionesPrimarioCatalogo::materiasParaSelectorAnio(
            (int) $matricula->idCursos,
            (int) $matricula->idNivel,
            (int) $matricula->idTerlec,
        )->sortBy('ord')->values();

        $filas = DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->get(['id', 'idMaterias', 'ord', ...CalificacionesEpqCatalogo::CAMPOS_NOTA]);

        $porMateria = [];
        $porOrdLegacy = [];
        foreach ($filas as $r) {
            $idMat = (int) ($r->idMaterias ?? 0);
            if ($idMat > 0) {
                $porMateria[$idMat] = $r;

                continue;
            }
            $porOrdLegacy[(int) $r->ord] = $r;
        }

        $notas = [];
        foreach ($materias as $m) {
            $idMaterias = (int) $m->id;
            $fila = $porMateria[$idMaterias] ?? $porOrdLegacy[(int) $m->ord] ?? null;
            $item = ['id' => $fila !== null ? (int) $fila->id : null];
            foreach (CalificacionesEpqCatalogo::CAMPOS_NOTA as $campo) {
                $item[$campo] = (string) ($fila?->$campo ?? '');
            }
            $notas[$idMaterias] = $item;
        }

        $legajo = $matricula->legajo;
        $alumnoLinea = trim(((string) ($legajo?->apellido ?? '')).' '.((string) ($legajo?->nombre ?? '')));

        return [
            'materias' => $materias,
            'notas' => $notas,
            'alumnoLinea' => $alumnoLinea,
            'cursoLabel' => $matricula->curso?->nombreParaListado() ?? '—',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function cargarInfoAdicional(int $idMatricula): array
    {
        $campos = CalificacionesEpqCatalogo::camposInfoAdicional();
        $fila = DB::table('matricula')->where('id', $idMatricula)->first($campos);

        $out = [];
        foreach ($campos as $campo) {
            $out[$campo] = (string) ($fila?->$campo ?? '');
        }

        return $out;
    }

    public static function guardarNota(
        Matricula $matricula,
        int $idMaterias,
        string $campo,
        string $valor,
    ): void {
        if (! in_array($campo, CalificacionesEpqCatalogo::CAMPOS_NOTA, true)) {
            abort(400);
        }

        $materia = CalificacionesPrimarioDatos::materiaDelCursoPorId(
            (int) $matricula->idCursos,
            (int) $matricula->idNivel,
            (int) $matricula->idTerlec,
            $idMaterias,
        );
        if ($materia === null) {
            abort(404, 'Materia no encontrada para este curso.');
        }

        $idMatricula = (int) $matricula->id;
        $existente = self::buscarCalificacion($idMatricula, $idMaterias, (int) $materia->ord);

        if ($existente === null) {
            abort(422, 'No existe el registro de calificación para este alumno y materia.');
        }

        $payload = [$campo => $valor];
        if ((int) ($existente->idMaterias ?? 0) !== $idMaterias) {
            $payload['idMaterias'] = $idMaterias;
        }
        DB::table('calificaciones')->where('id', (int) $existente->id)->update($payload);
    }

    public static function guardarInfoAdicional(int $idMatricula, string $campo, string $valor): void
    {
        if (! in_array($campo, CalificacionesEpqCatalogo::camposInfoAdicional(), true)) {
            abort(400);
        }

        DB::table('matricula')->where('id', $idMatricula)->update([$campo => $valor]);
    }

    /**
     * @return object{id: int, idMaterias: ?int, ord: ?int}|null
     */
    private static function buscarCalificacion(int $idMatricula, int $idMaterias, int $ordMateria): ?object
    {
        $columnas = ['id', 'idMaterias', 'ord'];

        if ($idMaterias > 0) {
            $fila = DB::table('calificaciones')
                ->where('idMatricula', $idMatricula)
                ->where('idMaterias', $idMaterias)
                ->first($columnas);

            if ($fila !== null) {
                return $fila;
            }
        }

        if ($ordMateria < 1) {
            return null;
        }

        return DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->where('ord', $ordMateria)
            ->where(function ($q): void {
                $q->whereNull('idMaterias')
                    ->orWhere('idMaterias', 0);
            })
            ->first($columnas);
    }
}
