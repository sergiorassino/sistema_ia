<?php

namespace App\Console\Commands;

use App\Support\DataMigracion\MigrarInfoxobseACalificacionesObs;
use Illuminate\Console\Command;

/**
 * Migra observaciones docentes de infoxobse (legacy) a calificaciones.obs01 / obs02.
 *
 * Comando puntual de datos: NO forma parte de `php artisan migrate` ni de
 * `php artisan se:migrate-legacy`. Debe invocarse explícitamente.
 *
 * Por defecto solo matrículas activas (fechaBaja NULL) del ciclo con mayor terlec.ano.
 *
 * Uso (con tenant/BD correctos en .env, o tras se:switch):
 *   php artisan se:migrar-infoxobse-a-calificaciones-obs --dry-run
 *   php artisan se:migrar-infoxobse-a-calificaciones-obs --id-nivel=2
 *   php artisan se:migrar-infoxobse-a-calificaciones-obs --force
 */
class MigrarInfoxobseACalificacionesObsCommand extends Command
{
    protected $signature = 'se:migrar-infoxobse-a-calificaciones-obs
                            {--dry-run : Simular sin escribir en la BD}
                            {--force : Sobrescribir obs01/obs02 ya cargados en calificaciones}
                            {--id-nivel= : Solo matrículas de ese nivel (1=inicial, 2=primario)}
                            {--id-terlec= : Solo un ciclo lectivo concreto (anula filtro de año actual)}
                            {--todos-ciclos : Incluir matrículas de todos los años (no solo el ciclo actual)}';

    protected $description = 'Copia infoxobse.etapa1/etapa2 → calificaciones.obs01/obs02 (solo ciclo lectivo actual por defecto).';

    public function handle(): int
    {
        $slug = env('TENANT_SLUG', '(sin definir)');
        $db = env('DB_DATABASE', '(sin definir)');

        $this->newLine();
        $this->line("  Tenant activo : <comment>{$slug}</comment>");
        $this->line("  Base de datos : <comment>{$db}</comment>");
        $this->newLine();

        $idNivel = $this->opcionEnteraPositiva('id-nivel');
        $idTerlec = $this->opcionEnteraPositiva('id-terlec');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $soloCicloActual = ! (bool) $this->option('todos-ciclos');

        if ($dryRun) {
            $this->warn('  Modo --dry-run: no se escribirá en la base de datos.');
        }

        if ($force && ! $dryRun) {
            if (! $this->confirm('  --force sobrescribirá obs01/obs02 existentes. ¿Continuar?', false)) {
                $this->line('  Cancelado.');

                return self::SUCCESS;
            }
        }

        $colMateria = MigrarInfoxobseACalificacionesObs::columnaMateriaInfoxobse();
        $this->line('  Origen : <comment>infoxobse</comment> (etapa1 → obs01, etapa2 → obs02)');
        $this->line('  Destino: <comment>calificaciones</comment>');
        $this->line('  Alumnos: matrículas activas (<comment>fechaBaja IS NULL</comment>)');
        if ($idTerlec !== null) {
            $this->line("  Ciclo  : idTerlec = {$idTerlec}");
        } elseif ($soloCicloActual) {
            $ano = MigrarInfoxobseACalificacionesObs::anoCicloActual();
            $ids = MigrarInfoxobseACalificacionesObs::idsTerlecCicloActual();
            $this->line('  Ciclo  : año lectivo actual (<comment>terlec.ano = '.($ano ?? '?').'</comment>, ids: '.implode(', ', $ids).')');
        } else {
            $this->line('  Ciclo  : <comment>todos los años</comment> (--todos-ciclos)');
        }
        if ($idNivel !== null) {
            $this->line("  Nivel  : idNivel = {$idNivel}");
        }
        if ($colMateria !== null) {
            $this->line("  Clave materia en infoxobse: <comment>{$colMateria}</comment>");
        } else {
            $this->line('  Clave materia en infoxobse: <comment>ord</comment> (sin idMaterias/idMateria)');
        }
        $this->newLine();

        $resultado = MigrarInfoxobseACalificacionesObs::ejecutar(
            $dryRun,
            $force,
            $idNivel,
            $idTerlec,
            $soloCicloActual,
        );

        if (! ($resultado['ok'] ?? false)) {
            $this->error('  '.($resultado['error'] ?? 'Error desconocido.'));

            return self::FAILURE;
        }

        $this->table(
            ['Concepto', 'Cantidad'],
            [
                ['Filas en infoxobse (con filtros)', (string) ($resultado['filas_infoxobse'] ?? 0)],
                [$dryRun ? 'Se actualizarían' : 'Actualizadas', (string) ($resultado['actualizadas'] ?? 0)],
                [$dryRun ? 'Se insertarían' : 'Insertadas', (string) ($resultado['insertadas'] ?? 0)],
                ['Omitidas (etapas vacías)', (string) ($resultado['omitidas_vacio'] ?? 0)],
                ['Omitidas (destino ya tenía texto distinto)', (string) ($resultado['omitidas_destino'] ?? 0)],
                ['Omitidas (sin materia resoluble)', (string) ($resultado['omitidas_sin_materia'] ?? 0)],
                ['Omitidas (calificaciones ambiguas)', (string) ($resultado['omitidas_ambiguo'] ?? 0)],
            ],
        );

        $advertencias = $resultado['advertencias'] ?? [];
        if ($advertencias !== []) {
            $this->newLine();
            $this->warn('  Advertencias:');
            foreach ($advertencias as $aviso) {
                $this->line("    • {$aviso}");
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info('  Simulación finalizada. Ejecute sin --dry-run para aplicar.');
        } else {
            $this->info('  Migración aplicada.');
            $this->line('  Revise en el sistema que las observaciones se vean correctamente antes de depender solo de calificaciones.');
        }
        $this->newLine();

        return self::SUCCESS;
    }

    private function opcionEnteraPositiva(string $nombre): ?int
    {
        $valor = $this->option($nombre);
        if ($valor === null || $valor === '') {
            return null;
        }

        $entero = (int) $valor;

        return $entero > 0 ? $entero : null;
    }
}
