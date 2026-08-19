<?php

namespace App\Support\CalificacionesSecundario;

use App\Support\CalificacionesColoquioSecundario;
use App\Support\Database\PersistenciaColumnas;
use App\Support\PromedioAnualCalificacionesSecundario;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persistencia de `calificaciones.calif` con la fórmula de módulos (Eval/JIS).
 *
 * Único puente hacia `PromedioAnualCalificacionesSecundario::calcular()`.
 *
 * @see docs/05-preferencias-y-convenciones.md §7
 * @see docs/modulos/recalculo-promedios-secundario.md
 */
final class RecalculoPromedioAnualSecundario
{
    public const TAMANIO_LOTE = 250;

    /**
     * @param  array<string, mixed>|object  $row
     * @return array<string, string>
     */
    public static function icArrayDesdeRow(array|object $row): array
    {
        $src = is_array($row) ? $row : (array) $row;
        $arr = [];
        foreach (PromedioAnualCalificacionesSecundario::camposIcModulos() as $k) {
            $arr[$k] = (string) ($src[$k] ?? '');
        }

        return $arr;
    }

    /**
     * @param  array<string, mixed>|object  $row
     */
    public static function califDesdeFilaModulos(array|object $row): string
    {
        $prom = PromedioAnualCalificacionesSecundario::calcular(self::icArrayDesdeRow($row));

        return (string) ($prom['promedio'] ?? '');
    }

    /**
     * No pisar `calif` escrito por coloquio Dic/Feb aprobado (≥ 7).
     *
     * @param  array<string, mixed>|object  $row
     */
    public static function omitirPorColoquioAprobado(array|object $row): bool
    {
        $src = is_array($row) ? $row : (array) $row;

        return CalificacionesColoquioSecundario::notaColoquioAprobada($src['dic'] ?? '')
            || CalificacionesColoquioSecundario::notaColoquioAprobada($src['feb'] ?? '');
    }

    /**
     * Filas de `calificaciones` del ciclo y nivel (cursos del contexto).
     */
    public static function contarFilas(int $idNivel, int $idTerlec): int
    {
        if ($idNivel < 1 || $idTerlec < 1) {
            return 0;
        }

        return (int) self::consultaBase($idNivel, $idTerlec)->count();
    }

    /**
     * Recalcula `calif` en todas las filas del nivel + ciclo.
     *
     * @return array{
     *     procesados: int,
     *     actualizados: int,
     *     sin_cambio: int,
     *     omitidos_coloquio: int,
     *     errores: int,
     *     mensaje_error: string|null
     * }
     */
    public static function ejecutar(int $idNivel, int $idTerlec): array
    {
        $vacio = [
            'procesados' => 0,
            'actualizados' => 0,
            'sin_cambio' => 0,
            'omitidos_coloquio' => 0,
            'errores' => 0,
            'mensaje_error' => null,
        ];

        if ($idNivel < 1 || $idTerlec < 1) {
            $vacio['mensaje_error'] = 'Contexto de nivel o ciclo lectivo inválido.';

            return $vacio;
        }

        $faltantes = self::columnasRequeridasFaltantes();
        if ($faltantes !== []) {
            $vacio['mensaje_error'] = PersistenciaColumnas::mensajeColumnasInexistentes('calificaciones', $faltantes);

            return $vacio;
        }

        $procesados = 0;
        $actualizados = 0;
        $sinCambio = 0;
        $omitidosColoquio = 0;
        $errores = 0;
        $mensajeError = null;

        $select = array_merge(
            ['c.id', 'c.calif', 'c.dic', 'c.feb'],
            array_map(
                fn (string $c): string => 'c.'.$c,
                PromedioAnualCalificacionesSecundario::camposIcModulos(),
            ),
        );

        try {
            self::consultaBase($idNivel, $idTerlec)
                ->orderBy('c.id')
                ->select($select)
                ->chunk(self::TAMANIO_LOTE, function ($rows) use (
                    $idTerlec,
                    &$procesados,
                    &$actualizados,
                    &$sinCambio,
                    &$omitidosColoquio,
                    &$errores,
                ): void {
                    foreach ($rows as $row) {
                        $procesados++;
                        $id = (int) ($row->id ?? 0);
                        if ($id < 1) {
                            $errores++;

                            continue;
                        }

                        if (self::omitirPorColoquioAprobado($row)) {
                            $omitidosColoquio++;

                            continue;
                        }

                        $nuevo = self::califDesdeFilaModulos($row);
                        $actual = trim((string) ($row->calif ?? ''));
                        if ($nuevo === $actual) {
                            $sinCambio++;

                            continue;
                        }

                        $preparado = PersistenciaColumnas::prepararPayload('calificaciones', ['calif' => $nuevo]);
                        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
                            $errores++;

                            continue;
                        }

                        try {
                            DB::table('calificaciones')
                                ->where('id', $id)
                                ->where('idTerlec', $idTerlec)
                                ->update($preparado['payload']);
                            $actualizados++;
                        } catch (QueryException $e) {
                            $errores++;
                        }
                    }
                });
        } catch (QueryException $e) {
            $mensajeError = PersistenciaColumnas::mensajeDesdeQueryException($e)
                ?? 'Error de base de datos al recalcular promedios.';
        }

        return [
            'procesados' => $procesados,
            'actualizados' => $actualizados,
            'sin_cambio' => $sinCambio,
            'omitidos_coloquio' => $omitidosColoquio,
            'errores' => $errores,
            'mensaje_error' => $mensajeError,
        ];
    }

    /**
     * @return list<string>
     */
    private static function columnasRequeridasFaltantes(): array
    {
        $requeridas = array_merge(
            ['calif', 'dic', 'feb', 'idTerlec', 'idCursos'],
            PromedioAnualCalificacionesSecundario::camposIcModulos(),
        );

        $faltantes = [];
        foreach ($requeridas as $col) {
            if (! Schema::hasTable('calificaciones') || ! Schema::hasColumn('calificaciones', $col)) {
                $faltantes[] = $col;
            }
        }

        sort($faltantes);

        return $faltantes;
    }

    private static function consultaBase(int $idNivel, int $idTerlec)
    {
        return DB::table('calificaciones as c')
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->where('c.idTerlec', $idTerlec)
            ->where('cu.idNivel', $idNivel)
            ->where('cu.idTerlec', $idTerlec);
    }
}
