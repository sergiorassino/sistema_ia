<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // La BD legacy debe respetar la estructura original del dump (`schema.sql`),
        // incluyendo índices y claves foráneas (p.ej. cascades).
        $dumpPath = base_path('../schema.sql');

        if (! is_file($dumpPath)) {
            // Compatibilidad: si el repo viejo no trae `schema.sql`, se usa el dump anterior.
            $fallback = base_path('../bd_con_datos.sql');
            if (! is_file($fallback)) {
                throw new RuntimeException("No se encontro el dump en: {$dumpPath} (ni fallback: {$fallback})");
            }
            $dumpPath = $fallback;
        }

        $sql = file_get_contents($dumpPath);

        if ($sql === false) {
            throw new RuntimeException("No se pudo leer el dump en: {$dumpPath}");
        }

        preg_match_all('/CREATE TABLE IF NOT EXISTS `[^`]+`.*?;/si', $sql, $matches);
        $createStatements = $matches[0] ?? [];

        if ($createStatements === []) {
            throw new RuntimeException('No se encontraron sentencias CREATE TABLE en el dump.');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($createStatements as $statement) {
            // Ejecutar el statement original (sin "sanitizar" FK/constraints).
            DB::unprepared($this->cleanupTrailingCommas($statement));
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        $dumpPath = base_path('../schema.sql');

        if (! is_file($dumpPath)) {
            $dumpPath = base_path('../bd_con_datos.sql');
            if (! is_file($dumpPath)) {
                return;
            }
        }

        $sql = file_get_contents($dumpPath);

        if ($sql === false) {
            return;
        }

        preg_match_all('/CREATE TABLE IF NOT EXISTS `([^`]+)`/i', $sql, $tableMatches);
        $tables = array_values(array_unique($tableMatches[1] ?? []));

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (array_reverse($tables) as $table) {
            DB::unprepared("DROP TABLE IF EXISTS `{$table}`;");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function cleanupTrailingCommas(string $statement): string
    {
        // Algunos dumps legacy pueden quedar con coma colgante antes de `)`.
        // Esto no modifica la estructura lógica, solo evita SQL inválido.
        $clean = preg_replace('/,\s*\)/m', "\n)", $statement);

        return $clean ?? $statement;
    }
};
