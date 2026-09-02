<?php

namespace App\Http\Controllers;

use App\Support\PermisosIaCatalog;
use App\Models\Sancion;
use App\Support\Seguimiento\ResumenComunicadoSancion;
use App\Support\Seguimiento\SancionActaHtmlSanitizer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SancionComunicadoPdfController extends Controller
{
    public function __invoke(Request $request, int $id)
    {
        abort_unless(tienePermiso(PermisosIaCatalog::SEGUIMIENTO_DISCIPLINARIO), 403, 'Sin permiso para seguimiento disciplinario.');

        $key = 'sancion-comunicado-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $validated = Validator::make(['id' => $id], [
            'id' => ['required', 'integer', 'min:1'],
        ]);
        if ($validated->fails()) {
            abort(404);
        }

        $ctx = schoolCtx();

        /** @var Sancion $sancion */
        $sancion = Sancion::query()
            ->with(['tipo', 'profesor', 'matricula.legajo', 'matricula.curso'])
            ->findOrFail($id);

        // Seguridad: acotar por contexto actual (año/nivel), usando matrícula
        if ((int) ($sancion->matricula?->idNivel ?? 0) !== (int) $ctx->idNivel
            || (int) ($sancion->matricula?->idTerlec ?? 0) !== (int) $ctx->idTerlec) {
            abort(404);
        }

        if (! $sancion->tipo?->permiteComunicadoPdf()) {
            abort(404);
        }

        $inst = DB::table('ento')
            ->where('idNivel', (int) $ctx->idNivel)
            ->first(['insti', 'localidad']);

        $nombreInstitucion = trim((string) ($inst->insti ?? ''));
        if ($nombreInstitucion === '') {
            $nombreInstitucion = 'Institución';
        }

        $localidad = trim((string) ($inst->localidad ?? ''));

        $legajo = $sancion->matricula?->legajo;
        $curso = $sancion->matricula?->curso;

        $alumnoNombre = trim((string) ($legajo?->apellido ?? '').' '.(string) ($legajo?->nombre ?? ''));
        $cursoLabel = $curso?->nombreParaListado() ?? '';

        $fecha = $sancion->fecha?->format('d/m/Y') ?? now()->format('d/m/Y');
        $lineaLugarFecha = $localidad !== '' ? "{$localidad}, {$fecha}" : $fecha;

        $motivo = trim((string) ($sancion->motivo ?? ''));
        $motivo = $motivo !== '' ? $motivo : '—';

        $tipoSancion = trim((string) ($sancion->tipo?->tipo ?? ''));
        if ($tipoSancion === '') {
            $tipoSancion = 'Sanción';
        }

        $cantidad = $sancion->cantidad ?? 1;
        $tipoSancionEtiqueta = ResumenComunicadoSancion::etiquetaSegunCantidad($tipoSancion, (int) $cantidad);

        $solicitadaPor = trim((string) ($sancion->solipor ?? ''));
        if ($solicitadaPor === '') {
            $solicitadaPor = $sancion->profesor?->nombre_completo ?? '';
        }
        // Quitar prefijo "Prof." / "Prof," / "Prof " (no tocar "Profesor/a…").
        $solicitadaPor = trim((string) preg_replace('/^prof[.,]?\s+/iu', '', $solicitadaPor));

        // Corte = fecha de esta sanción (inclusive). Una reimpresión no suma las posteriores.
        // Troquel 1 (solicitud): antecedentes a esa fecha, sin esta sanción.
        // Troquel 2 (notificación): totales a esa fecha, ya incluyendo esta sanción.
        $idMatricula = (int) $sancion->idMatricula;
        $idSancion = (int) $sancion->id;
        $hastaFecha = $sancion->fecha?->format('Y-m-d') ?? now()->format('Y-m-d');
        $lineasResumenSinActual = ResumenComunicadoSancion::lineas($idMatricula, $idSancion, $hastaFecha);
        $lineasResumenConActual = ResumenComunicadoSancion::lineas($idMatricula, null, $hastaFecha);

        $slug = Str::slug('comunicado-seguimiento-'.$alumnoNombre.'-'.$fecha, '_');
        if ($slug === '') {
            $slug = 'comunicado_seguimiento';
        }

        $actaHtml = SancionActaHtmlSanitizer::paraPdf($sancion->acta ?? null);

        $pdf = Pdf::loadView('pdf.sancion-comunicado', [
            'nombreInstitucion' => $nombreInstitucion,
            'alumnoNombre' => $alumnoNombre,
            'cursoLabel' => $cursoLabel,
            'lineaLugarFecha' => $lineaLugarFecha,
            'motivo' => $motivo,
            'solicitadaPor' => $solicitadaPor,
            'cantidad' => $cantidad,
            'tipoSancion' => $tipoSancionEtiqueta,
            'lineasResumenSinActual' => $lineasResumenSinActual,
            'lineasResumenConActual' => $lineasResumenConActual,
            'actaHtml' => $actaHtml,
            'pdfHeader' => schoolPdfHeaderData(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream($slug.'.pdf');
    }
}

