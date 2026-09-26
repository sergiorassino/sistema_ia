<?php

namespace App\Http\Controllers\ProyectosExtracurriculares;

use App\Http\Controllers\Controller;
use App\Models\ExtActividad;
use App\Support\Navegacion\MenuSecretariaPerfil;
use App\Support\PermisosIaCatalog;
use App\Support\ProyectosExtracurriculares\AutorizacionActividadDatos;
use App\Support\ProyectosExtracurriculares\AutorizacionActividadTcpdf;
use App\Support\ProyectosExtracurriculares\ExtActividadesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AutorizacionActividadPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(MenuSecretariaPerfil::muestraProyectosExtracurriculares(), 403);
        abort_unless(tienePermiso(PermisosIaCatalog::PROYECTOS_EXTRACURRICULARES_DOCUMENTOS), 403);
        abort_unless(ExtActividadesService::tablasDisponibles(), 404);

        @ini_set('memory_limit', '512M');
        set_time_limit(180);

        $uid = (string) (auth()->id() ?? '');
        $key = 'ext-autorizacion-pdf:'.$uid.':'.($request->ip() ?? '');
        if (RateLimiter::tooManyAttempts($key, 12)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $validated = $request->validate([
            'actividad' => ['required', 'integer', 'min:1'],
            'matriculas' => ['required', 'array', 'min:1', 'max:200'],
            'matriculas.*' => ['integer', 'min:1'],
        ]);

        $actividad = ExtActividadesService::scopedQuery()
            ->whereKey((int) $validated['actividad'])
            ->where('estado', ExtActividad::ESTADO_APROBADO)
            ->first();
        if ($actividad === null) {
            abort(404);
        }

        $alumnos = AutorizacionActividadDatos::alumnosParaPdf(
            $actividad,
            array_map('intval', $validated['matriculas'])
        );
        if ($alumnos === []) {
            abort(404);
        }

        $viaje = AutorizacionActividadDatos::viajeParaPdf($actividad);
        $pdf = AutorizacionActividadTcpdf::generarLote(
            AutorizacionActividadDatos::encabezadoInstitucional(),
            $viaje,
            $alumnos
        );

        $slug = Str::slug(Str::limit($viaje['nombre'] !== '' ? $viaje['nombre'] : 'actividad', 48, ''));
        if ($slug === '') {
            $slug = 'actividad';
        }

        return AutorizacionActividadTcpdf::respuestaHttp($pdf, 'autorizaciones-'.$slug.'.pdf');
    }
}
