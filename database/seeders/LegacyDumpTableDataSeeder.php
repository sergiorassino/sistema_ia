<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LegacyDumpTableDataSeeder extends Seeder
{
    public function run(): void
    {
        $dumpPath = base_path('../bd_con_datos.sql');

        if (! is_file($dumpPath)) {
            throw new \RuntimeException("No se encontro el dump en: {$dumpPath}");
        }

        $sql = file_get_contents($dumpPath);

        if ($sql === false) {
            throw new \RuntimeException("No se pudo leer el dump en: {$dumpPath}");
        }

        $statements = $this->extractDataStatements($sql);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($statements as $statement) {
            DB::unprepared($statement);
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * @return list<string>
     */
    private function extractDataStatements(string $sql): array
    {
        $allStatements = $this->splitSqlStatements($sql);
        $statements = [];

        foreach ($allStatements as $statement) {
            $upper = strtoupper($statement);
            if (str_starts_with($upper, 'DELETE FROM `') || str_starts_with($upper, 'INSERT INTO `')) {
                $statements[] = $statement;
            }
        }

        if ($statements === []) {
            throw new \RuntimeException('No se encontraron sentencias de datos (DELETE/INSERT) en el dump.');
        }

        return $statements;
    }

    /**
     * @return list<string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inSingle = false;
        $inDouble = false;
        $escaped = false;

        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];
            $buffer .= $char;

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === "'" && ! $inDouble) {
                $inSingle = ! $inSingle;
                continue;
            }

            if ($char === '"' && ! $inSingle) {
                $inDouble = ! $inDouble;
                continue;
            }

            if ($char === ';' && ! $inSingle && ! $inDouble) {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }
}
