<?php

namespace App\Support\Cuotas;

use App\Models\CuponAPagar;
use App\Models\CuotaGenerada;
use Carbon\CarbonInterface;

/**
 * Persiste cupones emitidos en {@see CuponAPagar}.
 */
final class CuponAPagarPersistencia
{
    /**
     * @param  array<string, mixed>  $detalle  Salida de {@see CuponAPagarSnapshot::armar}
     */
    public static function registrar(
        CuotaGenerada $registro,
        array $detalle,
        int $ultUpload,
        string $origen,
        ?string $nombreArchivoSiro = null,
    ): CuponAPagar {
        /** @var array<string, mixed> $formula */
        $formula = $detalle['formula'] ?? [];

        /** @var CarbonInterface $venc1 */
        $venc1 = $detalle['venc1'];
        /** @var CarbonInterface $venc2 */
        $venc2 = $detalle['venc2'];
        /** @var CarbonInterface $venc3 */
        $venc3 = $detalle['venc3'];

        return CuponAPagar::query()->create([
            'id_cuotas_generadas' => (int) $registro->id,
            'id_cursos' => (int) $registro->idCursos,
            'id_cuotasbecas' => (int) ($registro->idCuotasbecas ?? 0),
            'saldo_pagar' => (float) ($detalle['saldoPagar'] ?? $registro->faltapa ?? 0),
            'cpe' => (string) ($detalle['cpe'] ?? ''),
            'id_factura' => (string) ($detalle['idFactura'] ?? ''),
            'ult_upload' => $ultUpload,
            'origen' => $origen,
            'signo1v' => (string) ($formula['signo1v'] ?? '+'),
            'valor1v' => (float) ($formula['valor1v'] ?? 0),
            'porcan1v' => (string) ($formula['porcan1v'] ?? '%'),
            'fecha1venc' => $venc1->format('Y-m-d'),
            'importe1venc' => (float) ($detalle['importe1'] ?? 0),
            'signo2v' => (string) ($formula['signo2v'] ?? '+'),
            'valor2v' => (float) ($formula['valor2v'] ?? 0),
            'porcan2v' => (string) ($formula['porcan2v'] ?? '%'),
            'fecha2venc' => $venc2->format('Y-m-d'),
            'importe2venc' => (float) ($detalle['importe2'] ?? 0),
            'signo3v' => (string) ($formula['signo3v'] ?? '+'),
            'valor3v' => (float) ($formula['valor3v'] ?? 0),
            'porcan3v' => (string) ($formula['porcan3v'] ?? '%'),
            'fecha3venc' => $venc3->format('Y-m-d'),
            'importe3venc' => (float) ($detalle['importe3'] ?? 0),
            'fecha_emision' => now(),
            'nombre_archivo_siro' => $nombreArchivoSiro,
        ]);
    }
}
