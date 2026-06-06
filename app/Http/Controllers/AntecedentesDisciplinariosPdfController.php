<?php

namespace App\Http\Controllers;

use App\Support\PermisosIaCatalog;
use App\Models\Matricula;
use App\Models\Sancion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AntecedentesDisciplinariosPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(tienePermiso(PermisosIaCatalog::SEGUIMIENTO_DISCIPLINARIO), 403, 'Sin permiso para seguimiento disciplinario.');

        $validated = $request->validate([
            'matricula' => ['required', 'integer', 'min:1'],
        ]);
        $idMatricula = (int) $validated['matricula'];

        $key = 'antecedentes-disciplinarios-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $ctx = schoolCtx();

        /** @var Matricula $base */
        $base = Matricula::query()
            ->with(['legajo', 'curso'])
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->findOrFail($idMatricula);

        $inst = DB::table('ento')
            ->where('idNivel', (int) $ctx->idNivel)
            ->first(['insti']);

        $nombreInstitucion = trim((string) ($inst->insti ?? ''));
        if ($nombreInstitucion === '') {
            $nombreInstitucion = 'Institución';
        }

        $alumno = $base->legajo;
        $alumnoLinea = trim((string) ($alumno?->apellido ?? '').' '.(string) ($alumno?->nombre ?? ''));
        $dni = (string) ($alumno?->dni ?? '');
        $cursoLabel = $base->curso?->nombreParaListado() ?? '';

        $generadoEn = now()->format('d/m/Y H:i');
        $emitidoAl = now()->format('d/m/Y');

        $sanciones = Sancion::query()
            ->with(['tipo', 'profesor'])
            ->join('matricula', 'matricula.id', '=', 'sanciones.idMatricula')
            ->where('matricula.idLegajos', (int) $base->idLegajos)
            ->where('matricula.idNivel', (int) $ctx->idNivel)
            ->orderBy('sanciones.fecha')
            ->orderBy('sanciones.id')
            ->select('sanciones.*')
            ->get();

        $slug = Str::slug('antecedentes-disciplinarios-'.$alumnoLinea, '_');
        if ($slug === '') {
            $slug = 'antecedentes_disciplinarios';
        }

        $pdf = Pdf::loadView('pdf.antecedentes-disciplinarios', [
            'nombreInstitucion' => $nombreInstitucion,
            'alumnoLinea' => $alumnoLinea,
            'dni' => $dni,
            'cursoLabel' => $cursoLabel,
            'generadoEn' => $generadoEn,
            'emitidoAl' => $emitidoAl,
            'sanciones' => $sanciones,
            'pdfHeader' => schoolPdfHeaderData(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream($slug.'.pdf');
    }
}

