<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Models\CuponAPagar;

/**
 * TEMPORAL — avisos de las excepciones de puesta en marcha SIRO.
 *
 * Desactivar cada {@see *:HABILITADO} (o eliminar las clases) al cerrar la puesta en marcha.
 */
final class SiroDescargaRendicionProvisorios
{
    public static function hayAlgunoHabilitado(): bool
    {
        return SiroDescargaRendicionMatchUploadCercano::HABILITADO
            || SiroDescargaRendicionMatchCuotaSinCupon448::HABILITADO;
    }

    /**
     * @return list<string>
     */
    public static function mensajesAvisoFormulario(): array
    {
        $mensajes = [];
        if (SiroDescargaRendicionMatchUploadCercano::HABILITADO) {
            $mensajes[] = SiroDescargaRendicionMatchUploadCercano::mensajeAvisoFormulario();
        }
        if (SiroDescargaRendicionMatchCuotaSinCupon448::HABILITADO) {
            $mensajes[] = SiroDescargaRendicionMatchCuotaSinCupon448::mensajeAvisoFormulario();
        }

        return $mensajes;
    }

    /**
     * Cupón identificado pero el importe del archivo no cierra con importe1/2/3venc.
     *
     * @param  array<string, mixed>  $linea
     */
    public static function debeUsarImporteArchivoSiTramoNoCierra(array $linea): bool
    {
        return SiroDescargaRendicionMatchCuotaSinCupon448::aplicaALinea($linea)
            || SiroDescargaRendicionMatchUploadCercano::aplicaALinea($linea);
    }

    public static function avisoImporteArchivo(float $pagado, float $capital): string
    {
        return 'Match provisorio (puesta en marcha): el importe del archivo SIRO ($'
            .number_format($pagado, 2, ',', '.')
            .') no coincide con los vencimientos del cupón; se descarga ese valor '
            .'y se desglosa contra capital $'.number_format($capital, 2, ',', '.').'.';
    }

    /**
     * Texto para la columna Detalle del modal de carga cuando aplicó un provisorio.
     *
     * @param  array{
     *     matchTipo?: string,
     *     provisorioImporteArchivo?: bool,
     *     idFacturaBuscado?: string,
     *     cupon?: ?CuponAPagar,
     *     pagadoArchivo?: float
     * }  $datos
     */
    public static function detalleColumna(array $datos): ?string
    {
        $matchTipo = (string) ($datos['matchTipo'] ?? '');
        $importeArchivo = (bool) ($datos['provisorioImporteArchivo'] ?? false);
        $sinCupon = SiroDescargaRendicionMatchCuotaSinCupon448::esMatchTipo($matchTipo);
        $uploadCercano = $matchTipo === 'upload_cercano';

        if (! $sinCupon && ! $uploadCercano && ! $importeArchivo) {
            return null;
        }

        $cupon = $datos['cupon'] ?? null;
        $idArchivo = trim((string) ($datos['idFacturaBuscado'] ?? ''));
        if ($idArchivo === '') {
            $idArchivo = '—';
        }
        $pagado = round((float) ($datos['pagadoArchivo'] ?? 0), 2);

        $idCupon = '—';
        $importesCupon = '1v —  2v —  3v —';
        if ($cupon instanceof CuponAPagar) {
            $idCuponAttr = trim((string) ($cupon->getAttributes()['id_factura'] ?? ''));
            $idCupon = $idCuponAttr !== '' ? $idCuponAttr : '—';
            $importesCupon = self::resumenImportesCupon($cupon);
        }

        return 'PROVISORIO: id_factura archivo: '.$idArchivo
            .' - Importe archivo: '.self::dinero($pagado)
            .' - id_factura cupones_a_pagar: '.$idCupon
            .' - importes cupones_a_pagar: '.$importesCupon
            .'. RESOLVIENDO POR: '.self::metodoResolucion($matchTipo, $importeArchivo);
    }

    public static function metodoResolucion(string $matchTipo, bool $provisorioImporteArchivo): string
    {
        if (SiroDescargaRendicionMatchCuotaSinCupon448::esMatchTipo($matchTipo)) {
            return 'provisorio 2 — 448 sin cupón en cupones_a_pagar';
        }

        if ($matchTipo === 'upload_cercano') {
            if ($provisorioImporteArchivo) {
                return 'provisorio 1 — upload cercano (449); importe archivo distinto a vencimientos del cupón';
            }

            return 'provisorio 1 — upload cercano (449)';
        }

        if ($provisorioImporteArchivo) {
            return 'importe archivo distinto a vencimientos del cupón';
        }

        return 'provisorio (puesta en marcha)';
    }

    public static function dinero(float $valor): string
    {
        return '$'.number_format($valor, 2, ',', '.');
    }

    public static function resumenImportesCupon(CuponAPagar $cupon): string
    {
        $attrs = $cupon->getAttributes();
        $tramos = [];
        foreach ([1, 2, 3] as $n) {
            $importe = round((float) ($attrs['importe'.$n.'venc'] ?? 0), 2);
            $tramos[] = $n.'v '.self::dinero($importe);
        }

        return implode('  ', $tramos);
    }
}
