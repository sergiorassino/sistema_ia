<?php

namespace App\Support\Aspirantes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de columna en la tabla legacy `aspirantes` (para el formulario público).
 */
final class AspirantesColumnaTipo
{
    /** @var array<string, string>|null columna => DATA_TYPE (minúsculas) */
    private static ?array $dataTypes = null;

    public static function esFecha(string $columna): bool
    {
        $columna = trim($columna);
        if ($columna === '') {
            return false;
        }

        $tipo = self::dataType($columna);
        if (in_array($tipo, ['date', 'datetime', 'timestamp'], true)) {
            return true;
        }

        $nombre = strtolower($columna);

        return str_starts_with($nombre, 'fech')
            || str_contains($nombre, 'fecha')
            || str_ends_with($nombre, '_fec');
    }

    private static function dataType(string $columna): string
    {
        $tipos = self::dataTypesPorColumna();

        return $tipos[$columna] ?? '';
    }

    /**
     * @return array<string, string>
     */
    private static function dataTypesPorColumna(): array
    {
        if (self::$dataTypes !== null) {
            return self::$dataTypes;
        }

        self::$dataTypes = [];

        if (! Schema::hasTable('aspirantes')) {
            return self::$dataTypes;
        }

        $db = DB::getDatabaseName();
        $rows = DB::select(
            'SELECT COLUMN_NAME, DATA_TYPE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$db, 'aspirantes']
        );

        foreach ($rows as $row) {
            self::$dataTypes[(string) $row->COLUMN_NAME] = strtolower((string) $row->DATA_TYPE);
        }

        return self::$dataTypes;
    }
}
