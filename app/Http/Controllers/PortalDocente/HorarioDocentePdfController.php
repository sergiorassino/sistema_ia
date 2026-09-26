<?php

namespace App\Http\Controllers\PortalDocente;

use App\Http\Controllers\Controller;
use App\Support\HorariosProfesores;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * PDF del horario del docente de la sesión (horarios26). Sin IDs en la URL.
 */
class HorarioDocentePdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(tenantPortalDocenteHorarioHabilitado(), 404);

        $idProfesor = (int) (schoolCtx()->idProfesor ?? 0);
        abort_unless($idProfesor > 0, 403);

        $key = 'portal-docente-horario-pdf:'.$idProfesor;
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        @ini_set('memory_limit', '384M');

        $ctx = schoolCtx();
        $idNivel = (int) ($ctx->idNivel ?? 0);
        $idTerlec = (int) ($ctx->idTerlec ?? 0);

        $prof = DB::table('profesores')
            ->where('id', $idProfesor)
            ->first(['id', 'apellido', 'nombre']);

        if ($prof === null) {
            abort(403);
        }

        $tieneAsignacion = DB::table('ppc')
            ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
            ->where('ppc.idProfesor', $idProfesor)
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->exists();

        if (! $tieneAsignacion) {
            return response()->view('errors.portal-docente-pdf', [
                'mensaje' => 'No hay materias asignadas en este ciclo lectivo. Contacte a secretaría.',
            ], 422);
        }

        HorariosProfesores::modoImpresionPdfMasivaProfesores(true);

        try {
            $nombre = trim(((string) $prof->apellido).', '.((string) $prof->nombre));
            $titulo = 'Horario docente — '.$nombre;
            $subtitulo = $ctx->nivelNombre().' · Ciclo '.$ctx->terlecAno();
            $horasTotal = HorariosProfesores::contarHorasCatedraProfesor($idProfesor);
            $turnos = HorariosProfesores::turnosParaImpresionProfesor($idProfesor);

            $paginas = [];
            foreach ($turnos as $idTurnoClase) {
                $paginas[] = [
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'tituloTurno' => HorariosProfesores::nombreTurnoClase((int) $idTurnoClase),
                    'horasTotal' => $horasTotal,
                    'grilla' => HorariosProfesores::grillaProfesorParaImpresion($idProfesor, (int) $idTurnoClase),
                ];
            }

            if ($paginas === []) {
                return response()->view('errors.portal-docente-pdf', [
                    'mensaje' => 'Todavía no hay un horario cargado para este ciclo. Contacte a secretaría.',
                ], 422);
            }

            $slug = Str::slug($titulo, '_') ?: 'horario_docente';

            $pdf = Pdf::loadView('pdf.horario-grid', [
                'pdfHeader' => schoolPdfHeaderData(),
                'titulo' => $titulo,
                'subtitulo' => $subtitulo,
                'paginas' => $paginas,
            ])->setPaper('a4', 'landscape');

            unset($paginas);

            return $pdf->stream($slug.'.pdf');
        } finally {
            HorariosProfesores::modoImpresionPdfMasivaProfesores(false);
            HorariosProfesores::limpiarCachesRequestHorarios();
        }
    }
}
