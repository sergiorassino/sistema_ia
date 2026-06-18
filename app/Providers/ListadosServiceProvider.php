<?php

namespace App\Providers;

use App\Livewire\Listados\ListadoDocentes;
use App\Livewire\Listados\ListadoEstudiantesFormato;
use App\Livewire\Listados\ListadoPorCurso;
use App\Livewire\Parametrizacion\CamposLegajoIndex;
use App\Livewire\Parametrizacion\CamposProfesorIndex;
use App\Livewire\Parametrizacion\SolapaLegajoIndex;
use App\Livewire\Parametrizacion\SolapaLegajoProfesorIndex;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class ListadosServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/listados'), 'listados');

        Livewire::component('listados.por-curso', ListadoPorCurso::class);
        Livewire::component('listados.estudiantes-formato', ListadoEstudiantesFormato::class);
        Livewire::component('listados.docentes', ListadoDocentes::class);
        Livewire::component('listados.parametrizacion.campos-legajo', CamposLegajoIndex::class);
        Livewire::component('listados.parametrizacion.solapas-legajo', SolapaLegajoIndex::class);
        Livewire::component('listados.parametrizacion.campos-legajo-profesor', CamposProfesorIndex::class);
        Livewire::component('listados.parametrizacion.solapas-legajo-profesor', SolapaLegajoProfesorIndex::class);
    }
}
