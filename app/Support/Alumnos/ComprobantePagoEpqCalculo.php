<?php

namespace App\Support\Alumnos;

use App\Models\CuotaGenerada;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionBarcodeComprobante448;
use Carbon\CarbonInterface;

/**
 * Cupón EPQ (Escuelas Pías) — cálculo legacy: barcode 0448 modalidad anterior y CPE fijo.
 */
final class ComprobantePagoEpqCalculo
{
    private const EMPRESA_SERVICIO = '0448';

    /** Tipo + moneda + sucursal + cuenta + DV (10 dígitos en el barcode). */
    private const BARCODE_NUMERO_CUENTA = '5120281108';

    private const CPE_TIPO_OPERACION = '5';

    private const CPE_MONEDA = '1';

    private const CPE_SUCURSAL = '2';

    private const CPE_NUMERO_CUENTA = '028110';

    private const CPE_DIGITO_VERIFICADOR = '8';

    /**
     * @return array<string, mixed>|null
     */
    public static function paraCuotaGenerada(CuotaGenerada $registro, ?array $pdfHeader = null): ?array
    {
        $datos = ComprobantePagoCalculo::paraCuotaGenerada($registro, $pdfHeader);
        if ($datos === null) {
            return null;
        }

        $datos = self::aplicarFormatoFechasEpq($datos, $registro);

        if (! tenantCuotasSiroHabilitado()) {
            return $datos;
        }

        $idLegajos = (int) $registro->idLegajos;
        $idCuotas = (int) $registro->idCuotas;
        $ultUpload = max(1, (int) ($registro->ultUpload ?? 0));

        $identConcepto = ! empty($datos['cuponVencido'])
            ? '4'
            : (trim((string) ($datos['leyendaBonificada'] ?? '')) !== '' ? '3' : '1');

        $identUsuario = SiroDescargaRendicionBarcodeComprobante448::armarIdentUsuarioLegacy(
            $identConcepto,
            $idLegajos,
            $idCuotas,
            $ultUpload,
        );

        if (! empty($datos['cuponVencido'])) {
            $nuevoVenc = self::carbon($registro->nueVenc);
            $nuevoImporte = (float) ($datos['importeVenc3'] ?? 0);
            $partes = [
                'empresaServicio' => self::EMPRESA_SERVICIO,
                'identUsuario' => $identUsuario,
                'fecha1erVenc' => ComprobantePagoCodigoBarras::fechaCodigo($nuevoVenc),
                'importe1erVenc' => ComprobantePagoCodigoBarras::importeCodigo($nuevoImporte),
                'dias2doVenc' => '00',
                'importe2doVenc' => ComprobantePagoCodigoBarras::importeCodigo($nuevoImporte),
                'numeroCuenta' => self::BARCODE_NUMERO_CUENTA,
            ];
        } else {
            $venc1 = self::carbon($registro->venc1);
            $venc2 = self::carbon($registro->venc2);
            $importeVenc1 = (float) ($datos['importeVenc1'] ?? 0);
            $importeVenc2 = (float) ($datos['importeVenc2'] ?? 0);

            $partes = [
                'empresaServicio' => self::EMPRESA_SERVICIO,
                'identUsuario' => $identUsuario,
                'fecha1erVenc' => ComprobantePagoCodigoBarras::fechaCodigo($venc1),
                'importe1erVenc' => ComprobantePagoCodigoBarras::importeCodigo($importeVenc1),
                'dias2doVenc' => str_pad((string) self::diasEntre($venc2, $venc1), 2, '0', STR_PAD_LEFT),
                'importe2doVenc' => ComprobantePagoCodigoBarras::importeCodigo($importeVenc2),
                'numeroCuenta' => self::BARCODE_NUMERO_CUENTA,
            ];
        }

        $datos['barra'] = ComprobantePagoCodigoBarras::armar($partes);
        $datos['codigoPagoElectronico'] = self::codigoPagoElectronico($idLegajos);
        $datos['cadenaQr'] = '';

        return $datos;
    }

    /**
     * Fechas visibles en el cupón EPQ (legacy ScriptCase): Y-m-d.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private static function aplicarFormatoFechasEpq(array $datos, CuotaGenerada $registro): array
    {
        if (! empty($datos['cuponVencido'])) {
            $nuevoVenc = self::carbon($registro->nueVenc ?? $registro->venc3);
            $datos['nuevoVencEsp'] = $nuevoVenc?->format('Y-m-d') ?? '';

            return $datos;
        }

        $venc1 = self::carbon($registro->venc1);
        $venc2 = self::carbon($registro->venc2);
        $datos['venc1Esp'] = $venc1?->format('Y-m-d') ?? '';
        $datos['venc2Esp'] = $venc2?->format('Y-m-d') ?? '';

        return $datos;
    }

    public static function codigoPagoElectronico(int $idLegajos): string
    {
        if (! tenantCuotasSiroHabilitado()) {
            return '';
        }

        return str_pad((string) $idLegajos, 9, '0', STR_PAD_LEFT)
            .self::CPE_TIPO_OPERACION
            .self::CPE_MONEDA
            .self::CPE_SUCURSAL
            .self::CPE_NUMERO_CUENTA
            .self::CPE_DIGITO_VERIFICADOR;
    }

    private static function diasEntre(?CarbonInterface $fechaMayor, ?CarbonInterface $fechaMenor): int
    {
        if ($fechaMayor === null || $fechaMenor === null) {
            return 0;
        }

        return max(0, $fechaMenor->diffInDays($fechaMayor, false));
    }

    private static function carbon(mixed $fecha): ?CarbonInterface
    {
        if ($fecha instanceof CarbonInterface) {
            return $fecha;
        }

        $raw = trim((string) ($fecha ?? ''));
        if ($raw === '' || $raw === '0000-00-00') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
