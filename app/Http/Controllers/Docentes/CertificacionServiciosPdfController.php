<?php

namespace App\Http\Controllers\Docentes;

use App\Http\Controllers\Controller;
use App\Support\CertificacionServicios\CertificacionServicios;
use App\Support\CertificacionServicios\CertificacionServiciosTcpdf;
use App\Support\Pdf\PdfPostEntrega;
use App\Support\PermisosIaCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CertificacionServiciosPdfController extends Controller
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless(tienePermiso(PermisosIaCatalog::CERTIFICACION_SERVICIOS), 403, 'Sin permiso para certificación de servicios.');

        if (! CertificacionServicios::tablasDisponibles()) {
            abort(404, CertificacionServicios::mensajeTablasFaltantes());
        }

        $validator = Validator::make($request->all(), [
            'idPersonal' => ['required', 'integer', 'min:1'],
            'fechaEmision' => ['required', 'date'],
            'paraPresentar' => ['nullable', 'string', 'max:300'],
        ]);

        if ($validator->fails()) {
            abort(422, 'Datos del certificado incompletos o inválidos.');
        }

        $validated = $validator->validated();
        $idPersonal = (int) $validated['idPersonal'];

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $key = 'cert-servicios-pdf:'.(auth()->id() ?? $request->ip()).':'.$idPersonal;
        if (RateLimiter::tooManyAttempts($key, 40)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        try {
            $datos = CertificacionServicios::armarDatosPdf(
                $idPersonal,
                (string) $validated['fechaEmision'],
                (string) ($validated['paraPresentar'] ?? '')
            );
        } catch (ValidationException $e) {
            abort(422, collect($e->errors())->flatten()->first() ?: 'No se puede emitir el PDF.');
        }

        $pdf = CertificacionServiciosTcpdf::generar($datos);
        $nombreArchivo = CertificacionServicios::nombreArchivoPdf(
            (string) ($datos['profesorNombre'] ?? 'docente'),
            ''
        );

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $binario = $pdf->Output($nombreArchivo, 'S');

        return PdfPostEntrega::respuesta($binario, $nombreArchivo, $request);
    }
}
