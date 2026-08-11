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
        /** @var array<int, int> idCuotasgeneradas => nro de registro en el archivo */
        $cuotasVistasEnArchivo = [];
        $fechasAcred = [];
        $nroPlanilla = (int) $planilla->nroPlanilla;
        $nombreArchivo = self::resolverNombreArchivo($planilla, $nombreArchivoOrigen);
        $indicePlanilla = self::indiceRegistrosPlanilla($nroPlanilla);

        /** @var list<array<string, mixed>> $pendientes */
        $pendientes = [];
        $bloqueosCupon = 0;

        foreach ($lineas as $indice => $lineaCruda) {
            $lineaCruda = rtrim((string) $lineaCruda, "\r\n");
            if ($lineaCruda === '') {
                continue;
            }

            $nroRegistro = $indice + 1;
            $linea = SiroDescargaRendicionLinea::parsear($lineaCruda);
            if ($linea === null) {
                $resumen->omitidos++;
                $resumen->agregarAdvertencia(
                    'Formato inválido (menos de '.SiroDescargaRendicionLinea::LARGO_MINIMO.' caracteres).',
                    $nroRegistro,
                );
                $resumen->agregarRegistroArchivo([
                    'linea' => $nroRegistro,
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
                $resumen->agregarAdvertencia($detalleRechazo, $nroRegistro);
                $resumen->agregarRegistroArchivo([
                    'linea' => $nroRegistro,
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
                $resumen->agregarAdvertencia($motivoDuplicadoPlanilla, $nroRegistro);
                $resumen->agregarRegistroArchivo([
                    'linea' => $nroRegistro,
                    'canal' => $canalEtiqueta,
                    'idFacturaBuscado' => $idFacturaBuscado,
                    'modalidadIdentificacion' => $modalidadIdentificacion,
                    'estado' => 'omitido',
                    'detalle' => 'Registro ya cargado en esta planilla.',
                ]);

                continue;
            }

            $avisosPagoRepetido = [];
            $idPago = (string) ($linea['idPagoSiro'] ?? '');
            if ($idPago !== '' && $idPago !== '0000000000') {
                if (isset($idsPagoVistos[$idPago])) {
                    $avisosPagoRepetido[] = 'Id de pago SIRO duplicado en el archivo: '.$idPago.'; se registra igual (posible pago doble).';
                } else {
                    $idsPagoVistos[$idPago] = true;
                }

                $nroPlanillaPrevia = self::nroPlanillaImpactadoPorIdPagoSiro($idPago);
                if ($nroPlanillaPrevia !== null && $nroPlanillaPrevia !== $nroPlanilla) {
                    $avisosPagoRepetido[] = self::mensajePagoRepetidoPlanilla($idPago, $nroPlanillaPrevia);
                }
            }

            $match = SiroDescargaRendicionCupon::resolver($linea, $idTerlec);
            $cupon = $match['cupon'];
            $cuotaGenerada = $match['cuotaGenerada'];
            $modalidadMatch = $match['modalidadIdentificacion'] !== ''
                ? $match['modalidadIdentificacion']
                : $modalidadIdentificacion;

            if ($cupon === null || $cuotaGenerada === null) {
                $bloqueosCupon++;
                $detalle = $match['advertencias'][0]
                    ?? 'Sin cupón en cupones_a_pagar: no se descarga hasta resolver la referencia.';
                $mensaje = 'No se descarga el pago: '.$detalle
                    .' Resolvá la referencia en cupones_a_pagar y vuelva a cargar el archivo.';
                $resumen->omitidos++;
                $resumen->agregarError($mensaje, $nroRegistro);
                foreach ($avisosPagoRepetido as $aviso) {
                    $resumen->agregarAdvertencia($aviso, $nroRegistro);
                }
                $resumen->agregarRegistroArchivo([
                    'linea' => $nroRegistro,
                    'canal' => $canalEtiqueta,
                    'idFacturaBuscado' => $idFacturaBuscado,
                    'modalidadIdentificacion' => $modalidadMatch,
                    'estado' => 'no_encontrado',
                    'detalle' => $detalle,
                ]);

                continue;
            }

            $idCuotaGen = (int) $cuotaGenerada->id;
            if (isset($cuotasVistasEnArchivo[$idCuotaGen])) {
                $avisosPagoRepetido[] = 'La cuota ya tiene otro pago en este archivo (registro '
                    .$cuotasVistasEnArchivo[$idCuotaGen].'); posible pago doble.';
            }
            $cuotasVistasEnArchivo[$idCuotaGen] = $nroRegistro;

            $idEncontrado = (string) ($cupon->id_factura ?? '');
            $detalleMatch = trim((string) ($match['detalleMatch'] ?? ''));
            if ($avisosPagoRepetido !== []) {
                $detalleEncontrado = $avisosPagoRepetido[0];
            } elseif ($detalleMatch !== '') {
                $detalleEncontrado = $detalleMatch;
            } elseif ($idEncontrado !== '' && $idEncontrado !== $idFacturaBuscado) {
                $detalleEncontrado = 'Encontrado con id_factura '.$idEncontrado;
            } else {
                $detalleEncontrado = null;
            }

            $cuotaGenerada->loadMissing(['cuota', 'legajo', 'curso', 'beca']);

            $montos = SiroDescargaRendicionCalculo::calcular($linea, $cuotaGenerada, $cupon);
            if (! ($montos['descargable'] ?? false)) {
                $bloqueosCupon++;
                $detalle = $montos['advertencias'][0] ?? 'No se pudo desglosar el pago desde el cupón.';
                $resumen->omitidos++;
                $resumen->agregarError($detalle, $nroRegistro);
                foreach ($avisosPagoRepetido as $aviso) {
                    $resumen->agregarAdvertencia($aviso, $nroRegistro);
                }
                foreach ($match['advertencias'] as $adv) {
                    $resumen->agregarAdvertencia($adv, $nroRegistro);
                }
                $resumen->agregarRegistroArchivo([
                    'linea' => $nroRegistro,
                    'canal' => $canalEtiqueta,
                    'idFacturaBuscado' => $idFacturaBuscado,
                    'modalidadIdentificacion' => $modalidadMatch,
                    'estado' => 'no_encontrado',
                    'detalle' => $detalle,
                ]);

                continue;
            }

            $advertencias = array_merge($avisosPagoRepetido, $montos['advertencias'], $match['advertencias']);
            $fechaPago = SiroDescargaRendicionLinea::fechaDesdeSiro((string) $linea['fechaPago']);
            $fechaAcred = SiroDescargaRendicionLinea::fechaDesdeSiro((string) $linea['fechaAcreditacion']);
            $fechVenc1 = $cupon->fecha1venc?->format('Y-m-d')
                ?? SiroDescargaRendicionLinea::fechaDesdeSiro((string) $linea['fechVenc1'])
                ?? $cuotaGenerada->venc1?->format('Y-m-d');

            $pendientes[] = [
                'nroRegistro' => $nroRegistro,
                'linea' => $linea,
                'cuotaGenerada' => $cuotaGenerada,
                'cupon' => $cupon,
                'montos' => $montos,
                'avisosPagoRepetido' => $avisosPagoRepetido,
                'advertencias' => $advertencias,
                'fechaPago' => $fechaPago,
                'fechaAcred' => $fechaAcred,
                'fechVenc1' => $fechVenc1,
                'idFacturaBuscado' => $idFacturaBuscado,
                'modalidadIdentificacion' => $modalidadMatch,
                'canalEtiqueta' => $canalEtiqueta,
                'detalleEncontrado' => $detalleEncontrado,
            ];

            // Índice en memoria: evita duplicar la misma cadena/id dentro del mismo archivo.
            self::registrarEnIndicePlanilla($linea, $indicePlanilla);
        }

        if ($bloqueosCupon > 0) {
            $resumen->agregarError(
                'No se descargó ningún pago de este archivo: hay '.$bloqueosCupon
                .' registro(s) sin cupón resoluble en cupones_a_pagar (o desglose inválido).'
                .' Corrija esas referencias y vuelva a cargar.',
            );
            foreach ($pendientes as $pendiente) {
                $resumen->omitidos++;
                $resumen->agregarRegistroArchivo([
                    'linea' => $pendiente['nroRegistro'],
                    'canal' => $pendiente['canalEtiqueta'],
                    'idFacturaBuscado' => $pendiente['idFacturaBuscado'],
                    'modalidadIdentificacion' => $pendiente['modalidadIdentificacion'],
                    'estado' => 'omitido',
                    'detalle' => 'No descargado: hay cupones sin resolver en el archivo.',
                ]);
            }

            return [
                'resumen' => $resumen,
                'planilla' => $planilla->refresh(),
            ];
        }

        DB::transaction(function () use (
            $pendientes,
            $planilla,
            $nroPlanilla,
            $nombreArchivo,
            &$resumen,
            &$fechasAcred,
            &$indicePlanilla,
        ): void {
            foreach ($pendientes as $pendiente) {
                $linea = $pendiente['linea'];
                $cuotaGenerada = $pendiente['cuotaGenerada'];
                $montos = $pendiente['montos'];
                $fechaAcred = $pendiente['fechaAcred'];

                if ($fechaAcred !== null) {
                    $fechasAcred[] = $fechaAcred;
                }

                $obs = self::obsParaFormularioPlanilla(
                    $pendiente['avisosPagoRepetido'],
                    $montos['advertencias'],
                );

                RendicionRoela::query()->create([
                    'fechaPago' => $pendiente['fechaPago'],
                    'fechaAcreditacion' => $fechaAcred,
                    'idCuotastipopago' => SiroDescargaRendicionCanal::idCuotastipopago((string) $linea['canalAbrev']),
                    'idLegajos' => (int) $cuotaGenerada->idLegajos,
                    'nroPlanilla' => $nroPlanilla,
                    'idCuotas' => (int) $cuotaGenerada->idCuotas,
                    'fechVenc1' => $pendiente['fechVenc1'],
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
                    'linea' => $pendiente['nroRegistro'],
                    'canal' => $pendiente['canalEtiqueta'],
                    'idFacturaBuscado' => $pendiente['idFacturaBuscado'],
                    'modalidadIdentificacion' => $pendiente['modalidadIdentificacion'],
                    'estado' => 'encontrado',
                    'detalle' => $pendiente['detalleEncontrado'],
                ]);

                foreach ($pendiente['advertencias'] as $adv) {
                    $resumen->agregarAdvertencia($adv, $pendiente['nroRegistro']);
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
     * Observaciones del form de planilla: pago repetido y avisos de monto.
     * No incluye el match provisorio de puesta en marcha (sigue en el modal de carga).
     *
     * @param  list<string>  $avisosPagoRepetido
     * @param  list<string>  $advertenciasMontos
     */
    public static function obsParaFormularioPlanilla(array $avisosPagoRepetido, array $advertenciasMontos = []): ?string
    {
        $partes = [];
        foreach (array_merge($avisosPagoRepetido, $advertenciasMontos) as $texto) {
            $texto = trim((string) $texto);
            if ($texto === '' || self::esAdvertenciaMatchProvisorio($texto)) {
                continue;
            }
            if (! in_array($texto, $partes, true)) {
                $partes[] = $texto;
            }
        }

        $texto = implode(' | ', array_slice($partes, 0, 3));

        return $texto !== '' ? mb_substr($texto, 0, 500) : null;
    }

    public static function mensajePagoRepetidoPlanilla(string $idPago, int $nroPlanillaPrevia): string
    {
        $idPago = trim($idPago);

        return 'Pago repetido: pagado por primera vez en planilla '.$nroPlanillaPrevia
            .($idPago !== '' ? ' (SIRO '.$idPago.').' : '.');
    }

    public static function esAdvertenciaMatchProvisorio(string $texto): bool
    {
        return str_contains($texto, 'Match provisorio')
            || str_contains($texto, 'Provisorio upload cercano');
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
     * Busca un id de pago SIRO ya impactado, anclado a las posiciones 227–236 de la cadena
     * (no un LIKE libre que pueda coincidir con el código de barras).
     */
    public static function nroPlanillaImpactadoPorIdPagoSiro(string $idPago): ?int
    {
        $idPago = trim($idPago);
        if ($idPago === '' || $idPago === '0000000000') {
            return null;
        }

        $nro = RendicionRoela::query()
            ->where('impactado', 1)
            ->where('cadenaPago', 'like', self::patronLikeIdPagoSiro($idPago))
            ->orderByDesc('id')
            ->value('nroPlanilla');

        return $nro !== null ? (int) $nro : null;
    }

    /**
     * Patrón LIKE que exige el id de pago SIRO exactamente en la posición 227 (1-based).
     */
    public static function patronLikeIdPagoSiro(string $idPago): string
    {
        return str_repeat('_', 226).$idPago.'%';
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
