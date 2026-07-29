<?php

namespace App\Support;

use App\Models\Curso;
use App\Models\Matricula;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use Illuminate\Support\Collection;

/**
 * Resuelve matrículas válidas para el PDF en lote de informes de inasistencias.
 */
final class InformeInasistenciasLoteParams
{
    public const MAX_MATRICULAS = 50;

    /**
     * @param  list<int>  $idsSolicitados
     * @return list<int> IDs de matrícula en el curso/contexto, orden apellido y nombre.
     */
    public static function resolverIdsMatriculasDesdeLista(array $idsSolicitados, int $cursoId): array
    {
        if ($cursoId <= 0) {
            return [];
        }

        $parsed = collect($idsSolicitados)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($parsed->isEmpty() || $parsed->count() > self::MAX_MATRICULAS) {
            return [];
        }

        $ctx = schoolCtx();

        $cursoOk = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->where('Id', $cursoId)
            ->exists();

        if (! $cursoOk) {
            return [];
        }

        $idsCondicionesRegulares = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES
        );

        /** @var Collection<int, Matricula> $matriculas */
        $matriculas = Matricula::query()
            ->with('legajo')
            ->where('idCursos', $cursoId)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->whereIn('idCondiciones', $idsCondicionesRegulares)
            ->whereIn('id', $parsed->all())
            ->get();

        if ($matriculas->isEmpty()) {
            return [];
        }

        $allowed = $matriculas->keyBy(fn (Matricula $m) => (int) $m->id);

        $ordenados = [];
        foreach ($parsed as $id) {
            if ($allowed->has($id) && ! in_array($id, $ordenados, true)) {
                $ordenados[] = $id;
            }
        }

        usort($ordenados, function (int $a, int $b) use ($allowed): int {
            $ma = $allowed->get($a);
            $mb = $allowed->get($b);
            $apA = mb_strtolower((string) ($ma?->legajo?->apellido ?? ''));
            $apB = mb_strtolower((string) ($mb?->legajo?->apellido ?? ''));
            if ($apA !== $apB) {
                return $apA <=> $apB;
            }
            $noA = mb_strtolower((string) ($ma?->legajo?->nombre ?? ''));
            $noB = mb_strtolower((string) ($mb?->legajo?->nombre ?? ''));

            return $noA <=> $noB;
        });

        return $ordenados;
    }

    /**
     * @return list<int>
     */
    public static function resolverIdsMatriculas(string $matriculasParam, int $cursoId): array
    {
        if ($cursoId <= 0 || trim($matriculasParam) === '') {
            return [];
        }

        $parsed = collect(explode(',', $matriculasParam))
            ->map(fn ($v) => (int) trim((string) $v))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        return self::resolverIdsMatriculasDesdeLista($parsed, $cursoId);
    }
}
