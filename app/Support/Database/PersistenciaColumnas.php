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
    /** @var array<string, list<array{name: string, type: string, nullable: bool, default: mixed, extra: string}>> */
    private static array $metaColumnas = [];

    /**
     * En columnas enteras del esquema, vacío / null / guión se persisten como 0
     * (INT NOT NULL legacy no admite NULL). VARCHAR no se toca.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function adaptarEnterosVacios(string $tabla, array $payload): array
    {
        foreach ($payload as $columna => $valor) {
            if (! self::esTipoEntero(self::tipoColumna($tabla, (string) $columna))) {
                continue;
            }
            if (self::valorEnteroVacio($valor)) {
                $payload[$columna] = 0;
            }
        }

        return $payload;
    }

    /**
     * NULL explícito en INSERT/UPDATE ignora el DEFAULT de MySQL y falla (1048)
     * si la columna es NOT NULL. Enteros → 0, texto → '', fechas → se omiten.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function reemplazarNulosExplicitos(string $tabla, array $payload): array
    {
        foreach ($payload as $columna => $valor) {
            if ($valor !== null) {
                continue;
            }

            $tipo = self::tipoColumna($tabla, (string) $columna);
            if (self::esTipoEntero($tipo) || self::esTipoDecimal($tipo)) {
                $payload[$columna] = 0;

                continue;
            }
            if (self::esTipoTexto($tipo)) {
                $payload[$columna] = '';

                continue;
            }

            unset($payload[$columna]);
        }

        return $payload;
    }

    public static function columnaEsEntera(string $tabla, string $columna): bool
    {
        return self::esTipoEntero(self::tipoColumna($tabla, $columna));
    }

    /**
     * En INSERT, completa columnas NOT NULL sin DEFAULT que no vinieron en el payload
     * (enteros → 0, texto → ''). No inventa fechas ni toca auto_increment.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $ignorar
     * @return array<string, mixed>
     */
    public static function completarNotNullSinDefault(string $tabla, array $payload, array $ignorar = ['id']): array
    {
        foreach (self::metaColumnas($tabla) as $col) {
            $nombre = $col['name'];
            if (in_array($nombre, $ignorar, true)) {
                continue;
            }
            if ($col['nullable'] || $col['default'] !== null) {
                continue;
            }
            if (str_contains(strtolower($col['extra']), 'auto_increment')) {
                continue;
            }
            if (array_key_exists($nombre, $payload) && $payload[$nombre] !== null) {
                continue;
            }

            if (self::esTipoEntero($col['type'])) {
                $payload[$nombre] = 0;

                continue;
            }
            if (self::esTipoTexto($col['type'])) {
                $payload[$nombre] = '';
            }
        }

        return $payload;
    }

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

        if ($sqlState === '23000' && $code === 1048) {
            return 'No se pudo guardar: un campo obligatorio de la base quedó vacío. Complete los datos e intente de nuevo.';
        }

        return null;
    }

    /**
     * @return list<array{name: string, type: string, nullable: bool, default: mixed, extra: string}>
     */
    private static function metaColumnas(string $tabla): array
    {
        if (isset(self::$metaColumnas[$tabla])) {
            return self::$metaColumnas[$tabla];
        }

        self::$metaColumnas[$tabla] = [];

        if (! Schema::hasTable($tabla)) {
            return self::$metaColumnas[$tabla];
        }

        $schema = self::nombreBaseDatos();
        if ($schema === '') {
            return self::$metaColumnas[$tabla];
        }

        $rows = DB::select(
            'SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$schema, $tabla]
        );

        foreach ($rows as $row) {
            self::$metaColumnas[$tabla][] = [
                'name' => (string) $row->COLUMN_NAME,
                'type' => strtolower((string) $row->DATA_TYPE),
                'nullable' => strtoupper((string) $row->IS_NULLABLE) === 'YES',
                'default' => $row->COLUMN_DEFAULT,
                'extra' => (string) $row->EXTRA,
            ];
        }

        return self::$metaColumnas[$tabla];
    }

    private static function tipoColumna(string $tabla, string $columna): string
    {
        foreach (self::metaColumnas($tabla) as $col) {
            if (strcasecmp($col['name'], $columna) === 0) {
                return $col['type'];
            }
        }

        return '';
    }

    private static function esTipoEntero(string $tipo): bool
    {
        return in_array($tipo, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'boolean', 'bit'], true);
    }

    private static function esTipoDecimal(string $tipo): bool
    {
        return in_array($tipo, ['decimal', 'numeric', 'float', 'double', 'real'], true);
    }

    private static function esTipoTexto(string $tipo): bool
    {
        return in_array($tipo, ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'enum', 'set'], true);
    }

    private static function valorEnteroVacio(mixed $valor): bool
    {
        if ($valor === null || $valor === '') {
            return true;
        }
        if (! is_string($valor)) {
            return false;
        }

        $texto = trim($valor);
        if ($texto === '') {
            return true;
        }

        return (bool) preg_match('/^[\-\x{2010}-\x{2015}\x{2212}]+$/u', $texto);
    }

    private static function nombreBaseDatos(): string
    {
        $row = DB::selectOne('SELECT DATABASE() AS db');
        if ($row && $row->db !== null && (string) $row->db !== '') {
            return (string) $row->db;
        }

        return (string) DB::getDatabaseName();
    }

    private static function valoresEquivalentes(mixed $esperado, mixed $actual): bool
    {
        if ($esperado === null && ($actual === null || $actual === '')) {
            return true;
        }

        if (is_bool($esperado)) {
            return (int) $actual === ($esperado ? 1 : 0);
        }

        if (is_int($esperado)) {
            if ($actual === null || $actual === '') {
                return $esperado === 0;
            }

            if (is_bool($actual) || is_numeric($actual)) {
                return (int) $actual === $esperado;
            }
        }

        return (string) $esperado === (string) $actual;
    }
}
