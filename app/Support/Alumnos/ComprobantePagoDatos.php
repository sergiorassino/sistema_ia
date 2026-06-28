<?php

namespace App\Support\Alumnos;

use App\Models\CuponAPagar;
use App\Models\CuotaGenerada;
use App\Support\Cuotas\CuponAPagarEmision;

/**
 * Datos para el comprobante de pago de aranceles (portal alumno y administración).
 */
final class ComprobantePagoDatos
{
    /**
     * @return array<string, mixed>|null
     */
    public static function paraAutogestion(int $idCuotaGenerada): ?array
    {
        $registro = ArancelesEscolares::cuotaPendienteParaAutogestion($idCuotaGenerada);
        if ($registro === null) {
            return null;
        }

        return self::cuponTrasEmision(
            $registro,
            CuponAPagar::ORIGEN_IMPRESION_AUTOGESTION,
            studentPdfHeaderData(),
        );
    }

    public static function paraAdministracion(int $idCuotaGenerada, int $idLegajo): ?array
    {
        $registro = ArancelesEscolares::cuotaPendienteParaAdministracion($idCuotaGenerada, $idLegajo);
        if ($registro === null) {
            return null;
        }

        return self::cuponTrasEmision($registro, CuponAPagar::ORIGEN_IMPRESION_ADMIN, schoolPdfHeaderData());
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function cuponTrasEmision(CuotaGenerada $registro, string $origen, ?array $pdfHeader = null): ?array
    {
        $registro = CuponAPagarEmision::alImprimir($registro, $origen);

        return ComprobantePagoPdf::calcular($registro, $pdfHeader);
    }
}
