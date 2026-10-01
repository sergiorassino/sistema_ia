<?php

namespace App\Support\Cuotas;

use App\Models\CuponAPagar;
use App\Models\CuotaGenerada;
use App\Support\Alumnos\ComprobantePagoPdf;
use App\Support\Cuotas\Siro\SiroCodigoPagoElectronico;
use App\Support\Cuotas\Siro\SiroConfiguracionIncompletaException;
use App\Support\Cuotas\Siro\SiroIdFactura;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Emisión de cupón a pagar (incrementa {@see CuotaGenerada::$ultUpload} y persiste snapshot).
 *
 * Misma lógica que el legacy Scriptcase al imprimir o subir a SIRO.
 */
final class CuponAPagarEmision
{
    /**
     * Registra un nuevo cupón al imprimir el PDF (administración o autogestión).
     */
    public static function alImprimir(CuotaGenerada $registro, string $origen): CuotaGenerada
    {
        if (! tenantCuotasSiroHabilitado()) {
            return $registro;
        }

        if (! in_array($origen, [
            CuponAPagar::ORIGEN_IMPRESION_ADMIN,
            CuponAPagar::ORIGEN_IMPRESION_AUTOGESTION,
        ], true)) {
            return $registro;
        }

        return self::emitir($registro, $origen, null);
    }

    /**
     * Registra cupones incluidos en una subida base de deuda SIRO.
     *
     * @param  array<string, mixed>  $detalle
     * @return array<string, mixed> Detalle persistido (id_factura puede haber avanzado si el propuesto ya existía).
     */
    public static function desdeSubidaSiro(
        CuotaGenerada $registro,
        array $detalle,
        ?string $nombreArchivoSiro,
    ): array {
        if (! tenantCuotasSiroHabilitado()) {
            return $detalle;
        }

        $detalle = self::conIdFacturaLibre($registro, $detalle);

        try {
            self::persistirCupon($registro, $detalle, CuponAPagar::ORIGEN_SUBIDA_SIRO, $nombreArchivoSiro);
        } catch (QueryException $e) {
            if (! self::esClaveIdFacturaDuplicada($e)) {
                throw $e;
            }

            $detalle = self::conIdFacturaLibre($registro, $detalle);
            self::persistirCupon($registro, $detalle, CuponAPagar::ORIGEN_SUBIDA_SIRO, $nombreArchivoSiro);
        }

        return $detalle;
    }

    private static function emitir(CuotaGenerada $registro, string $origen, ?string $nombreArchivoSiro): CuotaGenerada
    {
        return DB::transaction(function () use ($registro, $origen, $nombreArchivoSiro): CuotaGenerada {
            /** @var CuotaGenerada $locked */
            $locked = CuotaGenerada::query()
                ->where('id', $registro->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->loadMissing(['legajo', 'curso', 'cuota']);

            $cupon = ComprobantePagoPdf::calcular($locked);
            if ($cupon === null) {
                return $locked;
            }

            $idNivel = (int) ($locked->curso?->idNivel ?? 0);
            if ($idNivel <= 0) {
                throw new SiroConfiguracionIncompletaException(
                    ['Prefijo CPE', 'Cuenta recaudadora SIRO', 'Mensaje en ticket / pantalla SIRO'],
                    $idNivel > 0 ? $idNivel : null,
                );
            }

            SiroCodigoPagoElectronico::exigirParaOperacion($idNivel);
            $cpe = SiroCodigoPagoElectronico::generar((int) $locked->idLegajos, $idNivel);
            $detalle = CuponAPagarSnapshot::armar($locked, $cupon, $cpe, $idNivel);
            $detalle = self::conIdFacturaLibre($locked, $detalle);

            try {
                self::persistirCupon($locked, $detalle, $origen, $nombreArchivoSiro);
            } catch (QueryException $e) {
                if (! self::esClaveIdFacturaDuplicada($e)) {
                    throw $e;
                }

                $detalle = self::conIdFacturaLibre($locked, $detalle);
                self::persistirCupon($locked, $detalle, $origen, $nombreArchivoSiro);
            }

            $locked->loadMissing(['legajo', 'curso', 'cuota', 'curso.nivel', 'cuota.terlec']);

            return $locked;
        });
    }

    /**
     * Si el id_factura propuesto ya está en cupones_a_pagar, usa el siguiente ultUpload libre.
     *
     * No retrocede por debajo de cuotasgeneradas.ultUpload. El PDF se recalcula
     * después, con ese mismo número, para que el código de barras coincida.
     *
     * @param  array<string, mixed>  $detalle
     * @return array<string, mixed>
     */
    private static function conIdFacturaLibre(CuotaGenerada $registro, array $detalle): array
    {
        $idLegajos = (int) $registro->idLegajos;
        $idCuotas = (int) $registro->idCuotas;
        $propuesto = (int) ($detalle['ultUploadNuevo'] ?? 0);
        if ($propuesto < 1) {
            $propuesto = (int) ($registro->ultUpload ?? 0) + 1;
        }

        $base = max((int) ($registro->ultUpload ?? 0), $propuesto - 1);
        $libre = SiroIdFactura::siguienteUltUploadLibre($base, self::ultUploadsOcupados($idLegajos, $idCuotas));

        $detalle['ultUploadNuevo'] = $libre;
        $detalle['idFactura'] = SiroIdFactura::generar($idLegajos, $idCuotas, $libre);

        return $detalle;
    }

    /**
     * @return list<int>
     */
    private static function ultUploadsOcupados(int $idLegajos, int $idCuotas): array
    {
        $muestra = SiroIdFactura::generar($idLegajos, $idCuotas, 1);
        $partes = SiroIdFactura::partesCadena($muestra);
        if ($partes === null) {
            return [];
        }

        $ids = CuponAPagar::query()
            ->where('id_factura', 'like', $partes['prefijoSinUpload'].'__'.$partes['sufijoCuota'])
            ->pluck('id_factura');

        $ocupados = [];
        foreach ($ids as $idFactura) {
            $p = SiroIdFactura::partesCadena((string) $idFactura);
            if ($p === null) {
                continue;
            }
            if ($p['prefijoSinUpload'] !== $partes['prefijoSinUpload'] || $p['sufijoCuota'] !== $partes['sufijoCuota']) {
                continue;
            }
            if ($p['ultUpload'] > 0) {
                $ocupados[] = $p['ultUpload'];
            }
        }

        return $ocupados;
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    private static function persistirCupon(
        CuotaGenerada $registro,
        array $detalle,
        string $origen,
        ?string $nombreArchivoSiro,
    ): void {
        $registro->ultUpload = (int) ($detalle['ultUploadNuevo'] ?? 0);
        $registro->save();

        CuponAPagarPersistencia::registrar(
            $registro,
            $detalle,
            (int) $registro->ultUpload,
            $origen,
            $nombreArchivoSiro,
        );
    }

    private static function esClaveIdFacturaDuplicada(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $codigo = (int) ($e->errorInfo[1] ?? 0);
        if ($sqlState !== '23000' && $codigo !== 1062) {
            return false;
        }

        $mensaje = $e->getMessage();

        return str_contains($mensaje, 'uq_cupones_a_pagar_id_factura')
            || str_contains($mensaje, 'id_factura');
    }
}
