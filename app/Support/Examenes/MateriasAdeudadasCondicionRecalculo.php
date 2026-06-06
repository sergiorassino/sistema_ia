<?php

namespace App\Support\Examenes;

use Illuminate\Support\Facades\DB;

/**
 * Recalcula condAdeuda e inscri en calificaciones adeudadas (apro = 1),
 * portando la lógica legacy de anaCond().
 */
final class MateriasAdeudadasCondicionRecalculo
{
    /**
     * @return array{procesados:int, actualizados:int, omitidos:int}
     */
    public static function recalcularNivel(
        int $idNivel,
        int $idTerlecActual,
        int $idTerlecTurno,
        int $idTurno,
    ): array {
        if ($idTurno <= 0 || MateriasAdeudadasPreparacion::anoTerlec($idTerlecTurno) === null) {
            return ['procesados' => 0, 'actualizados' => 0, 'omitidos' => 0];
        }

        $examTodosInscri = strtoupper(trim((string) DB::table('ento')
            ->where('idNivel', $idNivel)
            ->value('examTodosInscri')));

        $regulares = DB::table('matricula')
            ->where('idTerlec', $idTerlecActual)
            ->where('idCondiciones', 1)
            ->pluck('idLegajos')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $procesados = 0;
        $actualizados = 0;
        $omitidos = 0;

        DB::table('calificaciones as c')
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->where('c.apro', 1)
            ->where('cu.idNivel', $idNivel)
            ->select([
                'c.id',
                'c.idLegajos',
                'c.condAdeuda',
            ])
            ->orderBy('c.id')
            ->chunk(250, function ($filas) use (
                $regulares,
                $examTodosInscri,
                &$procesados,
                &$actualizados,
                &$omitidos,
            ) {
                foreach ($filas as $fila) {
                    $procesados++;
                    $cambio = self::analizarYActualizarFila(
                        (int) $fila->id,
                        (string) ($fila->condAdeuda ?? ''),
                        $regulares->has((int) $fila->idLegajos),
                        $examTodosInscri,
                    );
                    if ($cambio) {
                        $actualizados++;
                    } else {
                        $omitidos++;
                    }
                }
            });

        return [
            'procesados' => $procesados,
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
        ];
    }

    /**
     * Equivalente a anaCond() del sistema anterior (rama visible del script legacy).
     */
    private static function analizarYActualizarFila(
        int $idCalificacion,
        string $condAdeuda,
        bool $esRegularAnioActual,
        string $examTodosInscri,
    ): bool {
        $cond = strtoupper(trim($condAdeuda));

        if ($cond === 'EQ' || $cond === 'TM') {
            return false;
        }

        $nuevaCond = 'PR';
        $inscri = $esRegularAnioActual ? 1 : 0;

        if ($esRegularAnioActual) {
            if ($examTodosInscri === 'T') {
                return self::actualizar($idCalificacion, [
                    'inscri' => $inscri,
                    'condAdeuda' => $nuevaCond,
                ]);
            }

            return self::actualizar($idCalificacion, [
                'condAdeuda' => $nuevaCond,
            ]);
        }

        return self::actualizar($idCalificacion, [
            'condAdeuda' => $nuevaCond,
        ]);
    }

    /**
     * @param  array{inscri?:int, condAdeuda:string}  $datos
     */
    private static function actualizar(int $idCalificacion, array $datos): bool
    {
        $afectados = DB::table('calificaciones')
            ->where('id', $idCalificacion)
            ->update($datos);

        return $afectados > 0;
    }
}
