<?php

namespace App\Http\Controllers\Cuotas;

use App\Http\Controllers\Controller;
use App\Support\Cuotas\GeneracionMasivaCuotasConsulta;
use App\Support\Cuotas\LibroArancelesDatos;
use App\Support\Cuotas\LibroArancelesTcpdf;
use App\Support\Listados\ListadoCursoExportParams;
use App\Support\PermisosCuotas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * PDF «Libro de aranceles» — Gestión de aranceles (Administración).
 */
class LibroArancelesPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(PermisosCuotas::puedeLibroAranceles(), 403);

        @ini_set('memory_limit', '512M');

        $key = 'cuotas-libro-aranceles-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $validated = Validator::make($request->query(), [
            'cursos' => ['required', 'string', 'max:8000'],
            'pagina' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ], [
            'cursos.required' => 'Seleccione al menos un curso.',
            'pagina.min' => 'El número de página inicial debe ser al menos 1.',
        ])->validate();

        $cursosPermitidos = GeneracionMasivaCuotasConsulta::cursosEnContexto();
        if ($cursosPermitidos->isEmpty()) {
            abort(404);
        }

        $allowedById = $cursosPermitidos->keyBy(fn ($c) => (int) $c->Id);
        $cursoIds = ListadoCursoExportParams::resolverIdsCursos(trim((string) $validated['cursos']), $allowedById);
        if ($cursoIds === []) {
            abort(404);
        }

        $paginaInicial = (int) ($validated['pagina'] ?? 1);

        $datos = LibroArancelesDatos::build($cursoIds, $paginaInicial);
        if ($datos === null) {
            abort(404);
        }

        $ano = (int) ($datos['ano'] ?? schoolCtx()->terlecAno());
        $slug = Str::slug('libro-aranceles-'.$ano.'-'.count($cursoIds).'-cursos', '_');
        if ($slug === '') {
            $slug = 'libro_aranceles';
        }

        $pdf = LibroArancelesTcpdf::generar($datos);

        return LibroArancelesTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }
}
