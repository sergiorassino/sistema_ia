<?php

namespace App\Support\Alumnos;

use Illuminate\Support\Facades\DB;

/**
 * Resuelve matrículas válidas para el PDF en lote de ficha de matrícula (secretaría).
 */
final class FichaMatriculaSecretariaLoteParams
{
    public const MAX_MATRICULAS = 50;

    /**
     * @param  list<int>  $idsSolicitados
     * @return list<int>
     */
    public static function resolverIdsMatriculas(array $idsSolicitados): array
    {
        $parsed = collect($idsSolicitados)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($parsed === [] || count($parsed) > self::MAX_MATRICULAS) {
            return [];
        }

        $ctx = schoolCtx();

        return DB::table('matricula')
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->join('cursos', 'cursos.Id', '=', 'matricula.idCursos')
            ->whereIn('matricula.id', $parsed)
            ->where('matricula.idTerlec', (int) $ctx->idTerlec)
            ->where('matricula.idNivel', (int) $ctx->idNivel)
            ->where('matricula.idCondiciones', 1)
            ->whereNull('matricula.fechaBaja')
            ->orderBy('cursos.orden')
            ->orderBy('cursos.cursec')
            ->orderBy('cursos.Id')
            ->orderBy('legajos.apellido')
            ->orderBy('legajos.nombre')
            ->orderBy('matricula.id')
            ->pluck('matricula.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public static function resolverIdsMatriculasDesdeQuery(string $matriculasParam): array
    {
        if (trim($matriculasParam) === '') {
            return [];
        }

        $parsed = collect(explode(',', $matriculasParam))
            ->map(fn ($v) => (int) trim((string) $v))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        return self::resolverIdsMatriculas($parsed);
    }
}
