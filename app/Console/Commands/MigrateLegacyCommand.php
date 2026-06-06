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
        $this->fakeLegacySchemaMigration();

        // ── PASO 2: Migraciones core ───────────────────────────────────────────
        $this->newLine();
        $this->line('<comment>  Paso 2/4</comment> — Corriendo migraciones core...');
        $this->call('migrate', ['--force' => true]);

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
        $this->call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        $this->newLine();
        $this->info('  Migración completada.');
        $this->newLine();
        $this->line('  Resumen de lo que se agregó a la BD legacy:');
        $this->line('    • Tablas SE nuevas : push_subscriptions');
        $this->line('    • Tablas módulo Listados   : solapas_legajo, campos_legajo');
        $this->line('    • Tablas módulo Comunicaciones : com_canales, com_hilos, com_mensajes,');
        $this->line('                         com_mensajes_destinatarios, com_mensajes_envios,');
        $this->line('                         com_preferencias');
        $this->line('    • Columnas nuevas  : calificaciones.tea, ento.logo_path,');
        $this->line('                         ento.logo_original_name, matricula.fechaBaja');
        $this->line('                         + columnas de las migraciones tenant del colegio');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Marca la migración del schema legacy como "ya ejecutada" si aún no lo está.
     * Esto es necesario porque esa migración requiere un archivo schema.sql que
     * en una BD legacy ya existente no tiene sentido usar (las tablas ya están).
     */
    private function fakeLegacySchemaMigration(): void
    {
        // Asegurarse de que la tabla migrations existe ANTES de consultarla
        $this->ensureMigrationsTable();

        $alreadyRan = DB::table('migrations')
            ->where('migration', self::LEGACY_SCHEMA_MIGRATION)
            ->exists();

        if ($alreadyRan) {
            $this->line('           Ya estaba registrada. OK.');

            return;
        }

        // Obtener el batch máximo actual para usar el siguiente
        $batch = (int) DB::table('migrations')->max('batch') + 1;

        DB::table('migrations')->insert([
            'migration' => self::LEGACY_SCHEMA_MIGRATION,
            'batch' => $batch,
        ]);

        $this->line('           Registrada como ejecutada (tablas legacy ya existentes). OK.');
    }

    /**
     * Crea la tabla `migrations` de Laravel si no existe.
     * Normalmente `php artisan migrate` la crea automáticamente,
     * pero la necesitamos antes de ejecutar el primer migrate.
     */
    private function ensureMigrationsTable(): void
    {
        if (DB::getSchemaBuilder()->hasTable('migrations')) {
            return;
        }

        DB::getSchemaBuilder()->create('migrations', function ($table) {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
        });

        $this->line('           Tabla `migrations` creada. OK.');
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
