<?php

namespace App\Http\Controllers;

use App\Support\PermisosIaCatalog;
use App\Models\Sancion;
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

        $solicitadaPor = trim((string) ($sancion->solipor ?? ''));
        if ($solicitadaPor === '') {
            $solicitadaPor = $sancion->profesor?->nombre_completo ?? '';
        }
        if ($solicitadaPor !== '' && ! Str::startsWith(Str::lower($solicitadaPor), 'prof')) {
            $solicitadaPor = 'Prof. '.$solicitadaPor;
        }

        // Totales "hasta la fecha" (del año/matrícula actual)
        $totales = Sancion::query()
            ->join('sanciontipo', 'sanciontipo.id', '=', 'sanciones.idTipoSancion')
            ->where('sanciones.idMatricula', (int) $sancion->idMatricula)
            ->select([
                DB::raw('LOWER(COALESCE(sanciontipo.tipo, "")) as tipo_lower'),
                DB::raw('COALESCE(sanciones.cantidad, 1) as cantidad'),
            ])
            ->get();

        $totalApercib = (int) $totales
            ->filter(fn ($r) => is_string($r->tipo_lower) && str_contains($r->tipo_lower, 'apercib'))
            ->sum(fn ($r) => (int) $r->cantidad);

        $totalAmonest = (int) $totales
            ->filter(fn ($r) => is_string($r->tipo_lower) && str_contains($r->tipo_lower, 'amonest'))
            ->sum(fn ($r) => (int) $r->cantidad);

        $slug = Str::slug('comunicado-seguimiento-'.$alumnoNombre.'-'.$fecha, '_');
        if ($slug === '') {
            $slug = 'comunicado_seguimiento';
        }

        $pdf = Pdf::loadView('pdf.sancion-comunicado', [
            'nombreInstitucion' => $nombreInstitucion,
            'alumnoNombre' => $alumnoNombre,
            'cursoLabel' => $cursoLabel,
            'lineaLugarFecha' => $lineaLugarFecha,
            'motivo' => $motivo,
            'solicitadaPor' => $solicitadaPor,
            'cantidad' => $cantidad,
            'tipoSancion' => $tipoSancion,
            'totalApercib' => $totalApercib,
            'totalAmonest' => $totalAmonest,
            'pdfHeader' => schoolPdfHeaderData(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream($slug.'.pdf');
    }
}

