<?php

namespace App\Support\Cuotas;

use App\Models\ComprobanteAfip;
use App\Models\CuotaPago;
use App\Support\Afip\AfipComprobanteQrUrl;
use App\Support\Afip\AfipCondicionIvaReceptor;
use Carbon\Carbon;

/**
 * Arma los datos del PDF de comprobante AFIP a partir de un pago imputado.
 */
final class ComprobanteAfipDatos
{
    /**
     * @return array<string, mixed>|null
     */
    public static function paraComprobanteRegistro(int $idComprobanteAfip, int $idLegajo): ?array
    {
        $comprobante = ComprobanteAfip::query()->find($idComprobanteAfip);
        if ($comprobante === null) {
            return null;
        }

        $idCuotaGenerada = (int) ($comprobante->idCbteAsoc ?? 0);
        if ($idCuotaGenerada <= 0 || GestionAranceles::cuotaDelLegajo($idCuotaGenerada, $idLegajo) === null) {
            return null;
        }

        return self::datosDesdeModelo($comprobante);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function paraPago(CuotaPago $pago, int $idLegajo): ?array
    {
        $idCuotaGenerada = (int) ($pago->idCuotasGeneradas ?? 0);
        if ($idCuotaGenerada <= 0 || GestionAranceles::cuotaDelLegajo($idCuotaGenerada, $idLegajo) === null) {
            return null;
        }

        $comprobante = ComprobanteAfip::query()
            ->vinculadoAPago((int) $pago->id)
            ->orderByDesc('idComprobanteAfip')
            ->first();

        if ($comprobante === null) {
            return null;
        }

        return self::datosDesdeModelo($comprobante);
    }

    /**
     * @return array<string, mixed>
     */
    private static function datosDesdeModelo(ComprobanteAfip $comprobante): array
    {
        $config = tenantCuotasFacturacionAfipConfig();
        $docTipo = (int) ($config['doc_tipo'] ?? 96);
        $tipoComprobante = (int) ($comprobante->tipoComprobante ?? 15);
        $ptoVta = (int) ($comprobante->puntoVenta ?? 0);
        $nroRecibo = (int) ($comprobante->nroRecibo ?? 0);
        $importe = round((float) ($comprobante->importePagado ?? 0), 2);
        $fechaEmision = self::fechaAFormatoBarra((string) ($comprobante->fechaEmision ?? ''));
        $fechaYmd = self::fechaABarrraAYmd($fechaEmision);
        $fechaQr = self::fechaABarraAIso($fechaEmision);
        $vtoCae = self::fechaAFormatoBarra((string) ($comprobante->vtoCae ?? ''));
        $condicionIvaId = AfipCondicionIvaReceptor::idDesdeEtiqueta(
            (string) ($comprobante->condicionIvaAlumno ?? ''),
            (int) ($config['condicion_iva_receptor_id'] ?? 5),
        );
        $condicionIvaInstitucion = self::etiquetaCondicionIvaEmisor(
            (string) ($comprobante->ingresosBrutos ?? ''),
            (string) ($comprobante->condicionIvaInstitucion ?? ''),
        );

        $urlQr = AfipComprobanteQrUrl::generar([
            'fecha_yyyy_mm_dd' => $fechaQr,
            'cuit' => (string) ($comprobante->cuitInstitucion ?? ''),
            'pto_vta' => $ptoVta,
            'tipo_cmp' => $tipoComprobante,
            'nro_cmp' => $nroRecibo,
            'importe' => $importe,
            'doc_tipo' => $docTipo,
            'doc_nro' => (string) ($comprobante->dni ?? ''),
            'cae' => (string) ($comprobante->cae ?? ''),
        ]);

        return [
            'razonSocial' => trim((string) ($comprobante->razonSocial ?? $comprobante->nombreInstitucion ?? '')),
            'tipoComprobante' => $tipoComprobante,
            'puntoVenta' => $ptoVta,
            'nroComprobante' => $nroRecibo,
            'numeroComprobanteTexto' => str_pad((string) $ptoVta, 4, '0', STR_PAD_LEFT)
                .'-'
                .str_pad((string) $nroRecibo, 8, '0', STR_PAD_LEFT),
            'cuitInstitucion' => trim((string) ($comprobante->cuitInstitucion ?? '')),
            'domicilioComercial' => trim((string) ($comprobante->domicilioComercial ?? '')),
            'ingresosBrutos' => trim((string) ($comprobante->ingresosBrutos ?? '')),
            'fechaInicioActividades' => self::fechaAFormatoBarra((string) ($comprobante->fechaInicioActividades ?? '')),
            'condicionIvaInstitucion' => $condicionIvaInstitucion,
            'fechaEmision' => $fechaEmision,
            'docNro' => trim((string) ($comprobante->dni ?? '')),
            'docTipo' => $docTipo,
            'nombreCliente' => trim((string) ($comprobante->nombreAlumno ?? '')),
            'condicionIvaReceptorId' => $condicionIvaId,
            'condicionIvaReceptorTexto' => trim((string) ($comprobante->condicionIvaAlumno ?? '')),
            'condicionVenta' => self::etiquetaCondicionVenta((string) ($comprobante->condicionVenta ?? '')),
            'concepto' => trim((string) ($comprobante->concepto ?? '')),
            'importe' => $importe,
            'importeFmt' => number_format($importe, 2, ',', '.'),
            'lineas' => self::lineasDesdeComprobante($comprobante),
            'cae' => trim((string) ($comprobante->cae ?? '')),
            'vtoCae' => $vtoCae,
            'urlQr' => $urlQr,
            'fechaYmd' => $fechaYmd,
        ];
    }

    /**
     * @return list<array{concepto: string, importe: float, importeFmt: string}>
     */
    private static function lineasDesdeComprobante(ComprobanteAfip $comprobante): array
    {
        $subs = trim((string) ($comprobante->subConceptos ?? ''));
        $imps = trim((string) ($comprobante->importeSubConceptos ?? ''));

        if ($subs === '' || $imps === '') {
            $importe = round((float) ($comprobante->importePagado ?? 0), 2);

            return [[
                'concepto' => trim((string) ($comprobante->concepto ?? '')),
                'importe' => $importe,
                'importeFmt' => number_format($importe, 2, ',', '.'),
            ]];
        }

        $nombres = explode('|', $subs);
        $importes = explode('|', $imps);
        $lineas = [];

        foreach ($nombres as $idx => $nombre) {
            $concepto = trim((string) $nombre);
            if ($concepto === '') {
                continue;
            }

            $rawImporte = trim((string) ($importes[$idx] ?? '0'));
            $importe = round((float) str_replace(',', '.', $rawImporte), 2);

            $lineas[] = [
                'concepto' => $concepto,
                'importe' => $importe,
                'importeFmt' => number_format($importe, 2, ',', '.'),
            ];
        }

        if ($lineas === []) {
            $importe = round((float) ($comprobante->importePagado ?? 0), 2);

            return [[
                'concepto' => trim((string) ($comprobante->concepto ?? '')),
                'importe' => $importe,
                'importeFmt' => number_format($importe, 2, ',', '.'),
            ]];
        }

        return $lineas;
    }

    public static function etiquetaCondicionIvaEmisor(string $ingresosBrutos = '', string $fallback = ''): string
    {
        $ib = mb_strtolower(trim($ingresosBrutos));
        if ($ib !== '' && str_contains($ib, 'exento')) {
            return 'IVA Sujeto Exento';
        }

        $fallback = trim($fallback);
        if ($fallback !== '') {
            return $fallback;
        }

        return 'Responsable Monotributo';
    }

    private static function etiquetaCondicionVenta(string $valor): string
    {
        $n = mb_strtolower(trim($valor));

        return match ($n) {
            'contado' => 'Contado',
            'cuenta corriente', 'cuenta_corriente' => 'Cuenta Corriente',
            default => $valor !== '' ? mb_convert_case($valor, MB_CASE_TITLE, 'UTF-8') : 'Contado',
        };
    }

    private static function fechaAFormatoBarra(string $valor): string
    {
        $raw = trim($valor);
        if ($raw === '') {
            return Carbon::today()->format('d/m/Y');
        }

        if (str_contains($raw, '/')) {
            $partes = explode('/', $raw);
            if (count($partes) === 3 && strlen($partes[2]) === 4) {
                return sprintf('%02d/%02d/%04d', (int) $partes[0], (int) $partes[1], (int) $partes[2]);
            }

            if (count($partes) === 3 && strlen($partes[0]) === 4) {
                return sprintf('%02d/%02d/%04d', (int) $partes[2], (int) $partes[1], (int) $partes[0]);
            }

            return $raw;
        }

        try {
            return Carbon::parse($raw)->format('d/m/Y');
        } catch (\Throwable) {
            return Carbon::today()->format('d/m/Y');
        }
    }

    private static function fechaABarrraAYmd(string $fechaBarra): string
    {
        $partes = explode('/', $fechaBarra);
        if (count($partes) !== 3) {
            return Carbon::today()->format('Ymd');
        }

        return sprintf('%04d%02d%02d', (int) $partes[2], (int) $partes[1], (int) $partes[0]);
    }

    private static function fechaABarraAIso(string $fechaBarra): string
    {
        $partes = explode('/', $fechaBarra);
        if (count($partes) !== 3) {
            return Carbon::today()->format('Y-m-d');
        }

        return sprintf('%04d-%02d-%02d', (int) $partes[2], (int) $partes[1], (int) $partes[0]);
    }
}
