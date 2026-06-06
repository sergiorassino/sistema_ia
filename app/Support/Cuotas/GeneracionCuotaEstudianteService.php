<?php

namespace App\Support\Cuotas;

use App\Models\Condicion;
use App\Models\Cuota;
use App\Models\CuotaGenerada;
use App\Models\CuotasBeca;
use App\Models\CuotasImporte;
use App\Models\DatoVario;
use App\Models\Matricula;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generación manual de cuotas / reservas / matrícula en cuotasgeneradas.
 */
final class GeneracionCuotaEstudianteService
{
    public const TIPO_MATRICULA = 3;

    public const TIPO_RESERVA = 2;

    public const BECA_CUOTA_ENTERA = 1;

    public static function esEstudianteRegular(int $idLegajo): bool
    {
        $matricula = GestionAranceles::matriculaCicloActivo($idLegajo);
        if ($matricula === null) {
            return false;
        }

        $idCondicion = (int) ($matricula->idCondiciones ?? 0);
        if ($idCondicion !== 1) {
            return false;
        }

        $condicion = Condicion::query()->find($idCondicion, ['id', 'proteg']);
        if ($condicion === null) {
            return false;
        }

        return (int) ($condicion->proteg ?? 0) !== 99;
    }

    /**
     * Plantillas del ciclo activo con importe para el curso del estudiante.
     *
     * @return Collection<int, array{
     *     id: int,
     *     nombre: string,
     *     tipoNombre: string,
     *     mes: string,
     *     venc1: \Carbon\CarbonInterface|null,
     *     idCuotastipo: int,
     *     importeBase: float,
     *     importeEstimado: float,
     *     yaGenerada: bool
     * }>
     */
    public static function plantillasDelCiclo(int $idLegajo): Collection
    {
        $matricula = GestionAranceles::matriculaCicloActivo($idLegajo);
        if ($matricula === null || (int) ($matricula->idCursos ?? 0) < 1) {
            return collect();
        }

        $idTerlec = (int) schoolCtx()->idTerlec;
        $idCurso = (int) $matricula->idCursos;

        $idsGeneradas = CuotaGenerada::query()
            ->where('idLegajos', $idLegajo)
            ->pluck('idCuotas')
            ->flip();

        $importesPorCuota = CuotasImporte::query()
            ->where('idCursos', $idCurso)
            ->get(['idCuotas', 'importe'])
            ->keyBy('idCuotas');

        if ($importesPorCuota->isEmpty()) {
            return collect();
        }

        $cuotas = Cuota::query()
            ->where('idTerlec', $idTerlec)
            ->whereIn('id', $importesPorCuota->keys())
            ->with(['cuotasTipo:id,nombre', 'cuotasMes:id,mes'])
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        return $cuotas->map(function (Cuota $cuota) use ($importesPorCuota, $idsGeneradas, $matricula): array {
            $importeBase = round((float) ($importesPorCuota[$cuota->id]->importe ?? 0), 2);

            return [
                'id' => (int) $cuota->id,
                'nombre' => trim((string) ($cuota->nombre ?? '')),
                'tipoNombre' => trim((string) ($cuota->cuotasTipo?->nombre ?? '')),
                'mes' => trim((string) ($cuota->cuotasMes?->mes ?? '')),
                'venc1' => $cuota->venc1,
                'idCuotastipo' => (int) ($cuota->idCuotastipo ?? 0),
                'importeBase' => $importeBase,
                'importeEstimado' => self::calcularImporte(
                    $cuota,
                    $matricula,
                    $importeBase,
                ),
                'yaGenerada' => $idsGeneradas->has($cuota->id),
            ];
        })->values();
    }

    /**
     * Validaciones previas a insertar en cuotasgeneradas (sin escribir en BD).
     */
    public static function evaluarGeneracion(int $idLegajo, int $idCuota): GeneracionCuotaResultado
    {
        if (! self::esEstudianteRegular($idLegajo)) {
            return GeneracionCuotaResultado::fallo('El/La estudiante no es regular en el ciclo lectivo activo.');
        }

        if (GestionAranceles::legajoParaGestion($idLegajo) === null) {
            return GeneracionCuotaResultado::fallo('No se encontró el legajo del estudiante.');
        }

        $matricula = GestionAranceles::matriculaCicloActivo($idLegajo);
        if ($matricula === null || (int) ($matricula->idCursos ?? 0) < 1) {
            return GeneracionCuotaResultado::fallo('El estudiante no tiene matrícula con curso en el ciclo lectivo activo.');
        }

        $plantillas = self::plantillasDelCiclo($idLegajo);
        $plantilla = $plantillas->firstWhere('id', $idCuota);
        if ($plantilla === null) {
            return GeneracionCuotaResultado::fallo('La cuota seleccionada no está disponible para el curso del estudiante.');
        }

        if ($plantilla['yaGenerada']) {
            return GeneracionCuotaResultado::fallo(
                'La cuota «'.$plantilla['nombre'].'» ya está generada para este estudiante.',
            );
        }

        $cuota = Cuota::query()
            ->whereKey($idCuota)
            ->where('idTerlec', (int) schoolCtx()->idTerlec)
            ->first();
        if ($cuota === null) {
            return GeneracionCuotaResultado::fallo('La plantilla de cuota no pertenece al ciclo lectivo activo.');
        }

        $importeRow = CuotasImporte::query()
            ->where('idCuotas', $idCuota)
            ->where('idCursos', (int) $matricula->idCursos)
            ->first(['importe']);
        if ($importeRow === null) {
            return GeneracionCuotaResultado::fallo('No hay importe definido para esta cuota y el curso del estudiante.');
        }

        return GeneracionCuotaResultado::exito('OK', 0);
    }

    public static function generar(int $idLegajo, int $idCuota): GeneracionCuotaResultado
    {
        $evaluacion = self::evaluarGeneracion($idLegajo, $idCuota);
        if (! $evaluacion->exito) {
            return $evaluacion;
        }

        $matricula = GestionAranceles::matriculaCicloActivo($idLegajo);
        if ($matricula === null) {
            return GeneracionCuotaResultado::fallo('El estudiante no tiene matrícula con curso en el ciclo lectivo activo.');
        }

        $cuota = Cuota::query()
            ->whereKey($idCuota)
            ->where('idTerlec', (int) schoolCtx()->idTerlec)
            ->first();
        if ($cuota === null) {
            return GeneracionCuotaResultado::fallo('La plantilla de cuota no pertenece al ciclo lectivo activo.');
        }

        $importeRow = CuotasImporte::query()
            ->where('idCuotas', $idCuota)
            ->where('idCursos', (int) $matricula->idCursos)
            ->first(['importe']);
        if ($importeRow === null) {
            return GeneracionCuotaResultado::fallo('No hay importe definido para esta cuota y el curso del estudiante.');
        }

        $importeBase = round((float) $importeRow->importe, 2);
        [$importe, $idCuotasbecas] = self::resolverImporteYBeca($cuota, $matricula, $importeBase);

        if ($importe < 0) {
            $importe = 0.0;
        }

        $nombreCuota = trim((string) ($cuota->nombre ?? ''));

        try {
            $idGenerada = DB::transaction(function () use (
                $idLegajo,
                $matricula,
                $cuota,
                $importe,
                $idCuotasbecas,
            ): int {
                if (self::cuotaYaGeneradaEnTransaccion($idLegajo, (int) $cuota->id)) {
                    throw new \RuntimeException('duplicada');
                }

                $nroComprobante = self::siguienteNumeroComprobante();

                $registro = CuotaGenerada::query()->create([
                    'idTerlec' => (int) schoolCtx()->idTerlec,
                    'idLegajos' => $idLegajo,
                    'idCursos' => (int) $matricula->idCursos,
                    'idMatricula' => (int) $matricula->id,
                    'idCuotas' => (int) $cuota->id,
                    'idCuotastipo' => (int) $cuota->idCuotastipo,
                    'idCuotasmeses' => (int) $cuota->idCuotasmeses,
                    'idCuotasbecas' => $idCuotasbecas,
                    'venc1' => $cuota->venc1?->format('Y-m-d'),
                    'venc2' => $cuota->venc2?->format('Y-m-d'),
                    'venc3' => $cuota->venc3?->format('Y-m-d'),
                    'importe' => $importe,
                    'faltapa' => $importe,
                    'nroComp' => $nroComprobante,
                ]);

                return (int) $registro->id;
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'duplicada') {
                return GeneracionCuotaResultado::fallo(
                    'La cuota «'.$nombreCuota.'» ya está generada para este estudiante.',
                );
            }

            throw $e;
        }

        return GeneracionCuotaResultado::exito(
            'La cuota «'.$nombreCuota.'» se generó correctamente.',
            $idGenerada,
        );
    }

    /**
     * @return array{0: float, 1: int}
     */
    private static function resolverImporteYBeca(Cuota $cuota, Matricula $matricula, float $importeBase): array
    {
        $idTipo = (int) ($cuota->idCuotastipo ?? 0);

        if ($idTipo === self::TIPO_MATRICULA) {
            $importe = self::importeMatriculaConDescuentoReservas($matricula, $importeBase);

            return [$importe, self::BECA_CUOTA_ENTERA];
        }

        if ((int) ($cuota->sinConBeca ?? 0) === 1) {
            $idBeca = (int) ($matricula->idCuotasbecas ?? self::BECA_CUOTA_ENTERA);
            $porcentaje = (float) (CuotasBeca::query()->whereKey($idBeca)->value('porcentaje') ?? 0);
            $importe = round($importeBase - ($importeBase * $porcentaje / 100), 2);

            return [$importe, $idBeca > 0 ? $idBeca : self::BECA_CUOTA_ENTERA];
        }

        return [$importeBase, self::BECA_CUOTA_ENTERA];
    }

    public static function calcularImporte(Cuota $cuota, Matricula $matricula, float $importeBase): float
    {
        [$importe] = self::resolverImporteYBeca($cuota, $matricula, $importeBase);

        return max(0, $importe);
    }

    /**
     * Importe neto desde importe base (curso) y la beca del registro en cuotasgeneradas.
     * Null si la plantilla es matrícula (no aplica recálculo masivo por beca).
     */
    public static function importeDesdeBaseYBecaEnRegistro(CuotaGenerada $registro, float $importeBase): ?float
    {
        $importeBase = round($importeBase, 2);
        $cuota = $registro->cuota;

        if ($cuota === null) {
            return max(0.0, $importeBase);
        }

        if ((int) ($cuota->idCuotastipo ?? 0) === self::TIPO_MATRICULA) {
            return null;
        }

        if ((int) ($cuota->sinConBeca ?? 0) === 1) {
            $idBeca = (int) ($registro->idCuotasbecas ?? self::BECA_CUOTA_ENTERA);
            $porcentaje = (float) (CuotasBeca::query()->whereKey($idBeca)->value('porcentaje') ?? 0);
            $importe = round($importeBase - ($importeBase * $porcentaje / 100), 2);

            return max(0.0, $importe);
        }

        return max(0.0, $importeBase);
    }

    private static function importeMatriculaConDescuentoReservas(Matricula $matricula, float $importeBase): float
    {
        $totales = CuotaGenerada::query()
            ->where('idMatricula', (int) $matricula->id)
            ->where('idCuotastipo', self::TIPO_RESERVA)
            ->selectRaw(
                'COALESCE(SUM(pagado), 0) as pagado, COALESCE(SUM(bonificacion), 0) as bonificacion, COALESCE(SUM(interes), 0) as interes',
            )
            ->first();

        $pagadoReservas = round((float) ($totales->pagado ?? 0), 2);
        $bonificacionReservas = round((float) ($totales->bonificacion ?? 0), 2);
        $interesReservas = round((float) ($totales->interes ?? 0), 2);

        return round(
            $importeBase - ($pagadoReservas + $bonificacionReservas - $interesReservas),
            2,
        );
    }

    private static function cuotaYaGeneradaEnTransaccion(int $idLegajo, int $idCuota): bool
    {
        return CuotaGenerada::query()
            ->where('idLegajos', $idLegajo)
            ->where('idCuotas', $idCuota)
            ->lockForUpdate()
            ->exists();
    }

    private static function siguienteNumeroComprobante(): int
    {
        $dato = DatoVario::query()->whereKey(1)->lockForUpdate()->first();
        if ($dato === null) {
            throw new \RuntimeException('No se encontró el registro de numeración en datosvarios (id = 1).');
        }

        $nro = (int) ($dato->ultimoComprobante ?? 0) + 1;
        $dato->ultimoComprobante = $nro;
        $dato->save();

        return $nro;
    }
}
