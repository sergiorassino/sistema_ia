<?php

namespace App\Http\Controllers\CalificacionesSecundario;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Support\ActaVolanteColoquiosSecundario;
use App\Support\CalificacionesColoquioSecundario;
use App\Support\PermisosIaCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ActaVolanteColoquiosPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(
            tienePermiso(PermisosIaCatalog::CALIF_ACTAS_VOLANTES_COLOQUIO),
            403,
            'Sin permiso para actas volantes de coloquio.',
        );

        @ini_set('memory_limit', '512M');

        $key = 'acta-volante-coloquios-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $materiasInput = $request->query('materias');
        if (($materiasInput === null || $materiasInput === '') && $request->filled('materia')) {
            $materiasInput = (string) (int) $request->query('materia');
        }

        $validated = Validator::make(
            [
                'periodo' => $request->query('periodo'),
                'curso' => $request->query('curso'),
                'materias' => $materiasInput,
            ],
            [
                'periodo' => ['nullable', 'string', 'in:dic,feb'],
                'curso' => ['required', 'integer', 'min:1'],
                'materias' => ['required', 'string', 'max:8000'],
            ],
        )->validate();

        $periodo = CalificacionesColoquioSecundario::normalizarPeriodo(
            is_string($validated['periodo'] ?? null) ? $validated['periodo'] : null,
        );

        $ctx = schoolCtx();
        $cursoId = (int) $validated['curso'];

        $cursoOk = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->where('Id', $cursoId)
            ->exists();

        if (! $cursoOk) {
            abort(404);
        }

        $materiasPermitidas = ActaVolanteColoquiosSecundario::materiasConAlumnosElegibles($cursoId, $periodo);
        if ($materiasPermitidas->isEmpty()) {
            abort(404);
        }

        $materiaIds = ActaVolanteColoquiosSecundario::resolverIdsMaterias(
            trim((string) $validated['materias']),
            $materiasPermitidas,
        );
        if ($materiaIds === []) {
            abort(404);
        }

        $payload = ActaVolanteColoquiosSecundario::build($periodo, $cursoId, $materiaIds);
        if ($payload['actas'] === []) {
            abort(404);
        }

        $cantActas = count($payload['actas']);
        if ($cantActas === 1) {
            $a = $payload['actas'][0];
            $slug = Str::slug('acta-volante-'.($a['materiaLabel'] ?? '').'-'.($a['cursoLabel'] ?? ''), '_');
        } else {
            $slug = Str::slug('actas-volantes-coloquio-'.$cantActas.'-materias', '_');
        }
        if ($slug === '') {
            $slug = 'acta_volante_coloquios';
        }

        $header = schoolPdfHeaderData();
        $instiNombre = trim((string) ($header['insti'] ?? ''));
        if ($instiNombre === '') {
            $instiNombre = 'Institución educativa';
        }

        $pdf = Pdf::loadView('pdf.acta-volante-coloquios', [
            'instiNombre' => mb_strtoupper($instiNombre, 'UTF-8'),
            'condicionLabel' => $payload['condicionLabel'],
            'actas' => $payload['actas'],
            'filasPorActa' => ActaVolanteColoquiosSecundario::FILAS_POR_ACTA,
        ])->setPaper('a4', 'portrait');

        $response = $pdf->stream($slug.'.pdf');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
