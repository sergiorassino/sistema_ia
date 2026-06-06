<?php

namespace App\Support\Cuotas;

use App\Models\CuotaGenerada;
use App\Models\CuotaPago;
use Illuminate\Support\Facades\DB;

/**
 * Vista previa y eliminación masiva de registros en cuotasgeneradas por cursos.
 *
 * No elimina cuotas con pagos en cuotaspagos, con PAGADO &gt; 0, con FALTAPA en cero
 * (saldo saldado) ni con FALTAPA distinto del saldo íntegro sin pagos (ajustes parciales).
 */
final class EliminacionMasivaCuotasService
{
    public const ESTADO_ELIMINACION_EXITOSA = 'Eliminación exitosa';

    /**
     * @param  list<int>  $cursoIds
     * @return array{
     *     porCurso: array<int, array{cursoNombre: string, alumnos: list<array{idLegajo: int, etiqueta: string, estado: string, puedeEliminar: bool}>}>,
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

        foreach (GeneracionMasivaCuotasConsulta::alumnosRegularesPorCursos($cursoIds) as $alumno) {
            $idLegajo = (int) $alumno->id_legajo;
            $registro = self::cuotaGeneradaDelEstudiante($idLegajo, $idCuota);
            $motivo = self::motivoRechazoMasivo($registro);
            $puedeEliminar = $motivo === null;
            if ($puedeEliminar) {
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
                'estado' => $puedeEliminar
                    ? 'Se eliminará'
                    : self::mensajeEstadoParaLista($motivo),
                'puedeEliminar' => $puedeEliminar,
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
     * @param  list<int>  $cursoIds
     * @return array{
     *     porCurso: array<int, array{cursoNombre: string, alumnos: list<array{idLegajo: int, etiqueta: string, estado: string, exito: bool}>}>,
     *     eliminados: int,
     *     noEliminados: int,
     *     cuotaNombre: string
     * }
     */
    public static function eliminarEnCursos(array $cursoIds, int $idCuota): array
    {
        $cuota = CuotasImportesCatalog::cuotaDelCicloOrFail($idCuota);
        $cuotaNombre = trim((string) ($cuota->nombre ?? ''));

        $porCurso = [];
        $eliminados = 0;
        $noEliminados = 0;

        foreach (GeneracionMasivaCuotasConsulta::alumnosRegularesPorCursos($cursoIds) as $alumno) {
            $idLegajo = (int) $alumno->id_legajo;
            $idCurso = (int) $alumno->id_curso;
            $etiqueta = GeneracionMasivaCuotasConsulta::etiquetaAlumno($alumno);
            $cursoNombre = (string) ($alumno->curso_nombre ?? '');

            $registro = self::cuotaGeneradaDelEstudiante($idLegajo, $idCuota);
            $motivo = self::motivoRechazoMasivo($registro);
            $exito = $motivo === null && self::eliminarRegistro($registro);

            if ($exito) {
                $eliminados++;
            } else {
                $noEliminados++;
            }

            $porCurso[$idCurso] ??= [
                'cursoNombre' => $cursoNombre,
                'alumnos' => [],
            ];
            $porCurso[$idCurso]['alumnos'][] = [
                'idLegajo' => $idLegajo,
                'etiqueta' => $etiqueta,
                'estado' => $exito
                    ? self::ESTADO_ELIMINACION_EXITOSA
                    : self::mensajeEstadoParaLista($motivo ?? 'No se pudo eliminar la cuota.'),
                'exito' => $exito,
            ];
        }

        ksort($porCurso);

        return [
            'porCurso' => $porCurso,
            'eliminados' => $eliminados,
            'noEliminados' => $noEliminados,
            'cuotaNombre' => $cuotaNombre,
        ];
    }

    public static function motivoRechazoMasivo(?CuotaGenerada $registro): ?string
    {
        if ($registro === null) {
            return 'No tiene la cuota generada.';
        }

        if (CuotaPago::query()->where('idCuotasGeneradas', (int) $registro->id)->exists()) {
            return 'Tiene pagos registrados.';
        }

        $pagado = round((float) ($registro->pagado ?? 0), 2);
        if ($pagado > 0) {
            return 'Tiene importe en PAGADO.';
        }

        $faltapa = round((float) ($registro->faltapa ?? 0), 2);
        if ($faltapa <= 0) {
            return 'FALTAPA en cero (sin saldo adeudado).';
        }

        $deudaSinPagos = CuotasFormato::calcularFaltapa(
            (float) ($registro->importe ?? 0),
            0.0,
            (float) ($registro->bonificacion ?? 0),
            (float) ($registro->interes ?? 0),
        );

        if (abs($faltapa - $deudaSinPagos) > 0.009) {
            return 'FALTAPA distinto del saldo íntegro (posible pago o ajuste).';
        }

        return null;
    }

    public static function mensajeEstadoParaLista(?string $mensaje): string
    {
        $texto = trim((string) $mensaje);
        if ($texto === '') {
            return 'No se pudo eliminar la cuota';
        }

        if (preg_match('/no tiene la cuota generada/i', $texto) === 1) {
            return 'No tiene la cuota generada';
        }

        if (preg_match('/pagos registrados/i', $texto) === 1) {
            return 'Tiene pagos registrados';
        }

        if (preg_match('/PAGADO/i', $texto) === 1) {
            return 'Tiene importe en PAGADO';
        }

        if (preg_match('/FALTAPA en cero/i', $texto) === 1) {
            return 'FALTAPA en cero';
        }

        if (preg_match('/FALTAPA distinto/i', $texto) === 1) {
            return 'FALTAPA con ajuste o pago parcial';
        }

        return $texto;
    }

    private static function cuotaGeneradaDelEstudiante(int $idLegajo, int $idCuota): ?CuotaGenerada
    {
        return CuotaGenerada::query()
            ->where('idLegajos', $idLegajo)
            ->where('idCuotas', $idCuota)
            ->where('idTerlec', (int) schoolCtx()->idTerlec)
            ->first();
    }

    private static function eliminarRegistro(?CuotaGenerada $registro): bool
    {
        if ($registro === null || self::motivoRechazoMasivo($registro) !== null) {
            return false;
        }

        return DB::transaction(function () use ($registro): bool {
            $locked = CuotaGenerada::query()->whereKey($registro->id)->lockForUpdate()->first();
            if ($locked === null || self::motivoRechazoMasivo($locked) !== null) {
                return false;
            }

            if (CuotaPago::query()->where('idCuotasGeneradas', (int) $locked->id)->exists()) {
                return false;
            }

            return (bool) $locked->delete();
        });
    }
}
