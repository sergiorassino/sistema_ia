<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Support\Alumnos\PortalFamiliaDashboard;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $ctx = studentCtx();

        return view('alumnos.dashboard', [
            'nombreEstudiante' => PortalFamiliaDashboard::nombreEstudiante(),
            'nombreInstitucion' => PortalFamiliaDashboard::nombreInstitucion(),
            'heroLogo' => studentLogoUrl() ?: asset('img/3.png'),
            'ctx' => $ctx,
            'datosSesion' => PortalFamiliaDashboard::datosSesion(),
            'accesos' => PortalFamiliaDashboard::accesosRapidos(),
            'widgets' => PortalFamiliaDashboard::widgets(),
        ]);
    }
}
