<?php

namespace App\Http\Controllers;

use App\Support\PermisosIaCatalog;
use App\Support\Tea\ReincoTea;
use App\Support\Tea\TeaRegistroPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TeaRegistroPdfController extends Controller
{
    public function __invoke(Request $request, int $id): Response
    {
        abort_unless(
            tienePermiso(PermisosIaCatalog::TEA_ESTUDIANTES_GESTION),
            403,
            'Sin permiso para gestión de TEA de estudiantes.'
        );

        abort_unless(ReincoTea::tablasDisponibles(), 404);

        $key = 'tea-registro-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $registro = ReincoTea::registroEnContexto($id);
        $idTipo = (int) $registro->idReinco_tipo;

        abort_unless(tenantTeaRegistroPdfDisponible($idTipo), 404, 'Plantilla PDF no configurada para esta situación TEA.');

        $pdf = TeaRegistroPdf::generar($registro);

        $matricula = $registro->matricula;
        $slug = Str::slug(
            'tea-'.($matricula?->legajo?->apellido ?? 'estudiante').'-'.($registro->fecha?->format('Y-m-d') ?? 'registro'),
            '_'
        );
        if ($slug === '') {
            $slug = 'registro_tea';
        }

        return TeaRegistroPdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
