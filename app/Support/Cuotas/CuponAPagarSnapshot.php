<?php

namespace App\Support\Cuotas;

use App\Models\CuotaGenerada;
use App\Models\CuotasImporte;
use App\Support\Cuotas\Siro\SiroConfiguracionIncompletaException;
use App\Support\Cuotas\Siro\SiroIdFactura;
use App\Support\Cuotas\Siro\SiroSubidaBaseDeudaArchivo;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Arma el snapshot de un cupón emitido (subida SIRO o impresión PDF).
 */
final class CuponAPagarSnapshot
{
    /**
     * @param  array<string, mixed>  $cupon  Salida de {@see \App\Support\Alumnos\ComprobantePagoCalculo::paraCuotaGenerada}
     * @return array<string, mixed>
     */
    public static function armar(CuotaGenerada $registro, array $cupon, string $cpe, int $idNivel): array
    {
        $importes = CuotasImporte::query()
            ->where('idCuotas', (int) $registro->idCuotas)
            ->where('idCursos', (int) $registro->idCursos)
            ->first();

        $formula = self::formulaDesdeImportes($importes);

        // Legacy Scriptcase: el archivo SIRO siempre lleva venc1/venc2/venc3 de cuotasgeneradas.
        $venc1 = self::carbon($registro->venc1);
        $venc2 = self::carbon($registro->venc2);
        $venc3 = self::carbon($registro->venc3);

        if ($venc1 === null) {
            throw new \RuntimeException('Sin fecha de 1.er vencimiento para SIRO.');
        }

        $importe1 = (float) ($cupon['importeVenc1'] ?? 0);
        $importe2 = (float) ($cupon['importeVenc2'] ?? 0);
        $importe3 = (float) ($cupon['importeVenc3'] ?? 0);

        if ($venc2 === null) {
            $importe2 = 0.0;
        } elseif ($importe2 < $importe1) {
            $importe2 = $importe1;
        }

        if ($venc3 === null) {
            $importe3 = 0.0;
        } elseif ($importe3 < $importe2) {
            $importe3 = $importe2;
        }

        return self::completarDetalle($registro, $cupon, $cpe, $idNivel, $formula, $venc1, $venc2, $venc3, $importe1, $importe2, $importe3);
    }

    /**
     * Importe a pagar (saldo + interés − bonificación) según parámetros del sistema a una fecha dada.
     */
    public static function importeConInteresesEnFecha(CuotaGenerada $registro, CarbonInterface $fecha): float
    {
        $faltapa = max(0, round((float) ($registro->faltapa ?? 0), 2));
        if ($faltapa <= 0) {
            return 0.0;
        }

        $calc = ImputacionPagoCalculo::calcular($registro, $faltapa, $fecha->copy()->startOfDay());

        return max(0, round((float) $calc['aPagar'], 2));
    }

    /**
     * Snapshot para «Actualizar cupones vencidos y subir»: tres tramos con fecha {@see CuotaGenerada::$nueVenc}
     * e importe con intereses calculado a esa fecha ({@see ImputacionPagoCalculo}).
     *
     * @param  array<string, mixed>  $cupon
     * @return array<string, mixed>
     */
    public static function armarParaCuponesVencidosSiro(CuotaGenerada $registro, array $cupon, string $cpe, int $idNivel): array
    {
        $nueVenc = self::carbon($registro->nueVenc);
        if ($nueVenc === null) {
            throw new \RuntimeException('Sin fecha nueVenc para subida de cupones vencidos SIRO.');
        }

        $importe = self::importeConInteresesEnFecha($registro, $nueVenc);
        if ($importe <= 0) {
            throw new \RuntimeException('Importe con intereses en cero.');
        }

        $importes = CuotasImporte::query()
            ->where('idCuotas', (int) $registro->idCuotas)
            ->where('idCursos', (int) $registro->idCursos)
            ->first();

        $formula = self::formulaDesdeImportes($importes);

        return self::completarDetalle(
            $registro,
            $cupon,
            $cpe,
            $idNivel,
            $formula,
            $nueVenc,
            $nueVenc,
            $nueVenc,
            $importe,
            $importe,
            $importe,
        );
    }

    /**
     * @param  array<string, mixed>  $cupon
     * @param  array<string, mixed>  $formula
     * @return array<string, mixed>
     */
    private static function completarDetalle(
        CuotaGenerada $registro,
        array $cupon,
        string $cpe,
        int $idNivel,
        array $formula,
        CarbonInterface $venc1,
        ?CarbonInterface $venc2,
        ?CarbonInterface $venc3,
        float $importe1,
        float $importe2,
        float $importe3,
    ): array {
        $siroMje = mb_strtoupper(trim((string) ($cupon['entoNivel']['siroMje'] ?? '')));
        if ($siroMje === '') {
            throw new SiroConfiguracionIncompletaException(['Mensaje en ticket / pantalla SIRO'], $idNivel);
        }

        $cuotaNombre = mb_strtoupper(trim((string) ($cupon['cuotaNombre'] ?? '')));

        $ultUploadNuevo = (int) ($registro->ultUpload ?? 0) + 1;
        $idLegajos = (int) $registro->idLegajos;
        $idCuotas = (int) $registro->idCuotas;
        $idFactura = SiroIdFactura::generar($idLegajos, $idCuotas, $ultUploadNuevo);

        $mensajeTicket1 = SiroSubidaBaseDeudaArchivo::recortarAlfanumerico($siroMje, 15);
        $mensajeTicket2 = SiroSubidaBaseDeudaArchivo::recortarAlfanumerico($cuotaNombre, 25);

        return [
            'idCuotaGenerada' => (int) $registro->id,
            'idNivel' => $idNivel,
            'cpe' => $cpe,
            'idFactura' => $idFactura,
            'ultUploadNuevo' => $ultUploadNuevo,
            'saldoPagar' => (float) ($registro->faltapa ?? 0),
            'formula' => $formula,
            'venc1' => $venc1,
            'venc2' => $venc2,
            'venc3' => $venc3,
            'importe1' => $importe1,
            'importe2' => $importe2,
            'importe3' => $importe3,
            'mensajeTicket1' => $mensajeTicket1,
            'mensajeTicket2' => $mensajeTicket2,
            'mensajePantalla' => $mensajeTicket1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function formulaDesdeImportes(?CuotasImporte $importes): array
    {
        return [
            'signo1v' => trim((string) ($importes->signo1v ?? '+')),
            'valor1v' => (float) ($importes->valor1v ?? 0),
            'porcan1v' => trim((string) ($importes->porcan1v ?? '%')),
            'signo2v' => trim((string) ($importes->signo2v ?? '+')),
            'valor2v' => (float) ($importes->valor2v ?? 0),
            'porcan2v' => trim((string) ($importes->porcan2v ?? '%')),
            'signo3v' => trim((string) ($importes->signo3v ?? '+')),
            'valor3v' => (float) ($importes->valor3v ?? 0),
            'porcan3v' => trim((string) ($importes->porcan3v ?? '%')),
        ];
    }

    private static function carbon(mixed $fecha): ?CarbonInterface
    {
        if ($fecha instanceof CarbonInterface) {
            return $fecha->copy()->startOfDay();
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
