<?php

namespace App\Http\Controllers\Viajes;

use App\Http\Controllers\Controller;
use App\Support\Navegacion\MenuSecretariaPerfil;
use App\Support\PermisosIaCatalog;
use App\Support\Viajes\SalidaViajeDatos;
use App\Support\Viajes\SalidaViajeTcpdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class SalidaViajePdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(tienePermiso(PermisosIaCatalog::VIAJES_SALIDAS_EDUCATIVAS), 403);
        MenuSecretariaPerfil::abortSiNoViajesSalidasEducativas();

        @ini_set('memory_limit', '512M');
        set_time_limit(180);

        $uid = (string) (auth()->id() ?? '');
        $key = 'salida-viaje-pdf:'.$uid.':'.($request->ip() ?? '');
        if (RateLimiter::tooManyAttempts($key, 15)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $validated = $request->validate([
            'viaje' => ['required', 'integer', 'min:1'],
            'curso' => ['required', 'integer', 'min:1'],
            'matriculas' => ['required', 'array', 'min:1', 'max:50'],
            'matriculas.*' => ['integer', 'min:1'],
        ]);

        $viaje = SalidaViajeDatos::viajeParaPdf((int) $validated['viaje']);
        if ($viaje === null) {
            abort(404);
        }

        $cursoId = (int) $validated['curso'];
        $ids = array_map('intval', $validated['matriculas']);

        $alumnos = SalidaViajeDatos::alumnosParaPdf($viaje, $ids, $cursoId);
        if ($alumnos === []) {
            abort(404);
        }

        $cantidad = count($alumnos);
        $slug = Str::slug('salida-educativa-'.$cantidad.'-alumnos', '_');
        if ($slug === '') {
            $slug = 'salida-educativa';
        }

        $pdf = SalidaViajeTcpdf::generarLote($viaje, $alumnos);

        return SalidaViajeTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
