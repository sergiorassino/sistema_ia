<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Models\PlanillaDescargaCuota;
use App\Models\RendicionRoela;
use Illuminate\Support\Collection;
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
        if (str_starts_with($contenido, "\xEF\xBB\xBF")) {
            $contenido = substr($contenido, 3);
        }
        $lineas = preg_split('/\R/', $contenido) ?: [];
        $idsPagoVistos = [];
        /** @var array<string, int> idPagoSiro => nro de registro en el archivo */
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

            $idPago = (string) ($linea['idPagoSiro'] ?? '');
            $motivoDuplicadoPlanilla = self::motivoDuplicadoEnPlanilla($linea, $indicePlanilla);
            if ($motivoDuplicadoPlanilla !== null) {
                $avisosYaCargado = self::avisosIdPagoEnOtraPlanilla($idPago, $nroPlanilla);
                $existente = RendicionRoela::query()
                    ->where('nroPlanilla', $nroPlanilla)
                    ->where('cadenaPago', (string) ($linea['cadenaPago'] ?? ''))
                    ->first();
                if ($existente !== null) {
                    $avisoCuota = self::avisoCuotaYaDescargada((int) ($existente->idCuotasgeneradas ?? 0), $nroPlanilla);
                    if ($avisoCuota !== null) {
                        $avisosYaCargado[] = $avisoCuota;
                    }
                    $obs = self::obsParaFormularioPlanilla($avisosYaCargado);
                    if ($obs !== null && trim((string) ($existente->obs ?? '')) !== $obs) {
                        $existente->obs = $obs;
                        $existente->save();
                    }
                }

                $detalleDuplicado = self::detallePagoDuplicado($avisosYaCargado);
                $resumen->omitidos++;
                foreach ($avisosYaCargado !== [] ? $avisosYaCargado : [$motivoDuplicadoPlanilla] as $aviso) {
                    $resumen->agregarAdvertencia($aviso, $nroRegistro);
                }
                $resumen->agregarRegistroArchivo([
                    'linea' => $nroRegistro,
                    'canal' => $canalEtiqueta,
                    'idFacturaBuscado' => $idFacturaBuscado,
                    'modalidadIdentificacion' => $modalidadIdentificacion,
                    'estado' => $detalleDuplicado !== null ? 'encontrado_duplicado' : 'omitido',
                    'detalle' => $detalleDuplicado ?? 'Registro ya cargado en esta planilla.',
                ]);

                continue;
            }

            $avisosPagoRepetido = self::avisosIdPagoEnOtraPlanilla($idPago, $nroPlanilla);
            if ($idPago !== '' && $idPago !== '0000000000') {
                if (isset($idsPagoVistos[$idPago])) {
                    $avisosPagoRepetido[] = self::mensajePagoDuplicadoIdSiroMismoArchivo(
                        $idPago,
                        $idsPagoVistos[$idPago],
                    );
                } else {
                    $idsPagoVistos[$idPago] = $nroRegistro;
                }
            }

            $match = SiroDescargaRendicionCupon::resolver($linea, $idTerlec);
            $cupon = $match['cupon'];
            $cuotaGenerada = $match['cuotaGenerada'];
            $matchTipo = (string) ($match['matchTipo'] ?? '');
            $modalidadMatch = $match['modalidadIdentificacion'] !== ''
                ? $match['modalidadIdentificacion']
                : $modalidadIdentificacion;

            $permiteSinCupon = SiroDescargaRendicionMatchCuotaSinCupon448::esMatchTipo($matchTipo);
            if ($cuotaGenerada === null || ($cupon === null && ! $permiteSinCupon)) {
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
                $avisosPagoRepetido[] = self::mensajePagoDuplicadoMismoArchivo(
                    $cuotasVistasEnArchivo[$idCuotaGen],
                );
            }
            $cuotasVistasEnArchivo[$idCuotaGen] = $nroRegistro;
            $avisoCuotaPrevia = self::avisoCuotaYaDescargada($idCuotaGen, $nroPlanilla);
            if ($avisoCuotaPrevia !== null) {
                $avisosPagoRepetido[] = $avisoCuotaPrevia;
            }

            $cuotaGenerada->loadMissing(['cuota', 'legajo', 'curso', 'beca']);

            $montos = SiroDescargaRendicionCalculo::calcular($linea, $cuotaGenerada, $cupon, $matchTipo);
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

            $idEncontrado = (string) ($cupon?->id_factura ?? '');
            $detalleProvisorio = SiroDescargaRendicionProvisorios::detalleColumna([
                'matchTipo' => $matchTipo,
                'provisorioImporteArchivo' => (bool) ($montos['provisorioImporteArchivo'] ?? false),
                'idFacturaBuscado' => $idFacturaBuscado,
                'cupon' => $cupon,
                'cuotaGenerada' => $cuotaGenerada,
                'pagadoArchivo' => $montos['pagado'],
                'desglose' => $montos,
            ]);
            $detalleMatch = trim((string) ($match['detalleMatch'] ?? ''));
            $detallePagoDuplicado = self::detallePagoDuplicado($avisosPagoRepetido, $montos['advertencias']);
            $detalleEncontrado = self::componerDetalleEncontrado(
                $detallePagoDuplicado,
                $detalleProvisorio,
                $detalleMatch,
                $idEncontrado,
                $idFacturaBuscado,
            );

            $advertencias = array_merge($avisosPagoRepetido, $montos['advertencias'], $match['advertencias']);
            $fechaPago = SiroDescargaRendicionLinea::fechaDesdeSiro((string) $linea['fechaPago']);
            $fechaAcred = SiroDescargaRendicionLinea::fechaDesdeSiro((string) $linea['fechaAcreditacion']);
            $fechVenc1 = $cupon?->fecha1venc?->format('Y-m-d')
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
                'esPagoDuplicado' => $detallePagoDuplicado !== null,
            ];
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
                    'estado' => ($pendiente['esPagoDuplicado'] ?? false) ? 'encontrado_duplicado' : 'encontrado',
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

        if ($partes === []) {
            return null;
        }

        $texto = implode(' | ', array_slice($partes, 0, 3));
        foreach ($partes as $parte) {
            if (self::esAvisoPagoDuplicado($parte)) {
                $texto = 'PAGO DUPLICADO: '.$texto;
                break;
            }
        }

        return mb_substr($texto, 0, 500);
    }

    /**
     * Texto para la columna Detalle del modal cuando la cuota registra un pago duplicado.
     *
     * @param  list<string>  $avisosPagoRepetido
     * @param  list<string>  $advertenciasMontos
     */
    public static function detallePagoDuplicado(array $avisosPagoRepetido, array $advertenciasMontos = []): ?string
    {
        $partes = [];
        foreach (array_merge($avisosPagoRepetido, $advertenciasMontos) as $texto) {
            $texto = trim((string) $texto);
            if ($texto === '' || self::esAdvertenciaMatchProvisorio($texto) || ! self::esAvisoPagoDuplicado($texto)) {
                continue;
            }
            if (! in_array($texto, $partes, true)) {
                $partes[] = $texto;
            }
        }

        return $partes !== [] ? 'PAGO DUPLICADO: '.implode(' | ', $partes) : null;
    }

    /**
     * Detalle del modal: el aviso de duplicado va primero, después el provisorio.
     */
    public static function componerDetalleEncontrado(
        ?string $detallePagoDuplicado,
        ?string $detalleProvisorio,
        string $detalleMatch = '',
        string $idEncontrado = '',
        string $idFacturaBuscado = '',
    ): ?string {
        $partes = [];
        if ($detallePagoDuplicado !== null && $detallePagoDuplicado !== '') {
            $partes[] = $detallePagoDuplicado;
        }
        if ($detalleProvisorio !== null && $detalleProvisorio !== '') {
            $partes[] = $detalleProvisorio;
        }
        if ($partes !== []) {
            return implode(' | ', $partes);
        }

        $detalleMatch = trim($detalleMatch);
        if ($detalleMatch !== '') {
            return $detalleMatch;
        }
        if ($idEncontrado !== '' && $idEncontrado !== $idFacturaBuscado) {
            return 'Encontrado con id_factura '.$idEncontrado;
        }

        return null;
    }

    public static function esAvisoPagoDuplicado(string $texto): bool
    {
        return str_contains($texto, 'posible pago doble')
            || str_contains($texto, 'Pago repetido')
            || str_contains($texto, 'Id de pago SIRO duplicado')
            || str_contains($texto, 'ya tiene otro pago')
            || str_contains($texto, 'ya tiene un pago descargado')
            || str_contains($texto, 'ya estaba saldada')
            || str_contains($texto, 'ya tenía un pago registrado')
            || str_contains($texto, 'misma cadena SIRO ya imputada')
            || str_contains($texto, 'el saldo quedará negativo');
    }

    public static function leyendaCortaObs(?string $obs): string
    {
        $obs = trim((string) $obs);
        if ($obs === '') {
            return '';
        }
        if (str_contains($obs, 'PAGO DUPLICADO') || self::esAvisoPagoDuplicado($obs)) {
            return 'PAGO DUPLICADO';
        }

        return $obs;
    }

    /**
     * Completa obsMostrada para la grilla: usa rendicionesroela.obs o infiere
     * duplicado si la misma cuota ya está en otra planilla o repetida acá.
     *
     * @param  Collection<int, RendicionRoela>  $rendiciones
     * @return Collection<int, RendicionRoela>
     */
    public static function completarLeyendaDuplicados(Collection $rendiciones, int $nroPlanilla): Collection
    {
        $ids = $rendiciones
            ->pluck('idCuotasgeneradas')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $otraPlanillaPorCuota = [];
        if ($ids->isNotEmpty()) {
            $previas = RendicionRoela::query()
                ->whereIn('idCuotasgeneradas', $ids)
                ->where('nroPlanilla', '!=', $nroPlanilla)
                ->orderBy('id')
                ->get(['idCuotasgeneradas', 'nroPlanilla']);
            foreach ($previas as $previa) {
                $idCg = (int) $previa->idCuotasgeneradas;
                if ($idCg > 0 && ! isset($otraPlanillaPorCuota[$idCg])) {
                    $otraPlanillaPorCuota[$idCg] = (int) $previa->nroPlanilla;
                }
            }
        }

        $primeraEnPlanilla = [];
        $item = 0;
        foreach ($rendiciones as $rendicion) {
            $item++;
            $idCg = (int) ($rendicion->idCuotasgeneradas ?? 0);
            $obsDb = trim((string) ($rendicion->obs ?? ''));
            $avisos = [];
            if ($idCg > 0) {
                if (isset($primeraEnPlanilla[$idCg])) {
                    $avisos[] = self::mensajePagoDuplicadoMismoArchivo($primeraEnPlanilla[$idCg]);
                } else {
                    $primeraEnPlanilla[$idCg] = $item;
                }
                if (isset($otraPlanillaPorCuota[$idCg])) {
                    $avisos[] = self::mensajePagoDuplicadoCuotaPlanilla($otraPlanillaPorCuota[$idCg]);
                }
            }

            if ($obsDb !== '') {
                $rendicion->setAttribute('obsMostrada', $obsDb);
            } elseif ($avisos !== []) {
                $rendicion->setAttribute('obsMostrada', self::obsParaFormularioPlanilla($avisos) ?? '');
            } else {
                $rendicion->setAttribute('obsMostrada', '');
            }
        }

        return $rendiciones;
    }

    /**
     * @return list<string>
     */
    public static function avisosIdPagoEnOtraPlanilla(string $idPago, int $nroPlanilla): array
    {
        $nroPrevia = self::nroPlanillaPreviaPorIdPagoSiro($idPago, $nroPlanilla);
        if ($nroPrevia === null) {
            return [];
        }

        return [self::mensajePagoRepetidoPlanilla($idPago, $nroPrevia)];
    }

    public static function avisoCuotaYaDescargada(int $idCuotasgeneradas, int $nroPlanilla): ?string
    {
        $nroPrevia = self::nroPlanillaPreviaPorCuota($idCuotasgeneradas, $nroPlanilla);
        if ($nroPrevia === null) {
            return null;
        }

        return self::mensajePagoDuplicadoCuotaPlanilla($nroPrevia);
    }

    public static function mensajePagoDuplicadoIdSiroMismoArchivo(string $idPago, int $nroRegistroPrevio): string
    {
        return 'Id de pago SIRO duplicado en el archivo: '.$idPago
            .' (registro '.$nroRegistroPrevio.'); se registra igual (posible pago doble).';
    }

    public static function mensajePagoDuplicadoMismoArchivo(int $nroRegistroPrevio): string
    {
        return 'La cuota ya tiene otro pago en este archivo (registro '.$nroRegistroPrevio
            .'); se registra igual (posible pago doble).';
    }

    public static function mensajePagoDuplicadoCuotaPlanilla(int $nroPlanillaPrevia): string
    {
        return 'La cuota ya tiene un pago descargado en planilla '.$nroPlanillaPrevia
            .'; se registra igual (posible pago doble).';
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
            || str_contains($texto, 'Provisorio upload cercano')
            || str_contains($texto, 'Provisorio 448')
            || str_contains($texto, 'PROVISORIO');
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
     * Busca un id de pago SIRO ya descargado en otra planilla (impactado o no),
     * anclado a las posiciones 227–236 de la cadena (no un LIKE libre).
     */
    public static function nroPlanillaPreviaPorIdPagoSiro(string $idPago, int $nroPlanillaActual): ?int
    {
        $idPago = trim($idPago);
        if ($idPago === '' || $idPago === '0000000000') {
            return null;
        }

        $nro = RendicionRoela::query()
            ->where('nroPlanilla', '!=', $nroPlanillaActual)
            ->where('cadenaPago', 'like', self::patronLikeIdPagoSiro($idPago))
            ->orderBy('id')
            ->value('nroPlanilla');

        return $nro !== null ? (int) $nro : null;
    }

    /**
     * @deprecated Usar nroPlanillaPreviaPorIdPagoSiro (también considera no impactadas).
     */
    public static function nroPlanillaImpactadoPorIdPagoSiro(string $idPago): ?int
    {
        $idPago = trim($idPago);
        if ($idPago === '' || $idPago === '0000000000') {
            return null;
        }

        $nro = RendicionRoela::query()
            ->where('cadenaPago', 'like', self::patronLikeIdPagoSiro($idPago))
            ->orderBy('id')
            ->value('nroPlanilla');

        return $nro !== null ? (int) $nro : null;
    }

    public static function nroPlanillaPreviaPorCuota(int $idCuotasgeneradas, int $nroPlanillaActual): ?int
    {
        if ($idCuotasgeneradas <= 0) {
            return null;
        }

        $nro = RendicionRoela::query()
            ->where('idCuotasgeneradas', $idCuotasgeneradas)
            ->where('nroPlanilla', '!=', $nroPlanillaActual)
            ->orderBy('id')
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
     * Solo filas ya persistidas en esta planilla (una carga anterior).
     * Un pago repetido dentro del mismo archivo no se omite: se registra con aviso.
     *
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
