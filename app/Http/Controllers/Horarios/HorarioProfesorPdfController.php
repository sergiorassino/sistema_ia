<?php

namespace App\Http\Controllers\Horarios;

use App\Http\Controllers\Controller;
use App\Support\HorariosProfesores;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class HorarioProfesorPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        @ini_set('memory_limit', '384M');

        $key = 'horario-profesor-pdf:'.(auth()->id() ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429);
        }
        RateLimiter::hit($key, 60);

        $profesoresInput = $request->query('profesores');
        if (($profesoresInput === null || $profesoresInput === '') && $request->filled('profesor')) {
            $profesoresInput = (string) (int) $request->query('profesor');
        }

        $validated = Validator::make(
            ['profesores' => $profesoresInput],
            ['profesores' => ['required', 'string', 'max:8000']],
        )->validate();

        $profesorIds = self::resolverIdsProfesores(trim((string) $validated['profesores']));
        if ($profesorIds === []) {
            abort(404);
        }

        HorariosProfesores::modoImpresionPdfMasivaProfesores(true);

        try {
            $activos = HorariosProfesores::turnosActivos();
            $forzado = (int) $request->query('turno', 0);
            $subtitulo = schoolCtx()->nivelNombre().' · Ciclo '.schoolCtx()->terlecAno();

            $profesoresPorId = DB::table('profesores')
                ->whereIn('id', $profesorIds)
                ->get(['id', 'apellido', 'nombre'])
                ->keyBy('id');

            $ctx = schoolCtx();
            $idNivel = (int) ($ctx->idNivel ?? 0);
            $conAsignacion = DB::table('ppc')
                ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
                ->whereIn('ppc.idProfesor', $profesorIds)
                ->where('m.idNivel', $idNivel)
                ->where('m.idTerlec', (int) $ctx->idTerlec)
                ->distinct()
                ->pluck('ppc.idProfesor')
                ->flip();

            $paginas = [];
            foreach ($profesorIds as $profesorId) {
                if (! isset($conAsignacion[$profesorId])) {
                    continue;
                }

                $prof = $profesoresPorId->get($profesorId);
                if (! $prof) {
                    continue;
                }

                $nombre = trim(((string) $prof->apellido).', '.((string) $prof->nombre));
                $tituloProf = 'Horario docente — '.$nombre;
                $turnos = self::turnosParaProfesor($profesorId, $forzado, $activos);

                foreach ($turnos as $idTurnoClase) {
                    $paginas[] = [
                        'titulo' => $tituloProf,
                        'subtitulo' => $subtitulo,
                        'tituloTurno' => HorariosProfesores::nombreTurnoClase($idTurnoClase),
                        'grilla' => HorariosProfesores::grillaProfesorParaImpresion($profesorId, $idTurnoClase),
                    ];
                }

                if (count($paginas) > 0 && count($paginas) % 12 === 0) {
                    gc_collect_cycles();
                }
            }

            if ($paginas === []) {
                abort(404);
            }

            $tituloPdf = count($profesorIds) === 1
                ? $paginas[0]['titulo']
                : 'Horarios docentes';

            $slug = count($profesorIds) === 1
                ? (Str::slug($paginas[0]['titulo'], '_') ?: 'horario_profesor')
                : 'horarios_docentes';

            $pdfHeader = schoolPdfHeaderData();

            $pdf = Pdf::loadView('pdf.horario-grid', [
                'pdfHeader' => $pdfHeader,
                'titulo' => $tituloPdf,
                'subtitulo' => $subtitulo,
                'paginas' => $paginas,
            ])->setPaper('a4', 'landscape');

            unset($paginas, $profesoresPorId, $pdfHeader);

            return $pdf->stream($slug.'.pdf');
        } finally {
            HorariosProfesores::modoImpresionPdfMasivaProfesores(false);
            HorariosProfesores::limpiarCachesRequestHorarios();
        }
    }

    /**
     * @return list<int>
     */
    private static function resolverIdsProfesores(string $param): array
    {
        $ctx = schoolCtx();
        $idNivel = (int) ($ctx->idNivel ?? 0);

        $parsed = collect(explode(',', $param))
            ->map(fn ($v) => (int) trim((string) $v))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($parsed->isEmpty() || $parsed->count() > 200) {
            return [];
        }

        $permitidos = DB::table('profesores as p')
            ->whereIn('p.id', $parsed->all())
            ->where(function ($w) {
                $w->whereNull('p.IdTipoProf')->orWhere('p.IdTipoProf', '<>', 1);
            })
            ->whereExists(function ($q) use ($idNivel, $ctx) {
                $q->selectRaw('1')
                    ->from('ppc')
                    ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
                    ->whereColumn('ppc.idProfesor', 'p.id')
                    ->where('m.idNivel', $idNivel)
                    ->where('m.idTerlec', (int) $ctx->idTerlec);
            })
            ->orderBy('p.apellido')
            ->orderBy('p.nombre')
            ->pluck('p.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $out = [];
        foreach ($parsed as $id) {
            if (in_array($id, $permitidos, true) && ! in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $activos
     * @return list<int>
     */
    private static function turnosParaProfesor(int $profesorId, int $forzado, array $activos): array
    {
        if ($forzado > 0 && in_array($forzado, $activos, true)) {
            return [$forzado];
        }

        return HorariosProfesores::turnosParaImpresionProfesor($profesorId);
    }
}
