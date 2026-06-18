<?php

namespace App\Http\Controllers;

use App\Support\Alumnos\FichaMatriculaSecretariaLoteParams;
use App\Support\Alumnos\FichaMatriculaSecretariaZip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Fichas de matrícula en ZIP (secretaría, un PDF por alumno).
 */
class FichaMatriculaSecretariaZipController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(tenantSecretariaFichaMatriculaHabilitada(), 404);

        @ini_set('memory_limit', '512M');
        set_time_limit(180);

        $key = 'ficha-matricula-secretaria-zip:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $validated = $request->validate([
            'matriculas' => ['required', 'string', 'max:12000'],
        ]);

        $ids = FichaMatriculaSecretariaLoteParams::resolverIdsMatriculasDesdeQuery(
            (string) $validated['matriculas'],
        );

        if ($ids === []) {
            abort(404);
        }

        return FichaMatriculaSecretariaZip::respuestaHttp($ids);
    }
}
