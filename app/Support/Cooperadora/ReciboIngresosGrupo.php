<?php

namespace App\Support\Cooperadora;

use App\Models\CoopIngreso;
use Illuminate\Support\Collection;

final class ReciboIngresosGrupo
{
    /**
     * @return Collection<int, CoopIngreso>
     */
    public static function ingresosDelRecibo(int $idReferencia): Collection
    {
        $ingreso = CoopIngreso::query()
            ->where('anulado', false)
            ->findOrFail($idReferencia);

        $grupoId = (int) ($ingreso->recibo_grupo_id ?? $ingreso->id);

        return CoopIngreso::query()
            ->with(['rubro:id,nombre', 'item:id,nombre', 'legajo:id,apellido,nombre'])
            ->where('anulado', false)
            ->where(function ($q) use ($grupoId) {
                $q->where('id', $grupoId)
                    ->orWhere('recibo_grupo_id', $grupoId);
            })
            ->orderBy('id')
            ->get();
    }

    public static function idReferenciaPdf(CoopIngreso $ingreso): int
    {
        return (int) ($ingreso->recibo_grupo_id ?? $ingreso->id);
    }

    /**
     * @param  Collection<int, CoopIngreso>  $ingresos
     * @return array{
     *   lineas: list<array{concepto: string, importe: float}>,
     *   importe_total: float,
     *   importe_letras: string,
     * }
     */
    public static function datosPdf(Collection $ingresos): array
    {
        $lineas = [];
        $total = 0.0;

        foreach ($ingresos as $ingreso) {
            $importe = round((float) $ingreso->importe, 2);
            $total += $importe;
            $lineas[] = [
                'concepto' => self::conceptoLineaPdf($ingreso),
                'importe' => $importe,
            ];
        }

        $total = round($total, 2);

        return [
            'lineas' => $lineas,
            'importe_total' => $total,
            'importe_letras' => ImporteEnLetrasEs::pesos($total),
        ];
    }

    private static function conceptoLineaPdf(CoopIngreso $ingreso): string
    {
        $concepto = trim((string) $ingreso->concepto);
        $concepto = self::normalizarConceptoDetallePdf($concepto);

        if ($concepto !== '' && mb_stripos($concepto, ', Estudiante:') !== false) {
            return $concepto;
        }

        if ($ingreso->legajo !== null) {
            $estudiante = trim($ingreso->legajo->apellido.', '.$ingreso->legajo->nombre);
            if ($estudiante !== '') {
                return $concepto !== ''
                    ? $concepto.', Estudiante: '.$estudiante
                    : 'Estudiante: '.$estudiante;
            }
        }

        return $concepto;
    }

    private static function normalizarConceptoDetallePdf(string $concepto): string
    {
        $concepto = str_replace('Pago de contribución correspondiente a ', '', $concepto);
        $concepto = preg_replace('/,\s*Alumno:/u', ', Estudiante:', $concepto) ?? $concepto;
        $concepto = preg_replace('/^Alumno:/u', 'Estudiante:', $concepto) ?? $concepto;

        return trim($concepto);
    }
}
