<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $schemaPath = base_path('../schema.sql');
        if (! is_file($schemaPath)) {
            // Si no está el schema.sql, no hay fuente de verdad para restaurar FKs.
            return;
        }

        $sql = file_get_contents($schemaPath);
        if ($sql === false || trim($sql) === '') {
            return;
        }

        $dbName = (string) (DB::getDatabaseName() ?? '');
        if ($dbName === '') {
            return;
        }

        // Extrae los bloques CREATE TABLE y, dentro de cada bloque, las constraints FK.
        preg_match_all(
            '/CREATE\\s+TABLE\\s+IF\\s+NOT\\s+EXISTS\\s+`(?P<table>[^`]+)`\\s*\\((?P<body>.*?)\\)\\s*ENGINE=/si',
            $sql,
            $tables,
            PREG_SET_ORDER
        );

        if ($tables === []) {
            return;
        }

        $errors = [];

        foreach ($tables as $t) {
            $table = (string) ($t['table'] ?? '');
            $body = (string) ($t['body'] ?? '');
            if ($table === '' || $body === '') {
                continue;
            }

            preg_match_all(
                '/CONSTRAINT\\s+`(?P<name>[^`]+)`\\s+FOREIGN\\s+KEY\\s*\\(`(?P<col>[^`]+)`\\)\\s+REFERENCES\\s+`(?P<refTable>[^`]+)`\\s*\\(`(?P<refCol>[^`]+)`\\)\\s*(?P<actions>(?:\\s+ON\\s+DELETE\\s+(?:CASCADE|SET\\s+NULL|NO\\s+ACTION|RESTRICT))?(?:\\s+ON\\s+UPDATE\\s+(?:CASCADE|SET\\s+NULL|NO\\s+ACTION|RESTRICT))?)\\s*,?/i',
                $body,
                $fks,
                PREG_SET_ORDER
            );

            if ($fks === []) {
                continue;
            }

            foreach ($fks as $m) {
                $name = (string) ($m['name'] ?? '');
                $col = (string) ($m['col'] ?? '');
                $refTable = (string) ($m['refTable'] ?? '');
                $refCol = (string) ($m['refCol'] ?? '');
                $actions = trim((string) ($m['actions'] ?? ''));

                if ($name === '' || $col === '' || $refTable === '' || $refCol === '') {
                    continue;
                }

                // Si la FK ya existe, continuar.
                $exists = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
                    ->where('CONSTRAINT_SCHEMA', $dbName)
                    ->where('CONSTRAINT_NAME', $name)
                    ->exists();

                if ($exists) {
                    continue;
                }

                // Si la columna origen es nullable, limpiar huérfanos seteando NULL para permitir crear la FK.
                $nullable = DB::table('information_schema.COLUMNS')
                    ->where('TABLE_SCHEMA', $dbName)
                    ->where('TABLE_NAME', $table)
                    ->where('COLUMN_NAME', $col)
                    ->value('IS_NULLABLE');

                $isNullable = strtoupper((string) $nullable) === 'YES';

                // Para corregir datos legacy inconsistentes puede ser necesario borrar/actualizar
                // filas aún referenciadas por otras tablas (porque las FKs se fueron restaurando
                // en distinto orden). Desactivamos chequeo momentáneamente solo para la limpieza.
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                if ($isNullable) {
                    DB::statement(sprintf(
                        'UPDATE `%s` t
                         LEFT JOIN `%s` r ON r.`%s` = t.`%s`
                         SET t.`%s` = NULL
                         WHERE t.`%s` IS NOT NULL
                           AND r.`%s` IS NULL',
                        $table,
                        $refTable,
                        $refCol,
                        $col,
                        $col,
                        $col,
                        $refCol
                    ));
                } else {
                    // Si la columna NO permite NULL, no podemos "desenganchar" sin alterar estructura.
                    // Para poder restaurar la integridad referencial, se eliminan filas huérfanas.
                    DB::statement(sprintf(
                        'DELETE t FROM `%s` t
                         LEFT JOIN `%s` r ON r.`%s` = t.`%s`
                         WHERE r.`%s` IS NULL',
                        $table,
                        $refTable,
                        $refCol,
                        $col,
                        $refCol
                    ));
                }
                DB::statement('SET FOREIGN_KEY_CHECKS=1');

                try {
                    $ddl = sprintf(
                        'ALTER TABLE `%s`
                           ADD CONSTRAINT `%s`
                           FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`)%s',
                        $table,
                        $name,
                        $col,
                        $refTable,
                        $refCol,
                        $actions !== '' ? ' '.$actions : ''
                    );

                    DB::statement($ddl);
                } catch (Throwable $e) {
                    $errors[] = "{$table}.{$col} -> {$refTable}.{$refCol} ({$name}): {$e->getMessage()}";
                }
            }
        }

        if ($errors !== []) {
            throw new RuntimeException(
                "No se pudieron restaurar todas las foreign keys del schema.sql.\n".
                implode("\n", array_slice($errors, 0, 50)).
                (count($errors) > 50 ? "\n... (más errores omitidos)" : '')
            );
        }
    }

    public function down(): void
    {
        // No se elimina en down: son constraints legacy (estructura base).
    }
};
