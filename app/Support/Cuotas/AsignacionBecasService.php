<?php

namespace App\Support\Cuotas;

use App\Models\CuotasBeca;
use App\Models\Matricula;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Support\Facades\DB;

/**
 * Persistencia de becas en matricula.idCuotasbecas.
 */
final class AsignacionBecasService
{
    /**
     * @param  array<int|string, int|string>  $cambios  idMatricula => idCuotasbecas
     * @return array{actualizados: int, omitidos: int}
     */
    public static function guardar(array $cambios): array
    {
        if ($cambios === []) {
            return ['actualizados' => 0, 'omitidos' => 0];
        }

        $idsBecaValidos = CuotasBeca::query()->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $idTerlec = (int) schoolCtx()->idTerlec;

        $actualizados = 0;
        $omitidos = 0;

        DB::transaction(function () use ($cambios, $idsBecaValidos, $idTerlec, &$actualizados, &$omitidos) {
            foreach ($cambios as $idMatriculaRaw => $idBecaRaw) {
                $idMatricula = (int) $idMatriculaRaw;
                $idBeca = (int) $idBecaRaw;

                if ($idMatricula < 1 || ! $idsBecaValidos->has($idBeca)) {
                    $omitidos++;

                    continue;
                }

                $query = Matricula::query()
                    ->whereKey($idMatricula)
                    ->where('idTerlec', $idTerlec)
                    ->whereNull('fechaBaja');

                SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'idNivel');

                $matricula = $query->first(['id', 'idCuotasbecas']);

                if ($matricula === null) {
                    $omitidos++;

                    continue;
                }

                if ((int) ($matricula->idCuotasbecas ?? 0) === $idBeca) {
                    continue;
                }

                $matricula->update(['idCuotasbecas' => $idBeca]);
                $actualizados++;
            }
        });

        return ['actualizados' => $actualizados, 'omitidos' => $omitidos];
    }
}
