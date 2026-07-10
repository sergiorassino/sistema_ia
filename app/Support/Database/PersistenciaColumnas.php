<?php

namespace App\Support\Database;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Validación de columnas y verificación post-guardado en tablas legacy multi-tenant.
 *
 * Evita falsos éxitos cuando el esquema del tenant no tiene columnas que el formulario intenta persistir.
 */
final class PersistenciaColumnas
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $ignorar
     * @return list<string>
     */
    public static function columnasInexistentes(string $tabla, array $payload, array $ignorar = []): array
    {
        if (! Schema::hasTable($tabla)) {
            return array_values(array_diff(array_keys($payload), $ignorar));
        }

        $faltantes = [];
        foreach (array_keys($payload) as $columna) {
            if (in_array($columna, $ignorar, true)) {
                continue;
            }
            if (! Schema::hasColumn($tabla, $columna)) {
                $faltantes[] = $columna;
            }
        }

        return $faltantes;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $ignorar
     * @return array{payload: array<string, mixed>, columnas_con_valor_sin_columna: list<string>}
     */
    public static function prepararPayload(string $tabla, array $payload, array $ignorar = []): array
    {
        $preparado = [];
        $columnasConValorSinColumna = [];

        foreach ($payload as $columna => $valor) {
            if (in_array($columna, $ignorar, true)) {
                $preparado[$columna] = $valor;

                continue;
            }

            $existe = Schema::hasTable($tabla) && Schema::hasColumn($tabla, $columna);
            $vacio = self::valorVacioParaPersistencia($valor);

            if (! $existe) {
                if (! $vacio) {
                    $columnasConValorSinColumna[] = $columna;
                }

                continue;
            }

            $preparado[$columna] = $valor;
        }

        sort($columnasConValorSinColumna);

        return [
            'payload' => $preparado,
            'columnas_con_valor_sin_columna' => $columnasConValorSinColumna,
        ];
    }

    /**
     * @param  list<string>  $columnas
     */
    public static function mensajeColumnasInexistentes(string $tabla, array $columnas): string
    {
        sort($columnas);
        $lista = implode(', ', array_map(
            static fn (string $columna): string => "{$tabla}.{$columna}",
            $columnas
        ));

        return "No se pudieron guardar algunos datos: faltan columnas en la base de datos ({$lista}). "
            .'Ejecute las migraciones o el SQL de actualización de esquema del tenant.';
    }

    public static function valorVacioParaPersistencia(mixed $valor): bool
    {
        if ($valor === null) {
            return true;
        }

        if (is_bool($valor)) {
            return $valor === false;
        }

        if (is_int($valor) || is_float($valor)) {
            return false;
        }

        return trim((string) $valor) === '';
    }

    /**
     * @param  array<string, mixed>  $where
     * @param  array<string, mixed>  $valoresEsperados
     * @return list<string>
     */
    public static function columnasNoPersistidas(string $tabla, array $where, array $valoresEsperados): array
    {
        if ($valoresEsperados === [] || ! Schema::hasTable($tabla)) {
            return [];
        }

        $columnas = array_keys($valoresEsperados);
        $columnasExistentes = array_values(array_filter(
            $columnas,
            static fn (string $columna): bool => Schema::hasColumn($tabla, $columna)
        ));

        if ($columnasExistentes === []) {
            return [];
        }

        $query = DB::table($tabla);
        foreach ($where as $columna => $valor) {
            $query->where($columna, $valor);
        }

        $fila = $query->first($columnasExistentes);
        if ($fila === null) {
            return $columnasExistentes;
        }

        $noPersistidas = [];
        foreach ($valoresEsperados as $columna => $esperado) {
            if (! in_array($columna, $columnasExistentes, true)) {
                continue;
            }

            if (! self::valoresEquivalentes($esperado, $fila->{$columna} ?? null)) {
                $noPersistidas[] = $columna;
            }
        }

        return $noPersistidas;
    }

    /**
     * @param  list<string>  $columnas
     */
    public static function mensajeColumnasNoPersistidas(string $tabla, array $columnas): string
    {
        sort($columnas);
        $lista = implode(', ', array_map(
            static fn (string $columna): string => "{$tabla}.{$columna}",
            $columnas
        ));

        return "Los datos no quedaron guardados correctamente ({$lista}). "
            .'Verifique el esquema de la base de datos y vuelva a intentar.';
    }

    public static function mensajeDesdeQueryException(QueryException $exception): ?string
    {
        $sqlState = $exception->errorInfo[0] ?? '';
        $code = (int) ($exception->errorInfo[1] ?? 0);
        $message = $exception->getMessage();

        if ($sqlState === '42S22' || $code === 1054 || str_contains($message, 'Unknown column')) {
            return 'No se pudo guardar: hay columnas que no existen en la base de datos. '
                .'Ejecute las migraciones o el SQL de actualización de esquema del tenant.';
        }

        if ($sqlState === '42S02' || $code === 1146 || str_contains($message, "doesn't exist")) {
            return 'No se pudo guardar: falta una tabla en la base de datos.';
        }

        return null;
    }

    private static function valoresEquivalentes(mixed $esperado, mixed $actual): bool
    {
        if ($esperado === null && ($actual === null || $actual === '')) {
            return true;
        }

        if (is_bool($esperado)) {
            return (int) $actual === ($esperado ? 1 : 0);
        }

        if (is_int($esperado) && is_numeric($actual)) {
            return (int) $actual === $esperado;
        }

        return (string) $esperado === (string) $actual;
    }
}
