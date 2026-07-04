<?php

namespace App\Support\Cuotas;

use App\Models\ComprobanteAfip;
use App\Models\Ento;
use App\Support\Afip\AfipWsfeConsulta;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Consulta en AFIP de un comprobante por punto de venta, tipo y número.
 */
final class ConsultaAfipComprobanteService
{
    public const TIPO_FACTURA = 'factura';

    public const TIPO_NOTA_CREDITO = 'nota_credito';

    public static function moduloDisponible(): bool
    {
        return tenantCuotasFacturacionAfipHabilitada();
    }

    /**
     * @return array{ok: bool, mensaje: string, datos?: array<string, mixed>, registro_local?: array<string, mixed>|null}
     */
    public static function consultar(string $tipo, string $numeroComprobante): array
    {
        if (! self::moduloDisponible()) {
            return ['ok' => false, 'mensaje' => 'La facturación AFIP no está habilitada para este colegio.'];
        }

        $config = tenantCuotasFacturacionAfipConfig();
        if ($config === null) {
            return ['ok' => false, 'mensaje' => 'Falta configurar la facturación AFIP del colegio.'];
        }

        $tipoCmp = self::tipoComprobanteAfip($tipo, $config);
        if ($tipoCmp === null) {
            return ['ok' => false, 'mensaje' => 'Seleccione factura o nota de crédito.'];
        }

        $ento = Ento::query()
            ->where('idNivel', (int) schoolCtx()->idNivel)
            ->first(['cuit', 'ptoVta']);

        if ($ento === null || trim((string) $ento->cuit) === '') {
            return ['ok' => false, 'mensaje' => 'Faltan datos AFIP institucionales (CUIT / ento).'];
        }

        $ptoVtaDefault = (int) ($ento->ptoVta ?? 0);
        if ($ptoVtaDefault <= 0) {
            return ['ok' => false, 'mensaje' => 'Falta configurar el punto de venta AFIP en parámetros del sistema.'];
        }

        $parseo = self::parsearNumeroComprobante($numeroComprobante, $ptoVtaDefault);
        if (! $parseo['ok']) {
            return ['ok' => false, 'mensaje' => $parseo['mensaje']];
        }

        $ptoVta = $parseo['pto_vta'];
        $cbteNro = $parseo['cbte_nro'];
        $cuit = preg_replace('/\D/', '', (string) $ento->cuit) ?? '';

        try {
            $datos = AfipWsfeConsulta::consultarComprobante($config, [
                'cuit' => $cuit,
                'pto_vta' => $ptoVta,
                'cbte_tipo' => $tipoCmp,
                'cbte_nro' => $cbteNro,
            ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'mensaje' => 'Error al consultar en AFIP: '.$e->getMessage()];
        }

        if (! empty($config['simular']) && Schema::hasTable('comprobanteafip')) {
            $local = ComprobanteAfip::query()
                ->where('puntoVenta', $ptoVta)
                ->where('tipoComprobante', $tipoCmp)
                ->where('nroRecibo', $cbteNro)
                ->first();

            if ($local !== null) {
                $datos = self::enriquecerSimuladoDesdeLocal($datos, $local);
            }
        }

        $datos['numero_formateado'] = self::numeroFormateado($ptoVta, $cbteNro);
        $datos['tipo_etiqueta'] = self::etiquetaTipo($tipo, $config);
        $datos['cuit_emisor'] = $cuit;

        return [
            'ok' => true,
            'mensaje' => ! empty($datos['simulado'])
                ? 'Consulta simulada (sin envío a AFIP).'
                : 'Comprobante consultado en AFIP.',
            'datos' => $datos,
            'registro_local' => self::registroLocal($ptoVta, $tipoCmp, $cbteNro),
        ];
    }

    /**
     * Etiqueta del comprobante de débito según `cbte_tipo` del tenant (11 = Factura, 15 = Recibo).
     *
     * @param  array<string, mixed>|null  $config
     */
    public static function etiquetaComprobanteFacturaAfip(?array $config = null): string
    {
        $config ??= tenantCuotasFacturacionAfipConfig() ?? [];
        $cbteTipo = (int) ($config['cbte_tipo'] ?? 15);

        return match ($cbteTipo) {
            11 => 'Factura',
            15 => 'Recibo',
            default => 'Factura / Recibo',
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function etiquetaTipo(string $tipo, ?array $config = null): string
    {
        $config ??= tenantCuotasFacturacionAfipConfig() ?? [];

        return match ($tipo) {
            self::TIPO_NOTA_CREDITO => 'Nota de crédito C',
            self::TIPO_FACTURA => self::etiquetaComprobanteFacturaAfip($config),
            default => self::etiquetaComprobanteFacturaAfip($config),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function tipoComprobanteAfip(string $tipo, array $config): ?int
    {
        return match ($tipo) {
            self::TIPO_FACTURA => (int) ($config['cbte_tipo'] ?? 15),
            self::TIPO_NOTA_CREDITO => (int) ($config['nota_credito_tipo'] ?? 12),
            default => null,
        };
    }

    /**
     * @return array{ok: bool, mensaje: string, pto_vta?: int, cbte_nro?: int}
     */
    public static function parsearNumeroComprobante(string $entrada, int $ptoVtaDefault): array
    {
        $entrada = trim($entrada);
        if ($entrada === '') {
            return ['ok' => false, 'mensaje' => 'Ingrese el número de comprobante.'];
        }

        if (str_contains($entrada, '-')) {
            $partes = array_map('trim', explode('-', $entrada, 2));
            if (count($partes) !== 2) {
                return ['ok' => false, 'mensaje' => 'Formato de número inválido. Use PPPP-NNNNNNNN o solo el número.'];
            }

            $ptoVta = (int) preg_replace('/\D/', '', $partes[0]);
            $cbteNro = (int) preg_replace('/\D/', '', $partes[1]);

            if ($ptoVta <= 0 || $cbteNro <= 0) {
                return ['ok' => false, 'mensaje' => 'Punto de venta y número deben ser mayores a cero.'];
            }

            return ['ok' => true, 'mensaje' => '', 'pto_vta' => $ptoVta, 'cbte_nro' => $cbteNro];
        }

        $cbteNro = (int) preg_replace('/\D/', '', $entrada);
        if ($cbteNro <= 0) {
            return ['ok' => false, 'mensaje' => 'El número de comprobante debe ser mayor a cero.'];
        }

        return ['ok' => true, 'mensaje' => '', 'pto_vta' => $ptoVtaDefault, 'cbte_nro' => $cbteNro];
    }

    public static function numeroFormateado(int $ptoVta, int $nro): string
    {
        return str_pad((string) $ptoVta, 4, '0', STR_PAD_LEFT)
            .'-'
            .str_pad((string) $nro, 8, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function registroLocal(int $ptoVta, int $tipoCmp, int $cbteNro): ?array
    {
        if (! Schema::hasTable('comprobanteafip')) {
            return null;
        }

        $local = ComprobanteAfip::query()
            ->where('puntoVenta', $ptoVta)
            ->where('tipoComprobante', $tipoCmp)
            ->where('nroRecibo', $cbteNro)
            ->first();

        if ($local === null) {
            return null;
        }

        return [
            'id' => (int) $local->idComprobanteAfip,
            'importe' => round((float) ($local->importePagado ?? 0), 2),
            'cae' => trim((string) ($local->cae ?? '')),
            'fecha_emision' => trim((string) ($local->fechaEmision ?? '')),
            'nombre_alumno' => trim((string) ($local->nombreAlumno ?? '')),
            'dni' => trim((string) ($local->dni ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private static function enriquecerSimuladoDesdeLocal(array $datos, ComprobanteAfip $local): array
    {
        $datos['importe_total'] = round((float) ($local->importePagado ?? 0), 2);
        $datos['importe_neto'] = $datos['importe_total'];
        $datos['doc_nro'] = trim((string) ($local->dni ?? ''));
        $datos['cae'] = trim((string) ($local->cae ?? $datos['cae']));
        $datos['fecha_emision'] = self::fechaLocalABarra((string) ($local->fechaEmision ?? ''));
        $datos['vto_cae'] = self::fechaLocalABarra((string) ($local->vtoCae ?? ''));

        return $datos;
    }

    private static function fechaLocalABarra(string $fecha): ?string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $fecha, $m)) {
            return sprintf('%02d/%02d/%04d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', str_replace('-', '/', $fecha), $m)) {
            return sprintf('%02d/%02d/%04d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return $fecha;
    }
}
