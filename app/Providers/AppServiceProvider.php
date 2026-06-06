<?php

namespace App\Providers;

use App\Auth\AlumnoUserProvider;
use App\Auth\ProfesorUserProvider;
use App\Support\SchoolContext;
use App\Support\StudentContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SchoolContext::class, function ($app) {
            return SchoolContext::fromSession();
        });

        $this->app->singleton(StudentContext::class, function ($app) {
            return StudentContext::fromSession();
        });
    }

    public function boot(): void
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $appPath = rtrim((string) (parse_url($appUrl, PHP_URL_PATH) ?: ''), '/');

        if ($appPath !== '') {
            Config::set('session.path', $appPath);

            if (! config('app.asset_url')) {
                Config::set('app.asset_url', $appUrl);
            }

            // Logos y archivos públicos: /ia/colegio/storage/... (no dominio.com/storage)
            Config::set('filesystems.disks.public.url', $appUrl.'/storage');

            // Ruta Laravel /livewire-{hash}/livewire.js (no public/vendor/…): LiteSpeed y .htaccess suelen devolver 403 en URLs con "vendor".
            if (! config('livewire.asset_url')) {
                $livewireJs = config('app.debug') ? 'livewire.js' : 'livewire.min.js';
                Config::set('livewire.asset_url', $appUrl.EndpointResolver::prefix().'/'.$livewireJs);
            }

            // Redirecciones y route() deben incluir /ia/25demayo (p. ej. dashboard, no dominio.com/dashboard).
            URL::forceRootUrl($appUrl);
            if (str_starts_with($appUrl, 'https://')) {
                URL::forceScheme('https');
            }

            // data-update-uri de Livewire: LivewireDeploymentScripts (evita prefijo duplicado en el tag script).
        }

        Auth::provider('profesor', function ($app, array $config) {
            return new ProfesorUserProvider();
        });

        Auth::provider('alumno', function ($app, array $config) {
            return new AlumnoUserProvider();
        });

        Paginator::defaultView('vendor.pagination.se');
    }
}
