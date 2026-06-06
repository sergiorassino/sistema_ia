<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Mergea `config/tenants/{TENANT_SLUG}.php` sobre `config/tenant.php` (solo datos, versionado en git).
 *
 * Cada colegio puede tener un archivo con las claves que difieran del default; para uno nuevo,
 * suele bastar copiar el archivo del cliente más parecido y ajustar valores.
 */
class TenantConfigMergeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Usar config cacheada (config/tenant.php); env() en producción con config:cache suele devolver null.
        $slug = trim((string) config('tenant.slug', ''));
        if ($slug === '') {
            $slug = 'default';
        }

        $tenantFile = config_path("tenants/{$slug}.php");

        if (file_exists($tenantFile)) {
            /** @var array<string, mixed> $overrides */
            $overrides = require $tenantFile;

            config([
                'tenant' => array_replace_recursive(
                    config('tenant', []),
                    $overrides,
                    ['slug' => $slug],
                ),
            ]);
        } else {
            config(['tenant.slug' => $slug]);
        }
    }
}
