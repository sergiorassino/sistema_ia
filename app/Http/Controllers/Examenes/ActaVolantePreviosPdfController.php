<?php

namespace App\Http\Controllers\Examenes;

use App\Http\Controllers\Controller;
use App\Support\Examenes\ActaVolantePrevios;
use App\Support\Examenes\ActaVolantePreviosTcpdf;
use App\Support\Examenes\MateriasAdeudadasPreparacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ActaVolantePreviosPdfController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        abort_unless(tienePermiso(12), 403, 'Sin permiso para el módulo de exámenes.');

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $key = 'acta-volante-previos-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $validated = Validator::make(
            ['actas' => $request->query('actas')],
            ['actas' => ['required', 'string', 'max:16000']],
        )->validate();

        $ctx = schoolCtx();
        if (! $ctx->isValid() || ! MateriasAdeudadasPreparacion::visitaConfirmadaEnSesion(MateriasAdeudadasPreparacion::MODULO_ACTA_VOLANTE)) {
            return redirect()
                ->route('examenes.acta-volante-previos')
                ->with('status', 'Seleccioná el turno y el año lectivo y recalculá las condiciones antes de generar el PDF.');
        }

        $idNivel = (int) $ctx->idNivel;
        if ($idNivel < 1) {
            abort(404);
        }

        $permitidas = ActaVolantePrevios::actasPendientes($idNivel);
        if ($permitidas->isEmpty()) {
            abort(404);
        }

        $claves = ActaVolantePrevios::resolverClavesActas(
            trim((string) $validated['actas']),
            $permitidas,
        );
        if ($claves === []) {
            abort(404);
        }

        $payload = ActaVolantePrevios::build($idNivel, $claves);
        if ($payload['actas'] === []) {
            abort(404);
        }

        $cantActas = count($payload['actas']);
        if ($cantActas === 1) {
            $a = $payload['actas'][0];
            $slug = Str::slug('acta-volante-'.($a['materiaLabel'] ?? '').'-'.($a['cursoLabel'] ?? ''), '_');
        } else {
            $slug = Str::slug('actas-volantes-examen-'.$cantActas.'-actas', '_');
        }
        if ($slug === '') {
            $slug = 'acta_volante_examen';
        }

        $header = schoolPdfHeaderData();
        $instiNombre = trim((string) ($header['insti'] ?? ''));
        if ($instiNombre === '') {
            $instiNombre = 'Institución educativa';
        }

        $pdf = ActaVolantePreviosTcpdf::generar(
            $payload['actas'],
            [
                'instiNombre' => mb_strtoupper($instiNombre, 'UTF-8'),
                'tituloCajaActa' => 'Acta Volante de Exámenes',
            ],
            ActaVolantePrevios::FILAS_POR_ACTA,
        );

        if ($pdf->paginasGeneradas() < 1) {
            abort(404);
        }

        $this->limpiarBuffersSalida();

        $binario = $pdf->Output($slug.'.pdf', 'S');

        return response($binario, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$slug.'.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function limpiarBuffersSalida(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}
