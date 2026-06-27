<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Models\CuotaGenerada;
use App\Models\CuotaPago;
use App\Models\PlanillaDescargaCuota;
use App\Models\RendicionRoela;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Impacta pagos de una planilla en cuotaspagos y cuotasgeneradas.
 */
final class SiroDescargaRendicionImpacto
{
    /**
     * @return SiroDescargaRendicionResumen
     */
    public static function impactarPlanilla(PlanillaDescargaCuota $planilla, int $idTerlec): SiroDescargaRendicionResumen
    {
        $resumen = new SiroDescargaRendicionResumen;
        $nroPlanilla = (int) $planilla->nroPlanilla;

        $rendiciones = RendicionRoela::query()
            ->where('nroPlanilla', $nroPlanilla)
            ->orderBy('id')
            ->get();

        if ($rendiciones->isEmpty()) {
            $resumen->agregarError('La planilla no tiene pagos descargados para impactar.');

            return $resumen;
        }

        DB::transaction(function () use ($rendiciones, $planilla, $idTerlec, &$resumen): void {
            foreach ($rendiciones as $rendicion) {
                $resumen->procesados++;

                if ((int) ($rendicion->impactado ?? 0) === 1) {
                    $resumen->omitidos++;
                    $resumen->noImpactados++;

                    continue;
                }

                $idCuotaGen = (int) ($rendicion->idCuotasgeneradas ?? 0);
                if ($idCuotaGen <= 0) {
                    self::marcarNoImpactado($rendicion, 'Sin cuota generada asociada.');
                    $resumen->noImpactados++;
                    $resumen->agregarAdvertencia('Rendición #'.$rendicion->id.': sin cuota generada.');

                    continue;
                }

                $registro = CuotaGenerada::query()
                    ->where('id', $idCuotaGen)
                    ->where('idTerlec', $idTerlec)
                    ->lockForUpdate()
                    ->first();

                if ($registro === null) {
                    self::marcarNoImpactado($rendicion, 'Cuota no encontrada en el ciclo activo.');
                    $resumen->noImpactados++;
                    $resumen->agregarAdvertencia('Rendición #'.$rendicion->id.': cuota fuera del ciclo lectivo.');

                    continue;
                }

                $saldo = round((float) ($rendicion->importe ?? 0), 2);
                $pagado = round((float) ($rendicion->pagado ?? 0), 2);
                $interes = round((float) ($rendicion->interes ?? 0), 2);
                $bonificacion = round((float) ($rendicion->bonificacion ?? 0), 2);
                $faltapaAntes = round((float) ($registro->faltapa ?? 0), 2);

                if ($pagado <= 0) {
                    self::marcarNoImpactado($rendicion, 'Importe pagado inválido.');
                    $resumen->noImpactados++;

                    continue;
                }

                $advertencias = [];
                $totalRendicion = round($saldo + $interes - $bonificacion, 2);
                if ($saldo > $faltapaAntes + 0.02 && $faltapaAntes >= 0) {
                    $advertencias[] = 'Capital imputado superior al saldo adeudado.';
                }
                if ($pagado > $totalRendicion + 0.02 && $faltapaAntes > 0) {
                    $advertencias[] = 'Pago superior al total calculado para la rendición.';
                }

                $duplicado = CuotaPago::query()
                    ->where('idCuotasGeneradas', (int) $registro->id)
                    ->where('nombreArchivo', (string) $rendicion->nombreArchivo)
                    ->where('importe', $saldo)
                    ->whereDate('fechhora', $rendicion->fechaPago?->format('Y-m-d') ?? '')
                    ->exists();
                if ($duplicado) {
                    self::marcarNoImpactado($rendicion, 'Posible pago duplicado (ya existe en cuotaspagos).');
                    $resumen->noImpactados++;
                    $resumen->agregarAdvertencia('Rendición #'.$rendicion->id.': posible pago duplicado.');

                    continue;
                }

                $fechaPago = $rendicion->fechaPago instanceof Carbon
                    ? $rendicion->fechaPago
                    : Carbon::parse((string) ($rendicion->fechaPago ?? now()))->startOfDay();

                CuotaPago::query()->create([
                    'idCuotasGeneradas' => (int) $registro->id,
                    'idCuotastipopago' => (int) ($rendicion->idCuotastipopago ?? 8),
                    'fechhora' => $fechaPago->format('Y-m-d H:i:s'),
                    'importe' => $saldo,
                    'bonificacion' => $bonificacion,
                    'interes' => $interes,
                    'nombreArchivo' => (string) ($rendicion->nombreArchivo ?? $planilla->nombreArchivo ?? 'SIRO'),
                    'cadenaPago' => (string) ($rendicion->cadenaPago ?? ''),
                ]);

                $aPagar = round($saldo + $interes - $bonificacion, 2);
                $nuevoPagado = round((float) $registro->pagado + $aPagar, 2);
                $nuevoFaltapa = round((float) $registro->faltapa - $saldo, 2);
                $nuevoInteres = round((float) $registro->interes + $interes, 2);
                $nuevoBonif = round((float) $registro->bonificacion + $bonificacion, 2);

                if ($nuevoFaltapa < -0.01) {
                    $advertencias[] = 'Saldo negativo tras el impacto ($'.number_format($nuevoFaltapa, 2, ',', '.').').';
                }

                $registro->pagado = $nuevoPagado;
                $registro->faltapa = $nuevoFaltapa;
                $registro->interes = $nuevoInteres;
                $registro->bonificacion = $nuevoBonif;
                $registro->fechaPago = $fechaPago->format('Y-m-d H:i:s');
                $registro->avisoPago = 0;
                $registro->save();

                $obs = self::combinarObs((string) ($rendicion->obs ?? ''), $advertencias);
                $rendicion->impactado = 1;
                $rendicion->obs = $obs !== '' ? $obs : null;
                $rendicion->save();

                $resumen->impactados++;
                $resumen->montoImpactado = round($resumen->montoImpactado + $aPagar, 2);

                foreach ($advertencias as $adv) {
                    $resumen->agregarAdvertencia($adv);
                }
            }

            $pendientes = RendicionRoela::query()
                ->where('nroPlanilla', (int) $planilla->nroPlanilla)
                ->where('impactado', 0)
                ->count();

            $planilla->impactado = $pendientes === 0 ? 1 : 0;
            $planilla->save();
        });

        return $resumen;
    }

    private static function marcarNoImpactado(RendicionRoela $rendicion, string $motivo): void
    {
        $rendicion->impactado = 0;
        $rendicion->obs = self::combinarObs((string) ($rendicion->obs ?? ''), [$motivo]);
        $rendicion->save();
    }

    /**
     * @param  list<string>  $nuevas
     */
    private static function combinarObs(string $actual, array $nuevas): string
    {
        $partes = array_filter(array_merge(
            $actual !== '' ? explode(' | ', $actual) : [],
            $nuevas,
        ));

        return mb_substr(implode(' | ', array_unique($partes)), 0, 500);
    }
}
