<?php

namespace App\Http\Controllers\Docentes;

use App\Http\Controllers\Controller;
use App\Models\Ento;
use App\Support\InasistenciasDocentes;
use App\Support\InasistenciasDocentes\InformeInasistenciasDocenteTcpdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class InformeInasistenciasDocentePdfController extends Controller
{
    public function __invoke(Request $request, int $idProfesor, int $bimestre): Response
    {
        abort_unless(tienePermiso(InasistenciasDocentes::PERMISO_ORDEN), 403);
        abort_unless($bimestre >= 1 && $bimestre <= 6, 404);

        InasistenciasDocentes::profesorDelContexto($idProfesor);

        $anio = (int) $request->query('anio', InasistenciasDocentes::anoLectivo());

        $key = 'inasdoc-pdf:'.(auth()->id() ?? $request->ip()).":{$idProfesor}:{$bimestre}";
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429);
        }
        RateLimiter::hit($key, 60);

        $profesor = InasistenciasDocentes::profesorDelContexto($idProfesor);
        $institucion = Ento::query()->where('idNivel', schoolCtx()->idNivel)->value('insti') ?? config('app.name', 'Institución');

        $bin = InformeInasistenciasDocenteTcpdf::render($idProfesor, $bimestre, $anio, (string) $institucion);

        $bim = InasistenciasDocentes::BIMESTRES[$bimestre]['titulo'] ?? 'Bimestre';
        $nombre = 'InformeInasistencias_'.Str::slug($profesor->apellido.'_'.$profesor->nombre).'_'.$bim.'_'.$anio.'.pdf';

        return response($bin, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
        ]);
    }
}
