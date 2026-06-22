<?php

namespace App\Support\CalificacionesPrimario;

use App\Models\Matricula;
use Illuminate\Support\Collection;

/**
 * Boletín de calificaciones — variante Montecristo (primario).
 * Espacios con síntesis y calificación en boletín (`materias.infoCalif = 1`).
 *
 * Esquema de notas alineado al resto del primario:
 * - 1ª etapa: parciales ic05–ic10, final ic01
 * - 2ª etapa: parciales ic11–ic16, final ic02, intensificación dic
 * - Observaciones por materia en síntesis: calificaciones.obs01 / obs02
 */
final class BoletinIpeMontecristoDatos
{
    /**
     * @return array<string, mixed>
     */
    public static function buildForMatriculaEnContextoEscolar(int $idMatricula, int $etapa): array
    {
        $etapa = $etapa === 2 ? 2 : 1;

        $mat = CalificacionesPrimarioDatos::matriculaEnContexto($idMatricula);
        if ($mat === null) {
            return ['ok' => false, 'error' => 'Matrícula no encontrada en el contexto activo.'];
        }

        return self::buildDesdeMatricula($mat, $etapa);
    }

    /**
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     etapa: int,
     *     ano: int,
     *     titulo: string,
     *     alumnoLinea: string,
     *     dni: string,
     *     cursoLabel: string,
     *     filas: list<array{
     *         materia: string,
     *         tipo: string,
     *         sintesis: string,
     *         ic01: string,
     *         ic02: string,
     *         dic: string
     *     }>,
     *     directorFirma: string
     * }
     */
    public static function buildDesdeMatricula(Matricula $matricula, int $etapa): array
    {
        $etapa = $etapa === 2 ? 2 : 1;
        $form = CalificacionesPrimarioDatos::cargarFormulario($matricula);
        $idMatricula = (int) $matricula->id;

        $materiasCurso = CalificacionesPrimarioCatalogo::materiasParaCursoTodasOrd(
            (int) $matricula->idCursos,
            (int) $matricula->idNivel,
            (int) $matricula->idTerlec,
        );
        $materiasInfoCalif = self::materiasConInfoCalif($materiasCurso);
        $ords = $materiasInfoCalif->pluck('ord')->map(fn ($o) => (int) $o)->all();
        $notasPorOrd = CalificacionesPrimarioDatos::calificacionesCompletasPorOrd($idMatricula, $ords);

        $filas = [];
        foreach ($materiasInfoCalif as $m) {
            $ord = (int) $m->ord;
            $nombre = trim((string) ($m->materia ?? ''));
            $nota = $notasPorOrd[$ord] ?? self::notaVacia();
            $campoObs = CalificacionesPrimarioCatalogo::campoObsCalificacionPorEtapa($etapa);

            $filas[] = [
                'materia' => $nombre,
                'tipo' => self::tipoFila($nombre),
                'sintesis' => trim((string) ($nota[$campoObs] ?? '')),
                'ic01' => $nota[CalificacionesPrimarioCatalogo::CAMPO_FINAL_ETAPA_1],
                'ic02' => $nota[CalificacionesPrimarioCatalogo::CAMPO_FINAL_ETAPA_2],
                'dic' => $nota[CalificacionesPrimarioCatalogo::CAMPO_INTENSIFICACION],
            ];
        }

        $legajo = $matricula->legajo;
        $apellido = trim((string) ($legajo?->apellido ?? ''));
        $nombre = trim((string) ($legajo?->nombre ?? ''));
        $alumnoLinea = trim($apellido.' '.$nombre);

        $ctx = schoolCtx();
        $etapaLabel = $etapa === 1 ? 'PRIMERA ETAPA' : 'SEGUNDA ETAPA';

        return [
            'ok' => true,
            'etapa' => $etapa,
            'ano' => (int) ($ctx->terlecAno() ?? now()->year),
            'titulo' => 'BOLETÍN DE CALIFICACIONES - '.$etapaLabel,
            'alumnoLinea' => $alumnoLinea,
            'dni' => trim((string) ($legajo?->dni ?? '')),
            'cursoLabel' => $form['cursoLabel'],
            'filas' => $filas,
            'directorFirma' => trim((string) config('tenant.boletin_primario.director_firma', '')),
        ];
    }

    /**
     * @param  Collection<int, object{id: int, ord: int, abrev: string, materia: string, infoCalif: int}>  $materias
     * @return Collection<int, object{id: int, ord: int, abrev: string, materia: string, infoCalif: int}>
     */
    private static function materiasConInfoCalif(Collection $materias): Collection
    {
        return $materias
            ->filter(function (object $m): bool {
                $nombre = mb_strtoupper(trim((string) ($m->materia ?? '')));
                if (in_array($nombre, ['JUSTIFICADAS', 'INJUSTIFICADAS'], true)) {
                    return true;
                }

                return (int) ($m->infoCalif ?? 0) === 1;
            })
            ->sortBy(fn (object $m) => [(int) $m->ord, (int) $m->id])
            ->values();
    }

    private static function tipoFila(string $materia): string
    {
        $upper = mb_strtoupper(trim($materia));

        return match ($upper) {
            'JUSTIFICADAS' => 'justificadas',
            'INJUSTIFICADAS' => 'injustificadas',
            default => 'materia',
        };
    }

    /**
     * @return array<string, string>
     */
    private static function notaVacia(): array
    {
        $out = [];
        foreach (array_merge(
            CalificacionesPrimarioCatalogo::camposNotaTodos(),
            CalificacionesPrimarioCatalogo::camposObservacionCalificacion(),
            [CalificacionesPrimarioCatalogo::CAMPO_INTENSIFICACION],
        ) as $campo) {
            $out[$campo] = '';
        }

        return $out;
    }
}
