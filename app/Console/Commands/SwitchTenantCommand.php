<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Cambia el tenant activo en el .env local sin reemplazar el archivo completo.
 *
 * Uso:
 *   php artisan se:switch colegio_ejemplo
 *   php artisan se:switch sistemaprueba --db=ia_demo
 *   php artisan se:switch nssc
 *   php artisan se:switch          (sin argumento → muestra el tenant actual)
 *
 * El comando edita las líneas TENANT_SLUG= y DB_DATABASE= en el .env activo
 * y limpia el config cache. NO reemplaza el archivo entero (evita el crash
 * del file watcher de `php artisan serve`).
 */
class SwitchTenantCommand extends Command
{
    protected $signature = 'se:switch
                            {slug?           : Slug del colegio destino (ej: nssc, sistemaprueba)}
                            {--db=           : Nombre explícito de la BD. Por defecto: ia_{slug}}
                            {--list          : Listar los tenants conocidos sin cambiar nada}';

    protected $description = 'Cambia el tenant activo en el .env local y limpia el config cache.';

    /**
     * Mapa slug → base de datos para los casos que no siguen la convención ia_{slug}.
     * Agregar entradas cuando el nombre de la BD difiere del patrón por defecto.
     *
     * @var array<string, string>
     */
    private array $dbMap = [
        'sistemaprueba' => 'ia_demo',
        'demo' => 'ia_demo',
    ];

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->showList();
        }

        $slug = $this->argument('slug');

        if (! $slug) {
            $current = env('TENANT_SLUG', '(no definido)');
            $db = env('DB_DATABASE', '(no definido)');
            $this->info("Tenant activo: <comment>{$current}</comment>  |  BD: <comment>{$db}</comment>");
            $this->line('');
            $this->line('Uso: php artisan se:switch <slug>');

            return self::SUCCESS;
        }

        $db = $this->option('db') ?: ($this->dbMap[$slug] ?? "ia_{$slug}");

        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->error('.env no encontrado en: '.$envPath);

            return self::FAILURE;
        }

        $content = file_get_contents($envPath);

        // Reemplazar TENANT_SLUG (activo o comentado)
        $content = $this->replaceLine($content, 'TENANT_SLUG', $slug);

        // Reemplazar DB_DATABASE (activo o comentado)
        $content = $this->replaceLine($content, 'DB_DATABASE', $db);

        file_put_contents($envPath, $content);

        // Limpiar caches afectadas por el cambio de tenant
        $this->call('config:clear');
        $this->call('view:clear');

        $this->newLine();
        $this->line("  <info>✓</info> TENANT_SLUG  → <comment>{$slug}</comment>");
        $this->line("  <info>✓</info> DB_DATABASE  → <comment>{$db}</comment>");
        $this->line('  <info>✓</info> Config y views cache limpiados');
        $this->newLine();
        $this->line('  Siguiente: recargar el navegador (F5).');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Reemplaza el valor de KEY= en el contenido del .env,
     * manejando tanto líneas activas como comentadas (#KEY=...).
     */
    private function replaceLine(string $content, string $key, string $value): string
    {
        // Descomentar si estaba comentada y actualizar valor
        $content = preg_replace(
            '/^#?'.preg_quote($key, '/').'=.*/m',
            "{$key}={$value}",
            $content
        );

        // Si la clave no existía en el archivo, agregarla al final
        if (! preg_match('/^'.preg_quote($key, '/').'=/m', $content)) {
            $content .= "\n{$key}={$value}";
        }

        return $content;
    }

    private function showList(): int
    {
        $current = env('TENANT_SLUG', '—');

        $known = array_merge(
            array_keys($this->dbMap),
            ['epq', 'sfq', 'iess', 'montecristo', 'nssc', 'caixalsf', 'caiaxalsf', 'sanfranciscoasis', 'institutoramallo'],
        );
        $known = array_unique($known);
        sort($known);

        $this->line('');
        $this->line('  <comment>Tenants conocidos:</comment>');

        foreach ($known as $slug) {
            $db = $this->dbMap[$slug] ?? "ia_{$slug}";
            $marker = $slug === $current ? ' ← activo' : '';
            $this->line("    <info>{$slug}</info>  (BD: {$db}){$marker}");
        }

        $this->line('');
        $this->line('  Para agregar más, editar el array $dbMap en SwitchTenantCommand.');
        $this->line('');

        return self::SUCCESS;
    }
}
