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
                'cuit',
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

        $condicionAlumno = (string) ($config['condicion_iva_alumno'] ?? 'Consumidor Final');
        $condicionIvaId = AfipCondicionIvaReceptor::idDesdeEtiqueta(
            $condicionAlumno,
            (int) ($config['condicion_iva_receptor_id'] ?? 5),
        );

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
            self::guardarMensajeCuota($registro, 'Error AFIP: '.$e->getMessage());

            return ['ok' => false, 'mensaje' => 'Error al facturar en AFIP: '.$e->getMessage()];
        }

        $cae = (string) $emision['cae'];
        $vtoCaeYmd = (string) $emision['cae_fch_vto'];
        $nroRecibo = (int) $emision['cbte_hasta'];
        $codigoBarras = AfipCodigoBarras::generar(
            (string) $ento->cuit,
            (int) $config['cbte_tipo'],
            $ptoVta,
            $cae,
            $vtoCaeYmd,
        );

        [$nombreResp, $dniResp] = self::responsablePago($legajo);
        $concepto = mb_strtoupper(trim((string) ($registro->cuota?->nombre ?? 'CUOTA')));
        $nombreAlumno = mb_strtoupper(trim(($legajo->apellido ?? '').' '.($legajo->nombre ?? '')));

        try {
            DB::transaction(function () use (
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
            ): void {
                ComprobanteAfip::query()->create([
                    'nombreInstitucion' => trim((string) $ento->insti),
                    'razonSocial' => trim((string) $ento->insti),
                    'cuitInstitucion' => preg_replace('/\D/', '', (string) $ento->cuit),
                    'domicilioComercial' => trim((string) $ento->direccion),
                    'condicionIvaInstitucion' => trim((string) ($ento->condicionIva ?? '')),
                    'puntoVenta' => $ptoVta,
                    'ingresosBrutos' => trim((string) ($ento->ingresosBrutos ?? '')),
                    'fechaInicioActividades' => self::formatearFechaEnto($ento->fechaInicioAct ?? null),
                    'nombreAlumno' => $nombreAlumno,
                    'dni' => (string) $docNro,
                    'nombreResp' => $nombreResp,
                    'dniResp' => $dniResp,
                    'domicilioAlumno' => trim((string) ($legajo->callenum ?? '')),
                    'condicionIvaAlumno' => $condicionAlumno,
                    'condicionVenta' => (string) ($config['condicion_venta'] ?? 'contado'),
                    'fechaDesde' => self::formatearFechaBarra($fechaDesde),
                    'fechaHasta' => self::formatearFechaBarra($fechaHasta),
                    'fechaEmision' => $hoy->format('Y/m/d'),
                    'fechaVencimiento' => $hoy->format('Y/m/d'),
                    'tipoComprobante' => (int) $config['cbte_tipo'],
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

                $registro->nroComp = $nroRecibo;
                $registro->mensajeResultado = 'Comprobante AFIP emitido. CAE '.$cae;
                $registro->save();
            });
        } catch (Throwable $e) {
            return ['ok' => false, 'mensaje' => 'AFIP autorizó el comprobante pero no se pudo guardar: '.$e->getMessage()];
        }

        return [
            'ok' => true,
            'mensaje' => 'Comprobante AFIP emitido correctamente. CAE '.$cae.' — Recibo Nº '.$nroRecibo.'.',
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

    private static function guardarMensajeCuota(CuotaGenerada $registro, string $mensaje): void
    {
        try {
            $registro->mensajeResultado = mb_substr($mensaje, 0, 500);
            $registro->save();
        } catch (Throwable) {
            // No bloquear el flujo de pago por un fallo al guardar el mensaje auxiliar.
        }
    }
}
