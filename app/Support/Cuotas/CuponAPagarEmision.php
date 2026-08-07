<?php

namespace App\Support\Cuotas;

use App\Models\CuponAPagar;
use App\Models\CuotaGenerada;
use App\Support\Alumnos\ComprobantePagoPdf;
use App\Support\Cuotas\Siro\SiroCodigoPagoElectronico;
use App\Support\Cuotas\Siro\SiroConfiguracionIncompletaException;
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
     */
    public static function desdeSubidaSiro(
        CuotaGenerada $registro,
        array $detalle,
        ?string $nombreArchivoSiro,
    ): void {
        if (! tenantCuotasSiroHabilitado()) {
            return;
        }

        $registro->ultUpload = (int) ($registro->ultUpload ?? 0) + 1;
        $registro->save();

        CuponAPagarPersistencia::registrar(
            $registro,
            $detalle,
            (int) $registro->ultUpload,
            CuponAPagar::ORIGEN_SUBIDA_SIRO,
            $nombreArchivoSiro,
        );
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

            $locked->ultUpload = (int) ($locked->ultUpload ?? 0) + 1;
            $locked->save();

            CuponAPagarPersistencia::registrar(
                $locked,
                $detalle,
                (int) $locked->ultUpload,
                $origen,
                $nombreArchivoSiro,
            );

            $locked->loadMissing(['legajo', 'curso', 'cuota', 'curso.nivel', 'cuota.terlec']);

            return $locked;
        });
    }
}
