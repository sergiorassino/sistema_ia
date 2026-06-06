<?php

namespace App\Support\Cuotas;

use Illuminate\Support\Collection;

/**
 * Vista previa y ejecución de generación masiva en cuotasgeneradas.
 */
final class GeneracionMasivaCuotasService
{
    public const ESTADO_GENERACION_EXITOSA = 'Generación exitosa';

    /**
     * Todos los alumnos regulares de los cursos elegidos, con estado previo a generar.
     *
     * @param  list<int>  $cursoIds
     * @return array{
     *     porCurso: array<int, array{cursoNombre: string, alumnos: list<array{idLegajo: int, etiqueta: string, estado: string, puedeGenerar: bool}>}>,
     *     total: int,
     *     totalAlumnos: int,
     *     cuotaNombre: string
     * }
     */
    public static function vistaPrevia(array $cursoIds, int $idCuota): array
    {
        $cuota = CuotasImportesCatalog::cuotaDelCicloOrFail($idCuota);
        $cuotaNombre = trim((string) ($cuota->nombre ?? ''));

        $porCurso = [];
        $total = 0;
        $totalAlumnos = 0;

        foreach (self::alumnosPorCursos($cursoIds) as $alumno) {
            $idLegajo = (int) $alumno->id_legajo;
            $eval = GeneracionCuotaEstudianteService::evaluarGeneracion($idLegajo, $idCuota);
            $puedeGenerar = $eval->exito;
            if ($puedeGenerar) {
                $total++;
            }

            $idCurso = (int) $alumno->id_curso;
            $porCurso[$idCurso] ??= [
                'cursoNombre' => (string) ($alumno->curso_nombre ?? ''),
                'alumnos' => [],
            ];
            $porCurso[$idCurso]['alumnos'][] = [
                'idLegajo' => $idLegajo,
                'etiqueta' => GeneracionMasivaCuotasConsulta::etiquetaAlumno($alumno),
                'estado' => $puedeGenerar
                    ? 'Se generará'
                    : self::mensajeEstadoParaLista($eval->mensaje),
                'puedeGenerar' => $puedeGenerar,
            ];
            $totalAlumnos++;
        }

        ksort($porCurso);

        return [
            'porCurso' => $porCurso,
            'total' => $total,
            'totalAlumnos' => $totalAlumnos,
            'cuotaNombre' => $cuotaNombre,
        ];
    }

    /**
     * Genera la cuota para cada alumno regular de los cursos; lista unificada con estado por estudiante.
     *
     * @param  list<int>  $cursoIds
     * @return array{
     *     porCurso: array<int, array{cursoNombre: string, alumnos: list<array{idLegajo: int, etiqueta: string, estado: string, exito: bool}>}>,
     *     generados: int,
     *     noGenerados: int,
     *     cuotaNombre: string
     * }
     */
    public static function generarEnCursos(array $cursoIds, int $idCuota): array
    {
        $cuota = CuotasImportesCatalog::cuotaDelCicloOrFail($idCuota);
        $cuotaNombre = trim((string) ($cuota->nombre ?? ''));

        $porCurso = [];
        $generados = 0;
        $noGenerados = 0;

        foreach (self::alumnosPorCursos($cursoIds) as $alumno) {
            $idLegajo = (int) $alumno->id_legajo;
            $idCurso = (int) $alumno->id_curso;
            $etiqueta = GeneracionMasivaCuotasConsulta::etiquetaAlumno($alumno);
            $cursoNombre = (string) ($alumno->curso_nombre ?? '');

            $resultado = GeneracionCuotaEstudianteService::generar($idLegajo, $idCuota);
            $exito = $resultado->exito;
            if ($exito) {
                $generados++;
            } else {
                $noGenerados++;
            }

            $porCurso[$idCurso] ??= [
                'cursoNombre' => $cursoNombre,
                'alumnos' => [],
            ];
            $porCurso[$idCurso]['alumnos'][] = [
                'idLegajo' => $idLegajo,
                'etiqueta' => $etiqueta,
                'estado' => $exito
                    ? self::ESTADO_GENERACION_EXITOSA
                    : self::mensajeEstadoParaLista($resultado->mensaje),
                'exito' => $exito,
            ];
        }

        ksort($porCurso);

        return [
            'porCurso' => $porCurso,
            'generados' => $generados,
            'noGenerados' => $noGenerados,
            'cuotaNombre' => $cuotaNombre,
        ];
    }

    /**
     * Mensaje breve para la lista de generación masiva (vista previa y resultado).
     */
    public static function mensajeEstadoParaLista(string $mensaje): string
    {
        $texto = trim($mensaje);
        if ($texto === '') {
            return 'No se pudo generar la cuota';
        }

        if (preg_match('/ya está generada/i', $texto) === 1) {
            return 'Ya tiene la cuota generada';
        }

        if (preg_match('/no es regular/i', $texto) === 1) {
            return 'No es estudiante regular en el ciclo activo';
        }

        if (preg_match('/no tiene matrícula/i', $texto) === 1) {
            return 'Sin matrícula con curso en el ciclo activo';
        }

        if (preg_match('/no está disponible para el curso/i', $texto) === 1) {
            return 'La cuota no está disponible para su curso';
        }

        if (preg_match('/no hay importe definido/i', $texto) === 1) {
            return 'Sin importe definido para su curso';
        }

        if (preg_match('/no pertenece al ciclo lectivo activo/i', $texto) === 1) {
            return 'La cuota no pertenece al ciclo lectivo activo';
        }

        if (preg_match('/no se encontró el legajo/i', $texto) === 1) {
            return 'No se encontró el legajo del estudiante';
        }

        return $texto;
    }

    /**
     * @param  list<int>  $cursoIds
     * @return Collection<int, object>
     */
    private static function alumnosPorCursos(array $cursoIds): Collection
    {
        return GeneracionMasivaCuotasConsulta::alumnosRegularesPorCursos($cursoIds);
    }
}
