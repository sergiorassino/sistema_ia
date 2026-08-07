<?php

namespace App\Support\Alumnos;

use App\Models\CuotaGenerada;
use App\Support\Cuotas\Siro\Descarga\SiroDescargaRendicionBarcodeComprobante448;
use App\Support\Cuotas\Siro\SiroCodigoPagoElectronico;
use Carbon\CarbonInterface;

/**
 * Cupón EPQ / SFQ — barcode 0448 modalidad anterior; CPE y cuenta desde parámetros SIRO del nivel.
 */
final class ComprobantePagoEpqCalculo
{
    private const EMPRESA_SERVICIO = '0448';

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
        $idNivel = (int) ($registro->curso?->idNivel ?? 0);
        $ultUpload = max(1, (int) ($registro->ultUpload ?? 0));

        // CPE y cuenta: solo desde ento del nivel (sin constantes hardcodeadas).
        SiroCodigoPagoElectronico::exigirParaOperacion($idNivel);
        $cuentaSiroNivel = SiroCodigoPagoElectronico::cuentaRecaudadoraPorNivel($idNivel);
        $datos['codigoPagoElectronico'] = SiroCodigoPagoElectronico::generar($idLegajos, $idNivel);

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
                'numeroCuenta' => $cuentaSiroNivel,
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
                'numeroCuenta' => $cuentaSiroNivel,
            ];
        }

        $datos['barra'] = ComprobantePagoCodigoBarras::armar($partes);
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

    public static function codigoPagoElectronico(int $idLegajos, int $idNivel): string
    {
        if (! tenantCuotasSiroHabilitado()) {
            return '';
        }

        return SiroCodigoPagoElectronico::generar($idLegajos, $idNivel);
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
