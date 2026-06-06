<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compara el schema de dos bases de datos MySQL y reporta diferencias (drift).
 *
 * Uso:
 *   php artisan se:drift-report --compare-with=ia_ejemplo
 *   php artisan se:drift-report --compare-with=ia_ejemplo --format=markdown
 *
 * La base de referencia es la que está configurada en DB_DATABASE del .env actual.
 */
class DriftReportCommand extends Command
{
    protected $signature = 'se:drift-report
                            {--compare-with= : Nombre de la base de datos a comparar contra la de referencia}
                            {--reference=    : Base de datos de referencia (por defecto: DB_DATABASE del .env)}
                            {--format=table  : Formato de salida: table (consola) o markdown}
                            {--tables=       : Filtrar solo estas tablas (separadas por coma)}';

    protected $description = 'Compara el schema de la BD de referencia contra otra BD y reporta el drift.';

    public function handle(): int
    {
        $targetDb = $this->option('compare-with');

        if (! $targetDb) {
            $this->error('Especificar --compare-with=nombre_bd');
            return self::FAILURE;
        }

        $referenceDb  = $this->option('reference') ?: config('database.connections.mysql.database');
        $format       = $this->option('format');
        $filterTables = $this->option('tables')
            ? array_map('trim', explode(',', $this->option('tables')))
            : [];

        $this->info("Referencia : {$referenceDb}");
        $this->info("Comparando : {$targetDb}");
        $this->newLine();

        // Usamos una conexión sin base de datos predeterminada para poder consultar
        // INFORMATION_SCHEMA de cualquier BD a la que el usuario tenga acceso,
        // sin necesitar privilegio de conexión directa a cada base.
        config(['database.connections.drift_info' => array_merge(
            config('database.connections.mysql'),
            ['database' => ''],
        )]);
        DB::purge('drift_info');

        $refTables    = $this->getTableColumns($referenceDb, 'drift_info');
        $targetTables = $this->getTableColumns($targetDb, 'drift_info');

        if ($filterTables) {
            $refTables    = array_intersect_key($refTables,    array_flip($filterTables));
            $targetTables = array_intersect_key($targetTables, array_flip($filterTables));
        }

        $report = $this->buildReport($refTables, $targetTables, $referenceDb, $targetDb);

        if ($format === 'markdown') {
            $this->outputMarkdown($report, $referenceDb, $targetDb);
        } else {
            $this->outputTable($report);
        }

        $hasIssues = collect($report)->contains(fn ($r) => $r['tipo'] !== 'ok');
        return $hasIssues ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Devuelve ['tabla' => ['col1' => 'type', 'col2' => 'type', ...]] para una BD.
     *
     * @return array<string, array<string, string>>
     */
    private function getTableColumns(string $dbName, string $connection): array
    {
        $rows = DB::connection($connection)->select(
            "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ?
             ORDER BY TABLE_NAME, ORDINAL_POSITION",
            [$dbName]
        );

        $tables = [];
        foreach ($rows as $row) {
            $tables[$row->TABLE_NAME][$row->COLUMN_NAME] = [
                'type'     => $row->COLUMN_TYPE,
                'nullable' => $row->IS_NULLABLE === 'YES',
                'default'  => $row->COLUMN_DEFAULT,
            ];
        }

        return $tables;
    }

    /**
     * Genera la lista de diferencias entre los dos schemas.
     *
     * @param array<string, array<string, array<string, mixed>>> $ref
     * @param array<string, array<string, array<string, mixed>>> $target
     * @return list<array{tipo: string, tabla: string, columna: string|null, detalle: string}>
     */
    private function buildReport(array $ref, array $target, string $refName, string $targetName): array
    {
        $report = [];

        $allTables = array_unique(array_merge(array_keys($ref), array_keys($target)));
        sort($allTables);

        foreach ($allTables as $table) {
            $inRef    = isset($ref[$table]);
            $inTarget = isset($target[$table]);

            if ($inRef && ! $inTarget) {
                $report[] = [
                    'tipo'    => 'tabla_faltante',
                    'tabla'   => $table,
                    'columna' => null,
                    'detalle' => "Tabla presente en {$refName} pero AUSENTE en {$targetName}",
                ];
                continue;
            }

            if (! $inRef && $inTarget) {
                $report[] = [
                    'tipo'    => 'tabla_extra',
                    'tabla'   => $table,
                    'columna' => null,
                    'detalle' => "Tabla EXTRA en {$targetName}, no existe en {$refName}",
                ];
                continue;
            }

            // Ambos tienen la tabla: comparar columnas
            $refCols    = $ref[$table];
            $targetCols = $target[$table];
            $tableDiff  = false;

            foreach ($refCols as $col => $refInfo) {
                if (! isset($targetCols[$col])) {
                    $report[] = [
                        'tipo'    => 'columna_faltante',
                        'tabla'   => $table,
                        'columna' => $col,
                        'detalle' => "Columna `{$col}` ({$refInfo['type']}) presente en {$refName} pero AUSENTE en {$targetName}",
                    ];
                    $tableDiff = true;
                    continue;
                }

                $targetInfo = $targetCols[$col];
                if (strtolower($refInfo['type']) !== strtolower($targetInfo['type'])) {
                    $report[] = [
                        'tipo'    => 'tipo_distinto',
                        'tabla'   => $table,
                        'columna' => $col,
                        'detalle' => "Tipo distinto en `{$col}`: {$refName}=`{$refInfo['type']}` vs {$targetName}=`{$targetInfo['type']}`",
                    ];
                    $tableDiff = true;
                }
            }

            foreach ($targetCols as $col => $targetInfo) {
                if (! isset($refCols[$col])) {
                    $report[] = [
                        'tipo'    => 'columna_extra',
                        'tabla'   => $table,
                        'columna' => $col,
                        'detalle' => "Columna EXTRA `{$col}` ({$targetInfo['type']}) en {$targetName}, no existe en {$refName}",
                    ];
                    $tableDiff = true;
                }
            }

            if (! $tableDiff) {
                $report[] = [
                    'tipo'    => 'ok',
                    'tabla'   => $table,
                    'columna' => null,
                    'detalle' => 'Sin diferencias',
                ];
            }
        }

        return $report;
    }

    private function outputTable(array $report): void
    {
        $byType = [
            'tabla_faltante'  => [],
            'tabla_extra'     => [],
            'columna_faltante' => [],
            'columna_extra'   => [],
            'tipo_distinto'   => [],
            'ok'              => [],
        ];

        foreach ($report as $row) {
            $byType[$row['tipo']][] = $row;
        }

        $issues = array_merge(
            $byType['tabla_faltante'],
            $byType['tabla_extra'],
            $byType['columna_faltante'],
            $byType['columna_extra'],
            $byType['tipo_distinto'],
        );

        if (empty($issues)) {
            $this->info('No hay diferencias entre los dos schemas.');
            return;
        }

        $rows = array_map(fn ($r) => [
            $this->typeLabel($r['tipo']),
            $r['tabla'],
            $r['columna'] ?? '-',
            $r['detalle'],
        ], $issues);

        $this->table(['Tipo', 'Tabla', 'Columna', 'Detalle'], $rows);

        $okCount = count($byType['ok']);
        $this->newLine();
        $this->line("<comment>Tablas sin diferencias: {$okCount}</comment>");
        $this->line('<comment>Diferencias encontradas: ' . count($issues) . '</comment>');
    }

    private function outputMarkdown(array $report, string $refDb, string $targetDb): void
    {
        $byTable = [];
        foreach ($report as $row) {
            $byTable[$row['tabla']][] = $row;
        }

        $lines = [];
        $lines[] = "## Drift: `{$refDb}` → `{$targetDb}`";
        $lines[] = '';
        $lines[] = '| Tabla | Columna | Tipo de drift | Detalle | Estrategia sugerida |';
        $lines[] = '|---|---|---|---|---|';

        foreach ($byTable as $table => $rows) {
            foreach ($rows as $row) {
                if ($row['tipo'] === 'ok') {
                    continue;
                }
                $col      = $row['columna'] ?? '-';
                $tipo     = $this->typeLabel($row['tipo']);
                $detalle  = $row['detalle'];
                $estrategia = $this->suggestStrategy($row['tipo']);
                $lines[] = "| `{$table}` | `{$col}` | {$tipo} | {$detalle} | {$estrategia} |";
            }
        }

        $this->line(implode("\n", $lines));
    }

    private function typeLabel(string $tipo): string
    {
        return match ($tipo) {
            'tabla_faltante'   => '⚠️ tabla faltante',
            'tabla_extra'      => '➕ tabla extra',
            'columna_faltante' => '⚠️ columna faltante',
            'columna_extra'    => '➕ columna extra',
            'tipo_distinto'    => '🔀 tipo distinto',
            'ok'               => '✅ ok',
            default            => $tipo,
        };
    }

    private function suggestStrategy(string $tipo): string
    {
        return match ($tipo) {
            'tabla_faltante'   => 'Migration core aditiva con `Schema::hasTable`',
            'tabla_extra'      => 'Migration en `database/migrations/tenant/` del colegio',
            'columna_faltante' => 'Migration core aditiva con `Schema::hasColumn` + default nullable',
            'columna_extra'    => 'Migration tenant o Accessor Eloquent en el colegio',
            'tipo_distinto'    => 'Accessor Eloquent o migration normalizadora',
            default            => '-',
        };
    }
}
