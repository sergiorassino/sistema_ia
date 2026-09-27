<?php

namespace App\Support\Cuotas;

use App\Models\ComprobanteAfip;
use App\Models\CuotaGenerada;
use App\Models\CuotaPago;
use App\Models\CuotasDetalle;

/**
 * Líneas del detalle de la factura AFIP: nombre de la cuota, discriminación
 * cargada para esa cuota y ese curso, e intereses como ítem aparte.
 *
 * El total del comprobante no cambia. Si la suma de los ítems no coincide con
 * el neto facturado (importe del comprobante menos intereses), los montos se
 * reparten en la misma proporción para que el detalle cierre con ese total.
 */
final class FacturaAfipLineasDetalle
{
    public const ETIQUETA_INTERESES = 'Intereses';

    /**
     * @return list<array{concepto: string, importe: float, importeFmt: string, esTitulo: bool, esDetalle: bool}>|null
     */
    public static function desdeComprobante(ComprobanteAfip $comprobante): ?array
    {
        if (ComprobantesAfipCuotaService::esNotaCredito($comprobante)) {
            return null;
        }

        $bloques = self::bloques($comprobante);
        if ($bloques === []) {
            return null;
        }

        $lineas = [];
        foreach ($bloques as $bloque) {
            $items = self::itemsDelCurso((int) $bloque['idCuotas'], (int) $bloque['idCursos']);
            foreach (self::armarLineas(
                (string) $bloque['nombre'],
                $items,
                (float) $bloque['importe'],
                (float) $bloque['interes'],
            ) as $linea) {
                $lineas[] = $linea;
            }
        }

        return $lineas === [] ? null : $lineas;
    }

    /**
     * @param  list<array{nombre?: string, importe?: float|int|string}>  $items
     * @return list<array{concepto: string, importe: float, importeFmt: string, esTitulo: bool, esDetalle: bool}>
     */
    public static function armarLineas(string $nombreCuota, array $items, float $importeFacturado, float $interes): array
    {
        $nombreCuota = mb_strtoupper(trim($nombreCuota));
        if ($nombreCuota === '') {
            $nombreCuota = 'CUOTA';
        }

        $importeFacturado = round(max(0, $importeFacturado), 2);
        $interes = round(max(0, $interes), 2);
        if ($interes > $importeFacturado) {
            $interes = $importeFacturado;
        }
        $neto = round($importeFacturado - $interes, 2);

        $cargados = [];
        foreach ($items as $item) {
            $nombre = trim((string) ($item['nombre'] ?? ''));
            $importe = round((float) ($item['importe'] ?? 0), 2);
            if ($nombre === '' || $importe <= 0) {
                continue;
            }
            $cargados[] = ['nombre' => $nombre, 'importe' => $importe];
        }

        if ($cargados !== [] && $neto > 0) {
            $suma = round(array_sum(array_column($cargados, 'importe')), 2);
            $lineas = [self::linea($nombreCuota, 0.0, true, false)];
            $asignado = 0.0;
            $ultimo = count($cargados) - 1;

            foreach ($cargados as $i => $item) {
                if ($suma <= 0) {
                    $monto = 0.0;
                } elseif ($i === $ultimo) {
                    $monto = round($neto - $asignado, 2);
                } else {
                    $monto = round($item['importe'] * $neto / $suma, 2);
                    $asignado = round($asignado + $monto, 2);
                }
                if ($monto < 0) {
                    $monto = 0.0;
                }
                $lineas[] = self::linea($item['nombre'], $monto, false, true);
            }

            if ($interes > 0) {
                $lineas[] = self::linea(self::ETIQUETA_INTERESES, $interes, false, true);
            }

            return $lineas;
        }

        if ($interes > 0 && $neto > 0) {
            return [
                self::linea($nombreCuota, $neto, false, false),
                self::linea(self::ETIQUETA_INTERESES, $interes, false, true),
            ];
        }

        if ($interes > 0 && $neto <= 0) {
            return [self::linea(self::ETIQUETA_INTERESES, $interes, false, false)];
        }

        return [self::linea($nombreCuota, $importeFacturado, false, false)];
    }

    /**
     * @return list<array{nombre: string, idCuotas: int, idCursos: int, importe: float, interes: float}>
     */
    private static function bloques(ComprobanteAfip $comprobante): array
    {
        $idPago = (int) ($comprobante->idCuotasPagos ?? 0);
        $ids = self::idsLista((string) ($comprobante->saldoRestante ?? ''));

        if ($idPago > 0 && $ids !== []) {
            return self::bloquesDesdePagos($ids);
        }

        if ($idPago <= 0 && $ids !== []) {
            return self::bloquesDesdeCuotasGeneradas($comprobante, $ids);
        }

        return self::bloqueUnico($comprobante);
    }

    /**
     * @param  list<int>  $idsPagos
     * @return list<array{nombre: string, idCuotas: int, idCursos: int, importe: float, interes: float}>
     */
    private static function bloquesDesdePagos(array $idsPagos): array
    {
        $pagos = CuotaPago::query()
            ->whereIn('id', $idsPagos)
            ->with(['cuotaGenerada.cuota:id,nombre'])
            ->get()
            ->keyBy(fn (CuotaPago $pago): int => (int) $pago->id);

        $bloques = [];
        foreach ($idsPagos as $idPago) {
            $pago = $pagos->get($idPago);
            $registro = $pago?->cuotaGenerada;
            if (! $pago instanceof CuotaPago || ! $registro instanceof CuotaGenerada) {
                continue;
            }

            $bloques[] = self::bloqueDeRegistro(
                $registro,
                ComprobantesAfipCuotaService::importeFacturable($pago),
                round((float) ($pago->interes ?? 0), 2),
            );
        }

        return $bloques;
    }

    /**
     * @param  list<int>  $idsCuotasGeneradas
     * @return list<array{nombre: string, idCuotas: int, idCursos: int, importe: float, interes: float}>
     */
    private static function bloquesDesdeCuotasGeneradas(ComprobanteAfip $comprobante, array $idsCuotasGeneradas): array
    {
        $registros = CuotaGenerada::query()
            ->with(['cuota:id,nombre'])
            ->whereIn('id', $idsCuotasGeneradas)
            ->get()
            ->keyBy(fn (CuotaGenerada $registro): int => (int) $registro->id);

        $importes = explode('|', (string) ($comprobante->importeSubConceptos ?? ''));
        $nombres = explode('|', (string) ($comprobante->subConceptos ?? ''));
        $bloques = [];

        foreach ($idsCuotasGeneradas as $indice => $idRegistro) {
            $registro = $registros->get($idRegistro);
            $importe = round((float) str_replace(',', '.', trim((string) ($importes[$indice] ?? '0'))), 2);
            $nombreGuardado = mb_strtoupper(trim((string) ($nombres[$indice] ?? '')));

            if ($registro instanceof CuotaGenerada) {
                $bloque = self::bloqueDeRegistro($registro, $importe, 0.0);
                if ($bloque['nombre'] === 'CUOTA' && $nombreGuardado !== '') {
                    $bloque['nombre'] = $nombreGuardado;
                }
                $bloques[] = $bloque;

                continue;
            }

            if ($nombreGuardado === '' && $importe <= 0) {
                continue;
            }

            $bloques[] = [
                'nombre' => $nombreGuardado !== '' ? $nombreGuardado : 'CUOTA',
                'idCuotas' => 0,
                'idCursos' => 0,
                'importe' => $importe,
                'interes' => 0.0,
            ];
        }

        return $bloques;
    }

    /**
     * @return list<array{nombre: string, idCuotas: int, idCursos: int, importe: float, interes: float}>
     */
    private static function bloqueUnico(ComprobanteAfip $comprobante): array
    {
        $id = (int) ($comprobante->idCbteAsoc ?? 0);
        if ($id <= 0) {
            return [];
        }

        $registro = CuotaGenerada::query()
            ->with(['cuota:id,nombre'])
            ->find($id);

        if (! $registro instanceof CuotaGenerada) {
            return [];
        }

        $bloque = self::bloqueDeRegistro(
            $registro,
            round((float) ($comprobante->importePagado ?? 0), 2),
            round((float) ($comprobante->interesPagado ?? 0), 2),
        );

        if ($bloque['nombre'] === 'CUOTA') {
            $concepto = mb_strtoupper(trim((string) ($comprobante->concepto ?? '')));
            if ($concepto !== '') {
                $bloque['nombre'] = $concepto;
            }
        }

        return [$bloque];
    }

    /**
     * @return array{nombre: string, idCuotas: int, idCursos: int, importe: float, interes: float}
     */
    private static function bloqueDeRegistro(CuotaGenerada $registro, float $importe, float $interes): array
    {
        $nombre = mb_strtoupper(trim((string) ($registro->cuota?->nombre ?? '')));

        return [
            'nombre' => $nombre !== '' ? $nombre : 'CUOTA',
            'idCuotas' => (int) ($registro->idCuotas ?? 0),
            'idCursos' => (int) ($registro->idCursos ?? 0),
            'importe' => round(max(0, $importe), 2),
            'interes' => round(max(0, $interes), 2),
        ];
    }

    /**
     * @return list<array{nombre: string, importe: float}>
     */
    private static function itemsDelCurso(int $idCuotas, int $idCurso): array
    {
        if ($idCuotas <= 0 || $idCurso <= 0 || ! CuotasDetalleCatalog::tablasListas()) {
            return [];
        }

        return CuotasDetalle::query()
            ->where('idCuotas', $idCuotas)
            ->where('idCursos', $idCurso)
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['nombre', 'importe'])
            ->map(fn (CuotasDetalle $item): array => [
                'nombre' => (string) $item->nombre,
                'importe' => round((float) $item->importe, 2),
            ])
            ->all();
    }

    /**
     * @return list<int>
     */
    private static function idsLista(string $texto): array
    {
        if (trim($texto) === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $texto) as $parte) {
            $id = (int) trim($parte);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return array{concepto: string, importe: float, importeFmt: string, esTitulo: bool, esDetalle: bool}
     */
    private static function linea(string $concepto, float $importe, bool $esTitulo, bool $esDetalle): array
    {
        $importe = round($importe, 2);

        return [
            'concepto' => $concepto,
            'importe' => $esTitulo ? 0.0 : $importe,
            'importeFmt' => $esTitulo ? '' : number_format($importe, 2, ',', '.'),
            'esTitulo' => $esTitulo,
            'esDetalle' => $esDetalle,
        ];
    }
}
