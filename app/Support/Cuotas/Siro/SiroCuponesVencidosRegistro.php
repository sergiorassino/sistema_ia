<?php

namespace App\Support\Cuotas\Siro;

use App\Models\CuotaGenerada;
use App\Models\Legajo;
use App\Support\Cuotas\CuponAPagarSnapshot;
use App\Support\Cuotas\GeneracionCuotaEstudianteService;
use App\Support\MatriculaBloqueos;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Evalúa elegibilidad SIRO para cupones con 2.º vencimiento vencido.
 *
 * Permite 3.er vencimiento vencido si {@see CuotaGenerada::$nueVenc} (o la fecha «Actualizar al») es vigente.
 */
final class SiroCuponesVencidosRegistro
{
    /**
     * @return array{
     *     id: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string,
     *     cuotaNombre: string,
     *     ano: string,
     *     faltapa: string,
     *     venc1: string,
     *     venc2: string,
     *     venc3: string,
     *     obs: string,
     *     idLegajos: int,
     *     bloqmatr: int,
     *     bloqadmi: int,
     *     subeSiro: bool,
     *     motivoExclusion: string
     * }
     */
    public static function filaGrilla(CuotaGenerada $registro, ?string $fechaActualizarAl = null): array
    {
        $preparado = self::prepararParaEvaluar($registro, $fechaActualizarAl);
        $eval = self::evaluar($preparado);
        $legajo = $registro->legajo;
        $matricula = \App\Support\MatriculaBloqueos::paraCuotaGenerada($registro);
        $curso = $registro->curso;
        $cuota = $registro->cuota;

        return array_merge([
            'id' => (int) $registro->id,
            'apellido' => mb_strtoupper(trim((string) ($legajo->apellido ?? ''))),
            'nombre' => mb_strtoupper(trim((string) ($legajo->nombre ?? ''))),
            'dni' => \App\Support\Alumnos\ArancelesEscolares::formatearDni($legajo->dni ?? ''),
            'curso' => mb_strtoupper(trim((string) ($curso?->nombreParaListado() ?? ''))),
            'cuotaNombre' => mb_strtoupper(trim((string) ($cuota->nombre ?? ''))),
            'ano' => (string) ((int) ($cuota?->terlec?->ano ?? 0)),
            'faltapa' => \App\Support\Alumnos\ArancelesEscolares::formatearImporte((float) ($registro->faltapa ?? 0)),
            'venc1' => self::formatearFecha($registro->venc1),
            'venc2' => self::formatearFecha($registro->venc2),
            'venc3' => self::formatearFecha($registro->venc3),
            'nueVenc' => self::formatearFecha($preparado->nueVenc ?? $registro->nueVenc),
            'obs' => $eval['subeSiro'] ? '' : $eval['motivoExclusion'],
            'idLegajos' => (int) $registro->idLegajos,
            'bloqmatr' => (int) \App\Support\MatriculaBloqueos::bloqmatr($matricula),
            'bloqadmi' => (int) \App\Support\MatriculaBloqueos::bloqadmi($matricula),
            'subeSiro' => $eval['subeSiro'],
            'motivoExclusion' => $eval['motivoExclusion'],
        ], SiroSubidaGrillaColumnas::desdeDetalle($eval['subeSiro'] ? $eval['detalle'] : null));
    }

    /**
     * @return array{subeSiro: bool, motivoExclusion: string, detalle: ?array<string, mixed>}
     */
    public static function evaluar(CuotaGenerada $registro): array
    {
        $eval = SiroSubidaBaseDeudaRegistro::evaluar($registro);
        if ($eval['subeSiro']) {
            return self::aplicarDetalleSubidaCuponesVencidos($registro, $eval);
        }

        if ($eval['motivoExclusion'] !== '3.er vencimiento vencido.'
            && $eval['motivoExclusion'] !== 'Sin vencimiento vigente.') {
            return $eval;
        }

        $venc3 = self::carbon($registro->venc3);
        $nueVenc = self::carbon($registro->nueVenc);

        if ($venc3 !== null && $venc3->gte(Carbon::today())) {
            return $eval;
        }

        if ($nueVenc === null || $nueVenc->lt(Carbon::today())) {
            return [
                'subeSiro' => false,
                'motivoExclusion' => 'Sin vencimiento vigente (indique «Actualizar al»).',
                'detalle' => null,
            ];
        }

        return self::reevaluarConNueVencVigente($registro);
    }

    public static function prepararParaEvaluar(CuotaGenerada $registro, ?string $fechaActualizarAl): CuotaGenerada
    {
        $fecha = trim((string) ($fechaActualizarAl ?? ''));
        if ($fecha === '') {
            return $registro;
        }

        $copia = $registro->replicate();
        $copia->id = $registro->id;
        $copia->exists = true;
        $copia->nueVenc = Carbon::parse($fecha)->startOfDay();

        return $copia;
    }

    /**
     * @return array{subeSiro: bool, motivoExclusion: string, detalle: ?array<string, mixed>}
     */
    private static function reevaluarConNueVencVigente(CuotaGenerada $registro): array
    {
        if (! tenantCuotasSiroHabilitado()) {
            return self::rechazado('SIRO no habilitado para este colegio.');
        }

        $legajo = $registro->legajo;
        if ($legajo === null) {
            return self::rechazado('Sin legajo asociado.');
        }

        $motivoLegacy = self::motivoLegacyNoSube($registro, $legajo);
        if ($motivoLegacy !== null) {
            return self::rechazado($motivoLegacy);
        }

        if ((float) ($registro->faltapa ?? 0) <= 0) {
            return self::rechazado('Sin saldo adeudado.');
        }

        $cupon = \App\Support\Alumnos\ComprobantePagoCalculo::paraCuotaGenerada($registro);
        if ($cupon === null) {
            return self::rechazado('No se pudo calcular el cupón de pago.');
        }

        $idNivel = (int) ($registro->curso?->idNivel ?? 0);
        if ($idNivel <= 0) {
            return self::rechazado('Sin nivel de curso para el CPE SIRO.');
        }

        $cpe = SiroCodigoPagoElectronico::generar((int) $registro->idLegajos, $idNivel);
        if (strlen($cpe) !== 19) {
            return self::rechazado('Código de pago electrónico inválido.');
        }

        $cuentaSiro = SiroCodigoPagoElectronico::cuentaRecaudadoraPorNivel($idNivel);
        if (preg_match('/^0+$/', $cuentaSiro)) {
            return self::rechazado('Cuenta SIRO no configurada para este nivel.');
        }

        $nueVenc = self::carbon($registro->nueVenc);
        if ($nueVenc === null) {
            return self::rechazado('Sin vencimiento actualizado (indique «Actualizar al»).');
        }

        $importeConIntereses = CuponAPagarSnapshot::importeConInteresesEnFecha($registro, $nueVenc);
        if ($importeConIntereses <= 0) {
            return self::rechazado('Importe con intereses en cero.');
        }

        if (SiroSubidaBaseDeudaArchivo::fechaSiro($nueVenc, true) === str_repeat('0', 8)) {
            return self::rechazado('Fecha «Actualizar al» inválida.');
        }

        $venc1 = self::carbon($registro->venc1);
        if ($venc1 === null) {
            return self::rechazado('Sin fecha de 1.er vencimiento.');
        }

        $venc2 = self::carbon($registro->venc2);
        if ($venc2 !== null && $venc2->lt($venc1)) {
            return self::rechazado('Fechas de vencimiento no crecientes.');
        }

        $entoAdmin = \App\Models\Ento::query()->where('idNivel', 5)->first();
        $cuit = preg_replace('/\D+/', '', (string) ($entoAdmin?->cuit ?? '')) ?? '';
        if (strlen($cuit) < 11) {
            return self::rechazado('CUIT institucional no configurado.');
        }

        $detalle = CuponAPagarSnapshot::armarParaCuponesVencidosSiro($registro, $cupon, $cpe, $idNivel);

        return [
            'subeSiro' => true,
            'motivoExclusion' => '',
            'detalle' => $detalle,
        ];
    }

    /**
     * @param  array{subeSiro: bool, motivoExclusion: string, detalle: ?array<string, mixed>}  $eval
     * @return array{subeSiro: bool, motivoExclusion: string, detalle: ?array<string, mixed>}
     */
    private static function aplicarDetalleSubidaCuponesVencidos(CuotaGenerada $registro, array $eval): array
    {
        $detalleBase = $eval['detalle'];
        if ($detalleBase === null) {
            return $eval;
        }

        $nueVenc = self::carbon($registro->nueVenc);
        if ($nueVenc === null || $nueVenc->lt(Carbon::today())) {
            return self::rechazado('Sin vencimiento vigente (indique «Actualizar al»).');
        }

        $cupon = \App\Support\Alumnos\ComprobantePagoCalculo::paraCuotaGenerada($registro);
        if ($cupon === null) {
            return self::rechazado('No se pudo calcular el cupón de pago.');
        }

        $nueVencEval = self::carbon($registro->nueVenc);
        if ($nueVencEval === null || CuponAPagarSnapshot::importeConInteresesEnFecha($registro, $nueVencEval) <= 0) {
            return self::rechazado('Importe con intereses en cero.');
        }

        try {
            $eval['detalle'] = CuponAPagarSnapshot::armarParaCuponesVencidosSiro(
                $registro,
                $cupon,
                (string) ($detalleBase['cpe'] ?? ''),
                (int) ($detalleBase['idNivel'] ?? 0),
            );
        } catch (\RuntimeException $e) {
            return self::rechazado($e->getMessage());
        }

        return $eval;
    }

    /**
     * @return array{subeSiro: false, motivoExclusion: string, detalle: null}
     */
    private static function rechazado(string $motivo): array
    {
        return [
            'subeSiro' => false,
            'motivoExclusion' => $motivo,
            'detalle' => null,
        ];
    }

    private static function formatearFecha(mixed $fecha): string
    {
        $carbon = self::carbon($fecha);

        return $carbon?->format('d/m/Y') ?? '';
    }

    private static function carbon(mixed $fecha): ?\Carbon\CarbonInterface
    {
        if ($fecha instanceof \Carbon\CarbonInterface) {
            return $fecha->copy()->startOfDay();
        }

        $raw = trim((string) ($fecha ?? ''));
        if ($raw === '' || $raw === '0000-00-00') {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function motivoLegacyNoSube(CuotaGenerada $registro, Legajo $legajo): ?string
    {
        $idTipo = (int) ($registro->idCuotastipo ?? 0);
        $esReserva = $idTipo === GeneracionCuotaEstudianteService::TIPO_RESERVA;
        $esMatricula = $idTipo === GeneracionCuotaEstudianteService::TIPO_MATRICULA;

        if (! $esReserva && ! $esMatricula) {
            return null;
        }

        $matricula = MatriculaBloqueos::paraCuotaGenerada($registro);

        if (MatriculaBloqueos::bloqmatr($matricula)) {
            return 'Tiene bloqueo Pedagógico: no sube a Siro';
        }

        if (MatriculaBloqueos::bloqadmi($matricula)) {
            return 'Tiene bloqueo administrativo: no sube a Siro';
        }

        if ($esMatricula && self::deudaFamiliarVencida($legajo) > 0) {
            return 'Tiene deuda familiar: no sube a Siro';
        }

        return null;
    }

    private static function deudaFamiliarVencida(Legajo $legajo): float
    {
        $idFamilia = (int) ($legajo->idFamilias ?? 0);
        if ($idFamilia <= 0) {
            return 0.0;
        }

        $hoy = Carbon::today()->format('Y-m-d');

        return (float) CuotaGenerada::query()
            ->join('legajos', 'cuotasgeneradas.idLegajos', '=', 'legajos.id')
            ->where('legajos.idFamilias', $idFamilia)
            ->whereDate('cuotasgeneradas.venc2', '<', $hoy)
            ->where(function (Builder $q): void {
                $q->where('cuotasgeneradas.avisoPago', '<>', 1)
                    ->orWhereNull('cuotasgeneradas.avisoPago');
            })
            ->sum('cuotasgeneradas.faltapa');
    }
}
