<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Models\PlanillaDescargaCuota;
use App\Models\RendicionRoela;
use Illuminate\Support\Facades\DB;

/**
 * Procesa un archivo de rendición SIRO y persiste registros en rendicionesroela.
 */
final class SiroDescargaRendicionArchivo
{
    /**
     * @return array{resumen: SiroDescargaRendicionResumen, planilla: PlanillaDescargaCuota}
     */
    public static function procesar(
        PlanillaDescargaCuota $planilla,
        string $contenido,
        int $idTerlec,
    ): array {
        $resumen = new SiroDescargaRendicionResumen;
        $lineas = preg_split('/\R/', $contenido) ?: [];
        $idsPagoVistos = [];
        $fechasAcred = [];
        $nroPlanilla = (int) $planilla->nroPlanilla;
        $nombreArchivo = trim((string) $planilla->nombreArchivo);

        DB::transaction(function () use (
            $lineas,
            $planilla,
            $idTerlec,
            $nroPlanilla,
            $nombreArchivo,
            &$resumen,
            &$idsPagoVistos,
            &$fechasAcred,
        ): void {
            foreach ($lineas as $indice => $lineaCruda) {
                $lineaCruda = rtrim((string) $lineaCruda, "\r\n");
                if ($lineaCruda === '') {
                    continue;
                }

                $linea = SiroDescargaRendicionLinea::parsear($lineaCruda);
                if ($linea === null) {
                    $resumen->omitidos++;
                    $resumen->agregarAdvertencia('Línea '.($indice + 1).': formato inválido (menos de '.SiroDescargaRendicionLinea::LARGO_MINIMO.' caracteres).');

                    continue;
                }

                $idPago = (string) ($linea['idPagoSiro'] ?? '');
                if ($idPago !== '' && $idPago !== '0000000000') {
                    if (isset($idsPagoVistos[$idPago])) {
                        $resumen->omitidos++;
                        $resumen->agregarAdvertencia('Id de pago SIRO duplicado en el archivo: '.$idPago.'.');

                        continue;
                    }
                    $idsPagoVistos[$idPago] = true;

                    $yaExiste = RendicionRoela::query()
                        ->where('cadenaPago', 'like', '%'.$idPago.'%')
                        ->where('impactado', 1)
                        ->exists();
                    if ($yaExiste) {
                        $resumen->omitidos++;
                        $resumen->agregarAdvertencia('El pago SIRO '.$idPago.' ya fue impactado anteriormente.');

                        continue;
                    }
                }

                $match = SiroDescargaRendicionCupon::resolver($linea, $idTerlec);
                $cuotaGenerada = $match['cuotaGenerada'];
                if ($cuotaGenerada === null) {
                    $resumen->omitidos++;
                    foreach ($match['advertencias'] as $adv) {
                        $resumen->agregarAdvertencia('Línea '.($indice + 1).': '.$adv);
                    }

                    continue;
                }

                $cuotaGenerada->loadMissing(['cuota', 'legajo', 'curso', 'beca']);

                $montos = SiroDescargaRendicionCalculo::calcular($linea, $cuotaGenerada, $match['cupon']);
                $advertencias = array_merge($match['advertencias'], $montos['advertencias']);

                $fechaPago = SiroDescargaRendicionLinea::fechaDesdeSiro((string) $linea['fechaPago']);
                $fechaAcred = SiroDescargaRendicionLinea::fechaDesdeSiro((string) $linea['fechaAcreditacion']);
                $fechVenc1 = SiroDescargaRendicionLinea::fechaDesdeSiro((string) $linea['fechVenc1']);

                if ($fechaAcred !== null) {
                    $fechasAcred[] = $fechaAcred;
                }

                $obs = self::obsDesdeAdvertencias($advertencias);

                RendicionRoela::query()->create([
                    'fechaPago' => $fechaPago,
                    'fechaAcreditacion' => $fechaAcred,
                    'idCuotastipopago' => SiroDescargaRendicionCanal::idCuotastipopago((string) $linea['canalAbrev']),
                    'idLegajos' => (int) $cuotaGenerada->idLegajos,
                    'nroPlanilla' => $nroPlanilla,
                    'idCuotas' => (int) $cuotaGenerada->idCuotas,
                    'fechVenc1' => $fechVenc1 ?? $cuotaGenerada->venc1?->format('Y-m-d'),
                    'importe' => $montos['importe'],
                    'pagado' => $montos['pagado'],
                    'interes' => $montos['interes'],
                    'bonificacion' => $montos['bonificacion'],
                    'nombreArchivo' => $nombreArchivo,
                    'cadenaPago' => (string) $linea['cadenaPago'],
                    'idCuotasbecas' => (int) ($cuotaGenerada->idCuotasbecas ?? 0),
                    'idCuotasgeneradas' => (int) $cuotaGenerada->id,
                    'impactado' => 0,
                    'idCursos' => (int) ($cuotaGenerada->idCursos ?? 0),
                    'obs' => $obs,
                ]);

                $resumen->procesados++;
                $resumen->montoPagado = round($resumen->montoPagado + $montos['pagado'], 2);

                foreach ($advertencias as $adv) {
                    $resumen->agregarAdvertencia($adv);
                }
            }

            if ($fechasAcred !== []) {
                sort($fechasAcred);
                $planilla->desde = $fechasAcred[0];
                $planilla->hasta = $fechasAcred[array_key_last($fechasAcred)];
            }

            $planilla->impactado = 0;
            $planilla->save();
        });

        $planilla->refresh();

        return [
            'resumen' => $resumen,
            'planilla' => $planilla,
        ];
    }

    /**
     * @param  list<string>  $advertencias
     */
    private static function obsDesdeAdvertencias(array $advertencias): ?string
    {
        $texto = implode(' | ', array_slice($advertencias, 0, 3));

        return $texto !== '' ? mb_substr($texto, 0, 500) : null;
    }
}
