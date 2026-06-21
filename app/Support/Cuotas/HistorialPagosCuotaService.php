<?php

namespace App\Support\Cuotas;

use App\Models\CuotaGenerada;
use App\Models\CuotaPago;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Listado y anulación de pagos registrados en cuotaspagos para una cuota generada.
 */
final class HistorialPagosCuotaService
{
    /**
     * @return Collection<int, CuotaPago>
     */
    public static function pagosTodos(int $idCuotaGenerada, int $idLegajo): Collection
    {
        $registro = GestionAranceles::cuotaDelLegajo($idCuotaGenerada, $idLegajo);
        if ($registro === null) {
            return collect();
        }

        return CuotaPago::query()
            ->with([
                'tipoPago:id,abrev,tipoPago',
                'cuotaGenerada.cuota:id,nombre',
            ])
            ->where('idCuotasGeneradas', $idCuotaGenerada)
            ->orderByDesc('fechhora')
            ->orderByDesc('id')
            ->get();
    }

    public static function pagoDelHistorial(int $idCuotaPago, int $idLegajo, int $idCuotaGenerada): ?CuotaPago
    {
        if (GestionAranceles::cuotaDelLegajo($idCuotaGenerada, $idLegajo) === null) {
            return null;
        }

        return CuotaPago::query()
            ->whereKey($idCuotaPago)
            ->where('idCuotasGeneradas', $idCuotaGenerada)
            ->first();
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public static function puedeEliminar(int $idCuotaPago, int $idLegajo, int $idCuotaGenerada): array
    {
        if (self::pagoDelHistorial($idCuotaPago, $idLegajo, $idCuotaGenerada) === null) {
            return ['ok' => false, 'mensaje' => 'Pago no encontrado.'];
        }

        if (
            ComprobantesAfipCuotaService::moduloDisponible()
            && ComprobantesAfipCuotaService::facturaVigente($idCuotaPago) !== null
        ) {
            return [
                'ok' => false,
                'mensaje' => 'No se puede eliminar el pago porque tiene una factura AFIP vigente. Debe emitir una nota de crédito antes.',
            ];
        }

        return ['ok' => true, 'mensaje' => ''];
    }

    public static function eliminar(int $idCuotaPago, int $idLegajo, int $idCuotaGenerada): bool
    {
        $validacion = self::puedeEliminar($idCuotaPago, $idLegajo, $idCuotaGenerada);
        if (! $validacion['ok']) {
            return false;
        }

        $registro = GestionAranceles::cuotaDelLegajo($idCuotaGenerada, $idLegajo);
        if ($registro === null) {
            return false;
        }

        $pago = CuotaPago::query()
            ->whereKey($idCuotaPago)
            ->where('idCuotasGeneradas', $idCuotaGenerada)
            ->first();

        if ($pago === null) {
            return false;
        }

        $importe = round((float) ($pago->importe ?? 0), 2);
        $interes = round((float) ($pago->interes ?? 0), 2);
        $bonificacion = round((float) ($pago->bonificacion ?? 0), 2);
        $aPagar = round(max(0, $importe + $interes - $bonificacion), 2);

        DB::transaction(function () use ($pago, $registro, $importe, $interes, $bonificacion, $aPagar) {
            $locked = CuotaGenerada::query()->whereKey($registro->id)->lockForUpdate()->firstOrFail();

            $locked->pagado = round(max(0, (float) $locked->pagado - $aPagar), 2);
            $locked->faltapa = round((float) $locked->faltapa + $importe, 2);
            $locked->interes = round(max(0, (float) $locked->interes - $interes), 2);
            $locked->bonificacion = round(max(0, (float) $locked->bonificacion - $bonificacion), 2);

            $ultimaFecha = CuotaPago::query()
                ->where('idCuotasGeneradas', (int) $locked->id)
                ->whereKeyNot($pago->id)
                ->orderByDesc('fechhora')
                ->orderByDesc('id')
                ->value('fechhora');

            $locked->fechaPago = $ultimaFecha !== null && trim((string) $ultimaFecha) !== ''
                ? $ultimaFecha
                : null;

            $locked->save();
            $pago->delete();
        });

        return true;
    }

    public static function actualizarFechaPago(
        int $idCuotaPago,
        int $idLegajo,
        int $idCuotaGenerada,
        \Carbon\CarbonInterface $nuevaFecha,
    ): bool {
        $registro = GestionAranceles::cuotaDelLegajo($idCuotaGenerada, $idLegajo);
        if ($registro === null) {
            return false;
        }

        $pago = CuotaPago::query()
            ->whereKey($idCuotaPago)
            ->where('idCuotasGeneradas', $idCuotaGenerada)
            ->first();

        if ($pago === null) {
            return false;
        }

        $fechaNueva = $nuevaFecha->copy()->startOfDay();

        DB::transaction(function () use ($pago, $registro, $fechaNueva): void {
            $locked = CuotaGenerada::query()->whereKey($registro->id)->lockForUpdate()->firstOrFail();

            $pago->fechhora = $fechaNueva->format('Y-m-d H:i:s');
            $pago->save();

            self::sincronizarFechaPagoCuota($locked);
            $locked->save();
        });

        return true;
    }

    private static function sincronizarFechaPagoCuota(CuotaGenerada $registro): void
    {
        $ultimaFecha = CuotaPago::query()
            ->where('idCuotasGeneradas', (int) $registro->id)
            ->orderByDesc('fechhora')
            ->orderByDesc('id')
            ->value('fechhora');

        $registro->fechaPago = $ultimaFecha !== null && trim((string) $ultimaFecha) !== ''
            ? $ultimaFecha
            : null;
    }
}
