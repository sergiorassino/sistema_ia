<?php

namespace App\Console\Commands;

use App\Support\Migrations\EnsureProfesoresEmailInstiColumn;
use Illuminate\Console\Command;

/**
 * Agrega profesores.emailInsti en colegios que aún no tienen la columna.
 *
 * Uso (con el tenant/BD correctos en .env, o tras se:switch):
 *   php artisan se:ensure-email-insti-profesores
 *   php artisan se:ensure-email-insti-profesores --dry-run
 */
class EnsureProfesoresEmailInstiCommand extends Command
{
    protected $signature = 'se:ensure-email-insti-profesores
                            {--dry-run : Solo informa si haría falta agregar la columna}';

    protected $description = 'Agrega profesores.emailInsti si falta (recuperación de contraseña y legajo docente).';

    public function handle(): int
    {
        $slug = env('TENANT_SLUG', '(sin definir)');
        $db = env('DB_DATABASE', '(sin definir)');

        $this->newLine();
        $this->line("  Tenant activo : <comment>{$slug}</comment>");
        $this->line("  Base de datos : <comment>{$db}</comment>");
        $this->newLine();

        $estado = EnsureProfesoresEmailInstiColumn::estado();

        if ($estado === 'sin_tabla_profesores') {
            $this->error('  No existe la tabla profesores en esta base de datos.');

            return self::FAILURE;
        }

        if ($estado === 'ya_existe') {
            $this->info('  La columna profesores.emailInsti ya existe. No hay nada que hacer.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('  [dry-run] Se agregaría: ALTER TABLE profesores ADD emailInsti VARCHAR(100) NULL');
            $this->line('  Ejecute sin --dry-run para aplicar, o use: php artisan migrate');

            return self::SUCCESS;
        }

        $creada = EnsureProfesoresEmailInstiColumn::aplicar();
        if ($creada) {
            $this->info('  Columna profesores.emailInsti agregada correctamente.');
        } else {
            $this->info('  La columna profesores.emailInsti ya existía (sin cambios).');
        }

        return self::SUCCESS;
    }
}
