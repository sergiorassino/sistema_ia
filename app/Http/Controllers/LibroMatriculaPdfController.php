<?php

namespace App\Http\Controllers;

use App\Support\Listados\LibroMatriculaExporter;
use App\Support\Listados\LibroMatriculaPdfColumnas;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LibroMatriculaPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        @ini_set('memory_limit', '512M');
        set_time_limit(180);

        $key = 'libro-matricula-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Demasiadas solicitudes. Intente nuevamente en breve.');
        }
        RateLimiter::hit($key, 60);

        $validated = Validator::make(
            ['inscriptos_al' => $request->query('inscriptos_al')],
            ['inscriptos_al' => ['required', 'date']],
        );

        if ($validated->fails()) {
            abort(404);
        }

        $ctx = schoolCtx();
        $inscriptosAl = Carbon::parse($validated->validated()['inscriptos_al'])->startOfDay();

        $anoCiclo = $ctx->terlecAno();
        $anoCiclo = is_numeric($anoCiclo) ? (int) $anoCiclo : null;

        $resultado = LibroMatriculaExporter::datosParaPdf(
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
            $inscriptosAl,
            $anoCiclo,
        );

        $ano = $ctx->terlecAno();
        $insti = trim((string) (schoolPdfHeaderData()['insti'] ?? ''));

        $slug = Str::slug('libro-matricula-'.($ano ?? ''), '_');
        if ($slug === '') {
            $slug = 'libro_matricula';
        }

        $pdf = Pdf::loadView('listados::pdf.libro-matricula', [
            'filas' => $resultado['filas'],
            'totales' => $resultado['totales'],
            'inscriptosAl' => $inscriptosAl,
            'ano' => $ano,
            'insti' => $insti !== '' ? $insti : 'Institución',
            'filasHojaManual' => LibroMatriculaExporter::FILAS_HOJA_MANUAL,
            'libroMatriculaColumnas' => LibroMatriculaPdfColumnas::todas(),
        ])
            ->setPaper('legal', 'landscape')
            ->setOption('isFontSubsettingEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('dpi', 72);

        $pdf->getDomPDF()->setCallbacks([
            [
                'event' => 'end_document',
                'f' => static function (int $pageNumber, int $pageCount, $canvas, $fontMetrics): void {
                    $font = $fontMetrics->getFont('DejaVu Sans');
                    $size = 6;
                    $text = 'Pag: '.$pageNumber;
                    $margenDerPt = 14.17; // ~5 mm
                    $margenSupPt = 12; // arriba, dentro del margen superior
                    $x = $canvas->get_width() - $canvas->get_text_width($text, $font, $size) - $margenDerPt;
                    $y = $margenSupPt;
                    $canvas->text($x, $y, $text, $font, $size, [0.45, 0.45, 0.45]);
                },
            ],
        ]);

        return $pdf->stream($slug.'.pdf');
    }
}
