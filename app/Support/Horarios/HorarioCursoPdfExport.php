<?php

namespace App\Support\Horarios;

use App\Models\Curso;
use App\Support\HorariosProfesores;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * PDF de horario por curso (Dompdf). Usado desde secretaría y autogestión alumno.
 */
final class HorarioCursoPdfExport
{
    /**
     * @param  list<int>  $cursoIds
     */
    public static function stream(
        int $idNivel,
        int $idTerlec,
        array $cursoIds,
        int $forzadoTurno,
        array $pdfHeader,
        string $subtituloNivelCiclo,
    ): Response {
        $cursosPermitidos = Curso::query()
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);

        if ($cursosPermitidos->isEmpty() || $cursoIds === []) {
            abort(404);
        }

        $allowedById = $cursosPermitidos->keyBy(fn (Curso $c) => (int) $c->Id);
        $cursoIds = array_values(array_filter(
            $cursoIds,
            static fn (int $id) => $allowedById->has($id),
        ));

        if ($cursoIds === []) {
            abort(404);
        }

        $activos = HorariosProfesores::turnosActivos($idNivel);
        $paginas = [];

        foreach ($cursoIds as $cursoId) {
            $curso = $allowedById->get($cursoId);
            if ($curso === null) {
                continue;
            }
            $tituloCurso = 'Horario — '.$curso->nombreParaListado();
            $turnos = self::turnosParaCurso($curso, $forzadoTurno, $activos, $idNivel);
            foreach ($turnos as $idTurnoClase) {
                $paginas[] = [
                    'titulo' => $tituloCurso,
                    'subtitulo' => $subtituloNivelCiclo,
                    'tituloTurno' => HorariosProfesores::nombreTurnoClase($idTurnoClase),
                    'grilla' => HorariosProfesores::grillaCurso($cursoId, $idTurnoClase, $idNivel, $idTerlec),
                ];
            }
        }

        if ($paginas === []) {
            abort(404);
        }

        $tituloPdf = count($cursoIds) === 1
            ? $paginas[0]['titulo']
            : 'Horarios por curso';

        $slug = count($cursoIds) === 1
            ? (Str::slug($paginas[0]['titulo'], '_') ?: 'horario_curso')
            : 'horarios_cursos';

        $pdf = Pdf::loadView('pdf.horario-grid', [
            'pdfHeader' => $pdfHeader,
            'titulo' => $tituloPdf,
            'subtitulo' => $subtituloNivelCiclo,
            'paginas' => $paginas,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream($slug.'.pdf');
    }

    /**
     * @param  list<int>  $activos
     * @return list<int>
     */
    private static function turnosParaCurso(Curso $curso, int $forzado, array $activos, int $idNivel): array
    {
        if ($forzado > 0 && in_array($forzado, $activos, true)) {
            if (HorariosProfesores::esTurnoClaseDobleJornada($forzado)) {
                [$ma, $ta] = HorariosProfesores::idsTurnoClaseBandasMananaTarde();
                $turnos = array_values(array_filter([$ma, $ta], fn (int $t) => in_array($t, $activos, true)));

                return $turnos !== [] ? $turnos : [$ma, $ta];
            }

            return [$forzado];
        }

        return HorariosProfesores::turnosParaImpresionCurso($curso, $idNivel);
    }
}
