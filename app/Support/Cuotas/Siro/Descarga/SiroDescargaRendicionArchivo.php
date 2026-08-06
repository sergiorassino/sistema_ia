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
        ?string $nombreArchivoOrigen = null,
    ): array {
        $resumen = new SiroDescargaRendicionResumen;
        $lineas = preg_split('/\R/', $contenido) ?: [];
        $idsPagoVistos = [];
        $fechasAcred = [];
        $nroPlanilla = (int) $planilla->nroPlanilla;
        $nombreArchivo = self::resolverNombreArchivo($planilla, $nombreArchivoOrigen);
        $indicePlanilla = self::indiceRegistrosPlanilla($nroPlanilla);

        DB::transaction(function () use (
            $lineas,
            $planilla,
            $idTerlec,
            $nroPlanilla,
            $nombreArchivo,
            &$resumen,
            &$idsPagoVistos,
            &$fechasAcred,
            &$indicePlanilla,
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
                    $resumen->agregarRegistroArchivo([
                        'linea' => $indice + 1,
                        'canal' => '—',
                        'idFacturaBuscado' => '—',
                        'modalidadIdentificacion' => '—',
                        'estado' => 'omitido',
                        'detalle' => 'Formato inválido.',
                    ]);

                    continue;
                }

                $resolucion = SiroDescargaRendicionIdFactura::resolucionDesdeLinea($linea, $idTerlec);
                $idFacturaBuscado = $resolucion['idFactura'] ?? '—';
                $modalidadIdentificacion = $resolucion['modalidadEtiqueta'] !== ''
                    ? $resolucion['modalidadEtiqueta']
                    : '—';
                $canal = trim((string) ($linea['canalAbrev'] ?? ''));
                $canalEtiqueta = $canal !== '' ? $canal : '—';

                // Canales no presentes en cuotastipopago.abrev = rechazo SIRO (BPR, DDR, MCR, VSR, …).
                // Se informan en el modal de carga y no se persisten en rendicionesroela.
                if (! SiroDescargaRendicionCanal::esMedioPagoConocido($canal)) {
                    $detalleRechazo = SiroDescargaRendicionCanal::detalleRechazoCanal(
                        $canal,
                        (string) ($linea['textoTrasCanal'] ?? ''),
                    );
                    $resumen->rechazos++;
                    $resumen->agregarAdvertencia('Línea '.($indice + 1).': '.$detalleRechazo);
                    $resumen->agregarRegistroArchivo([
                        'linea' => $indice + 1,
                        'canal' => $canalEtiqueta,
                        'idFacturaBuscado' => $idFacturaBuscado,
                        'modalidadIdentificacion' => $modalidadIdentificacion,
                        'estado' => 'rechazo',
                        'detalle' => $detalleRechazo,
                    ]);

                    continue;
                }

                $motivoDuplicadoPlanilla = self::motivoDuplicadoEnPlanilla($linea, $indicePlanilla);
                if ($motivoDuplicadoPlanilla !== null) {
                    $resumen->omitidos++;
                    $resumen->agregarAdvertencia($motivoDuplicadoPlanilla);
                    $resumen->agregarRegistroArchivo([
                        'linea' => $indice + 1,
                        'canal' => $canalEtiqueta,
                        'idFacturaBuscado' => $idFacturaBuscado,
                        'modalidadIdentificacion' => $modalidadIdentificacion,
                        'estado' => 'omitido',
                        'detalle' => 'Registro ya cargado en esta planilla.',
                    ]);

                    continue;
                }

                $idPago = (string) ($linea['idPagoSiro'] ?? '');
                if ($idPago !== '' && $idPago !== '0000000000') {
                    if (isset($idsPagoVistos[$idPago])) {
                        $resumen->omitidos++;
                        $resumen->agregarAdvertencia('Id de pago SIRO duplicado en el archivo: '.$idPago.'.');
                        $resumen->agregarRegistroArchivo([
                            'linea' => $indice + 1,
                            'canal' => $canalEtiqueta,
                            'idFacturaBuscado' => $idFacturaBuscado,
                            'modalidadIdentificacion' => $modalidadIdentificacion,
                            'estado' => 'omitido',
                            'detalle' => 'Id de pago SIRO duplicado.',
                        ]);

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
                        $resumen->agregarRegistroArchivo([
                            'linea' => $indice + 1,
                            'canal' => $canalEtiqueta,
                            'idFacturaBuscado' => $idFacturaBuscado,
                            'modalidadIdentificacion' => $modalidadIdentificacion,
                            'estado' => 'omitido',
                            'detalle' => 'Pago ya impactado anteriormente.',
                        ]);

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
                    $resumen->agregarRegistroArchivo([
                        'linea' => $indice + 1,
                        'canal' => $canalEtiqueta,
                        'idFacturaBuscado' => $idFacturaBuscado,
                        'modalidadIdentificacion' => $match['modalidadIdentificacion'] !== ''
                            ? $match['modalidadIdentificacion']
                            : $modalidadIdentificacion,
                        'estado' => 'no_encontrado',
                        'detalle' => $match['advertencias'][0] ?? 'Cupón no encontrado.',
                    ]);

                    continue;
                }

                $idEncontrado = (string) ($match['cupon']?->id_factura ?? '');
                $detalleEncontrado = $idEncontrado !== '' && $idEncontrado !== $idFacturaBuscado
                    ? 'Encontrado con id_factura '.$idEncontrado
                    : null;

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

                self::registrarEnIndicePlanilla($linea, $indicePlanilla);

                $resumen->procesados++;
                $resumen->montoPagado = round($resumen->montoPagado + $montos['pagado'], 2);
                $resumen->agregarRegistroArchivo([
                    'linea' => $indice + 1,
                    'canal' => $canalEtiqueta,
                    'idFacturaBuscado' => $idFacturaBuscado,
                    'modalidadIdentificacion' => $match['modalidadIdentificacion'] !== ''
                        ? $match['modalidadIdentificacion']
                        : $modalidadIdentificacion,
                    'estado' => 'encontrado',
                    'detalle' => $detalleEncontrado,
                ]);

                foreach ($advertencias as $adv) {
                    $resumen->agregarAdvertencia($adv);
                }
            }

            if ($fechasAcred !== []) {
                sort($fechasAcred);
                $planilla->desde = $fechasAcred[0];
                $planilla->hasta = $fechasAcred[array_key_last($fechasAcred)];
            }

            $planilla->nombreArchivo = $nombreArchivo;
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

    public static function normalizarNombreArchivo(string $nombre): string
    {
        return mb_substr(trim($nombre), 0, 50);
    }

    private static function resolverNombreArchivo(PlanillaDescargaCuota $planilla, ?string $nombreArchivoOrigen): string
    {
        $subido = self::normalizarNombreArchivo((string) $nombreArchivoOrigen);
        if ($subido !== '') {
            return $subido;
        }

        return self::normalizarNombreArchivo((string) $planilla->nombreArchivo);
    }

    /**
     * Índice de pagos ya descargados en la planilla (cargas parciales anteriores).
     *
     * @return array{cadenas: array<string, true>, idsPago: array<string, true>}
     */
    private static function indiceRegistrosPlanilla(int $nroPlanilla): array
    {
        $indice = [
            'cadenas' => [],
            'idsPago' => [],
        ];

        $cadenas = RendicionRoela::query()
            ->where('nroPlanilla', $nroPlanilla)
            ->pluck('cadenaPago');

        foreach ($cadenas as $cadena) {
            $cadena = (string) $cadena;
            if ($cadena === '') {
                continue;
            }

            $indice['cadenas'][$cadena] = true;

            $parsed = SiroDescargaRendicionLinea::parsear($cadena);
            if ($parsed === null) {
                continue;
            }

            $idPago = (string) ($parsed['idPagoSiro'] ?? '');
            if ($idPago !== '' && $idPago !== '0000000000') {
                $indice['idsPago'][$idPago] = true;
            }
        }

        return $indice;
    }

    /**
     * @param  array{cadenas: array<string, true>, idsPago: array<string, true>}  $indicePlanilla
     * @param  array{idPagoSiro: string, cadenaPago: string}  $linea
     */
    private static function motivoDuplicadoEnPlanilla(array $linea, array $indicePlanilla): ?string
    {
        $cadena = (string) ($linea['cadenaPago'] ?? '');
        if ($cadena !== '' && isset($indicePlanilla['cadenas'][$cadena])) {
            $idPago = (string) ($linea['idPagoSiro'] ?? '');
            if ($idPago !== '' && $idPago !== '0000000000') {
                return 'El pago SIRO '.$idPago.' ya fue cargado en esta planilla.';
            }

            return 'Registro ya cargado en esta planilla.';
        }

        $idPago = (string) ($linea['idPagoSiro'] ?? '');
        if ($idPago !== '' && $idPago !== '0000000000' && isset($indicePlanilla['idsPago'][$idPago])) {
            return 'El pago SIRO '.$idPago.' ya fue cargado en esta planilla.';
        }

        return null;
    }

    /**
     * @param  array{cadenas: array<string, true>, idsPago: array<string, true>}  $indicePlanilla
     * @param  array{idPagoSiro: string, cadenaPago: string}  $linea
     */
    private static function registrarEnIndicePlanilla(array $linea, array &$indicePlanilla): void
    {
        $cadena = (string) ($linea['cadenaPago'] ?? '');
        if ($cadena !== '') {
            $indicePlanilla['cadenas'][$cadena] = true;
        }

        $idPago = (string) ($linea['idPagoSiro'] ?? '');
        if ($idPago !== '' && $idPago !== '0000000000') {
            $indicePlanilla['idsPago'][$idPago] = true;
        }
    }
}
