<?php

namespace App\Support\Listados;

use App\Models\Curso;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Support\Collection;

/**
 * Cursos y matrícula permitidos para listados por curso (PDF/Excel).
 * En Administración incluye Inicial, Primario y Secundario del ciclo activo.
 */
final class ListadoCursoConsulta
{
    /** @return Collection<int, Curso> */
    public static function cursosPermitidosEnContexto(): Collection
    {
        $idTerlec = (int) schoolCtx()->idTerlec;

        $query = Curso::query()
            ->with(['curplan', 'turnoClase', 'nivel:id,nivel'])
            ->join('niveles', 'niveles.id', '=', 'cursos.idNivel')
            ->where('cursos.idTerlec', $idTerlec);

        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'cursos.idNivel');

        return $query
            ->orderBy('niveles.nivel')
            ->orderByRaw('COALESCE(cursos.orden, 9999) asc')
            ->orderBy('cursos.cursec')
            ->orderBy('cursos.Id')
            ->get([
                'cursos.Id',
                'cursos.cursec',
                'cursos.orden',
                'cursos.c',
                'cursos.s',
                'cursos.idTurnoClase',
                'cursos.idCurPlan',
                'cursos.idNivel',
                'cursos.idTerlec',
            ]);
    }

    public static function etiquetaCursoConNivel(Curso $curso): string
    {
        $nivel = trim((string) ($curso->nivel?->nivel ?? ''));
        $nombre = $curso->nombreParaListado();

        if ($nivel === '') {
            return $nombre;
        }

        return $nombre.' ('.$nivel.')';
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public static function aplicarFiltroMatriculaNivel($query, string $column = 'matricula.idNivel'): void
    {
        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, $column);
    }
}
