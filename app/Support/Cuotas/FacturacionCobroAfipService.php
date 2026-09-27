<?php

namespace App\Support\Cuotas;

use App\Models\ComprobanteAfip;
use App\Models\CuotaGenerada;
use App\Models\CuotaPago;
use App\Models\Ento;
use App\Models\Legajo;
use App\Models\RendicionRoela;
use App\Support\Afip\AfipCodigoBarras;
use App\Support\Afip\AfipCondicionIvaReceptor;
use App\Support\Afip\AfipWsfeEmision;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Facturación AFIP al cobrar (modo `cobro`): solo pagos ya imputados, un comprobante por estudiante.
 */
final class FacturacionCobroAfipService
{
    private const TAMANO_LOTE_AFIP = 50;

    /**
     * Pagos de caja creados al impactar la planilla SIRO.
     *
     * @return list<int>
     */
    public static function idsPagosDePlanilla(int $nroPlanilla): array
    {
        if ($nroPlanilla <= 0) {
            return [];
        }

        $rendiciones = RendicionRoela::query()
            ->where('nroPlanilla', $nroPlanilla)
            ->where('impactado', 1)
            ->orderBy('id')
            ->get([
                'id',
                'idCuotasgeneradas',
                'cadenaPago',
                'nombreArchivo',
                'importe',
                'fechaPago',
            ]);

        $usados = [];
        $ids = [];

        foreach ($rendiciones as $rendicion) {
            $idCuota = (int) ($rendicion->idCuotasgeneradas ?? 0);
            if ($idCuota <= 0) {
                continue;
            }

            $candidatos = CuotaPago::query()
                ->where('idCuotasGeneradas', $idCuota)
                ->where('cadenaPago', (string) ($rendicion->cadenaPago ?? ''))
                ->where('nombreArchivo', (string) ($rendicion->nombreArchivo ?? ''))
                ->when($rendicion->fechaPago !== null, function ($query) use ($rendicion): void {
                    $query->whereDate('fechhora', $rendicion->fechaPago->format('Y-m-d'));
                })
                ->orderBy('id')
                ->get(['id']);

            foreach ($candidatos as $pago) {
                $idPago = (int) $pago->id;
                if (isset($usados[$idPago])) {
                    continue;
                }
                $usados[$idPago] = true;
                $ids[] = $idPago;
                break;
            }
        }

        return $ids;
    }

    /**
     * @param  list<int>  $idsPagos
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     total: int,
     *     totalAlumnos: int,
     *     cuotasNombre: string
     * }
     */
    public static function vistaPreviaPorPagos(array $idsPagos, ?int $soloLegajo = null, ?int $nroPlanilla = null): array
    {
        $grupos = self::gruposDesdePagos($idsPagos, $soloLegajo, $nroPlanilla);
        $filas = [];
        $facturables = 0;

        foreach ($grupos as $grupo) {
            if ($grupo['puedeFacturar']) {
                $facturables++;
            }
            $filas[] = [
                'idLegajo' => $grupo['idLegajo'],
                'idFamilia' => $grupo['idFamilia'],
                'apellido' => $grupo['apellido'],
                'nombre' => $grupo['nombre'],
                'dni' => $grupo['dni'],
                'destinatario' => $grupo['destinatario'],
                'dniDestinatario' => $grupo['dniDestinatario'],
                'cuotaNombre' => $grupo['cuotaNombre'],
                'importe' => $grupo['importeTotal'],
                'puedeFacturar' => $grupo['puedeFacturar'],
                'estado' => $grupo['estado'],
            ];
        }

        return [
            'filas' => $filas,
            'total' => $facturables,
            'totalAlumnos' => count($filas),
            'cuotasNombre' => 'Cuotas cobradas',
        ];
    }

    /**
     * @param  list<int>  $idsPagos
     * @return array{
     *     facturados: int,
     *     noFacturados: int,
     *     cuotasNombre: string,
     *     comprobantes: list<array{id: int, idLegajo: int}>,
     *     mensaje: string
     * }
     */
    public static function facturarPagos(array $idsPagos, ?int $soloLegajo = null, ?int $nroPlanilla = null): array
    {
        $vacio = [
            'facturados' => 0,
            'noFacturados' => 0,
            'cuotasNombre' => 'Cuotas cobradas',
            'comprobantes' => [],
            'mensaje' => '',
        ];

        if (! tenantCuotasFacturacionAfipEnCobro()) {
            $vacio['mensaje'] = 'La facturación AFIP al cobrar no está habilitada para este colegio.';

            return $vacio;
        }

        if (! Schema::hasTable('comprobanteafip')) {
            $vacio['mensaje'] = 'La tabla comprobanteafip no existe.';

            return $vacio;
        }

        $config = tenantCuotasFacturacionAfipConfig();
        if ($config === null) {
            $vacio['mensaje'] = 'Falta configurar la facturación AFIP.';

            return $vacio;
        }

        $ento = self::entoInstitucional();
        if ($ento === null) {
            $vacio['mensaje'] = 'Falta configurar el CUIT de facturación en parámetros del sistema (Facturación AFIP).';

            return $vacio;
        }

        $ptoVta = (int) ($ento->ptoVta ?? 0);
        if ($ptoVta <= 0) {
            $vacio['mensaje'] = 'Falta el punto de venta AFIP.';

            return $vacio;
        }

        $grupos = array_values(array_filter(
            self::gruposDesdePagos($idsPagos, $soloLegajo, $nroPlanilla),
            fn (array $grupo): bool => $grupo['puedeFacturar'],
        ));

        if ($grupos === []) {
            $vacio['mensaje'] = 'No hay cobros pendientes de facturar.';

            return $vacio;
        }

        $facturados = 0;
        $noFacturados = 0;
        $comprobantes = [];
        $ultimoError = '';
        $hoy = Carbon::today();
        $fechaYmd = $hoy->format('Ymd');
        $simulado = ! empty($config['simular']);
        $sufijoSimulado = $simulado ? ' (simulado, sin envío a AFIP)' : '';
        $condicionAlumnoDefault = trim((string) ($ento->condicionIva ?? ''));
        if ($condicionAlumnoDefault === '') {
            $condicionAlumnoDefault = (string) ($config['condicion_iva_alumno'] ?? 'Consumidor Final');
        }
        $condicionIvaEmisor = ComprobanteAfipDatos::condIvaInstDesdeEnto($ento);

        foreach (array_chunk($grupos, self::TAMANO_LOTE_AFIP) as $lote) {
            $payload = [];
            foreach ($lote as $grupo) {
                /** @var Legajo $legajo */
                $legajo = $grupo['legajo'];
                /** @var list<CuotaGenerada> $registros */
                $registros = $grupo['registros'];
                [$fechaDesde, $fechaHasta] = FacturacionAfipComun::periodoServicioLote($registros);
                $payload[] = [
                    'cuit' => $ento->cuitParaFacturar(),
                    'pto_vta' => $ptoVta,
                    'doc_nro' => FacturacionAfipComun::docNroReceptorDesdeLegajo($legajo),
                    'importe' => (float) $grupo['importeTotal'],
                    'fecha_yyyymmdd' => $fechaYmd,
                    'fch_serv_desde' => $fechaDesde,
                    'fch_serv_hasta' => $fechaHasta,
                    'condicion_iva_receptor_id' => AfipCondicionIvaReceptor::idDesdeEtiqueta(
                        $condicionAlumnoDefault,
                        (int) ($config['condicion_iva_receptor_id'] ?? 5),
                    ),
                ];
            }

            try {
                $respuestas = AfipWsfeEmision::emitirReciboLote($config, $payload);
            } catch (Throwable $e) {
                $noFacturados += count($lote);
                $ultimoError = $e->getMessage();

                continue;
            }

            foreach ($respuestas as $idx => $respuesta) {
                $grupo = $lote[$idx] ?? null;
                if ($grupo === null) {
                    continue;
                }

                if (empty($respuesta['ok'])) {
                    $noFacturados++;
                    $ultimoError = (string) ($respuesta['mensaje'] ?? 'AFIP rechazó el comprobante.');
                    foreach ($grupo['registros'] as $registro) {
                        FacturacionAfipComun::guardarMensajeCuota($registro, 'Error AFIP: '.$ultimoError);
                    }

                    continue;
                }

                try {
                    $idComprobante = self::persistir(
                        $grupo,
                        $ento,
                        $config,
                        $ptoVta,
                        $hoy,
                        (string) ($respuesta['cae'] ?? ''),
                        (string) ($respuesta['cae_fch_vto'] ?? ''),
                        (int) ($respuesta['cbte_hasta'] ?? 0),
                        $condicionAlumnoDefault,
                        $condicionIvaEmisor,
                        $sufijoSimulado,
                    );
                    $facturados++;
                    $comprobantes[] = [
                        'id' => $idComprobante,
                        'idLegajo' => (int) $grupo['idLegajo'],
                    ];
                } catch (Throwable $e) {
                    $noFacturados++;
                    $ultimoError = 'AFIP autorizó el comprobante pero no se pudo guardar: '.$e->getMessage();
                }
            }
        }

        $mensaje = $facturados > 0
            ? 'Se emitieron '.$facturados.' comprobante(s) AFIP.'.$sufijoSimulado
            : ($ultimoError !== '' ? 'Error al facturar en AFIP: '.$ultimoError : 'No se emitió ningún comprobante.');

        if ($facturados > 0 && $noFacturados > 0) {
            $mensaje .= ' No procesados: '.$noFacturados.'.';
        }

        return [
            'facturados' => $facturados,
            'noFacturados' => $noFacturados,
            'cuotasNombre' => 'Cuotas cobradas',
            'comprobantes' => $comprobantes,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * @param  list<int>  $idsPagos
     * @return list<array<string, mixed>>
     */
    private static function gruposDesdePagos(array $idsPagos, ?int $soloLegajo, ?int $nroPlanilla): array
    {
        $idsPagos = array_values(array_unique(array_filter(
            array_map('intval', $idsPagos),
            fn (int $id) => $id > 0,
        )));

        if ($nroPlanilla !== null && $nroPlanilla > 0) {
            $permitidos = array_flip(self::idsPagosDePlanilla($nroPlanilla));
            $idsPagos = array_values(array_filter(
                $idsPagos,
                fn (int $id): bool => isset($permitidos[$id]),
            ));
        }

        if ($idsPagos === []) {
            return [];
        }

        $pagos = CuotaPago::query()
            ->with([
                'cuotaGenerada.cuota:id,nombre',
                'cuotaGenerada.terlec:id,ano',
                'cuotaGenerada.curso:Id,cursec,c,s,idCurPlan,idTurnoClase,idNivel',
                'cuotaGenerada.curso.curplan:id,curPlanCurso',
                'cuotaGenerada.curso.turnoClase:id,nombre',
                'cuotaGenerada.curso.nivel:id,nivel',
            ])
            ->whereIn('id', $idsPagos)
            ->orderBy('id')
            ->get();

        $porLegajo = [];
        foreach ($pagos as $pago) {
            $registro = $pago->cuotaGenerada;
            if (! $registro instanceof CuotaGenerada) {
                continue;
            }
            $idLegajo = (int) ($registro->idLegajos ?? 0);
            if ($idLegajo <= 0) {
                continue;
            }
            if ($soloLegajo !== null && $soloLegajo > 0 && $idLegajo !== $soloLegajo) {
                continue;
            }
            $porLegajo[$idLegajo][] = $pago;
        }

        $idsCuotas = [];
        foreach ($porLegajo as $lista) {
            foreach ($lista as $pago) {
                $idsCuotas[] = (int) $pago->idCuotasGeneradas;
            }
        }
        $devengadas = array_flip(self::idsCuotasFacturadasPorDevengamiento($idsCuotas));

        $grupos = [];
        foreach ($porLegajo as $idLegajo => $lista) {
            $legajo = GestionAranceles::legajoParaFacturacionAfip((int) $idLegajo);
            if ($legajo === null) {
                continue;
            }

            $items = [];
            $registros = [];
            $conceptos = [];
            $omitidos = [];
            $importeTotal = 0.0;

            foreach ($lista as $pago) {
                $registro = $pago->cuotaGenerada;
                $nombre = trim((string) ($registro->cuota?->nombre ?? 'Cuota'));
                $importe = ComprobantesAfipCuotaService::importeFacturable($pago);
                if ($importe <= 0) {
                    $omitidos[] = $nombre.' (importe cero)';

                    continue;
                }
                if (isset($devengadas[(int) $registro->id])) {
                    $omitidos[] = $nombre.' (ya facturada por devengamiento)';

                    continue;
                }
                if (ComprobantesAfipCuotaService::facturaVigente((int) $pago->id) !== null) {
                    $omitidos[] = $nombre.' (ya facturada)';

                    continue;
                }

                $items[] = $pago;
                $registros[] = $registro;
                $conceptos[] = mb_strtoupper($nombre);
                $importeTotal += $importe;
            }

            $destinatario = FacturacionAfipComun::destinatarioFacturaDesdeLegajo($legajo);
            $puede = $items !== [] && $destinatario['valido'] && $importeTotal > 0;
            if ($items === []) {
                $estado = $omitidos !== [] ? implode('; ', $omitidos) : 'No hay cobros para facturar.';
            } elseif (! $destinatario['valido']) {
                $estado = (string) $destinatario['motivo'];
                $puede = false;
            } else {
                $estado = implode(', ', $conceptos).' (se facturará)';
                if ($omitidos !== []) {
                    $estado .= '. Omitidos: '.implode('; ', $omitidos);
                }
            }

            $primer = $registros[0] ?? $lista[0]->cuotaGenerada;
            $grupos[] = [
                'idLegajo' => (int) $idLegajo,
                'idFamilia' => (int) ($legajo->idFamilias ?? 0),
                'legajo' => $legajo,
                'apellido' => (string) ($legajo->apellido ?? ''),
                'nombre' => (string) ($legajo->nombre ?? ''),
                'dni' => (string) ($legajo->dni ?? ''),
                'destinatario' => (string) ($destinatario['responsable'] ?? ''),
                'dniDestinatario' => (string) ($destinatario['dniResp'] ?? ''),
                'cuotaNombre' => implode(', ', $conceptos) !== '' ? implode(', ', $conceptos) : '—',
                'importeTotal' => round($importeTotal, 2),
                'puedeFacturar' => $puede,
                'estado' => $estado,
                'pagos' => $items,
                'registros' => $registros,
                'idCurso' => (int) ($primer->idCursos ?? $primer->curso?->Id ?? 0),
                'cursoNombre' => $primer instanceof CuotaGenerada
                    ? FacturacionAfipComun::cursoTextoDesdeRegistro($primer)
                    : '',
            ];
        }

        return $grupos;
    }

    /**
     * @param  array<string, mixed>  $grupo
     */
    private static function persistir(
        array $grupo,
        Ento $ento,
        array $config,
        int $ptoVta,
        Carbon $hoy,
        string $cae,
        string $vtoCaeYmd,
        int $nroRecibo,
        string $condicionAlumno,
        string $condicionIvaEmisor,
        string $sufijoSimulado,
    ): int {
        /** @var list<CuotaPago> $pagos */
        $pagos = $grupo['pagos'];
        /** @var list<CuotaGenerada> $registros */
        $registros = $grupo['registros'];
        /** @var Legajo $legajo */
        $legajo = $grupo['legajo'];

        $conceptos = [];
        $importesLinea = [];
        $idsPagos = [];
        $importeTotal = 0.0;
        $interesTotal = 0.0;

        foreach ($pagos as $idx => $pago) {
            $registro = $registros[$idx] ?? null;
            $importe = ComprobantesAfipCuotaService::importeFacturable($pago);
            $conceptos[] = mb_strtoupper(trim((string) ($registro?->cuota?->nombre ?? 'CUOTA')));
            $importesLinea[] = number_format($importe, 2, '.', '');
            $idsPagos[] = (int) $pago->id;
            $importeTotal += $importe;
            $interesTotal += round((float) ($pago->interes ?? 0), 2);
        }

        $importeTotal = round($importeTotal, 2);
        $nombreResp = FacturacionAfipComun::nombreDestinatarioAfipDesdeLegajo($legajo);
        $dniResp = FacturacionAfipComun::dniDestinatarioAfipDesdeLegajo($legajo);
        $docNro = FacturacionAfipComun::documentoNumerico($dniResp);
        [$fechaDesde, $fechaHasta] = FacturacionAfipComun::periodoServicioLote($registros);
        $nombreAlumno = mb_strtoupper(trim(($legajo->apellido ?? '').' '.($legajo->nombre ?? '')));
        $conceptoPrincipal = count($conceptos) === 1 ? $conceptos[0] : 'CUOTAS ESCOLARES';
        $codigoBarras = AfipCodigoBarras::generar(
            $ento->cuitParaFacturar(),
            (int) $config['cbte_tipo'],
            $ptoVta,
            $cae,
            $vtoCaeYmd,
        );
        $primerRegistro = $registros[0];
        $snapshotInst = FacturacionAfipComun::snapshotInstitucionalPdf($ento, $primerRegistro);
        $cursoAlumno = FacturacionAfipComun::cursoTextoDesdeRegistro($primerRegistro);
        $idComprobante = 0;

        DB::transaction(function () use (
            &$idComprobante,
            $ento,
            $config,
            $ptoVta,
            $hoy,
            $cae,
            $vtoCaeYmd,
            $nroRecibo,
            $condicionAlumno,
            $condicionIvaEmisor,
            $sufijoSimulado,
            $pagos,
            $registros,
            $importeTotal,
            $interesTotal,
            $nombreResp,
            $dniResp,
            $docNro,
            $fechaDesde,
            $fechaHasta,
            $nombreAlumno,
            $conceptoPrincipal,
            $conceptos,
            $importesLinea,
            $idsPagos,
            $codigoBarras,
            $primerRegistro,
            $snapshotInst,
            $cursoAlumno,
        ): void {
            $comprobante = ComprobanteAfip::query()->create([
                'nombreInstitucion' => trim((string) $ento->insti),
                'razonSocial' => trim((string) $ento->insti),
                'cuitInstitucion' => preg_replace('/\D/', '', $ento->cuitParaFacturar()),
                'domicilioComercial' => $ento->domicilioComercialCompleto(),
                'condicionIvaInstitucion' => $condicionIvaEmisor,
                'telefonoInstitucion' => $snapshotInst['telefonoInstitucion'],
                'aporteEstatal' => $snapshotInst['aporteEstatal'],
                'puntoVenta' => $ptoVta,
                'ingresosBrutos' => trim((string) ($ento->ingresosBrutos ?? '')),
                'fechaInicioActividades' => FacturacionAfipComun::formatearFechaEnto($ento->fechaInicioAct ?? null),
                'nombreAlumno' => $nombreAlumno,
                'dni' => (string) $docNro,
                'nombreResp' => $nombreResp,
                'dniResp' => $dniResp,
                'cursoAlumno' => $cursoAlumno,
                'condicionIvaAlumno' => $condicionAlumno,
                'condicionVenta' => (string) ($config['condicion_venta'] ?? 'contado'),
                'fechaDesde' => FacturacionAfipComun::formatearFechaBarra($fechaDesde),
                'fechaHasta' => FacturacionAfipComun::formatearFechaBarra($fechaHasta),
                'fechaEmision' => $hoy->format('Y/m/d'),
                'fechaVencimiento' => $hoy->format('Y/m/d'),
                'tipoComprobante' => (int) $config['cbte_tipo'],
                'docTipoAfip' => (int) ($config['doc_tipo'] ?? 96),
                'codigoBarras' => $codigoBarras,
                'nroRecibo' => $nroRecibo,
                'cae' => $cae,
                'vtoCae' => FacturacionAfipComun::formatearFechaBarra($vtoCaeYmd),
                'importePagado' => $importeTotal,
                'interesPagado' => round($interesTotal, 2),
                'idCbteAsoc' => (int) $primerRegistro->id,
                'concepto' => $conceptoPrincipal,
                'subConceptos' => implode('|', $conceptos),
                'importeSubConceptos' => implode('|', $importesLinea),
                'saldoRestante' => implode(',', $idsPagos),
                'idCuotasPagos' => (int) ($pagos[0]->id ?? 0),
            ]);

            $idComprobante = (int) $comprobante->idComprobanteAfip;

            foreach ($registros as $registro) {
                FacturacionAfipComun::guardarMensajeCuota(
                    $registro,
                    'Comprobante AFIP emitido. CAE '.$cae.$sufijoSimulado,
                );
            }
        });

        return $idComprobante;
    }

    /**
     * Cuotas que ya tienen factura de devengamiento vigente (no ligada a un pago).
     *
     * @param  list<int>  $idsCuotas
     * @return list<int>
     */
    private static function idsCuotasFacturadasPorDevengamiento(array $idsCuotas): array
    {
        $idsCuotas = array_values(array_unique(array_filter(
            array_map('intval', $idsCuotas),
            fn (int $id) => $id > 0,
        )));

        if ($idsCuotas === [] || ! Schema::hasTable('comprobanteafip')) {
            return [];
        }

        $tipo = (int) config('tenant.cuotas.facturacion_afip.cbte_tipo', 11);
        $tipoNc = (int) config('tenant.cuotas.facturacion_afip.nota_credito_tipo', 12);

        $facturas = ComprobanteAfip::query()
            ->where('tipoComprobante', $tipo)
            ->where(function ($query): void {
                $query->whereNull('idCuotasPagos')->orWhere('idCuotasPagos', 0);
            })
            ->where(function ($query) use ($idsCuotas): void {
                $query->whereIn('idCbteAsoc', $idsCuotas);
                foreach ($idsCuotas as $idCuota) {
                    $query->orWhereRaw('FIND_IN_SET(?, saldoRestante)', [$idCuota]);
                }
            })
            ->get(['idComprobanteAfip', 'idCbteAsoc', 'saldoRestante']);

        if ($facturas->isEmpty()) {
            return [];
        }

        $anuladas = ComprobanteAfip::query()
            ->where('tipoComprobante', $tipoNc)
            ->whereIn('subConceptos', $facturas->pluck('idComprobanteAfip')->map(fn ($id) => (string) (int) $id)->all())
            ->pluck('subConceptos')
            ->map(fn ($id) => (int) $id)
            ->all();

        $cubiertas = [];
        $buscadas = array_flip($idsCuotas);
        foreach ($facturas as $factura) {
            if (in_array((int) $factura->idComprobanteAfip, $anuladas, true)) {
                continue;
            }
            $idAsoc = (int) ($factura->idCbteAsoc ?? 0);
            if (isset($buscadas[$idAsoc])) {
                $cubiertas[$idAsoc] = true;
            }
            foreach (explode(',', (string) ($factura->saldoRestante ?? '')) as $parte) {
                $id = (int) trim($parte);
                if (isset($buscadas[$id])) {
                    $cubiertas[$id] = true;
                }
            }
        }

        return array_map('intval', array_keys($cubiertas));
    }

    private static function entoInstitucional(): ?Ento
    {
        $columnas = [
            'insti',
            'direccion',
            'localidad',
            'provincia',
            'telefono',
            'cuit',
            'domicFact',
            'condIvaInst',
            'aporteEstatal',
            'condicionIva',
            'ptoVta',
            'ingresosBrutos',
            'fechaInicioAct',
        ];
        if (Schema::hasColumn('ento', 'cuitFact')) {
            $columnas[] = 'cuitFact';
        }

        $ento = Ento::query()
            ->where('idNivel', (int) schoolCtx()->idNivel)
            ->first($columnas);

        if ($ento === null || $ento->cuitParaFacturar() === '') {
            return null;
        }

        return $ento;
    }
}
