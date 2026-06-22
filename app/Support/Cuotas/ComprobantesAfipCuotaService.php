<?php

namespace App\Support\Cuotas;

use App\Models\ComprobanteAfip;
use App\Models\CuotaGenerada;
use App\Models\CuotaPago;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Consulta y emisión tardía de comprobantes AFIP vinculados a un pago imputado.
 */
final class ComprobantesAfipCuotaService
{
    /**
     * @return Collection<int, ComprobanteAfip>
     */
    public static function comprobantes(int $idCuotaPago, int $idLegajo, int $idCuotaGenerada): Collection
    {
        if (! self::moduloDisponible() || self::pagoParaGestion($idCuotaPago, $idLegajo, $idCuotaGenerada) === null) {
            return collect();
        }

        return self::comprobantesDelPago($idCuotaPago);
    }

    public static function moduloDisponible(): bool
    {
        return tenantCuotasFacturacionAfipHabilitada()
            && Schema::hasTable('comprobanteafip');
    }

    public static function esFactura(ComprobanteAfip $comprobante): bool
    {
        $config = tenantCuotasFacturacionAfipConfig();
        $tipoFactura = (int) ($config['cbte_tipo'] ?? 15);

        return (int) ($comprobante->tipoComprobante ?? 0) === $tipoFactura;
    }

    public static function esNotaCredito(ComprobanteAfip $comprobante): bool
    {
        $config = tenantCuotasFacturacionAfipConfig();
        $tipoNc = (int) ($config['nota_credito_tipo'] ?? 12);

        return (int) ($comprobante->tipoComprobante ?? 0) === $tipoNc;
    }

    public static function idFacturaAnuladaPor(ComprobanteAfip $notaCredito): ?int
    {
        if (! self::esNotaCredito($notaCredito)) {
            return null;
        }

        $id = (int) trim((string) ($notaCredito->subConceptos ?? ''));

        return $id > 0 ? $id : null;
    }

    /**
     * @return Collection<int, int>
     */
    public static function idsFacturasAnuladas(int $idCuotaPago): Collection
    {
        return self::comprobantesDelPago($idCuotaPago)
            ->filter(fn (ComprobanteAfip $c) => self::esNotaCredito($c))
            ->map(fn (ComprobanteAfip $c) => self::idFacturaAnuladaPor($c))
            ->filter(fn (?int $id) => $id !== null && $id > 0)
            ->values();
    }

    public static function facturaVigente(int $idCuotaPago): ?ComprobanteAfip
    {
        $anuladas = self::idsFacturasAnuladas($idCuotaPago);

        return self::comprobantesDelPago($idCuotaPago)
            ->first(function (ComprobanteAfip $c) use ($anuladas): bool {
                if (! self::esFactura($c)) {
                    return false;
                }

                return ! $anuladas->contains((int) $c->idComprobanteAfip);
            });
    }

    /**
     * Factura AFIP vigente vinculada a la cuota generada (pago simple o cobro múltiple).
     */
    public static function facturaVigentePorCuotaGenerada(int $idCuotaGenerada): ?ComprobanteAfip
    {
        if (! self::moduloDisponible() || $idCuotaGenerada <= 0) {
            return null;
        }

        $comprobantes = self::comprobantesPorCuotaGenerada($idCuotaGenerada);
        if ($comprobantes->isEmpty()) {
            return null;
        }

        $idsPagos = CuotaPago::query()
            ->where('idCuotasGeneradas', $idCuotaGenerada)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $anuladas = collect();
        foreach ($idsPagos as $idPago) {
            $anuladas = $anuladas->merge(self::idsFacturasAnuladas($idPago));
        }
        $anuladas = $anuladas->unique()->values();

        return $comprobantes->first(function (ComprobanteAfip $c) use ($anuladas): bool {
            if (! self::esFactura($c)) {
                return false;
            }

            return ! $anuladas->contains((int) $c->idComprobanteAfip);
        });
    }

    /**
     * @param  list<int>  $idsCuotasGeneradas
     * @return array<int, ComprobanteAfip>
     */
    public static function facturasVigentesPorCuotasGeneradas(array $idsCuotasGeneradas): array
    {
        if (! self::moduloDisponible() || $idsCuotasGeneradas === []) {
            return [];
        }

        $idsCuotasGeneradas = array_values(array_unique(array_filter(
            array_map('intval', $idsCuotasGeneradas),
            fn (int $id) => $id > 0,
        )));

        $map = [];
        foreach ($idsCuotasGeneradas as $idCuota) {
            $factura = self::facturaVigentePorCuotaGenerada($idCuota);
            if ($factura !== null) {
                $map[$idCuota] = $factura;
            }
        }

        return $map;
    }

    /**
     * El comprobante está vinculado a la cuota generada del estudiante (mismo criterio que el listado).
     */
    public static function comprobanteVinculadoACuotaDelEstudiante(
        int $idComprobanteAfip,
        int $idCuotaGenerada,
        int $idLegajo,
    ): bool {
        if (! self::moduloDisponible()
            || $idComprobanteAfip <= 0
            || $idCuotaGenerada <= 0
            || $idLegajo <= 0) {
            return false;
        }

        $perteneceAlLegajo = CuotaGenerada::query()
            ->whereKey($idCuotaGenerada)
            ->where('idLegajos', $idLegajo)
            ->exists();

        if (! $perteneceAlLegajo) {
            return false;
        }

        return self::comprobantesPorCuotaGenerada($idCuotaGenerada)
            ->contains(fn (ComprobanteAfip $c) => (int) $c->idComprobanteAfip === $idComprobanteAfip);
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public static function puedeGenerarFactura(int $idCuotaPago, int $idLegajo, int $idCuotaGenerada): array
    {
        if (! self::moduloDisponible()) {
            return ['ok' => false, 'mensaje' => 'La facturación AFIP no está habilitada para este colegio.'];
        }

        $pago = self::pagoParaGestion($idCuotaPago, $idLegajo, $idCuotaGenerada);
        if ($pago === null) {
            return ['ok' => false, 'mensaje' => 'Pago no encontrado.'];
        }

        if (self::facturaVigente($idCuotaPago) !== null) {
            return ['ok' => false, 'mensaje' => 'Este pago ya tiene una factura AFIP vigente. Debe emitir una nota de crédito antes de facturar nuevamente.'];
        }

        if (self::importeFacturable($pago) <= 0) {
            return ['ok' => false, 'mensaje' => 'El pago no tiene importe para facturar.'];
        }

        return ['ok' => true, 'mensaje' => ''];
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public static function puedeEmitirNotaCredito(int $idCuotaPago, int $idLegajo, int $idCuotaGenerada): array
    {
        if (! self::moduloDisponible()) {
            return ['ok' => false, 'mensaje' => 'La facturación AFIP no está habilitada para este colegio.'];
        }

        if (self::pagoParaGestion($idCuotaPago, $idLegajo, $idCuotaGenerada) === null) {
            return ['ok' => false, 'mensaje' => 'Pago no encontrado.'];
        }

        if (self::facturaVigente($idCuotaPago) === null) {
            return ['ok' => false, 'mensaje' => 'No hay factura AFIP vigente para anular con nota de crédito.'];
        }

        return ['ok' => true, 'mensaje' => ''];
    }

    public static function importeFacturable(CuotaPago $pago): float
    {
        $importe = round((float) ($pago->importe ?? 0), 2);
        $interes = round((float) ($pago->interes ?? 0), 2);
        $bonificacion = round((float) ($pago->bonificacion ?? 0), 2);

        return round(max(0, $importe + $interes - $bonificacion), 2);
    }

    public static function etiquetaTipo(ComprobanteAfip $comprobante): string
    {
        if (self::esNotaCredito($comprobante)) {
            return 'Nota de crédito C';
        }

        if (self::esFactura($comprobante)) {
            return 'Factura / Recibo C';
        }

        return 'Comprobante AFIP';
    }

    public static function numeroFormateado(ComprobanteAfip $comprobante): string
    {
        $ptoVta = (int) ($comprobante->puntoVenta ?? 0);
        $nro = (int) ($comprobante->nroRecibo ?? 0);

        return str_pad((string) $ptoVta, 4, '0', STR_PAD_LEFT)
            .'-'
            .str_pad((string) $nro, 8, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{ok: bool, mensaje: string, idComprobanteAfip?: int}
     */
    public static function generarFactura(int $idCuotaPago, int $idLegajo, int $idCuotaGenerada): array
    {
        $validacion = self::puedeGenerarFactura($idCuotaPago, $idLegajo, $idCuotaGenerada);
        if (! $validacion['ok']) {
            return $validacion;
        }

        $pago = self::pagoParaGestion($idCuotaPago, $idLegajo, $idCuotaGenerada);
        if ($pago === null) {
            return ['ok' => false, 'mensaje' => 'Pago no encontrado.'];
        }

        $registro = GestionAranceles::cuotaDelLegajo($idCuotaGenerada, $idLegajo);
        if ($registro === null) {
            return ['ok' => false, 'mensaje' => 'Cuota no encontrada.'];
        }

        $registro->loadMissing(['cuota', 'terlec']);

        return FacturacionAfipImputacionPago::facturar(
            $pago,
            $registro,
            $idLegajo,
            self::importeFacturable($pago),
        );
    }

    /**
     * @return array{ok: bool, mensaje: string, idComprobanteAfip?: int}
     */
    public static function emitirNotaCredito(int $idCuotaPago, int $idLegajo, int $idCuotaGenerada): array
    {
        $validacion = self::puedeEmitirNotaCredito($idCuotaPago, $idLegajo, $idCuotaGenerada);
        if (! $validacion['ok']) {
            return $validacion;
        }

        $registro = GestionAranceles::cuotaDelLegajo($idCuotaGenerada, $idLegajo);
        if ($registro === null) {
            return ['ok' => false, 'mensaje' => 'Cuota no encontrada.'];
        }

        $factura = self::facturaVigente($idCuotaPago);
        if ($factura === null) {
            return ['ok' => false, 'mensaje' => 'No hay factura AFIP vigente.'];
        }

        $registro->loadMissing(['cuota', 'terlec']);

        return FacturacionAfipImputacionPago::emitirNotaCredito(
            $registro,
            $idLegajo,
            $factura,
        );
    }

    public static function pagoParaGestion(int $idCuotaPago, int $idLegajo, int $idCuotaGenerada): ?CuotaPago
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
     * Comprobantes AFIP asociados a una cuota (idCbteAsoc, pago principal o cobro múltiple).
     *
     * @return Collection<int, ComprobanteAfip>
     */
    private static function comprobantesPorCuotaGenerada(int $idCuotaGenerada): Collection
    {
        $registro = CuotaGenerada::query()
            ->with('cuota:id,nombre')
            ->whereKey($idCuotaGenerada)
            ->first();
        $nombreCuota = mb_strtoupper(trim((string) ($registro?->cuota?->nombre ?? '')));

        return ComprobanteAfip::query()
            ->where(function (Builder $q) use ($idCuotaGenerada, $nombreCuota): void {
                $q->where('idCbteAsoc', $idCuotaGenerada)
                    ->orWhereIn('idCuotasPagos', function ($sub) use ($idCuotaGenerada): void {
                        $sub->select('id')
                            ->from('cuotaspagos')
                            ->where('idCuotasGeneradas', $idCuotaGenerada);
                    })
                    ->orWhereExists(function ($sub) use ($idCuotaGenerada): void {
                        $sub->selectRaw('1')
                            ->from('cuotaspagos as cp_batch')
                            ->where('cp_batch.idCuotasGeneradas', $idCuotaGenerada)
                            ->whereRaw('FIND_IN_SET(cp_batch.id, comprobanteafip.saldoRestante)');
                    });

                if ($nombreCuota !== '') {
                    $q->orWhere(function (Builder $sub) use ($nombreCuota): void {
                        $sub->where('subConceptos', $nombreCuota)
                            ->orWhere('subConceptos', 'like', $nombreCuota.'|%')
                            ->orWhere('subConceptos', 'like', '%|'.$nombreCuota.'|%')
                            ->orWhere('subConceptos', 'like', '%|'.$nombreCuota);
                    });
                }
            })
            ->orderByDesc('idComprobanteAfip')
            ->get();
    }

    /**
     * @return Collection<int, ComprobanteAfip>
     */
    private static function comprobantesDelPago(int $idCuotaPago): Collection
    {
        $config = tenantCuotasFacturacionAfipConfig();
        $tipoNc = (int) ($config['nota_credito_tipo'] ?? 12);

        return ComprobanteAfip::query()
            ->where(function (Builder $q) use ($idCuotaPago, $tipoNc): void {
                $q->vinculadoAPago($idCuotaPago)
                    ->orWhere(function (Builder $nc) use ($idCuotaPago, $tipoNc): void {
                        // NC de factura de cobro múltiple: subConceptos = idComprobanteAfip de la factura.
                        $nc->where('tipoComprobante', $tipoNc)
                            ->whereExists(function ($sub) use ($idCuotaPago): void {
                                $sub->from('comprobanteafip as factura_afip')
                                    ->selectRaw('1')
                                    ->whereColumn(
                                        'factura_afip.idComprobanteAfip',
                                        'comprobanteafip.subConceptos',
                                    )
                                    ->where(function ($f) use ($idCuotaPago): void {
                                        $f->where('factura_afip.idCuotasPagos', $idCuotaPago)
                                            ->orWhereRaw(
                                                'FIND_IN_SET(?, factura_afip.saldoRestante)',
                                                [$idCuotaPago],
                                            );
                                    });
                            });
                    });
            })
            ->orderByDesc('idComprobanteAfip')
            ->get();
    }
}
