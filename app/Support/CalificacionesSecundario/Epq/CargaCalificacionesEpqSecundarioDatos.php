<?php

namespace App\Support\CalificacionesSecundario\Epq;

use App\Models\Curso;
use App\Support\PlanillaCalificacionesSecundario;
use Illuminate\Support\Facades\DB;

/**
 * Datos para PDF de carga EPQ secundario (curso + materia).
 */
final class CargaCalificacionesEpqSecundarioDatos
{
    /**
     * @return array{
     *     pdfHeader: array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string},
     *     ano: int|null,
     *     cursoLabel: string,
     *     materiaLabel: string,
     *     profesoresLinea: string,
     *     filas: list<array<string, mixed>>
     * }
     */
    public static function build(int $cursoId, int $materiaId): array
    {
        $ctx = schoolCtx();

        $curso = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->where('Id', $cursoId)
            ->first(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);

        if (! $curso) {
            abort(404);
        }

        $materia = DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('idCursos', $cursoId)
            ->where('id', $materiaId)
            ->first(['id', 'materia']);

        if (! $materia) {
            abort(404);
        }

        $campos = CalificacionesEpqSecundarioCatalogo::CAMPOS_NOTA;
        $select = array_merge(['c.ord', 'l.apellido', 'l.nombre'], array_map(fn (string $c) => 'c.'.$c, $campos));

        $califs = DB::table('calificaciones as c')
            ->join('legajos as l', 'l.id', '=', 'c.idLegajos')
            ->where('c.idTerlec', (int) $ctx->idTerlec)
            ->where('c.idCursos', $cursoId)
            ->where('c.idMaterias', $materiaId)
            ->orderByRaw('COALESCE(c.ord, 9999) asc')
            ->orderBy('l.apellido')
            ->orderBy('l.nombre')
            ->get($select);

        $filas = [];
        foreach ($califs as $r) {
            $item = [
                'ord' => $r->ord,
                'alumno' => trim(((string) $r->apellido).', '.((string) $r->nombre)),
            ];
            foreach ($campos as $campo) {
                $item[$campo] = (string) ($r->{$campo} ?? '');
            }
            $filas[] = $item;
        }

        $ano = DB::table('terlec')->where('id', (int) $ctx->idTerlec)->value('ano');

        return [
            'pdfHeader' => schoolPdfHeaderData(),
            'ano' => $ano !== null ? (int) $ano : null,
            'cursoLabel' => (string) ($curso->cursec ?? $curso->nombreParaListado()),
            'materiaLabel' => trim((string) ($materia->materia ?? '')),
            'profesoresLinea' => PlanillaCalificacionesSecundario::profesoresLinea($materiaId),
            'filas' => $filas,
        ];
    }
}
