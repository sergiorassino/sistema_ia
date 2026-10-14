<?php

namespace App\Http\Controllers\Horarios;

use App\Http\Controllers\Controller;
use App\Support\Horarios\ListadoProfesoresHorariosConsulta;
use App\Support\Horarios\ListadoProfesoresHorariosTcpdf;
use App\Support\PermisosIaCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ListadoProfesoresHorariosPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(tienePermiso(PermisosIaCatalog::LISTADO_PROFESORES_HORARIOS), 403);

        $key = 'listado-profesores-horarios-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $datos = ListadoProfesoresHorariosConsulta::consultar();
        if (! ($datos['ok'] ?? false)) {
            abort(404);
        }

        $ctx = schoolCtx();
        $subtitulo = trim($ctx->nivelNombre().' · Ciclo '.$ctx->terlecAno());
        $pdf = ListadoProfesoresHorariosTcpdf::generar($datos, schoolPdfHeaderData(), $subtitulo);

        return ListadoProfesoresHorariosTcpdf::respuestaHttp($pdf, 'listado_profesores_horarios.pdf');
    }
}
