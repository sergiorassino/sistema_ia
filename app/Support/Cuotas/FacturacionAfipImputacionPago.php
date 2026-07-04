<?php

namespace App\Support\Cuotas;

use App\Models\ComprobanteAfip;
use App\Models\CuotaGenerada;
use App\Models\CuotaPago;
use App\Models\CuotasMes;
use App\Models\Ento;
use App\Models\Legajo;
use App\Support\Afip\AfipCodigoBarras;
use App\Support\Afip\AfipCondicionIvaReceptor;
use App\Support\Afip\AfipWsfeEmision;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Emite comprobante AFIP y registra fila en `comprobanteafip` tras imputar un pago.
 */
final class FacturacionAfipImputacionPago
{
    /**
     * @return array{ok: bool, mensaje: string}
     */
    public static function facturar(
        CuotaPago $pago,
        CuotaGenerada $registro,
        int $idLegajo,
        float $importeFacturar,
    ): array {
        if (! tenantCuotasFacturacionAfipHabilitada()) {
            return ['ok' => false, 'mensaje' => 'La facturación AFIP no está habilitada para este colegio.'];
        }

        if (! Schema::hasTable('comprobanteafip')) {
            return ['ok' => false, 'mensaje' => 'La tabla comprobanteafip no existe en esta base de datos.'];
        }

        if ($importeFacturar <= 0) {
            return ['ok' => false, 'mensaje' => 'No hay importe para facturar en AFIP.'];
        }

        $config = tenantCuotasFacturacionAfipConfig();
        if ($config === null) {
            return ['ok' => false, 'mensaje' => 'Falta configurar la facturación AFIP del colegio.'];
        }

        $legajo = GestionAranceles::legajoParaGestion($idLegajo);
        if ($legajo === null) {
            return ['ok' => false, 'mensaje' => 'No se encontró el legajo del estudiante.'];
        }

        $ento = Ento::query()
            ->where('idNivel', (int) schoolCtx()->idNivel)
            ->first([
                'insti',
                'direccion',
                'localidad',
                'telefono',
                'cuit',
                'condIvaInst',
                'aporteEstatal',
                'condicionIva',
                'ptoVta',
                'ingresosBrutos',
                'fechaInicioAct',
            ]);

        if ($ento === null || trim((string) $ento->cuit) === '') {
            return ['ok' => false, 'mensaje' => 'Faltan datos AFIP institucionales (CUIT / ento).'];
        }

        $ptoVta = (int) ($ento->ptoVta ?? 0);
        if ($ptoVta <= 0) {
            return ['ok' => false, 'mensaje' => 'Falta configurar el punto de venta AFIP en parámetros del sistema.'];
        }

        $docNro = self::documentoNumerico($legajo->dni ?? null);
        if ($docNro <= 0) {
            return ['ok' => false, 'mensaje' => 'El estudiante no tiene DNI válido para facturar.'];
        }

        [$fechaDesde, $fechaHasta] = self::periodoServicio($registro);
        $hoy = Carbon::today();
        $fechaYmd = $hoy->format('Ymd');

        $condicionAlumno = trim((string) ($ento->condicionIva ?? ''));
        if ($condicionAlumno === '') {
            $condicionAlumno = (string) ($config['condicion_iva_alumno'] ?? 'Consumidor Final');
        }
        $condicionIvaId = AfipCondicionIvaReceptor::idDesdeEtiqueta(
            $condicionAlumno,
            (int) ($config['condicion_iva_receptor_id'] ?? 5),
        );
        $condicionIvaEmisor = ComprobanteAfipDatos::condIvaInstDesdeEnto($ento);

        try {
            $emision = AfipWsfeEmision::emitirRecibo($config, [
                'cuit' => (string) $ento->cuit,
                'pto_vta' => $ptoVta,
                'doc_nro' => $docNro,
                'importe' => $importeFacturar,
                'fecha_yyyymmdd' => $fechaYmd,
                'fch_serv_desde' => $fechaDesde,
                'fch_serv_hasta' => $fechaHasta,
                'condicion_iva_receptor_id' => $condicionIvaId,
            ]);
        } catch (Throwable $e) {
            FacturacionAfipComun::guardarMensajeCuota($registro, 'Error AFIP: '.$e->getMessage());

            return ['ok' => false, 'mensaje' => 'Error al facturar en AFIP: '.$e->getMessage()];
        }

        $simulado = ! empty($config['simular']);
        $cae = (string) $emision['cae'];
        $vtoCaeYmd = (string) $emision['cae_fch_vto'];
        $nroRecibo = (int) $emision['cbte_hasta'];
        $sufijoSimulado = $simulado ? ' (simulado, sin envío a AFIP)' : '';
        $codigoBarras = AfipCodigoBarras::generar(
            (string) $ento->cuit,
            (int) $config['cbte_tipo'],
            $ptoVta,
            $cae,
            $vtoCaeYmd,
        );

        $nombreResp = FacturacionAfipComun::responsableEconomicoFamilia($legajo);
        $dniResp = FacturacionAfipComun::dniRespDesdeFamilia($legajo);
        $concepto = mb_strtoupper(trim((string) ($registro->cuota?->nombre ?? 'CUOTA')));
        $nombreAlumno = mb_strtoupper(trim(($legajo->apellido ?? '').' '.($legajo->nombre ?? '')));
        $snapshotInst = FacturacionAfipComun::snapshotInstitucionalPdf($ento);
        $cursoAlumno = FacturacionAfipComun::cursoTextoDesdeRegistro($registro);

        try {
            $idComprobanteAfip = 0;
            DB::transaction(function () use (
                &$idComprobanteAfip,
                $pago,
                $registro,
                $ento,
                $legajo,
                $ptoVta,
                $config,
                $importeFacturar,
                $fechaDesde,
                $fechaHasta,
                $hoy,
                $cae,
                $vtoCaeYmd,
                $nroRecibo,
                $codigoBarras,
                $nombreResp,
                $dniResp,
                $concepto,
                $nombreAlumno,
                $docNro,
                $condicionAlumno,
                $condicionIvaEmisor,
                $sufijoSimulado,
                $snapshotInst,
                $cursoAlumno,
            ): void {
                $comprobante = ComprobanteAfip::query()->create([
                    'nombreInstitucion' => trim((string) $ento->insti),
                    'razonSocial' => trim((string) $ento->insti),
                    'cuitInstitucion' => preg_replace('/\D/', '', (string) $ento->cuit),
                    'domicilioComercial' => $ento->domicilioComercialCompleto(),
                    'condicionIvaInstitucion' => $condicionIvaEmisor,
                    'telefonoInstitucion' => $snapshotInst['telefonoInstitucion'],
                    'aporteEstatal' => $snapshotInst['aporteEstatal'],
                    'puntoVenta' => $ptoVta,
                    'ingresosBrutos' => trim((string) ($ento->ingresosBrutos ?? '')),
                    'fechaInicioActividades' => self::formatearFechaEnto($ento->fechaInicioAct ?? null),
                    'nombreAlumno' => $nombreAlumno,
                    'dni' => (string) $docNro,
                    'nombreResp' => $nombreResp,
                    'dniResp' => $dniResp,
                    'cursoAlumno' => $cursoAlumno,
                    'condicionIvaAlumno' => $condicionAlumno,
                    'condicionVenta' => (string) ($config['condicion_venta'] ?? 'contado'),
                    'fechaDesde' => self::formatearFechaBarra($fechaDesde),
                    'fechaHasta' => self::formatearFechaBarra($fechaHasta),
                    'fechaEmision' => $hoy->format('Y/m/d'),
                    'fechaVencimiento' => $hoy->format('Y/m/d'),
                    'tipoComprobante' => (int) $config['cbte_tipo'],
                    'docTipoAfip' => (int) ($config['doc_tipo'] ?? 96),
                    'codigoBarras' => $codigoBarras,
                    'nroRecibo' => $nroRecibo,
                    'cae' => $cae,
                    'vtoCae' => self::formatearFechaBarra($vtoCaeYmd),
                    'importePagado' => round($importeFacturar, 2),
                    'interesPagado' => round((float) ($pago->interes ?? 0), 2),
                    'idCbteAsoc' => (int) $registro->id,
                    'concepto' => $concepto,
                    'subConceptos' => '',
                    'importeSubConceptos' => '',
                    'saldoRestante' => '',
                    'idCuotasPagos' => (int) $pago->id,
                ]);

                $idComprobanteAfip = (int) $comprobante->idComprobanteAfip;

                FacturacionAfipComun::guardarMensajeCuota(
                    $registro,
                    'Comprobante AFIP emitido. CAE '.$cae.$sufijoSimulado,
                );
            });
        } catch (Throwable $e) {
            return ['ok' => false, 'mensaje' => 'AFIP autorizó el comprobante pero no se pudo guardar: '.$e->getMessage()];
        }

        return [
            'ok' => true,
            'mensaje' => 'Comprobante AFIP emitido correctamente. CAE '.$cae.' — Recibo Nº '.$nroRecibo.'.'.$sufijoSimulado,
            'idComprobanteAfip' => $idComprobanteAfip,
        ];
    }

    /**
     * Emite un único comprobante AFIP por varias cuotas imputadas en el mismo cobro.
     *
     * @param  list<array{pago: CuotaPago, registro: CuotaGenerada, importe: float}>  $items
     * @return array{ok: bool, mensaje: string, idComprobanteAfip?: int}
     */
    public static function facturarLote(array $items, int $idLegajo): array
    {
        if ($items === []) {
            return ['ok' => false, 'mensaje' => 'No hay cuotas para facturar en AFIP.'];
        }

        if (count($items) === 1) {
            $item = $items[0];

            return self::facturar(
                $item['pago'],
                $item['registro'],
                $idLegajo,
                (float) $item['importe'],
            );
        }

        if (! tenantCuotasFacturacionAfipHabilitada()) {
            return ['ok' => false, 'mensaje' => 'La facturación AFIP no está habilitada para este colegio.'];
        }

        if (! Schema::hasTable('comprobanteafip')) {
            return ['ok' => false, 'mensaje' => 'La tabla comprobanteafip no existe en esta base de datos.'];
        }

        $importeTotal = 0.0;
        $interesTotal = 0.0;
        $conceptos = [];
        $importesLinea = [];
        $registros = [];

        foreach ($items as $item) {
            $pago = $item['pago'] ?? null;
            $registro = $item['registro'] ?? null;
            $importe = round((float) ($item['importe'] ?? 0), 2);

            if (! $pago instanceof CuotaPago || ! $registro instanceof CuotaGenerada || $importe <= 0) {
                continue;
            }

            $importeTotal += $importe;
            $interesTotal += round((float) ($pago->interes ?? 0), 2);
            $conceptos[] = mb_strtoupper(trim((string) ($registro->cuota?->nombre ?? 'CUOTA')));
            $importesLinea[] = number_format($importe, 2, '.', '');
            $registros[] = $registro;
        }

        $importeTotal = round($importeTotal, 2);
        if ($importeTotal <= 0 || $registros === []) {
            return ['ok' => false, 'mensaje' => 'No hay importe para facturar en AFIP.'];
        }

        $config = tenantCuotasFacturacionAfipConfig();
        if ($config === null) {
            return ['ok' => false, 'mensaje' => 'Falta configurar la facturación AFIP del colegio.'];
        }

        $legajo = GestionAranceles::legajoParaGestion($idLegajo);
        if ($legajo === null) {
            return ['ok' => false, 'mensaje' => 'No se encontró el legajo del estudiante.'];
        }

        $ento = Ento::query()
            ->where('idNivel', (int) schoolCtx()->idNivel)
            ->first([
                'insti',
                'direccion',
                'localidad',
                'telefono',
                'cuit',
                'condIvaInst',
                'aporteEstatal',
                'condicionIva',
                'ptoVta',
                'ingresosBrutos',
                'fechaInicioAct',
            ]);

        if ($ento === null || trim((string) $ento->cuit) === '') {
            return ['ok' => false, 'mensaje' => 'Faltan datos AFIP institucionales (CUIT / ento).'];
        }

        $ptoVta = (int) ($ento->ptoVta ?? 0);
        if ($ptoVta <= 0) {
            return ['ok' => false, 'mensaje' => 'Falta configurar el punto de venta AFIP en parámetros del sistema.'];
        }

        $docNro = self::documentoNumerico($legajo->dni ?? null);
        if ($docNro <= 0) {
            return ['ok' => false, 'mensaje' => 'El estudiante no tiene DNI válido para facturar.'];
        }

        [$fechaDesde, $fechaHasta] = self::periodoServicioLote($registros);
        $hoy = Carbon::today();
        $fechaYmd = $hoy->format('Ymd');

        $condicionAlumno = trim((string) ($ento->condicionIva ?? ''));
        if ($condicionAlumno === '') {
            $condicionAlumno = (string) ($config['condicion_iva_alumno'] ?? 'Consumidor Final');
        }
        $condicionIvaId = AfipCondicionIvaReceptor::idDesdeEtiqueta(
            $condicionAlumno,
            (int) ($config['condicion_iva_receptor_id'] ?? 5),
        );
        $condicionIvaEmisor = ComprobanteAfipDatos::condIvaInstDesdeEnto($ento);

        try {
            $emision = AfipWsfeEmision::emitirRecibo($config, [
                'cuit' => (string) $ento->cuit,
                'pto_vta' => $ptoVta,
                'doc_nro' => $docNro,
                'importe' => $importeTotal,
                'fecha_yyyymmdd' => $fechaYmd,
                'fch_serv_desde' => $fechaDesde,
                'fch_serv_hasta' => $fechaHasta,
                'condicion_iva_receptor_id' => $condicionIvaId,
            ]);
        } catch (Throwable $e) {
            foreach ($registros as $registro) {
                FacturacionAfipComun::guardarMensajeCuota($registro, 'Error AFIP: '.$e->getMessage());
            }

            return ['ok' => false, 'mensaje' => 'Error al facturar en AFIP: '.$e->getMessage()];
        }

        $simulado = ! empty($config['simular']);
        $cae = (string) $emision['cae'];
        $vtoCaeYmd = (string) $emision['cae_fch_vto'];
        $nroRecibo = (int) $emision['cbte_hasta'];
        $sufijoSimulado = $simulado ? ' (simulado, sin envío a AFIP)' : '';
        $codigoBarras = AfipCodigoBarras::generar(
            (string) $ento->cuit,
            (int) $config['cbte_tipo'],
            $ptoVta,
            $cae,
            $vtoCaeYmd,
        );

        $nombreResp = FacturacionAfipComun::responsableEconomicoFamilia($legajo);
        $dniResp = FacturacionAfipComun::dniRespDesdeFamilia($legajo);
        $conceptoPrincipal = count($conceptos) === 1
            ? $conceptos[0]
            : 'CUOTAS ESCOLARES';
        $nombreAlumno = mb_strtoupper(trim(($legajo->apellido ?? '').' '.($legajo->nombre ?? '')));
        $primerPago = $items[0]['pago'];
        $primerRegistro = $registros[0];
        $subConceptos = implode('|', $conceptos);
        $importeSubConceptos = implode('|', $importesLinea);
        $snapshotInst = FacturacionAfipComun::snapshotInstitucionalPdf($ento);
        $cursoAlumno = FacturacionAfipComun::cursoTextoDesdeRegistro($primerRegistro);

        try {
            $idComprobanteAfip = 0;
            DB::transaction(function () use (
                &$idComprobanteAfip,
                $items,
                $registros,
                $ento,
                $legajo,
                $ptoVta,
                $config,
                $importeTotal,
                $interesTotal,
                $fechaDesde,
                $fechaHasta,
                $hoy,
                $cae,
                $vtoCaeYmd,
                $nroRecibo,
                $codigoBarras,
                $nombreResp,
                $dniResp,
                $conceptoPrincipal,
                $subConceptos,
                $importeSubConceptos,
                $nombreAlumno,
                $docNro,
                $condicionAlumno,
                $condicionIvaEmisor,
                $sufijoSimulado,
                $primerPago,
                $primerRegistro,
                $snapshotInst,
                $cursoAlumno,
            ): void {
                $comprobante = ComprobanteAfip::query()->create([
                    'nombreInstitucion' => trim((string) $ento->insti),
                    'razonSocial' => trim((string) $ento->insti),
                    'cuitInstitucion' => preg_replace('/\D/', '', (string) $ento->cuit),
                    'domicilioComercial' => $ento->domicilioComercialCompleto(),
                    'condicionIvaInstitucion' => $condicionIvaEmisor,
                    'telefonoInstitucion' => $snapshotInst['telefonoInstitucion'],
                    'aporteEstatal' => $snapshotInst['aporteEstatal'],
                    'puntoVenta' => $ptoVta,
                    'ingresosBrutos' => trim((string) ($ento->ingresosBrutos ?? '')),
                    'fechaInicioActividades' => self::formatearFechaEnto($ento->fechaInicioAct ?? null),
                    'nombreAlumno' => $nombreAlumno,
                    'dni' => (string) $docNro,
                    'nombreResp' => $nombreResp,
                    'dniResp' => $dniResp,
                    'cursoAlumno' => $cursoAlumno,
                    'condicionIvaAlumno' => $condicionAlumno,
                    'condicionVenta' => (string) ($config['condicion_venta'] ?? 'contado'),
                    'fechaDesde' => self::formatearFechaBarra($fechaDesde),
                    'fechaHasta' => self::formatearFechaBarra($fechaHasta),
                    'fechaEmision' => $hoy->format('Y/m/d'),
                    'fechaVencimiento' => $hoy->format('Y/m/d'),
                    'tipoComprobante' => (int) $config['cbte_tipo'],
                    'docTipoAfip' => (int) ($config['doc_tipo'] ?? 96),
                    'codigoBarras' => $codigoBarras,
                    'nroRecibo' => $nroRecibo,
                    'cae' => $cae,
                    'vtoCae' => self::formatearFechaBarra($vtoCaeYmd),
                    'importePagado' => $importeTotal,
                    'interesPagado' => $interesTotal,
                    'idCbteAsoc' => (int) $primerRegistro->id,
                    'concepto' => $conceptoPrincipal,
                    'subConceptos' => $subConceptos,
                    'importeSubConceptos' => $importeSubConceptos,
                    'saldoRestante' => implode(',', array_map(
                        fn (array $item) => (int) ($item['pago']->id ?? 0),
                        $items,
                    )),
                    'idCuotasPagos' => (int) $primerPago->id,
                ]);

                $idComprobanteAfip = (int) $comprobante->idComprobanteAfip;

                foreach ($registros as $registro) {
                    FacturacionAfipComun::guardarMensajeCuota(
                        $registro,
                        'Comprobante AFIP emitido. CAE '.$cae.$sufijoSimulado,
                    );
                }
            });
        } catch (Throwable $e) {
            return ['ok' => false, 'mensaje' => 'AFIP autorizó el comprobante pero no se pudo guardar: '.$e->getMessage()];
        }

        return [
            'ok' => true,
            'mensaje' => 'Comprobante AFIP emitido correctamente. CAE '.$cae.' — Recibo Nº '.$nroRecibo.'.'.$sufijoSimulado,
            'idComprobanteAfip' => $idComprobanteAfip,
        ];
    }

    /**
     * @param  list<CuotaGenerada>  $registros
     * @return array{0: string, 1: string}
     */
    private static function periodoServicioLote(array $registros): array
    {
        $desde = null;
        $hasta = null;

        foreach ($registros as $registro) {
            [$ini, $fin] = self::periodoServicio($registro);
            if ($desde === null || $ini < $desde) {
                $desde = $ini;
            }
            if ($hasta === null || $fin > $hasta) {
                $hasta = $fin;
            }
        }

        if ($desde === null || $hasta === null) {
            $hoy = Carbon::today()->format('Ymd');

            return [$hoy, $hoy];
        }

        return [$desde, $hasta];
    }

    /**
     * Emite nota de crédito AFIP anulando la factura vigente de la cuota.
     *
     * @return array{ok: bool, mensaje: string, idComprobanteAfip?: int}
     */
    public static function emitirNotaCredito(
        CuotaGenerada $registro,
        int $idLegajo,
        ComprobanteAfip $factura,
    ): array {
        if (! tenantCuotasFacturacionAfipHabilitada()) {
            return ['ok' => false, 'mensaje' => 'La facturación AFIP no está habilitada para este colegio.'];
        }

        if (! Schema::hasTable('comprobanteafip')) {
            return ['ok' => false, 'mensaje' => 'La tabla comprobanteafip no existe en esta base de datos.'];
        }

        $config = tenantCuotasFacturacionAfipConfig();
        if ($config === null) {
            return ['ok' => false, 'mensaje' => 'Falta configurar la facturación AFIP del colegio.'];
        }

        $tipoNc = (int) ($config['nota_credito_tipo'] ?? 12);
        if ($tipoNc <= 0) {
            return ['ok' => false, 'mensaje' => 'Falta configurar el tipo de nota de crédito AFIP.'];
        }

        $importe = round((float) ($factura->importePagado ?? 0), 2);
        if ($importe <= 0) {
            return ['ok' => false, 'mensaje' => 'La factura no tiene importe para anular.'];
        }

        $ptoVta = (int) ($factura->puntoVenta ?? 0);
        if ($ptoVta <= 0) {
            return ['ok' => false, 'mensaje' => 'La factura no tiene punto de venta válido.'];
        }

        $nroFactura = (int) ($factura->nroRecibo ?? 0);
        if ($nroFactura <= 0) {
            return ['ok' => false, 'mensaje' => 'La factura no tiene número válido.'];
        }

        $tipoFactura = (int) ($factura->tipoComprobante ?? $config['cbte_tipo']);
        $docNro = self::documentoNumerico($factura->dni ?? null);
        if ($docNro <= 0) {
            return ['ok' => false, 'mensaje' => 'La factura no tiene DNI válido del destinatario.'];
        }

        $condicionIvaId = AfipCondicionIvaReceptor::idDesdeEtiqueta(
            (string) ($factura->condicionIvaAlumno ?? ''),
            (int) ($config['condicion_iva_receptor_id'] ?? 5),
        );

        [$fechaDesde, $fechaHasta] = self::periodoServicio($registro);
        $hoy = Carbon::today();
        $fechaYmd = $hoy->format('Ymd');

        try {
            $emision = AfipWsfeEmision::emitirRecibo($config, [
                'cuit' => (string) ($factura->cuitInstitucion ?? ''),
                'pto_vta' => $ptoVta,
                'doc_nro' => $docNro,
                'importe' => $importe,
                'fecha_yyyymmdd' => $fechaYmd,
                'fch_serv_desde' => $fechaDesde,
                'fch_serv_hasta' => $fechaHasta,
                'condicion_iva_receptor_id' => $condicionIvaId,
                'tipo_cbte' => $tipoNc,
                'cbte_asoc_tipo' => $tipoFactura,
                'cbte_asoc_pto_vta' => $ptoVta,
                'cbte_asoc_nro' => $nroFactura,
            ]);
        } catch (Throwable $e) {
            FacturacionAfipComun::guardarMensajeCuota($registro, 'Error AFIP NC: '.$e->getMessage());

            return ['ok' => false, 'mensaje' => 'Error al emitir nota de crédito en AFIP: '.$e->getMessage()];
        }

        $simulado = ! empty($config['simular']);
        $cae = (string) $emision['cae'];
        $vtoCaeYmd = (string) $emision['cae_fch_vto'];
        $nroNc = (int) $emision['cbte_hasta'];
        $sufijoSimulado = $simulado ? ' (simulado, sin envío a AFIP)' : '';
        $codigoBarras = AfipCodigoBarras::generar(
            (string) ($factura->cuitInstitucion ?? ''),
            $tipoNc,
            $ptoVta,
            $cae,
            $vtoCaeYmd,
        );

        $idPago = (int) ($factura->idCuotasPagos ?? 0);

        try {
            $idComprobanteAfip = 0;
            DB::transaction(function () use (
                &$idComprobanteAfip,
                $factura,
                $registro,
                $config,
                $importe,
                $fechaDesde,
                $fechaHasta,
                $hoy,
                $cae,
                $vtoCaeYmd,
                $nroNc,
                $codigoBarras,
                $tipoNc,
                $idPago,
                $sufijoSimulado,
            ): void {
                $comprobante = ComprobanteAfip::query()->create([
                    'nombreInstitucion' => trim((string) $factura->nombreInstitucion),
                    'razonSocial' => trim((string) ($factura->razonSocial ?? $factura->nombreInstitucion)),
                    'cuitInstitucion' => preg_replace('/\D/', '', (string) ($factura->cuitInstitucion ?? '')),
                    'domicilioComercial' => trim((string) ($factura->domicilioComercial ?? '')),
                    'condicionIvaInstitucion' => trim((string) ($factura->condicionIvaInstitucion ?? '')),
                    'telefonoInstitucion' => trim((string) ($factura->telefonoInstitucion ?? '')),
                    'aporteEstatal' => trim((string) ($factura->aporteEstatal ?? '')),
                    'puntoVenta' => (int) ($factura->puntoVenta ?? 0),
                    'ingresosBrutos' => trim((string) ($factura->ingresosBrutos ?? '')),
                    'fechaInicioActividades' => trim((string) ($factura->fechaInicioActividades ?? '')),
                    'nombreAlumno' => trim((string) ($factura->nombreAlumno ?? '')),
                    'dni' => trim((string) ($factura->dni ?? '')),
                    'nombreResp' => trim((string) ($factura->nombreResp ?? '')),
                    'dniResp' => trim((string) ($factura->dniResp ?? '')),
                    'cursoAlumno' => trim((string) ($factura->cursoAlumno ?? '')),
                    'condicionIvaAlumno' => trim((string) ($factura->condicionIvaAlumno ?? '')),
                    'condicionVenta' => trim((string) ($factura->condicionVenta ?? 'contado')),
                    'fechaDesde' => trim((string) ($factura->fechaDesde ?? self::formatearFechaBarra($fechaDesde))),
                    'fechaHasta' => trim((string) ($factura->fechaHasta ?? self::formatearFechaBarra($fechaHasta))),
                    'fechaEmision' => $hoy->format('Y/m/d'),
                    'fechaVencimiento' => $hoy->format('Y/m/d'),
                    'tipoComprobante' => $tipoNc,
                    'docTipoAfip' => (int) ($factura->docTipoAfip ?? $config['doc_tipo'] ?? 96),
                    'codigoBarras' => $codigoBarras,
                    'nroRecibo' => $nroNc,
                    'cae' => $cae,
                    'vtoCae' => self::formatearFechaBarra($vtoCaeYmd),
                    'importePagado' => $importe,
                    'interesPagado' => round((float) ($factura->interesPagado ?? 0), 2),
                    'idCbteAsoc' => (int) $registro->id,
                    'concepto' => trim((string) ($factura->concepto ?? '')),
                    'subConceptos' => (string) (int) $factura->idComprobanteAfip,
                    'importeSubConceptos' => '',
                    'saldoRestante' => trim((string) ($factura->saldoRestante ?? '')),
                    'idCuotasPagos' => $idPago > 0 ? $idPago : (int) ($factura->idCuotasPagos ?? 0),
                ]);

                $idComprobanteAfip = (int) $comprobante->idComprobanteAfip;

                FacturacionAfipComun::guardarMensajeCuota(
                    $registro,
                    'Nota de crédito AFIP emitida. CAE '.$cae.$sufijoSimulado,
                );
            });
        } catch (Throwable $e) {
            return ['ok' => false, 'mensaje' => 'AFIP autorizó la nota de crédito pero no se pudo guardar: '.$e->getMessage()];
        }

        return [
            'ok' => true,
            'mensaje' => 'Nota de crédito AFIP emitida correctamente. CAE '.$cae.' — Nº '.$nroNc.'.'.$sufijoSimulado,
            'idComprobanteAfip' => $idComprobanteAfip,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function periodoServicio(CuotaGenerada $registro): array
    {
        $ano = (int) ($registro->terlec?->ano ?? schoolCtx()->terlecAno());
        if ($ano <= 0) {
            $ano = (int) Carbon::today()->year;
        }

        $mes = self::numeroMesDesdeRegistro($registro);
        if ($mes < 1 || $mes > 12) {
            $fecha = $registro->venc1 ?? Carbon::today();

            return [$fecha->copy()->startOfMonth()->format('Ymd'), $fecha->copy()->endOfMonth()->format('Ymd')];
        }

        $inicio = Carbon::create($ano, $mes, 1)->startOfDay();
        $fin = $inicio->copy()->endOfMonth();

        return [$inicio->format('Ymd'), $fin->format('Ymd')];
    }

    private static function numeroMesDesdeRegistro(CuotaGenerada $registro): int
    {
        $idMes = (int) ($registro->idCuotasmeses ?? 0);
        if ($idMes > 0) {
            $mesCatalogo = CuotasMes::query()->find($idMes, ['mes']);
            $mes = self::mesDesdeEtiqueta((string) ($mesCatalogo?->mes ?? ''));
            if ($mes > 0) {
                return $mes;
            }
        }

        return self::mesDesdeEtiqueta((string) ($registro->cuota?->nombre ?? ''));
    }

    private static function mesDesdeEtiqueta(string $texto): int
    {
        $mapa = [
            'enero' => 1,
            'febrero' => 2,
            'marzo' => 3,
            'abril' => 4,
            'mayo' => 5,
            'junio' => 6,
            'julio' => 7,
            'agosto' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'octubre' => 10,
            'noviembre' => 11,
            'diciembre' => 12,
        ];

        $n = mb_strtolower(trim($texto));

        return $mapa[$n] ?? 0;
    }

    private static function documentoNumerico(mixed $valor): int
    {
        $digits = preg_replace('/\D/', '', (string) $valor) ?? '';

        return (int) $digits;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function responsablePago(Legajo $legajo): array
    {
        $nombre = trim((string) ($legajo->respAdmiNom ?? ''));
        $dni = self::documentoNumerico($legajo->respAdmiDni ?? null);

        if ($nombre === '' || $dni <= 0) {
            $nombre = trim((string) ($legajo->nombrepad ?? ''));
            $dni = self::documentoNumerico($legajo->dnipad ?? null);
        }
        if ($nombre === '' || $dni <= 0) {
            $nombre = trim((string) ($legajo->nombremad ?? ''));
            $dni = self::documentoNumerico($legajo->dnimad ?? null);
        }

        return [$nombre, $dni > 0 ? (string) $dni : ''];
    }

    private static function formatearFechaBarra(string $yyyymmdd): string
    {
        $raw = preg_replace('/\D/', '', $yyyymmdd) ?? '';
        if (strlen($raw) !== 8) {
            return Carbon::today()->format('Y/m/d');
        }

        return substr($raw, 0, 4).'/'.substr($raw, 4, 2).'/'.substr($raw, 6, 2);
    }

    private static function formatearFechaEnto(mixed $valor): string
    {
        $raw = trim((string) ($valor ?? ''));
        if ($raw === '') {
            return '';
        }

        if (str_contains($raw, '/')) {
            return $raw;
        }

        try {
            return Carbon::parse($raw)->format('d/m/Y');
        } catch (Throwable) {
            return $raw;
        }
    }
}
