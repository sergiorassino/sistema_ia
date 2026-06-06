<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ExamenesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/examenes'), 'examenes');
    }
}
