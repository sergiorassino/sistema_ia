<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Agrega las tablas y columnas del sistema SE a una BD legacy de ScriptCase,
 * sin tocar ni destruir las tablas y datos que ya existen.
 *
 * Uso (después de se:switch al colegio correcto):
 *   php artisan se:migrate-legacy
 *   php artisan se:migrate-legacy --dry-run    (solo muestra qué haría)
 *
 * Qué hace:
 *   1. Marca como "ya ejecutada" la migración que crea el schema desde dump
 *      (las tablas legacy ya existen en la BD destino; no necesitamos recrearlas).
 *   2. Corre el resto de las migraciones core (son todas idempotentes).
 *   3. Corre las migraciones de los paquetes (com_*, campos_legajo, solapas_legajo).
 *   4. Corre las migraciones tenant del colegio activo (si hay).
 *
 * No ejecuta migraciones que modifiquen estructuralmente tablas existentes
 * sin verificar primero con hasColumn / hasTable.
 */
class MigrateLegacyCommand extends Command
{
    protected $signature = 'se:migrate-legacy
                            {--dry-run : Mostrar qué migraciones se ejecutarían sin correr nada}
                            {--force   : No pedir confirmación}';

    protected $description = 'Agrega las tablas y columnas del SE a una BD legacy de ScriptCase.';

    /**
     * Migración del schema inicial: crea tablas desde un dump SQL.
     * En una BD legacy ya existente DEBE falsificarse (las tablas ya están).
     */
    private const LEGACY_SCHEMA_MIGRATION = '2026_04_24_000000_create_legacy_school_schema_from_dump';

    public function handle(): int
    {
        $slug = env('TENANT_SLUG', '(sin definir)');
        $db = env('DB_DATABASE', '(sin definir)');

        $this->newLine();
        $this->line("  Tenant activo : <comment>{$slug}</comment>");
        $this->line("  Base de datos : <comment>{$db}</comment>");
        $this->newLine();

        if ($this->option('dry-run')) {
            return $this->dryRun();
        }

        if (! $this->option('force')) {
            $confirm = $this->confirm(
                "  ¿Continuar con la migración de <comment>{$db}</comment>?",
                false
            );
            if (! $confirm) {
                $this->line('  Cancelado.');

                return self::SUCCESS;
            }
        }

        $this->newLine();

        // ── PASO 1: Verificar y falsificar la migración del schema legacy ──────
        $this->line('<comment>  Paso 1/4</comment> — Verificando migración del schema legacy...');
        $paso1 = $this->fakeLegacySchemaMigration();

        // ── PASO 2: Migraciones core ───────────────────────────────────────────
        $this->newLine();
        $this->line('<comment>  Paso 2/4</comment> — Corriendo migraciones core...');
        $migracionesAntesCore = $this->migrationNames();
        $this->call('migrate', ['--force' => true]);
        $migracionesCore = $this->nuevasMigraciones($migracionesAntesCore);

        // ── PASO 3: Migraciones de paquetes ────────────────────────────────────
        $this->newLine();
        $this->line('<comment>  Paso 3/4</comment> — Corriendo migraciones de paquetes (listados, comunicaciones)...');
        // Los paquetes registran sus migraciones via loadMigrationsFrom(),
        // por lo que ya están incluidas en el `migrate` anterior.
        // Este paso es informativo.
        $this->line('           (incluidas en el paso anterior vía ServiceProvider)');

        // ── PASO 4: Migraciones tenant ─────────────────────────────────────────
        $this->newLine();
        $this->line('<comment>  Paso 4/4</comment> — Corriendo migraciones tenant del colegio...');
        $migracionesAntesTenant = $this->migrationNames();
        $this->call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
        $migracionesTenant = $this->nuevasMigraciones($migracionesAntesTenant);

        $this->newLine();
        $this->info('  Migración completada.');
        $this->newLine();
        $this->imprimirResumenEjecucion($paso1, $migracionesCore, $migracionesTenant);
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Marca la migración del schema legacy como "ya ejecutada" si aún no lo está.
     * Esto es necesario porque esa migración requiere un archivo schema.sql que
     * en una BD legacy ya existente no tiene sentido usar (las tablas ya están).
     *
     * @return array{migrations_table_creada: bool, legacy_schema: 'registered'|'already'}
     */
    private function fakeLegacySchemaMigration(): array
    {
        // Asegurarse de que la tabla migrations existe ANTES de consultarla
        $migrationsTableCreada = $this->ensureMigrationsTable();

        $alreadyRan = DB::table('migrations')
            ->where('migration', self::LEGACY_SCHEMA_MIGRATION)
            ->exists();

        if ($alreadyRan) {
            $this->line('           Ya estaba registrada. OK.');

            return [
                'migrations_table_creada' => $migrationsTableCreada,
                'legacy_schema' => 'already',
            ];
        }

        // Obtener el batch máximo actual para usar el siguiente
        $batch = (int) DB::table('migrations')->max('batch') + 1;

        DB::table('migrations')->insert([
            'migration' => self::LEGACY_SCHEMA_MIGRATION,
            'batch' => $batch,
        ]);

        $this->line('           Registrada como ejecutada (tablas legacy ya existentes). OK.');

        return [
            'migrations_table_creada' => $migrationsTableCreada,
            'legacy_schema' => 'registered',
        ];
    }

    /**
     * Crea la tabla `migrations` de Laravel si no existe.
     * Normalmente `php artisan migrate` la crea automáticamente,
     * pero la necesitamos antes de ejecutar el primer migrate.
     */
    private function ensureMigrationsTable(): bool
    {
        if (DB::getSchemaBuilder()->hasTable('migrations')) {
            return false;
        }

        DB::getSchemaBuilder()->create('migrations', function ($table) {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
        });

        $this->line('           Tabla `migrations` creada. OK.');

        return true;
    }

    /** @return list<string> */
    private function migrationNames(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('migrations')) {
            return [];
        }

        return DB::table('migrations')
            ->orderBy('id')
            ->pluck('migration')
            ->all();
    }

    /**
     * @param  list<string>  $antes
     * @return list<string>
     */
    private function nuevasMigraciones(array $antes): array
    {
        $setAntes = array_flip($antes);

        return array_values(array_filter(
            $this->migrationNames(),
            fn (string $migration) => ! isset($setAntes[$migration])
        ));
    }

    /**
     * @param  array{migrations_table_creada: bool, legacy_schema: 'registered'|'already'}  $paso1
     * @param  list<string>  $migracionesCore
     * @param  list<string>  $migracionesTenant
     */
    private function imprimirResumenEjecucion(array $paso1, array $migracionesCore, array $migracionesTenant): void
    {
        $huboCambios = $paso1['migrations_table_creada']
            || $paso1['legacy_schema'] === 'registered'
            || $migracionesCore !== []
            || $migracionesTenant !== [];

        $this->line('  Resumen de esta ejecución:');

        if (! $huboCambios) {
            $this->line('    Sin cambios: la BD ya estaba al día.');

            return;
        }

        if ($paso1['migrations_table_creada']) {
            $this->line('    • Tabla <comment>migrations</comment> creada.');
        }

        if ($paso1['legacy_schema'] === 'registered') {
            $this->line('    • Schema legacy registrado como ejecutado (sin recrear tablas existentes).');
        }

        $this->imprimirMigracionesDelPaso('Core + paquetes', $migracionesCore);
        $this->imprimirMigracionesDelPaso('Tenant del colegio', $migracionesTenant);
    }

    /** @param  list<string>  $migraciones */
    private function imprimirMigracionesDelPaso(string $etiqueta, array $migraciones): void
    {
        if ($migraciones === []) {
            $this->line("    • {$etiqueta}: sin migraciones pendientes.");

            return;
        }

        $cantidad = count($migraciones);
        $this->line("    • {$etiqueta}: {$cantidad} migración".($cantidad === 1 ? '' : 'es').' aplicada'.($cantidad === 1 ? '' : 's').':');

        foreach ($migraciones as $migration) {
            $this->line("        - {$migration}");
        }
    }

    private function dryRun(): int
    {
        $this->line('  <comment>Modo --dry-run: no se ejecuta nada.</comment>');
        $this->newLine();

        $this->line('  Lo que haría se:migrate-legacy:');
        $this->newLine();
        $this->line('  1. Falsificar migración del schema legacy (tablas ya existen en la BD)');
        $this->line('     → '.self::LEGACY_SCHEMA_MIGRATION);
        $this->newLine();
        $this->line('  2. php artisan migrate  (migraciones core + paquetes)');
        $this->line('     → Columnas nuevas en tablas existentes (idempotentes)');
        $this->line('     → Tablas nuevas del SE: push_*, com_*, solapas_legajo, campos_legajo');
        $this->newLine();
        $this->line('  3. php artisan migrate --path=database/migrations/tenant');
        $this->line('     → Migraciones exclusivas de este colegio (si las hay)');
        $this->newLine();
        $this->line('  <info>Para ejecutar:</info> php artisan se:migrate-legacy --force');
        $this->newLine();

        return self::SUCCESS;
    }
}
