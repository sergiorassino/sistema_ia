<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Support\HorariosProfesores;
use App\Support\Listados\ListadoCursoExportParams;
use App\Support\ParteDiario\ParteDiarioSanfranciscoasisDatos;
use App\Support\ParteDiario\ParteDiarioSanfranciscoasisTcpdf;
use App\Support\PermisosIaCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Dompdf\Adapter\CPDF as DompdfCpdfAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ParteDiarioPreceptorPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(tienePermiso(PermisosIaCatalog::PARTE_DIARIO_PRECEPTOR), 403, 'Sin permiso para el parte diario del preceptor.');

        @ini_set('memory_limit', '512M');
        set_time_limit(120);

        $key = 'parte-diario-preceptor-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $cursosInput = $request->query('cursos');
        if (($cursosInput === null || $cursosInput === '') && $request->filled('curso')) {
            $cursosInput = (string) (int) $request->query('curso');
        }

        $validated = Validator::make(
            ['cursos' => $cursosInput],
            ['cursos' => ['required', 'string', 'max:8000']],
        )->validate();

        $ctx = schoolCtx();
        $cursosPermitidos = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);

        if ($cursosPermitidos->isEmpty()) {
            abort(404);
        }

        $allowedById = $cursosPermitidos->keyBy(fn (Curso $c) => (int) $c->Id);
        $cursoIds = ListadoCursoExportParams::resolverIdsCursos(trim((string) $validated['cursos']), $allowedById);
        if ($cursoIds === []) {
            abort(404);
        }

        $ordenados = [];
        foreach ($cursosPermitidos as $c) {
            $id = (int) $c->Id;
            if (in_array($id, $cursoIds, true)) {
                $ordenados[] = $c;
            }
        }

        $fechaRaw = trim((string) $request->query('fecha', ''));
        $fechaReferencia = null;
        if ($fechaRaw !== '') {
            try {
                $fechaReferencia = Carbon::createFromFormat('Y-m-d', $fechaRaw)->startOfDay();
            } catch (\Throwable) {
                $fechaReferencia = null;
            }
        }
        if ($fechaReferencia === null) {
            $fechaReferencia = Carbon::now()->startOfDay();
        }

        $turnoElegido = (int) $request->query('turnoElegido', 0);
        $turnoElegido = $turnoElegido > 0 ? $turnoElegido : null;
        if ($turnoElegido !== null && ! in_array($turnoElegido, HorariosProfesores::turnosActivos(), true)) {
            $turnoElegido = null;
        }

        if (tenantParteDiarioImplementacion() === 'sanfranciscoasis') {
            return $this->pdfSanfranciscoasis($ordenados, $fechaReferencia, $turnoElegido);
        }

        return $this->pdfEstandar($ordenados, $fechaReferencia, $turnoElegido);
    }

    /**
     * @param  list<Curso>  $ordenados
     */
    private function pdfSanfranciscoasis(array $ordenados, Carbon $fechaReferencia, ?int $turnoElegido)
    {
        $paginas = ParteDiarioSanfranciscoasisDatos::paginas($ordenados, $fechaReferencia, $turnoElegido);
        if ($paginas === []) {
            abort(404);
        }

        $nombreDia = HorariosProfesores::DIAS[(int) $fechaReferencia->dayOfWeekIso] ?? '';
        if (count($paginas) === 1) {
            $slug = Str::slug('parte-diario-'.$paginas[0]['cursoLabel'].'-'.$nombreDia, '_') ?: 'parte_diario_preceptor';
        } else {
            $slug = Str::slug('partes-diarios-'.count($paginas).'-cursos-'.$fechaReferencia->format('Y-m-d'), '_') ?: 'partes_diarios_preceptor';
        }

        $pdf = ParteDiarioSanfranciscoasisTcpdf::generar($paginas, schoolPdfHeaderData());

        return ParteDiarioSanfranciscoasisTcpdf::respuestaHttp($pdf, $slug.'.pdf');
    }

    /**
     * @param  list<Curso>  $ordenados
     */
    private function pdfEstandar(array $ordenados, Carbon $fechaReferencia, ?int $turnoElegido)
    {
        $fechaTexto = $fechaReferencia->format('d/m/Y');
        $dia = (int) $fechaReferencia->dayOfWeekIso;
        if ($dia < 1 || $dia > 7) {
            $dia = 1;
        }
        $nombreDia = HorariosProfesores::DIAS[$dia] ?? '';
        $lineaDia = $nombreDia !== '' ? 'Día: '.$nombreDia : '';

        $ctx = schoolCtx();

        $paginas = [];
        foreach ($ordenados as $curso) {
            $cursoId = (int) $curso->Id;
            $turnos = HorariosProfesores::turnosParaParteDiario($curso, $turnoElegido);
            foreach ($turnos as $idTurnoClase) {
                $idTurnoClase = (int) $idTurnoClase;
                if ($idTurnoClase <= 0) {
                    $idTurnoClase = 1;
                }

                $filas = HorariosProfesores::filasParteDiarioCursoDia(
                    $cursoId,
                    $dia,
                    $idTurnoClase,
                    (int) $ctx->idNivel,
                    (int) $ctx->idTerlec,
                );

                $paginas[] = [
                    'subtitulo' => 'PARTE DIARIO DEL PRECEPTOR — '.$curso->nombreParaListado(),
                    'lineaDia' => $lineaDia,
                    'fechaTexto' => $fechaTexto,
                    'turnoTitulo' => HorariosProfesores::nombreTurnoClase($idTurnoClase),
                    'filasHorario' => $filas,
                ];
            }
        }

        if ($paginas === []) {
            abort(404);
        }

        if (count($paginas) === 1) {
            $slug = Str::slug('parte-diario-'.$ordenados[0]->nombreParaListado().'-'.$nombreDia, '_') ?: 'parte_diario_preceptor';
        } else {
            $slug = Str::slug('partes-diarios-'.count($paginas).'-cursos-'.$fechaReferencia->format('Y-m-d'), '_') ?: 'partes_diarios_preceptor';
        }

        $pdf = Pdf::loadView('pdf.parte-diario-preceptor', [
            'pdfHeader' => schoolPdfHeaderData(),
            'metaCiclo' => schoolCtx()->nivelNombre().' · Ciclo '.schoolCtx()->terlecAno(),
            'paginas' => $paginas,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('dpi', 72);

        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();
        if ($canvas instanceof DompdfCpdfAdapter) {
            $canvas->get_cpdf()->setPreferences('PrintScaling', 'None');
        }

        return $pdf->stream($slug.'.pdf');
    }
}
