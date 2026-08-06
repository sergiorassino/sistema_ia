<?php

namespace App\Providers;

use App\Livewire\Alumnos\Comunicaciones\BandejaFamilia;
use App\Livewire\Alumnos\Comunicaciones\HiloShowFamilia;
use App\Livewire\Alumnos\Comunicaciones\NuevoComunicadoFamilia;
use App\Livewire\Comunicaciones\BandejaGestion;
use App\Livewire\Comunicaciones\BandejaRevision;
use App\Livewire\Comunicaciones\HiloShow;
use App\Livewire\Comunicaciones\InformeEnvioComunicado;
use App\Livewire\Comunicaciones\NuevoComunicado;
use App\Livewire\Parametrizacion\ComCanalesIndex;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class ComunicacionesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/comunicaciones.php'), 'comunicaciones');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/comunicaciones'), 'comunicaciones');

        Livewire::component('comunicaciones.bandeja-gestion', BandejaGestion::class);
        Livewire::component('comunicaciones.bandeja-revision', BandejaRevision::class);
        Livewire::component('comunicaciones.hilo-show', HiloShow::class);
        Livewire::component('comunicaciones.nuevo-comunicado', NuevoComunicado::class);
        Livewire::component('comunicaciones.informe-envio-comunicado', InformeEnvioComunicado::class);
        Livewire::component('alumnos.comunicaciones.bandeja-familia', BandejaFamilia::class);
        Livewire::component('alumnos.comunicaciones.hilo-show-familia', HiloShowFamilia::class);
        Livewire::component('alumnos.comunicaciones.nuevo-comunicado-familia', NuevoComunicadoFamilia::class);
        Livewire::component('parametrizacion.com-canales-index', ComCanalesIndex::class);
    }
}
