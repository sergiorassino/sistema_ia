<?php

namespace App\Http\Controllers\Examenes;

use App\Http\Controllers\Controller;
use App\Support\Examenes\MateriasAdeudadasPreparacion;
use App\Support\Examenes\PermisoExamen;
use App\Support\Examenes\PermisoExamenTcpdf;
use App\Support\Examenes\PermisoExamenPdfPedido;
use App\Support\SchoolContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class PermisoExamenPdfController extends Controller
{
    /**
     * Genera el PDF en la misma petición POST (evita redirect y salida previa).
     */
    public function preparar(Request $request): Response|RedirectResponse
    {
        abort_unless(tienePermiso(12), 403, 'Sin permiso para el módulo de exámenes.');

        $validated = $request->validate([
            'alumnos' => ['required', 'array', 'min:1', 'max:500'],
            'alumnos.*' => ['integer', 'min:1'],
            'numero' => ['required', 'integer', 'min:1', 'max:99999'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ]);

        $ctx = schoolCtx();
        if (! $ctx->isValid()
            || ! MateriasAdeudadasPreparacion::visitaConfirmadaEnSesion(
                MateriasAdeudadasPreparacion::MODULO_PERMISO_EXAMEN,
            )) {
            return redirect()
                ->route('examenes.permiso-examen')
                ->with('status', 'Seleccioná el turno y el año lectivo y recalculá las condiciones antes de generar el PDF.');
        }

        $idNivel = (int) $ctx->idNivel;
        $permitidos = PermisoExamen::estudiantes($idNivel);
        $ids = PermisoExamen::filtrarIdsPermitidos($validated['alumnos'], $permitidos);

        if ($ids === []) {
            return redirect()
                ->route('examenes.permiso-examen')
                ->with('status', 'No hay alumnos válidos seleccionados para imprimir.');
        }

        return $this->responderPdf(
            $request,
            $idNivel,
            $ids,
            (int) $validated['numero'],
            (string) $validated['fecha'],
        );
    }

    public function __invoke(Request $request): Response|RedirectResponse
    {
        abort_unless(tienePermiso(12), 403, 'Sin permiso para el módulo de exámenes.');

        $ctx = schoolCtx();
        if (! $ctx->isValid()
            || ! MateriasAdeudadasPreparacion::visitaConfirmadaEnSesion(
                MateriasAdeudadasPreparacion::MODULO_PERMISO_EXAMEN,
            )) {
            return redirect()
                ->route('examenes.permiso-examen')
                ->with('status', 'Seleccioná el turno y el año lectivo y recalculá las condiciones antes de generar el PDF.');
        }

        $idNivel = (int) $ctx->idNivel;
        if ($idNivel < 1) {
            abort(404);
        }

        $pedido = $this->resolverPedido($request, $idNivel);
        if ($pedido === null) {
            return redirect()
                ->route('examenes.permiso-examen')
                ->with('status', 'El pedido de impresión expiró o no es válido. Volvé a seleccionar los alumnos.');
        }

        $permitidos = PermisoExamen::estudiantes($idNivel);
        $ids = PermisoExamen::filtrarIdsPermitidos($pedido['ids'], $permitidos);
        if ($ids === []) {
            abort(404);
        }

        return $this->responderPdf(
            $request,
            $idNivel,
            $ids,
            (int) $pedido['numero'],
            (string) $pedido['fecha'],
        );
    }

    /**
     * @param  list<int>  $ids
     */
    private function responderPdf(
        Request $request,
        int $idNivel,
        array $ids,
        int $numeroPermisoInicio,
        string $fechaYmd,
    ): Response|RedirectResponse {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $key = 'permiso-examen-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 15)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 120);

        $ctx = schoolCtx();
        $meta = $this->metaPdf($ctx, $fechaYmd);

        $pdf = PermisoExamenTcpdf::generar($idNivel, $ids, $numeroPermisoInicio, $meta);
        if ($pdf->paginasGeneradas() < 1) {
            abort(404);
        }

        $cant = $pdf->paginasGeneradas();
        $slug = $cant === 1
            ? 'permiso-examen'
            : Str::slug('permisos-examen-'.$cant.'-alumnos', '_');
        if ($slug === '') {
            $slug = 'permiso_examen';
        }

        $this->limpiarBuffersSalida();

        // TCPDF: Output(nombre, destino). 'S' = devolver string (no enviar al navegador desde TCPDF).
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

    /**
     * @return array{ids: list<int>, numero: int, fecha: string}|null
     */
    private function resolverPedido(Request $request, int $idNivel): ?array
    {
        $token = trim((string) $request->query('token', ''));
        if ($token !== '') {
            return PermisoExamenPdfPedido::consumir($token, $idNivel);
        }

        $alumnosCsv = trim((string) $request->query('alumnos', ''));
        if ($alumnosCsv === '') {
            return null;
        }

        $permitidos = PermisoExamen::estudiantes($idNivel);
        $ids = PermisoExamen::resolverIdsAlumnos($alumnosCsv, $permitidos);
        if ($ids === []) {
            return null;
        }

        $numero = (int) $request->query('numero', 1);
        $fecha = trim((string) $request->query('fecha', ''));
        if ($fecha === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return null;
        }

        return [
            'ids' => $ids,
            'numero' => max(1, $numero),
            'fecha' => $fecha,
        ];
    }

    /**
     * @return array{
     *     instiNombre: string,
     *     etiquetaTurno: string,
     *     pieLugarFecha: string
     * }
     */
    private function metaPdf(SchoolContext $ctx, string $fechaYmd): array
    {
        $datosPrep = MateriasAdeudadasPreparacion::datosConfirmadosParaRestaurar(
            $ctx,
            MateriasAdeudadasPreparacion::MODULO_PERMISO_EXAMEN,
        );
        $etiquetaTurno = $datosPrep !== null
            ? PermisoExamen::etiquetaTurnoExamen(
                $datosPrep['idTurno'],
                MateriasAdeudadasPreparacion::anoTerlec($datosPrep['idTerlec']),
            )
            : 'Turno de examen';

        $fechaLugar = Carbon::createFromFormat('Y-m-d', $fechaYmd)->format('d/m/Y');

        $header = schoolPdfHeaderData();
        $instiNombre = trim((string) ($header['insti'] ?? ''));
        if ($instiNombre === '') {
            $instiNombre = 'Institución educativa';
        }

        $localidad = trim((string) ($header['localidad'] ?? ''));
        $pieLugarFecha = $localidad !== ''
            ? $localidad.', '.$fechaLugar
            : $fechaLugar;

        return [
            'instiNombre' => mb_strtoupper($instiNombre, 'UTF-8'),
            'etiquetaTurno' => $etiquetaTurno,
            'pieLugarFecha' => $pieLugarFecha,
        ];
    }
}
