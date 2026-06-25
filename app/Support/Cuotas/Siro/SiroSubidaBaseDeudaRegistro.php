<?php

namespace App\Support\Cuotas\Siro;

use App\Models\CuotaGenerada;
use App\Models\Ento;
use App\Models\Legajo;
use App\Support\Alumnos\ArancelesEscolares;
use App\Support\Alumnos\ComprobantePagoCalculo;
use App\Support\Cuotas\CuponAPagarSnapshot;
use App\Support\Cuotas\GeneracionCuotaEstudianteService;
use App\Support\MatriculaBloqueos;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Evalúa elegibilidad SIRO y arma filas de grilla / detalle de archivo.
 */
final class SiroSubidaBaseDeudaRegistro
{
    private const ID_NIVEL_ADMINISTRACION = 5;

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
    public static function filaGrilla(CuotaGenerada $registro): array
    {
        $eval = self::evaluar($registro);
        $legajo = $registro->legajo;
        $matricula = MatriculaBloqueos::paraCuotaGenerada($registro);
        $curso = $registro->curso;
        $cuota = $registro->cuota;

        return [
            'id' => (int) $registro->id,
            'apellido' => mb_strtoupper(trim((string) ($legajo->apellido ?? ''))),
            'nombre' => mb_strtoupper(trim((string) ($legajo->nombre ?? ''))),
            'dni' => ArancelesEscolares::formatearDni($legajo->dni ?? ''),
            'curso' => mb_strtoupper(trim((string) ($curso?->nombreParaListado() ?? ''))),
            'cuotaNombre' => mb_strtoupper(trim((string) ($cuota->nombre ?? ''))),
            'ano' => (string) ((int) ($cuota?->terlec?->ano ?? 0)),
            'faltapa' => ArancelesEscolares::formatearImporte((float) ($registro->faltapa ?? 0)),
            'venc1' => self::formatearFecha($registro->venc1),
            'venc2' => self::formatearFecha($registro->venc2),
            'venc3' => self::formatearFecha($registro->venc3),
            'obs' => $eval['subeSiro'] ? '' : $eval['motivoExclusion'],
            'idLegajos' => (int) $registro->idLegajos,
            'bloqmatr' => (int) MatriculaBloqueos::bloqmatr($matricula),
            'bloqadmi' => (int) MatriculaBloqueos::bloqadmi($matricula),
            'subeSiro' => $eval['subeSiro'],
            'motivoExclusion' => $eval['motivoExclusion'],
        ];
    }

    /**
     * @return array{subeSiro: bool, motivoExclusion: string, detalle: ?array<string, mixed>}
     */
    public static function evaluar(CuotaGenerada $registro): array
    {
        if (! tenantCuotasSiroHabilitado()) {
            return self::rechazado('SIRO no habilitado para este colegio.');
        }

        $legajo = $registro->legajo;
        if ($legajo === null) {
            return self::rechazado('Sin legajo asociado.');
        }

        if ((float) ($registro->faltapa ?? 0) <= 0) {
            return self::rechazado('Sin saldo adeudado.');
        }

        $motivoLegacy = self::motivoLegacyNoSube($registro, $legajo);
        if ($motivoLegacy !== null) {
            return self::rechazado($motivoLegacy);
        }

        $cupon = ComprobantePagoCalculo::paraCuotaGenerada($registro);
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

        $importe1 = (float) ($cupon['importeVenc1'] ?? 0);
        if ($importe1 <= 0) {
            return self::rechazado('Importe de 1.er vencimiento en cero.');
        }

        $venc1 = self::carbon($registro->venc1);
        if ($venc1 === null) {
            return self::rechazado('Sin fecha de 1.er vencimiento.');
        }

        $venc3 = self::carbon($registro->venc3);
        if ($venc3 === null || $venc3->lt(Carbon::today())) {
            return self::rechazado('3.er vencimiento vencido.');
        }

        $cuponVencido = (bool) ($cupon['cuponVencido'] ?? false);
        if ($cuponVencido) {
            $nuevoVenc = self::carbon($registro->nueVenc ?? $registro->venc3);
            if ($nuevoVenc === null || $nuevoVenc->lt(Carbon::today())) {
                return self::rechazado('Sin vencimiento vigente.');
            }
        }

        $entoAdmin = Ento::query()->where('idNivel', self::ID_NIVEL_ADMINISTRACION)->first();
        $cuit = preg_replace('/\D+/', '', (string) ($entoAdmin?->cuit ?? '')) ?? '';
        if (strlen($cuit) < 11) {
            return self::rechazado('CUIT institucional no configurado.');
        }

        $detalle = self::armarDetalleArchivo($registro, $cupon, $cpe, $idNivel);

        return [
            'subeSiro' => true,
            'motivoExclusion' => '',
            'detalle' => $detalle,
        ];
    }

    /**
     * @param  array<string, mixed>  $cupon
     * @return array<string, mixed>
     */
    public static function armarDetalleArchivo(CuotaGenerada $registro, array $cupon, string $cpe, int $idNivel): array
    {
        return CuponAPagarSnapshot::armar($registro, $cupon, $cpe, $idNivel);
    }

    /**
     * Reglas legacy Scriptcase al armar la grilla (reserva / matrícula final).
     */
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

    /**
     * Suma de saldos familiares con 2.º vencimiento anterior a hoy (legacy subida SIRO).
     */
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

    private static function carbon(mixed $fecha): ?CarbonInterface
    {
        if ($fecha instanceof CarbonInterface) {
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
}
